# ADR 0034 — Mercado Pago Marketplace: fundação OAuth de vendedores (config fora do git, costuras de ambiente)

- **Status:** Aceita — 2026-10-20 (F5 Tarefa 02)
- **Contexto:** O plano da Fase 5 objetiva pagamento real no ReamurCMS. O
  modelador aprovou como recorte F5-02 um **marketplace multi-vendedor
  real**: lojistas (sellers) conectados via **OAuth do Mercado Pago**, no
  caminho do *split* (`marketplace_fee`) que será a F5-03 — quando o
  usuário provisionar **credenciais reais de teste** no painel de
  desenvolvedor do MP. Nesta entrega: cadastro da aplicação (App ID /
  Client Secret / Redirect URI / ambiente), conexão OAuth de vendedor com
  `state` anti-CSRF, persistência segura e **renovação local** de tokens —
  tudo com **mock do MP** na bateria e2e.
- **Leitura real (prova):** `system/library/mercadopago_marketplace.php`
  existia **órfã desde a Fase 0** — só métodos OAuth
  (`authorizationUrl`, `exchangeAuthorizationCode`, `refreshSellerToken`)
  e **zero consumidores** (`grep -R mercadopago` fora da própria library =
  0); sem tabela de vendedores, sem SDK composer, sem chaves `mp_*` em
  `rms_setting`, sem controller/tela. Padrões espelhados:
  `upgrade_f4_03` (grants idempotentes + `INSERT IGNORE` de seeds),
  `setting/site_info` (tela admin F4-03), `model/setting/setting`
  (`getSettings`/`editSettings`), `common/dashboard` (`modules[]` +
  `hasPermission` por rota).

- **Decisões:**

  1. **Configuração do app em `rms_setting` — nunca no git.** Seeds
     idempotentes (`INSERT IGNORE`) das chaves `mp_app_id`,
     `mp_client_secret`, `mp_env` (default `test`) e `mp_redirect_uri`
     sob `code='config'`; a tela admin lê/grava via
     `model_setting_setting->getSettings()/editSettings()`. O campo
     **Client Secret nunca retorna ao GET** preenchido; POST vazio =
     **manter** o valor gravado (padrão dos campos sensíveis da casa).
     Credenciais reais entram pelo painel quando existirem — até lá o
     banco de teste carrega valores fictícios *claramente* marcados.

  2. **OAuth com `state` aleatório preso à sessão admin.** `authorize`
     gera `bin2hex(random_bytes(16))`, guarda em
     `$this->session->data['mp_oauth_state']` e redireciona à URL de
     autorização do MP (`https://auth.mercadopago.com/authorization`…).
     O `callback` compara com **`hash_equals`** (timing-safe), **consome
     (unset)** a sessão antes de qualquer outra ação e rejeita state
     ausente/divergente. Após validar: troca `code→tokens`, enriquece
     com `/users/me` (`getUserInfo`) e faz **upsert por `mp_user_id`**
     (UNIQUE) — reconectar o mesmo vendedor **atualiza** o registro, não
     duplica.

  3. **Nova tabela `rms_mp_seller`.** PK `mp_seller_id` AI;
     `mp_user_id BIGINT UNIQUE` (chave natural do MP — empresas da
     plataforma MP são estáveis); `nickname`/`email` (linha de
     identificação na grade — **sem** tokens); `access_token`/
     `refresh_token` TEXT; `scope`; `token_expires_at` = NOW() +
     `expires_in`; `status` (ativo/revogado) e timestamps
     (created/added/modified). Upsert `INSERT … ON DUPLICATE KEY UPDATE`
     por `mp_user_id`.

  4. **Costuras de ambiente na library (mock × sandbox).** Duas chaves
     **opcionais** em `rms_setting` — `mp_token_url` e `mp_users_me_url`
     — leem override **só se gravadas** no banco (validadas com
     `FILTER_VALIDATE_URL`); **default** = endpoints oficiais
     (`https://api.mercadopago.com/oauth/token` e
     `https://api.mercadopago.com/users/me`). A tela admin **não
     expõe** essas chaves; elas existem para a bateria e2e apontar o MP
     para um **mock local** (`/var/www/html/hookmock.php`) gravado em
     tempo de teste — e para futuras canários/ponteiros de sandbox. Sem
     chaves gravadas = produção/sandbox real, sem intervenção.

  5. **Admin sem Csrf por formulário — padrão da casa preservado.**
     Autorização = sessão admin + `hasPermission('access'|'modify',
     'setting/marketplace')` por ação, exatamente como
     `setting/site_info` (F4-03), `setting/setting` (F4-01) e CRUDs
     F2/F3. Ações mutantes (`save`, `refresh`, `delete`) exigem **POST**.
     `redirect_uri` exige prefixo `https://` na validação (alinhado ao
     cadastro real no painel MP e à política HTTPS do CRUD de webhook —
     ADR 0016); `mp_app_id` numérico (o `client_id` que o MP exibe).
     **Csrf introduzido por formulário = dívida reconhecida ainda não
     paga na casa** — manter homogeneidade nesta entrega.

  6. **Tokens de vendedor em TEXT pt pleno no banco — risco aceito e
     declarado.** Nesta fundação, `access_token`/`refresh_token` são
     persistidos sem cifragem em repouso, como já ocorre com secrets de
     webhook (`rms_webhook.secret`). Justificativa: sem gestão de
     chave-mestra/KMS no projeto, cifrar com chave em arquivo/config
     próximo ao código seria segurança percebida. **Hardening futuro:**
     keyring/env fora do docroot + cifragem em repouso (ex.: AES-256-GCM
     com nonce randomizado por registro) — tarefa explícita da Fase 6.
     Mitigações **já presentes**: grade sem tokens (`getSellers` só
     seleciona colunas não-sensíveis), arquivos fora do docroot não são
     afetados (banco é o alvo), e o revogamento `status=0` marca
     `date_modified` sem apagar histórico.

  7. **Refresh e delete honestos.** `refresh|N` (POST) rotaciona tokens
     via `refresh_token` e atualiza `token_expires_at` — preserva o
     refresh antigo se o MP não devolver um novo (campo pode vir ausente
     no sandbox/mock). `delete|N` (POST) = **desconexão LOCAL**: remove
     a linha e avisa em tela que a **revogação real é no painel do
     vendedor** no MP — não há endpoint de revoke no fluxo OAuth de
     marketplace que a plataforma opere em nome do vendedor.

  8. **`getUserInfo` nunca lança.** Falha de `/users/me` deixa
     `nickname`/`email` vazios e **não impede** o vínculo: os tokens
     (a parte crítica) já foram negociados com sucesso; a linha fica
     identificável por `mp_user_id` e o admin reconecta para enriquecer.

  9. **Dashboard + idiomas.** Módulo `text_manage_marketplace` no
     dashboard admin (×3 idiomas) com guarda
     `hasPermission('access','setting/marketplace')`, e grants
     idempotentes `setting/marketplace` access+modify no upgrade SQL
     (espelho F4-03). Tela = painel único (config + grade de vendedores).

 10. **Fora do escopo (F5-03, com credenciais reais de teste MP):**
     preference/checkout com `marketplace_fee`, IPN/Webhook de
     pagamento, conciliação de split e onboarding guiado de sellers
     externos.

- **Alternativas avaliadas e descartadas:**
  - *SDK oficial do MP via composer:* sem `composer.json`/autoload no
    projeto (verificado) — trafegar JSON via `system/helper` existente é
    coerente com a Fase 0 da library; SDK entra quando F5-03 exigir
    recursos avançados (assinaturas idempotency, SDK mantido pelo MP).
  - *Cifrar tokens nesta entrega com chave em config:* ilusão de
    segurança sem KMS (ver §6).
  - *Controller novo em `extension/payment/…`*: a casa não tem fronteira
    de extensões instaladas — `setting/marketplace` segue a topologia
    real dos painéis F4 (config + lista + ações em um lugar).

- **Consequências:** `rms_mp_seller` pronta p/ F5-03 (coleção de
  receivers por pedido = verificação de `mp_user_id` conectado/ativo);
  tela já exercitável no XAMPP do usuário com o mock (guia
  `docs/MercadoPago-Marketplace.md`); sem credenciais o botão de conectar
  fica desabilitado e a tela explica o pré-requisito — **zero 500/blank
  em qualquer estado de configuração**.

- **Bateria e evidências:** `tools/e2e/e2e_f502.sh` — 10 checks (schema,
  seeds idempotentes, tela renderiza/trava em desconfigurado, save
  valida/rejeita, `authorize` redireciona ao MP com state na sessão,
  `callback` rejeita state forjado, fluxo OAuth completo **contra mock
  local** com upsert por `mp_user_id`, refresh rotaciona
  `token_expires_at`, delete local-only, summary + kill). Mock do MP
  marketplace servido em `/var/www/html/hookmock.php?r=` com armazenamento
  em `hookdata/mp/` (mesmo padrão do receptor de webhooks F5-01 — Apache
  `PrivateTmp` impede mock em `/tmp`).
