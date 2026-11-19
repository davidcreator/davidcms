# JOW-Payments — adapters multi-gateway (Mercado Pago, Stripe, PayPal)

**Estado:** implementado em patch, para aplicação autorizada na base. Nenhuma migração, cobrança real ou chamada a provedor foi executada pela automação.

## Contas próprias por provedor

O painel **Admin → payments/jow** passa a manter **uma conta própria por gateway**, todas com rótulo `JOW platform` na tabela `rms_jow_gateway` (uma linha por `provider_code`), credenciais cifradas por campo e ambiente `sandbox|live` independente:

| Provider | Método | Moedas | Credenciais (cifradas) | Webhook assinado | Conciliação |
|---|---|---|---|---|---|
| `mp` | `checkout_pro` | BRL | `access_token`, `webhook_secret` | `x-signature` (HMAC `id;request-id;ts`) | `GET /v1/orders/{id}` |
| `stripe` | `checkout_session` | BRL, USD, EUR | `secret_key`, `webhook_secret` | `Stripe-Signature` (`t=…,v1=…`, tolerância 300 s, esquema `whsec_` removido antes do HMAC) | `GET /v1/checkout/sessions/{id}`; reembolsos por `GET /v1/charges/{id}` |
| `paypal` | `checkout_orders` | BRL, USD, EUR | `client_id`, `client_secret`, `webhook_id` | `POST /v1/notifications/verify-webhook-signature` (API oficial) | `GET /v2/checkout/orders/{id}`; capture server-side idempotente em `CHECKOUT.ORDER.APPROVED` |

Nenhum adapter lê tokens OAuth de sellers nem envia split (`marketplace_fee`, `application_fee_amount`, `transfer_data`, `payee`). O checkout público Marketplace permanece intocado.

## Fluxo por provedor

- **Mercado Pago** — igual ao patch anterior: `POST /v1/orders` com `X-Idempotency-Key` e `external_reference` (≤64), `checkout_url` para redirect; estado só pela ordem consultada.
- **Stripe** — `POST /v1/checkout/sessions` (form-encoded) com `Idempotency-Key`, `mode=payment`, `client_reference_id` e `metadata[jow_reference]` iguais à chave idempotente; o pagador é redirecionado para `session.url`. Eventos tratados: `checkout.session.completed`, `checkout.session.async_payment_succeeded/failed`, `checkout.session.expired`, `charge.refunded` (parcial → `partially_refunded`, total → `refunded`). A referência persistida no ledger é o `client_reference_id` (a chave), não o id da sessão.
- **PayPal** — OAuth2 client-credentials, `POST /v2/checkout/orders` com `PayPal-Request-Id` (a chave idempotente; `custom_id`/`invoice_id` também recebem a chave), `intent=CAPTURE`, `user_action=PAY_NOW`, `shipping_preference=NO_SHIPPING`. O pagador é redirecionado para o link `payer-action`. No webhook `CHECKOUT.ORDER.APPROVED`, o adapter executa `POST …/capture` com `PayPal-Request-Id = chave + ':capture'` e concilia pela ordem consultada: `captures[0].status` `COMPLETED→paid`, `PARTIALLY_REFUNDED→partial`, `REFUNDED→refunded`, `DECLINED→failed`, `PENDING→authorized`. Eventos `PAYMENT.CAPTURE.*` resolvem a ordem via `supplementary_data.related_ids.order_id` (ou link `up`). Disputas (`CUSTOMER.DISPUTE.*`) ainda não são mapeadas — chegam como `ignored` e ficam para uma fase futura.

Reembolso parcial e total e estados `failed/expired/cancelled` são normalizados pelo mesmo conjunto `GatewayPaymentStatus` das outras gateways; transições fora de ordem não regridem estado.

## Idempotência

A mesma política do patch Mercado Pago, agora por provedor: UUID v5 determinístico por cobrança na tentativa inicial; tentativas abertas reutilizam a chave persistida; após falha/cancelamento/expiração, chave nova derivada da cobrança + transação anterior. O salt do MP permanece `reamurcms:jow:mercadopago:` (compatibilidade com tentativas em voo); Stripe e PayPal usam `reamurcms:jow:gateway:<provider>:`. Limites por provedor: MP `external_reference` ≤ 64; PayPal `invoice_id` ≤ 127; Stripe `Idempotency-Key` ≤ 255.

## Segurança

- Corpo do webhook lido com limite de 65.536 bytes; respostas `200 ok` para evento ignorado/assinatura inválida e `503 + Retry-After: 60` apenas para falha transitória.
- Nenhum segredo entra em `public_config_json`; os campos de credencial por provedor são fixados no controller (`PROVIDER_SECRET_FIELDS`) e cifrados pelo `GatewayCredentialVault`.
- A conta só é habilitada quando **todos** os campos do provedor abrem com a chave atual; detalhes do provedor nunca aparecem em logs/erros.
- `REAMUR_PAYMENTS_ALLOW_LIVE=true` continua obrigatório para `environment=live` (todos os provedores).
- Sandboxes: Mercado Pago exige vendedor de teste e pagador `@testuser.com`; Stripe usa chaves `sk_test_`/`whsec_` do modo test; PayPal usa credenciais de sandbox (`api-m.sandbox.paypal.com`) sem restrição de e-mail.

## Como adicionar um novo gateway (preparação para o futuro)

O núcleo já é provider-agnostic; novo gateway exige apenas:

1. Implementar `GatewayAdapter` (`code()`, `label()`, `capabilities()`, `quote()`, `createPayment()`, `verifyNotification()`) num novo arquivo em `dev/system/library/payment/`, sem ler tokens de sellers nem enviar split.
2. Declarar a matriz de métodos/moedas/dígitos em `GatewayCapabilities` — par não listado falha fechado.
3. Registrar o adapter em **um único ponto**: `InstalledGatewayAdapters::registry()` (allowlist explícita; valores do banco nunca viram nomes de classe).
4. Adicionar os campos de credencial em `PROVIDER_SECRET_FIELDS`/`PROVIDER_FIELD_TEXTS` no controller admin e as entradas de idioma nas três línguas.
5. Escrever testes com client fake injetado (como `StripeCheckoutAdapterTest`/`PaypalOrdersAdapterTest`).

Migrations novas normalmente **não** são necessárias: `rms_jow_gateway.provider_code`, `rms_payment_transaction.provider_code` e `rms_jow_charge.method` já comportam os códigos (`method` VARCHAR(8) aceita `stripe`/`paypal`).

## Verificação

- Testes: 26 testes novos (126 assertions) para os dois adapters, mais os 55 existentes do núcleo Mercado Pago/ledger — todos com fakes em memória, sem chamada real a provedor.
- `composer analyse` sem erros; suíte completa verde.
- Nenhuma migração foi aplicada, nenhum pagamento real foi tentado e F9-27 não foi executada.
