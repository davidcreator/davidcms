# ADR 0118 — Pagamentos multi-gateway: núcleo novo por tentativa e reconciliação ativa

| | |
| --- | --- |
| **Status** | ✅ aceita (KZ-64, 2026-11-20) |
| **Data** | 2026-11-20 |
| **Contexto** | O núcleo multi-gateway entrou na `main` em `090fda1` (`feat: add multi-gateway support for Stripe, PayPal, MP`) sem registro de decisão, e a primeira auditoria do fluxo de pagamentos encontrou dois defeitos: a escrita na cobrança legada filtrava o provedor fixo `'mp'` (cobrança paga por Stripe ou PayPal nunca saía de `pending`) e não existia nenhum caminho para recuperar um webhook perdido. Corrigir os dois exigia decidir como os dois núcleos de pagamento convivem. |
| **Relacionadas** | ADR 0097 (contrato de meio de pagamento: `Method`/`Checkout`/`DbLedger`), ADR 0094 (segredos cifrados no banco), ADR 0033 (webhooks de pedido, HMAC e retries), ADR 0034 e ADR 0035 (Mercado Pago marketplace) |

## Decisão

**1. Os dois contratos convivem, e a convivência é declarada.**
O fluxo público de pedido continua no contrato da ADR 0097 (`Method` → `Checkout` → `DbLedger` sobre `rms_mp_payment`). O núcleo novo (`GatewayAdapter` → `GatewayPaymentService` → `GatewayLedger` sobre `rms_payment_transaction`) é o dono das **tentativas de pagamento** por provedor. Nenhum código novo escreve nas duas estruturas ao mesmo tempo: o que atravessa é só o status da cobrança legada (`rms_jow_charge`), pelo ponto único `GatewayPayment::syncJowChargeStatus()`. **Levar o fluxo `order` para o ledger novo é dívida declarada e entra em ADR própria** — não é consequência desta.

**2. Um adapter por provedor, registrado, com chave de idempotência própria.**
`GatewayAdapter` (contrato), `GatewayRegistry` (registro), `InstalledGatewayAdapters` (montagem a partir de `rms_jow_gateway` + `GatewayCredentialVault`) e três implementações: `mercado_pago_gateway_adapter`, `stripe_checkout_adapter`, `paypal_orders_adapter`. A chave de idempotência é sempre nossa e vai ao provedor (`external_reference` no MP, `client_reference_id` na sessão do Stripe, `custom_id` na ordem do PayPal).

**3. Uma regra só para aplicar evento verificado.**
`GatewayEventApplier` concentra as checagens pós-verificação: a referência do evento é de **uma tentativa nossa**, o valor confere ao centavo, `provider_payment_id` não muda depois de gravado, a transição é monotônica (`paid` não volta para `failed`), a notificação repetida não altera nada. `GatewayWebhookService` passou a delegar nele. Sem esse ponto único, a reconciliação seria a terceira cópia da regra de dinheiro — a duplicação foi exatamente o que produziu o filtro `'mp'` fixo.

**4. Reconciliação é rede de segurança, não gatilho.**
`ReconcilableGatewayAdapter::reconcile()` é **read-only por contrato**: pergunta o estado real e devolve um `GatewayWebhookResult` — nada de criar cobrança, capturar ou reembolsar. Ordem do PayPal aprovada e não capturada continua `authorized` e aparece no relatório; mover dinheiro a partir de um cron é decisão de produto, não recuperação de webhook. A varredura é limitada por janela dupla (`min_age` padrão 15 min, `lookback` padrão 7 dias, `limit` padrão 25, teto 200), isola falha por tentativa, confere a referência antes de aplicar e devolve relatório **sanitizado** (`safeReason()` mascara e-mail e sequência longa).

**5. O gatilho é uma rota de catálogo protegida por token, não um agendador.**
O framework não tem runner de cron (`action_cron` é config vazia): a rota `cron/reconcile` é chamada pelo crontab do sistema e exige `REAMUR_RECONCILE_TOKEN` com no mínimo 32 caracteres, comparado com `hash_equals`, em `Bearer` ou `?token=`. Sem token configurado a rota responde 403 **antes** de tocar no banco (fail closed). Parâmetros fora de faixa respondem 400; tabela ausente ou exceção respondem 503 com `Retry-After: 60`; `retry` de provedor é resultado esperado e não pinta a execução de vermelho.

**6. A âncora consultável do provedor é coluna aditiva e a migração é opcional.**
Mercado Pago já guardava o id da ordem em `provider_reference` e reconcilia sem migração. Stripe e PayPal não persistem nada consultável (a chave que eles guardam é a **nossa**), então ganham `provider_checkout_id` (`rms_payment_transaction`), com índice `provider_checkout (gateway_id, provider_checkout_id)`. A coluna é detectada por instância (`SHOW COLUMNS`, cache **não estático**, para um deploy que aplique a migração ser visto no request seguinte) e o SQL é idempotente (`information_schema` + `PREPARE`). Sem a migração o sistema funciona: Stripe e PayPal reportam `missing_provider_anchor` e o Mercado Pago reconcilia normalmente.

**7. A cobrança legada é sincronizada pelo provedor verificado, não por um provedor fixo.**
`GatewayPayment::syncJowChargeStatus(int $chargeId, string $providerStatus, string $providerCode)` usa o `provider_code` do registro **verificado no ledger**, validado por regex antes de qualquer query. Cobrança manual (`pix`/`card`/`boleto`) continua intocada, porque nenhum código de provedor instalado é igual a um método manual — a invariante "um evento de provedor só altera a cobrança do próprio provedor" passa a estar no dado, não numa constante.

## Alternativas consideradas

1. **Reconciliar só quando o provedor reenviar o webhook (retry do lado dele)** — rejeitada: é justamente o caminho que falhou. O provedor desiste depois de algumas tentativas e o pedido fica em aberto sem ninguém saber.
2. **Capturar no cron a ordem do PayPal aprovada e não capturada** — rejeitada: captura é movimento de dinheiro e exige decisão humana; o cron passa a ser um lugar onde dinheiro se move sem ninguém olhar. Fica `authorized` e visível no relatório; se virar rotina, o caminho é uma ação no admin.
3. **Unificar os dois contratos agora** (levar `order` para `rms_payment_transaction` e aposentar `DbLedger`) — adiada, não rejeitada: é a dívida arquitetural central, mas mexer no fluxo público de pedido no mesmo passo de um hotfix de status aumenta o risco sem necessidade. Tem ADR própria.
4. **`provider_checkout_id` NOT NULL na migração** — rejeitada: quebraria instalação que aplique o código antes do SQL e não há valor para preencher nas linhas existentes (o id nunca foi gravado; não existe backfill possível).
5. **Varredura sem janela mínima** (`min_age = 0`) — rejeitada: competiria com o webhook que está chegando e multiplicaria chamada ao provedor por um resultado que chegaria sozinho segundos depois.

## Limites conhecidos

- **Stripe não reconcilia reembolso.** O estado vem da sessão; `charge.refunded` segue sendo a única fonte para reembolso. Reembolso com webhook perdido só aparece na próxima notificação. Próximo passo: `GET /v1/payment_intents/{id}` → `latest_charge`.
- **Tentativas Stripe/PayPal anteriores a esta entrega ficam fora** (`missing_provider_anchor`): o id consultável nunca foi gravado.
- **Sem throttle por tentativa.** `date_modified` só muda quando o estado muda, então a tentativa mais antiga da janela é reconsultada em toda execução (o `limit` contém o custo). Se a fila aberta passar de algumas centenas, avaliar `last_checked_at`.
- **Sem trava entre execuções concorrentes.** Duas execuções simultâneas podem consultar a mesma tentativa; a aplicação é idempotente pelo ledger, então o efeito é chamada duplicada ao provedor, não estado errado.
- **O adapter do Stripe não reconcilia `payment_intent` quando o pagamento foi por boleto/Pix assíncrono** — nesses casos a sessão fica `unpaid` e a reconciliação reporta `pending`, coerente com o ledger.

## Consequências

- **Arquivos novos:** `reconcilable_gateway_adapter.php`, `gateway_reconcilable_ledger.php`, `gateway_reconciliation_service.php`, `gateway_reconciliation_report.php`, `gateway_event_applier.php`, `dev/catalog/controller/cron/reconcile.php`.
- **Arquivos alterados:** `gateway_webhook_service.php`, `db_gateway_ledger.php`, `db_gateway_account_store.php`, `gateway_account_store.php`, `gateway_payment_record.php`, `gateway_payment_result.php`, os três adapters, `dev/catalog/model/checkout/gateway_payment.php`, `dev/catalog/controller/checkout/gateway.php`.
- **Interfaces:** `GatewayAccountStore::all()` (varredura inclui conta desativada — uma conta desligada ainda pode ter pagamento em voo); `GatewayReconcilableLedger::pendingAttempts()` é interface **separada** que estende `GatewayLedger`, então nenhum implementador existente quebra.
- **Tabelas/colunas:** `provider_checkout_id` + índice `provider_checkout` em `rms_payment_transaction` (`tools/upgrade_jow_payments_reconcile.sql`, aditivo e idempotente). As tabelas de pagamento e a coluna nova entraram no **`docs/arquitetura/Dicionario-de-Dados.md` §9** logo depois desta ADR (KZ-64, 2ª parte): a divergência do checklist do PR (`Diverge → ADR obrigatório`) está fechada, com `jow_gateway`, `payment_transaction`, `payment_gateway_event`, `jow_charge`, `jow_pix_key`, `mp_payment` e `mp_seller` documentados, incluindo os três vocabulários de `status` e a pegadinha `cancelled` × `canceled`.
- **Chaves de configuração:** `REAMUR_RECONCILE_TOKEN` (mínimo 32 caracteres). Nenhuma outra chave nova.
- **Testes:** 53 novos — `JowChargeStatusSyncTest` (5), `GatewayReconciliationServiceTest` (17), `GatewayAdapterReconcileTest` (13), `DbGatewayLedgerReconcileTest` (9), `ReconcileCronControllerTest` (9). Suíte completa 1881 testes verde, PHPStan sem erros.
- ⚠️ **Custo operacional novo:** uma rota pública a mais para monitorar. Sem token válido ela responde 403 e não faz nada, mas o log do servidor precisa ser observado nas primeiras semanas para distinguir 403 (token errado) de 503 (schema/provedor).
- ⚠️ **Dois núcleos de pagamento convivendo** até a ADR de migração do `order`: quem for ler o código precisa saber qual contrato está olhando. A separação é por tabela (`rms_mp_payment` × `rms_payment_transaction`).

## Documentos atualizados

- `docs/integracoes/JOW-Payments-Reconciliacao.md` (novo) — ativação, crontab, parâmetros, respostas HTTP, leitura do relatório, limites;
- `docs/integracoes/KZ-64-Como-Aplicar.md` e `docs/historico/KZ-64-Relatorio.md` (novos) — aplicação e entrega do pacote, movidos da raiz para `docs/`;
- `docs/CHANGELOG.md` — entrada KZ-64;
- `dev/admin/model/tool/internal-tools.json` — `jow-payments` 1.2.0 com as duas notas novas;
- `tools/upgrade_jow_payments_reconcile.sql` (novo);
- `docs/arquitetura/Dicionario-de-Dados.md` §9 — schema canônico dos pagamentos (KZ-64, 2ª parte);
- `agents-ia/decisoes/README.md` — índice.
