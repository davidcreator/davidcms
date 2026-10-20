# ADR 0035 — Checkout com split (marketplace_fee) e notificação de pagamento: a verdade é a API, nunca o POST

- **Status:** Aceita — 2026-10-20 (F5 Tarefa 03)
- **Contexto:** ADR 0034 entregou a fundação do Marketplace (OAuth de
  vendedores). F5-03 entrega o fluxo de dinheiro: o admin gera um **link
  de pagamento** por pedido (Checkout Pro autenticado no **seller**, com
  `marketplace_fee` = comissão da plataforma), o cliente paga no MP e o
  CMS é **notificado** (webhook/IPN) para refletir o status no pedido —
  tudo provado por bateria contra **mock local**; a validação em sandbox
  real fica para quando o usuário provisionar as credenciais de teste
  (já decidido na aprovação do escopo).
- **Leitura real (prova, HEAD `937286d`):** `catalog/checkout` só tinha
  `quote.php` (orçamento sem preço — `total` 0.0000, Dicionário §6);
  `rms_order` não tinha nenhuma coluna/mencionamento de pagamento
  (`grep payment|mp_` no schema = 0); o dispatcher de webhooks F5-01 já
  cobria `order_history_edit` no admin, reusável no catálogo.

- **Decisões:**

  1. **Um pagamento por tentativa, link público por token.** Nova tabela
     `rms_mp_payment` (UNIQUE `preference_id` e `public_token`). O link
     do cliente NUNCA expõe `order_id` na URL: `checkout/payment.pay|TOKEN`
     com `bin2hex(random_bytes(24))`. Pedido com tentativa **pending**
     REUSA o mesmo link (não duplica preference no MP); pedido já
     `approved` não regenera (erro honesto ao admin).

  2. **Split = `marketplace_fee` calculado no backend.** Percentual em
     `rms_setting.mp_marketplace_fee_percent` (default **10%**, clamps
     0–100; nesta entrega a edição é por chave no banco — sem tela).
     Valor = `round(total * pct / 100, 2)`, enviado como escalar
     `marketplace_fee` da preference; item único "Pedido #N" com
     `unit_price = total` e `currency_id = BRL`. O MP repassa
     `total − fee` ao seller e retém `fee` na conta da plataforma.

  3. **Costura `mp_api_url` no mesmo padrão do ADR 0034 §4.** A library
     chama `apiUrl().'/checkout/preferences'` e `apiUrl().'/v1/payments/{id}'`;
     default `https://api.mercadopago.com`. A bateria grava o override
     SÓ no banco de teste apontando ao mock do webroot. Métodos novos na
     library: `createPreference()` (transporte+auth; payload é domínio do
     caller) e `getPayment()` (consulta autenticada no seller).

  4. **O notify NUNCA confia no POST.** Controller público
     `checkout/payment.notify` aceita webhook v2 (`type=payment`,
     `data.id` na query OU no JSON do corpo) e IPN clássico
     (`topic=payment` + `id` em GET **ou POST form** — ambos lidos).
     Extraído o id, o handler **consulta `/v1/payments/{id}` autenticado
     no seller** e só reflete no banco o status 2xx bem formado. Resposta
     **sempre 200** com rótulo (`ok:processed|already-processed|ignored|
     not-ours|no-reference|no-local-record|signature-rejected|error`) —
     falha local não deve gerar re-entrega em loop; e o MP não recebe
     detalhe algum em caso de erro.

  5. **Descoberta do seller no notify.** Webhook v2 carrega `user_id` no
     payload → resolve o seller direto (chave natural UNIQUE). Sem ele
     (IPN), itera os sellers ativos até um confirmar o payment via API
     (recorte honesto do modelo: N sellers ativos = N chamadas no pior
     caso — F5-04+ evolui para `metadata` na preference). O lançamento
     local casa pelo `external_reference` (= `order_id`, gravado na
     preference) contra a tentativa mais recente do pedido.

  6. **Assinatura x-signature opcional, já implementada.** Se
     `mp_webhook_secret` estiver preenchido (seed vazio — do painel MP,
     "assinatura de webhooks"), o notify valida `hash_hmac(sha256,
     "id:<id>;request-id:<rid>;ts:<ts>;", secret)` contra `v1` com
     `hash_equals` ANTES de chamar a API. Vazio = desligado (a defesa por
     consulta à API do §4 segue valendo). Bateria NÃO configura secret;
     a validação em sandbox real o cobrirá.

  7. **Efeitos de domínio.** `approved` → pedido status **4 (Approved)**
     + `rms_order_history` + dispara webhooks F5-01
     (`order_history_edit`) pelo MESMO dispatcher/payload do admin
     (receptores veem um schema único). `rejected|cancelled` → status
     **6 (Canceled)**. `pending|refunded|...` → só registra no payment.
      Idempotência estrita por `(payment_id, status)`: replay do MP
     não duplica history (assertado na bateria).

  8. **Link público e rota de catálogo.** `pay|TOKEN` → 302 ao
     `init_point` (sem sessão/cookie); token inválido, inexistente ou de
     pagamento terminal (`approved|rejected|cancelled`) → 404 da casa
     (`error/not_found`), nunca stack trace. O notify não requer nada da
     sessão (não é form do usuário): guarda é a consulta à API + assinatura
     opcional.

  9. **Admin: site_url do CATÁLOGO, não da admin (bug achado na revisão).**
     A primeira versão montava `notification_url`/link público com
     `$this->config->get('site_url')` — que na aplicação ADMIN vale
     `.../dev/admin/`. Helper `catalogUrl()` deriva a base pública
     (rtrim + strip do sufixo `/admin` + `/catalog/index.php?route=`),
     convenção do Contrato-Bootstrap. Bateria asserta o link público na
     tela contendo `catalog/index.php?route=checkout/payment.pay|`.

 10. **SQL só em models (espelho admin×catálogo).** Admin model
     `setting/marketplace` ganha `getActiveSeller` (o PRIMEIRO conectado
     ativo recebe — mapeamento produto→seller fica para o catálogo de
     produtos), `addPayment`, `getPaymentByOrder`, `getPayments` (grade
     sem tokens); catálogo model `checkout/mp_payment` (classe
     `Mp_payment` — underscore é a convenção do autoloader da casa,
     achado na bateria) com `reflectPaymentStatus` idempotente.

- **Alternativas avaliadas e descartadas:**
  - *Payment Brick/API transparente (auto-captura):* exige PCI/UX nova e
    SDK; Checkout Pro com link é o recorte honesto do modelo orçamento
    (o preço é definido **depois** da análise — o link é gerado pelo
    admin, não num carrinho).
  - *Cron de reconciliação:* sem scheduler na casa; a notificação + tela
    de status do pagamento cobrem. (Fallback manual: status muda na grade
    quando o MP re-notifica.)
  - *Validar webhook SÓ pela assinatura:* segredo ainda não provisionado
    e a consulta à API é a defesa mais forte disponível sem depender de
    config — os dois se compõem (§6).

- **Consequências:** com credenciais reais, a validação vira troca de
  seeds (app do usuário) + replay da F5-03: cada assert é verificável em
  sandbox. Conhecidas honestas: (a) fee só % global (sem fee por vendedor);
  (b) notify sem `user_id` itera sellers ativos; (c) `refunded` não move
  o pedido (decisão de negócio p/ quando o fluxo existir).

- **Bateria e evidências:** `tools/e2e/e2e_f503.sh` — 10 checks
  (schema/seeds idempotentes, paylink sem seller/total zero/approved =
  erros honestos sem 500, criação com fee exata 10% e payload conferido
  no mock, replay = reuso, link público 302/404, notify approved com
  confirmação via API do seller + status 4 + history + webhook F5-01
  entregue no receptor, replay idempotente sem history duplicada, notify
  rejected → status 6). Suíte: **24 baterias, 281 asserts, 0 fail**.
- **Ambiente (registro):** sandbox foi reconstruído nesta sessão;
  `sync_live.sh` deixa de usar symlink `/tmp/fui` e copia para
  `/var/www/html/davidcms/dev` (default novo); as baterias antigas foram
  re-homed para o mesmo path (upload checks dependem dele).
