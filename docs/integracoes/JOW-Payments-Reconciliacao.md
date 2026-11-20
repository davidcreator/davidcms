# JOW-Payments — reconciliação ativa (webhook perdido)

**Estado:** implementado em patch, para aplicação autorizada. Nenhuma migração foi aplicada por esta automação, nenhum pagamento real foi tentado e nenhuma chamada de rede é feita pelos testes.

## O problema que isto fecha

O fluxo do JOW-Payments já tratava o webhook como *gatilho* e o provedor como fonte da verdade, mas dependia de o webhook **chegar**. Se a notificação fosse perdida — firewall, WAF, timeout do provedor, aplicação fora do ar, URL de webhook errada no painel do provedor — a transação ficava `pending` no ledger para sempre:

- o painel continuava mostrando "link gerado" e a cobrança em aberto;
- o relatório de recebimentos não via aquele dinheiro;
- nenhuma rotina voltava a perguntar ao provedor o que aconteceu.

Reconciliação ativa é a rede de segurança: uma varredura periódica que **pergunta ao provedor** o estado real das tentativas abertas e aplica exatamente a mesma regra do webhook.

## O que a reconciliação faz

1. Lê do ledger as tentativas em estado aberto (`pending`, `requires_action`, `authorized`).
2. Filtra por janela: **mais velhas que `min_age`** (padrão 15 min — não concorre com o webhook que está chegando) e **mais novas que `lookback`** (padrão 7 dias — não faz polling eterno de cobrança velha).
3. Consulta o provedor com um método **read-only** (`ReconcilableGatewayAdapter::reconcile()`).
4. Aplica o resultado por `GatewayEventApplier` — o **mesmo** caminho do webhook: valor exato, `provider_payment_id` imutável, transição monotônica (nada de `paid → failed`), deduplicação de evento.
5. Devolve um relatório com contadores e uma linha por transação. Uma falha nunca aborta a varredura.

## O que ela não faz (de propósito)

| Não faz | Por quê |
| --- | --- |
| Criar cobrança | Reconciliação não é criação de pagamento |
| Capturar ordem PayPal aprovada | Mover dinheiro a partir de um cron é decisão de produto, não rede de segurança. A ordem fica `authorized` e aparece no relatório |
| Reembolsar | Idem; reembolso continua só via webhook assinado |
| Converter moeda | Nenhum adapter cota câmbio hoje; par não anunciado falha fechado |
| Mexer em `rms_mp_payment` / `rms_jow_charge` além do status | A tabela legada só recebe o status sincronizado, como já acontecia no webhook |
| Rodar sem token | Sem `REAMUR_RECONCILE_TOKEN` a rota responde 403 e não faz nada |

## Contrato

`dev/system/library/payment/reconcilable_gateway_adapter.php`

```php
interface ReconcilableGatewayAdapter {
    public function reconcile(
        GatewayPaymentRecord $record,
        GatewayAccount $account,
        GatewayCredentials $credentials
    ): GatewayWebhookResult;
}
```

`GatewayWebhookResult` reaproveita o vocabulário que já existia: `verified()` (estado conhecido), `retry()` (falha transitória), `ignored('motivo')` (não deu para conciliar).

Quem não implementa a interface simplesmente não é reconciliável — o relatório diz `provider_not_reconcilable` em vez de fingir que a fila estava vazia.

## Como cada provedor é consultado

| Provider | Âncora | Chamada | Observações |
| --- | --- | --- | --- |
| `mp` | `provider_reference` (id da ordem) ou `provider_checkout_id` | `GET /v1/orders/{id}` | Funciona com o dado já existente, **sem migração**. Reaproveita `reconcileOrderById()` do webhook |
| `stripe` | `provider_checkout_id` (id da sessão) | `GET /v1/checkout/sessions/{id}` | Sem âncora → `missing_provider_anchor`. Reembolso continua dependendo de `charge.refunded` |
| `paypal` | `provider_checkout_id` (id da ordem) | `GET /v2/checkout/orders/{id}` | Sem captura. Estados de captura (`REFUNDED`, `PARTIALLY_REFUNDED`) vêm na própria ordem |

## Migração (opcional, mas recomendada)

`tools/upgrade_jow_payments_reconcile.sql` — aditiva e idempotente (`information_schema` + `PREPARE`): acrescenta `provider_checkout_id` e o índice `provider_checkout` em `rms_payment_transaction`.

Sem ela o sistema continua funcionando: o código detecta a coluna (`SHOW COLUMNS`, cache por instância) e simplesmente não a usa. Mercado Pago reconcilia de qualquer forma; Stripe e PayPal passam a reconciliar para as tentativas criadas depois da migração.

Troque `rms_` se o `DB_PREFIX` da instalação for outro.

## Ativação

1. Aplicar (com autorização) `tools/upgrade_jow_payments_reconcile.sql`.
2. Definir o token em `config.php` — mínimo 32 caracteres, gerado aleatoriamente:

```php
define('REAMUR_RECONCILE_TOKEN', 'cole-aqui-um-segredo-de-32-ou-mais-caracteres');
```

3. Testar a rota uma vez, à mão:

```bash
curl -sS "https://SEU-SITE/catalog/index.php?route=cron/reconcile&format=json" \
  -H "Authorization: Bearer SEU_TOKEN"
```

4. Agendar. Exemplo de crontab (a cada 10 minutos):

```cron
*/10 * * * * curl -fsS -m 120 "https://SEU-SITE/catalog/index.php?route=cron/reconcile" -H "Authorization: Bearer SEU_TOKEN" >/dev/null
```

Também aceita `?token=SEU_TOKEN` na query string, para painéis de hospedagem que só permitem uma URL.

## Parâmetros

| Parâmetro | Padrão | Faixa | Para que serve |
| --- | --- | --- | --- |
| `limit` | 25 | 1..200 | Tentativas por conta em cada execução |
| `min_age` | 15 | 1..10080 (min) | Idade mínima antes de perguntar ao provedor |
| `lookback` | 7 | 1..90 (dias) | Janela de criação considerada |
| `format` | `text` | `text` \| `json` | `json` é o formato para monitoramento |

## Respostas HTTP

| Código | Quando |
| --- | --- |
| `200` | Varredura concluída sem falhas de integridade/configuração |
| `400` | Parâmetro fora da faixa ou não numérico |
| `403` | Token ausente, errado ou não configurado (fail closed) |
| `503` | Migração do núcleo ausente, ou falha de banco/credencial — com `Retry-After: 60` |

Um `retry` de provedor (`provider_unavailable` etc.) **não** é falha: ele conta no campo `retry` e a próxima execução tenta de novo.

## Formato do relatório

```json
{
  "ok": true,
  "generated_at": "2026-11-20T14:03:11Z",
  "scanned": 3,
  "applied": 1,
  "unchanged": 1,
  "skipped": 1,
  "retry": 0,
  "failed": 0,
  "details": [
    {
      "transaction_id": 41,
      "provider_code": "mp",
      "status_before": "pending",
      "status_after": "paid",
      "outcome": "applied",
      "reason": "provider_state_applied"
    }
  ]
}
```

O relatório nunca carrega payload do provedor, credencial, e-mail ou token. Motivos passam por `GatewayReconciliationReport::safeReason()`: e-mails viram `[email]`, sequências longas viram `[redacted]`, tudo é truncado.

## Onde está o código

| Arquivo | Papel |
| --- | --- |
| `dev/system/library/payment/reconcilable_gateway_adapter.php` | Contrato read-only por provedor |
| `dev/system/library/payment/gateway_reconciliation_service.php` | Janela, página, orquestração, isolamento de falhas |
| `dev/system/library/payment/gateway_reconciliation_report.php` | Contadores e sanitização do relatório |
| `dev/system/library/payment/gateway_event_applier.php` | Regras pós-verificação compartilhadas com o webhook |
| `dev/system/library/payment/gateway_reconcilable_ledger.php` | Contrato de fila (`pendingAttempts`) |
| `dev/system/library/payment/db_gateway_ledger.php` | Implementação da fila + coluna aditiva |
| `dev/catalog/controller/cron/reconcile.php` | Rota protegida por token |

`GatewayWebhookService::process()` passou a delegar a aplicação a `GatewayEventApplier`: **uma** implementação das regras, usada pelos dois caminhos. Era exatamente onde o bug do `method = 'mp'` nasceu — regra duplicada diverge.

## Limites conhecidos

- **Stripe não reconcilia reembolso.** O estado vem da sessão; `charge.refunded` continua sendo a fonte. Um reembolso cujo webhook se perdeu só aparece na próxima notificação. Próxima fase: enriquecer com `GET /v1/payment_intents/{id}` → `latest_charge`.
- **Stripe/PayPal sem âncora ficam de fora.** Tentativas criadas antes desta entrega não têm id consultável; o relatório mostra `missing_provider_anchor`. Não há backfill possível.
- **Sem throttle por transação.** `date_modified` só muda quando o estado muda, então a tentativa mais antiga é reconsultada em toda execução (o `limit` existe para conter custo). Se a fila aberta passar de algumas centenas, avaliar uma coluna `last_checked_at`.
- **Ordem PayPal aprovada e não capturada** não é capturada pela reconciliação — fica `authorized` e visível. Se isso virar rotina, o certo é uma ação explícita no admin, não um cron.
- **Um tenant, uma execução.** A varredura percorre todas as contas de `rms_jow_gateway`; não há paralelismo nem lock entre execuções concorrentes (duas execuções simultâneas podem consultar a mesma tentativa — a aplicação é idempotente pelo ledger, então o efeito é apenas chamada duplicada ao provedor).
