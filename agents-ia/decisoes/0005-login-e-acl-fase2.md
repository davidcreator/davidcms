# ADR 0005 — Sessão, login e ACL do painel (Fase 2, Tarefa F2-2)

| | |
| --- | --- |
| **Status** | ✅ aceita |
| **Data** | 2026-10-16 |
| **Proponente** | humano (David L. Almeida) |
| **Implementação** | agente-admin (F2-2) |

## Contexto

A Fase 1 entregou o núcleo sem nenhuma muralha: o admin respondia qualquer rota
(caía em 404 por falta de controllers). A F2-2 precisa do primeiro fluxo
autenticado contra `rms_user`/`rms_user_group` (seed do wizard: grupo sem rotas
explícitas — `permission: {"access":[],"modify":[]}`).

## Decisão

1. **Serviço `user`** (`system/library/user.php`, namespace `Reamur\System\Library`):
   estado na sessão, hidratado do banco a cada requisição; `login()` exige
   `status=1`, usa `password_hash/password_verify` e **re-hasheia
   silenciosamente** quando `password_needs_rehash` acusar evolução de algoritmo;
2. **Muralha por pré-ação** (`startup/login` + `startup/permission` em
   `action_pre_action` do config admin): o front controller é imutável por app
   (Contrato §2), então o serviço `user` é montado **preguiçosamente** na
   primeira pré-ação;
3. **Allowlist de bootstrap** — porque o seed zera as permissões, estas rotas
   são livres de ACL (login/logout) ou liberadas para qualquer usuário logado
   (`common/dashboard`, `error/not_found`, `error/permission`). Toda rota nova
   fora dessa lista exige concessão explícita no JSON do grupo;
4. **Negação = 403 honesto**: ACL reprovou → redireciona para `error/permission`,
   que responde `HTTP/1.1 403 Forbidden`; login ausente → `common/login`;
5. **Higiene de sessão**: `login()` regenera o session-id (fixation);
   `logout()` destrói a sessão; usuário desativado (`status=0`) perde a sessão
   efetiva na próxima requisição.

## Pendências declaradas (fase de hardening — fora do escopo F2-2)

- Proteção CSRF no formulário de login;
- Throttling/lockout de tentativas;
- UI `user/user_permission` para administrar o JSON de permissões + re-grant no
  upgrade (hoje: edição manual via SQL conforme o comentário do schema);
- Token lembrar-me; 2FA;
- Idiomas pt-br/es-eu (ADR 0001/F2-3).

## Consequências

- ✅ Portão PHPStan (nível 2) agora analisa `library/user.php` — código novo
  entra limpo sem baseline (dívida congelada é só do legado, ADR 0004 Adendo F2-0);
- ✅ Contrato do JSON `permission` (`{"access":[rotas],"modify":[rotas]}`)
  vira regra de arquitetura testável;
- ⚠️ Todo módulo novo do admin precisará declarar suas rotas ao grupo
  (grants) — retrabalho evitado por ADR, não por convenção informal.

## Adendo F2-6 — Granularidade base-route e telas de autogestão

**Contexto.** A F2-6 entrega o CRUD `user/user` + `user/user_group` (a pendência
"UI para administrar o JSON de permissões" acima **deixa de existir**) e expõe
métodos de controller via URL — o que este ADR não previu.

**Decisões.**

1. **Granularidade da ACL = base-route** (o *arquivo* `grupo/modulo`). Rotas
   com gramática de método/args (`user/user.add`, `user/user.delete|3`) são
   normalizadas para `user/user` antes de `is_file`/`hasPermission` em
   `startup/permission`. Um grant cobre **todos** os métodos do controller;
2. **`modify` protege efeitos colaterais**: qualquer POST mutável verifica
   `hasPermission('modify', $baseRoute)` dentro do método — `access` abre a
   tela, `modify` altera dados. GET jamais muta;
3. **Seed honesto**: o wizard (F2-1) cria o grupo `Administrator` já com
   `access`+`modify` em `user/user` e `user/user_group` — senão o único admin
   saía da instalação incapaz de administrar contas sem SQL manual;
4. **Sem autoexclusão**: `user/user.delete` recusa apagar o próprio usuário
   logado (servidor + botão omitido na lista). Grupo não tem delete (evita
   órfãos de `user.user_group_id` sem FK rígida nesta fase);
5. **Args de método vêm do `|` na rota, nunca da querystring** — o `Action`
   não transporta `$_GET` para argumentos; URLs do tipo
   `?route=user/user.edit&user_id=3` chamam `edit()` **sem** argumento.

**Consequências.** ⚠️ Alguém com `access` mas sem `modify` em `user/user` vê
telas, mas toda ação mutável é derrubada para `error/permission`; ✅ a matriz
de permissões descobre rotas do filesystem (`controller/**.php`) — módulo novo
aparece na grade sem migration.
