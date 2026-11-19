# JOW-Payments + Mercado Pago

**Estado:** integração publicada no GitHub do projeto. A validação Sandbox operacional continua pendente; veja também `JOW-Payments-MultiGateway-Adapters.md` para os adapters Stripe/PayPal da mesma fundação.

## Dois fluxos, credenciais separadas

- **Cobranças internas do JOW:** `payments/jow` pode criar uma cobrança Checkout Pro com a conta própria Reamur. O adapter `MercadoPagoGatewayAdapter` usa a Orders API (`POST /v1/orders`, Checkout Pro em modo manual), `X-Idempotency-Key` e `external_reference` ligados à tentativa do ledger. Não envia `marketplace_fee` nem lê tokens de sellers.
- **Checkout público Marketplace:** permanece no fluxo já existente de `setting/marketplace.paylink` e `checkout/payment`: OAuth, tokens dos vendedores conectados, seleção do primeiro seller ativo e `mp_marketplace_fee_percent` continuam no adapter legado `Mercadopago`. Não há cutover, migração de registros nem troca para conta única.
- **Cobranças JOW manuais/PIX local:** permanecem disponíveis. O provedor é escolhido explicitamente no formulário; Checkout Pro deixa PIX/cartão/boleto a cargo do Mercado Pago.

A Orders API foi escolhida para o fluxo JOW porque a referência oficial exige `X-Idempotency-Key` e retorna `checkout_url` para Checkout Pro [Mercado Pago — Create order](https://www.mercadopago.com.br/developers/en/reference/online-payments/checkout-pro/create-order/post). A notificação da Orders API é do tipo `order`; o adapter consulta `GET /v1/orders/{id}`, verifica a assinatura, compara referência, moeda e valor e só então aplica o estado [Mercado Pago — Order notifications](https://www.mercadopago.com.br/developers/en/docs/checkout-api-orders/notifications).

## Configuração necessária após a migração autorizada

1. Aplicar manualmente, no momento aprovado pelo operador, `tools/upgrade_jow_payments_multigateway_core.sql`. O patch não executa SQL.
2. Em **Admin → JOW-payments → Mercado Pago — conta própria**, informar o Access Token da conta da plataforma e habilitar um segredo de assinatura Webhooks de pelo menos 16 caracteres. Os segredos são cifrados por campo no armazenamento `jow_gateway`, com contexto próprio; não reutilizam `mp_client_secret`, `mp_webhook_secret` nem os tokens OAuth de seller.
3. No aplicativo da conta própria em Mercado Pago Developers, configurar o evento **Order (Mercado Pago)** para a URL mostrada no painel JOW (`.../catalog/index.php?route=checkout/gateway.notify&gateway_id=...`) e salvar o mesmo segredo de assinatura no JOW.
4. Começar em **Sandbox / teste** com uma conta vendedora de teste e o Access Token de usuário de teste; a Orders API não aceita credenciais estáticas `TEST-`. Se houver e-mail do pagador, ele precisa ser `@testuser.com`. O seletor `sandbox` é uma trava interna e não troca o host da API: nunca o combine com o token da conta real. Para produção, além de escolher `live`, é necessário liberar explicitamente `REAMUR_PAYMENTS_ALLOW_LIVE=true` no `config.php`. Sem essa chave, o núcleo recusa a chamada antes de acessar a API. Consulte os requisitos da [referência oficial de Orders](https://www.mercadopago.com.br/developers/en/reference/online-payments/checkout-pro/create-order/post).
5. Criar uma nova cobrança escolhendo **Mercado Pago Checkout Pro**; o link da ordem aparece na coluna Checkout. O botão de gerar/reutilizar usa a mesma chave idempotente enquanto a tentativa está aberta; após falha/cancelamento/expiração, uma nova tentativa recebe um UUID v5 estável, derivado da cobrança e da tentativa anterior. Assim, uma repetição após erro de rede ou falha de persistência reapresenta a mesma chave ao Mercado Pago. O e-mail do pagador, se informado no JOW, é encaminhado como `payer.email`.

A URL de retorno configurada para o comprador volta à página pública inicial. A URL de notificação precisa ser HTTPS e alcançável pelo Mercado Pago. Se o ambiente não tiver os três tables do núcleo, a tela antiga continua acessível e as opções MultiGateway ficam sinalizadas como não instaladas.

## Verificação e limites da entrega

- Webhook Orders: assinatura HMAC-SHA256, referência da ordem, `total_amount`/BRL, ID de pagamento e transições seguras; duplicatas são deduplicadas pelo ledger e notificações fora de ordem não regridem o estado.
- Status `partially_refunded` fica distinto no ledger e sincroniza como `partial` no registro legado do JOW. Chargebacks sincronizam como `disputed`.
- Testes usam clientes falsos em memória; não fazem chamadas reais ao Mercado Pago. Nenhuma migração foi aplicada, nenhum pagamento real foi tentado e F9-27 não foi executada.
- A leitura/escrita do ledger novo é aditiva. As tabelas legadas `rms_mp_payment` e `rms_jow_charge` não são migradas nem substituídas.
