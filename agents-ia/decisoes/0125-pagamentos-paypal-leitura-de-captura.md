# ADR 0125 — Pagamentos: o PayPal também é lido pela captura, não só pela ordem

| | |
| --- | --- |
| **Status** | ✅ executada (KZ-64, 2026-11-20) |
| **Data** | 2026-11-20 |
| **Agente proponente** | system |
| **Contexto** | A ADR 0123 passou a varrer tentativas **já pagas** e a ADR 0124 fechou o Stripe. Ao revisar os três provedores para dizer "leitura de estorno está completa", apareceu um furo no **PayPal** que o próprio teste existente escondia: `reconcileOrder()` só descobre estorno pelo bloco `purchase_units[0].payments.captures[]` **embutido na resposta da ordem**. Quando a resposta consultada vem sem esse bloco, a ordem `COMPLETED` normaliza para `paid` e a varredura conclui "está tudo pago" — para sempre. O estorno volta a depender do aviso `PAYMENT.CAPTURE.REFUNDED`, que é exatamente o que se perde. |
| **Relacionadas** | ADR 0124 (Stripe: sessão não muda com estorno), ADR 0123 (varredura de pagas), ADR 0121 (escopo do estorno), ADR 0118 (reconciliação ativa) |

## O fato, verificado no código

`reconcileOrder()` tem hoje:

```php
$capture = self::firstCapture($order);
if ($capture !== []) {           // só olha o estado de estorno SE o bloco veio embutido
    $status = self::normalizeCaptureStatus($capture['status']) ?? $status;
}
```

`firstCapture()` lê `purchase_units[0].payments.captures[0]`. Se o bloco não vier, `$capture` é `[]`, o estado de estorno **nunca é considerado**, e a ordem `COMPLETED` responde `paid`.

A captura é um **recurso separado** do pagamento (`/v2/payments/captures/{id}`) e a ordem aponta para ela por **link** (`rel: up`) em vez de embutir o objeto. A documentação do PayPal descreve a captura como consultável por id próprio — e a ordem completa nem sempre traz o objeto embutido.

## Decisão

**1. Ordem paga sem captura embutida → ler a captura pela link `up`.**

| Passo | Regra |
| --- | --- |
| ordem | `getOrder()` — continua sendo a fonte de "pago" |
| a ordem respondeu `paid` **e** veio **sem** `payments.captures`? | procura a link `up` que aponte para `/v2/payments/captures/{id}` |
| achou id de captura | `getCapture()` (GET puro) e o estado de estorno vem dela |
| não achou | vale o veredicto da ordem, **sem inventar id** |

**2. Só o estorno é aceito vindo da captura.**

`REFUNDED` e `PARTIALLY_REFUNDED` da captura viram veredicto. `COMPLETED` **não** reescreve o que a ordem já disse: a captura aqui existe para descobrir **o que a ordem não contou**, não para ser uma segunda opinião sobre "pago". Sem isso, toda tentativa paga apareceria como `ignorado` a cada ciclo — o mesmo erro que a ADR 0124 evitou no Stripe.

**3. Nenhum caminho novo de dinheiro.**

`getCapture()` é `GET`. Não captura, não estorna, não cria. A rota de captura (`captureOrder`, POST) continua sendo chamada **apenas** no fluxo de webhook de ordem aprovada, como antes.

**4. A identidade da captura é conferida.**

`capture_not_reconciled` quando o id devolvido não é o id pedido — um id reaproveitado no caminho não pode virar veredicto sobre outro pagamento.

## O que muda em cada arquivo

| Arquivo | Mudança |
| --- | --- |
| `system/library/payment/paypal_orders_client.php` | `getCapture()` — `GET /v2/payments/captures/{id}`, com o mesmo cuidado de id e credenciais que `getOrder()` |
| `system/library/payment/paypal_orders_adapter.php` | `reconcile()` lê a captura quando a ordem está paga e sem bloco embutido; `reconcileCapture()` (novo) e `linkedCaptureId()` (novo, lê a link `up`) |

Nenhuma migração, nenhuma coluna, nenhuma tabela.

## O que esta ADR **não** faz

| Fora | Por quê |
| --- | --- |
| **Não** estorna nada | R2/R3 (D01) continuam abertos |
| **Não** captura ordem autorizada | continua de propósito: capturar a partir de cron é decisão de produto, e a ADR 0118 fixa isso |
| **Não** muda o caminho de webhook | o aviso `PAYMENT.CAPTURE.REFUNDED` continua indo pelo mesmo `reconcileOrder()` |
| **Não** mexe em Mercado Pago | o Mercado Pago lê a **ordem**, e a Order API do MP carrega o estado de pagamento na própria ordem — não há segunda chamada a fazer |
| **Não** decide D01 | estornar pela tela é outra release |

## Riscos residuais

| Risco | Situação |
| --- | --- |
| Custo: uma requisição a mais por tentativa paga **sem** captura embutida | só quando a ordem não traz o bloco; ordem com captura embutida não gasta nada |
| Ordem paga antiga sem link `up` | vale o veredicto da ordem, como antes desta ADR |
| Captura com estado desconhecido | `ignorado`/`unknown_capture_status`; nada é gravado |
| PayPal fora do ar na leitura da captura | `retry('provider_unavailable')` |
| Sandbox | provado com dublês e leitura de contrato, **não** contra o PayPal de verdade — depende das credenciais (P4) |

## Prova

| O que | Como |
| --- | --- |
| estorno achado pela captura ligada | ordem paga sem `payments` + captura `REFUNDED` → `refunded`, e a captura lida **pelo id da link `up`** |
| estorno parcial | captura `PARTIALLY_REFUNDED` → `partially_refunded` |
| captura `COMPLETED` | veredicto continua o da ordem (`paid`) |
| ordem paga sem link | `paid`, **sem** chamada de captura |
| ordem não paga | nenhuma chamada de captura |
| captura de outro pagamento | `ignorado`/`capture_not_reconciled` |
| provedor fora do ar | `retry` |
| nada regrediu | suíte **1964 testes / 46.429 asserções** verde, PHPStan sem erro |

## Nota para quem for mudar aqui

O teste que já existia (`testCaptureEventsReconcileThroughTheParentOrder`) passava porque **montava** a ordem com o bloco `payments.captures` embutido. Um dublê que sempre entrega o caso feliz não distingue "o código sabe ler a captura" de "o dublê entregou tudo pronto". Os testes novos entregam a ordem **sem** o bloco — é o formato que o provedor pode devolver e que o código não sabia tratar.
