# ADR 0027 — Auto-perfil, delete de grupos e guardas de auto-trava em usuários

- **Status:** Aceita — 2026-10-19 (F4 Tarefa 04)
- **Contexto:** A F2-6 (ADR 0005 e adendo) entregou CRUD de `user/user` e a
  matriz de grupos `user/user_group`, porém três lacunas **de segurança e de UX**
  permaneciam: (1) o usuário logado não podia mudar a **própria** senha/e-mail
  sem que um administrador abrisse `user/user` — quebra o princípio de
  self-service e força operação privilegiada desnecessária; (2) `user/user_group`
  não tinha DELETE, então grupos vazios se acumulavam; (3) os fluxos de
  `user/user` permitiam ao mantenedor **trancar a si mesmo**: desativar a própria
  conta ou excluir/inativar o "último administrador ativo" eram ações permitidas.
  O `self_delete` existia, mas o hardening de servidor era parcial.
- **Leitura real (antes de escrever — prova do estado):**
  - `admin/controller/user/user.php` (337 l) tinha `delete`/`edit` mas só
    bloqueava ID=próprio no delete; faltavam barreiras de status e de
    "último admin ativo".
  - `admin/controller/user/user_group.php` tinha index/add/edit — sem delete.
  - `common/dashboard.twig` mostrava card Perfil **sem** link (text_profile era
    só dado informativo).
  - `system/library/user.php::login()` usa `password_verify` sobre hash bcrypt
    — o mesmo contrato é reutilizado no "confirme a senha atual" do profile.
- **Decisões:**

  1. **Rota `user/profile` para self-service de identidade.** Recusado
     esmagar tudo em `user/user.edit|self` (a ACL de user/user já barra). O
     profile edita email/firstname/lastname + troca de senha opcional (trio:
     corrente obrigatória + nova × 2 com confirmação). Entra na
     `ALLOWED_ROUTES` do `startup/permission` (mesma categoria de
     `common/logout`/`common/dashboard`): função-todos, não privilegiada —
     cada guimi acessa seu perfil.

  2. **Troca de senha sem invalidar a sessão.** A explosão do cookie de
     sessão não muda (PHP nativo, sem token persistente); um `session_rotate`
     está na agenda de hardening futura — registrado como limitação a fechar.

  3. **Deleção de grupo só quando segregável.** O servidor bloqueia quando
     `total_users > 0` (ninguém pode ser desvinculado) **ou quando é o último
     grupo cuja ACL inclui `user/user`** (apagar mata a governança). A mensagem
     no flash é específica para cada causa (cada motivo tem string própria).

  4. **Guardas de auto-trava em user/user.** Duas barreiras novas:
     (a) **desativar a própria conta logada** — o checkbox Status desmarcado
     no edit do próprio user erra a validação com mensagem própria;
     (b) **inativar/demovê alterar-grupo/excluir o "último usuário ativo com
     ACL user/user"** — validado no servidor (CLI ou outra sessão também
     bloqueia porque a guarda roda no modelo, não na tela).
     Critério de seleção: `hasOtherActiveUserAdmin(exclude_user_id)` =
     JOIN `user` × `user_group` filtrando `status=1`, exclude do próprio, e
     permissão LIKE `'%user/user%'`.

  5. **Heurística LIKE no JSON de permissões — conservadora, declarada.**
     A matriz ACL é JSON `{"access":[], "modify":[]}`. Decodificar porconsulta
     consertar seria SQL custoso e frágil. O LIKE direto no texto do JSON tem
     **falso-positivo seguro**: pode classificar como "portador" um grupo cuja
     rota contenha a substring (ex.: `user/user_group`) — isso só endurece a
     barreira, nunca a afrouxa. Falsos-negativos (o direito existe mas não casa
     a substring) são impossíveis pela própria natureza de LIKE '%' + contains.
     Quando a F4 avançar para ACL por método (access/modify separados) a guarda
     deve evoluir para decodify-by-row; documentado aqui para o mantenedor da vez.

     **Correção já aplicada (captura da bateria f404):** matriz salva via
     form guarda JSON com slash escapado (`"user\\/user"`), mas os SQLs
     `upgrade_*` semeia os grants crus (`"user/user"`). O LIKE conservador
     tolera os dois formatos (falso-negativo foi PROVADO — delete do grupo
     vazio temporário foi erroneamente bloqueado antes do fix).

  6. **Sem upgrade SQL nesta tarefa.** `user/profile` entra via bootstrap
     allowlist (ADR 0005 §Regra — não é grant) e `user/user_group` já tinha
     grants do seed F2-6. Nada a migrar: baterias revelaram que JSON_ARRAY_APPEND
     pré-existente continua idempotente e correto para as rotas existentes.

- **DoD verificado:** bateria `e2e_f404.sh` com 10 checks: wizard ok, muralha
  de perfil anônimo, GET da tela, save válido + round-trip (email persiste;
  login novo funciona com a senha trocada), validação bloqueia banco (email
  inválido / atual errada / confirma divergente), senha-trocada acessa login
  novo, desativação-self bloqueada, delete do último admin bloqueado, ACL
  deny retornando 302 para error/permission quando retirado grant user/user,
  i18n EN/PT/ES — suite completa revalida.
- **Registro de suposições (anti-alucinação):** a partir do estado real do
  HEAD `a04a188` — o CRUD `user/user` veio da F2-6. Confirma-me antes de
  escrever validou que delete não precisava existe (o twig ainda não tinha a
  coluna; agora tem, gateada por `total_users==0`).
