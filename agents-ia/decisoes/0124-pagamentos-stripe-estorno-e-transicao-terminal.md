# ADR 0124 — Pagamentos: o Stripe deixa de ser cego para estorno, e o estorno total que chega antes do `paid`

| | |
| --- | --- |
| **Status** | ✅ executada (KZ-64, 2026-11-20) |
| **Data** | 2026-11-20 |
| **Agente proponente** | system |
| **Contexto** | A ADR 0123 fez a reconciliação varrer tentativas **já pagas** e deixou escrito, com todas as letras, o buraco que sobrava: `StripeCheckoutAdapter::reconcile()` lê a **sessão** de Checkout, e a sessão **não muda quando há estorno** — `payment_status` continua `paid` para sempre. Varrer a tentativa paga do Stripe seria perguntar, a cada ciclo, a um objeto que responde "pago". O estorno só chegaria pelo aviso assinado (`charge.refunded`), que é exatamente o que se perde quando o webhook falha. Esta ADR fecha esse caminho e resolve, de passagem, um segundo caso que só aparece quando se varre tentativa paga: o **estorno total que chega antes de o CMS registrar o pagamento**. |
| **Relacionadas** | ADR 0123 (R1 — varredura de pagas, que deixou este buraco dito), ADR 0121 (escopo do estorno), ADR 0119 (fluxo do pedido no ledger novo), ADR 0118 (reconciliação ativa) |

## Fato 1 — o Stripe já tinha a âncora da cobrança, só não a usava na varredura

O `payment_intent` sempre esteve guardado: `createPayment()` o devolve em `GatewayPaymentResult`, o ledger o grava em `provider_payment_id`, e a sessão consultada na API o informa de novo a cada leitura. E o adaptador **já sabia** ler uma cobrança — `GET /v1/charges/{id}`, com `amount` e `amount_refunded` — porque é assim que o aviso `charge.refunded` é verificado desde antes desta ADR.

O que faltava era juntar as duas coisas na varredura: sessão paga → `payment_intent` → cobrança → `amount_refunded`.

## Decisão

**1. `reconcile()` do Stripe passa a olhar a cobrança quando a sessão diz "pago".**

Ordem, e o motivo de cada passo:

| Passo | O que acontece | Por quê |
| --- | --- | --- |
| sessão | `getSession()` | é a fonte do "pago"; a cobrança não sabe dizer se o pagamento aconteceu |
| se não está paga | devolve o veredicto da sessão | sessão aberta/expirada não gasta chamada de cobrança |
| `payment_intent` | tem de casar `^[A-Za-z0-9_-]{1,128}$` | sem id consultável, não se inventa id: vale o estado da sessão |
| cobrança | `getCharge(payment_intent)` | é o único objeto que sabe do estorno |
| veredicto | mesmo `reconcileCharge()` do aviso assinado | **um só caminho de estorno** para webhook e varredura |

**2. Sessão paga + cobrança sem estorno continua dando `paid`.**

Se a cobrança fosse consultada e devolvesse "nada estornado", o veredicto **não** é `ignorado` — é o da sessão. Sem isso, toda tentativa paga do Stripe apareceria como `ignorado` a cada ciclo e o relatório do cron viraria ruído. A cobrança aqui é usada para descobrir **o que a sessão não sabe**, não para reescrever o que ela já disse.

**3. Um segundo caminho de transição, e só um: `→ refunded`.**

O caso real que aparece ao varrer pagas: o dinheiro entrou e voltou dentro da janela (estorno logo depois do pagamento — comum em teste de cartão e em cobrança duplicada cancelada no mesmo dia). A tentativa está em `requires_action` no ledger, porque o aviso de `paid` nunca chegou, e o provedor afirma `refunded`.

`GatewayPaymentStatus::canTransition('requires_action', 'refunded')` é `false`. Recusar por `unsafe_status_transition` deixaria a tentativa nesse estado **para sempre**: a fila aberta a consultaria a cada ciclo, o provedor responderia `refunded`, e nada seria gravado. O dinheiro já voltou; faltava o registro.

A classe nova `GatewayTerminalStatus` abre **só**:

```
pending | requires_action | authorized  →  refunded
```

e nada mais. Consequências deliberadas:

- **`partially_refunded` continua exigindo `paid`.** Parcial de um pagamento que o CMS nunca viu pago não tem contra o que ser conciliado: falta o valor registrado. Recusar é o certo, e continua recusado.
- **`refunded` continua terminal.** Nada entra em `refunded` vindo de `refunded`, e nada sai dele.
- A transição nova vale **nos dois lados** — `GatewayEventApplier` (decisão) e `DbGatewayLedger::applyVerifiedEvent()` (escrita). Se as duas checagens divergissem, o applier aprovaria e a gravação estouraria: erro em produção, não em teste.
- **Nada aqui move dinheiro.** É a mesma transição que o painel do provedor já executou; o CMS está registrando um fato consumado.

## O que muda em cada arquivo

| Arquivo | Mudança |
| --- | --- |
| `system/library/payment/stripe_checkout_adapter.php` | `reconcile()` consulta a cobrança quando a sessão está paga; `reconcileCharge()` extraído e usado **também** pelo aviso `charge.refunded` |
| `system/library/payment/gateway_terminal_status.php` | **novo** — a transição `→ refunded` a partir de estado não pago |
| `system/library/payment/gateway_event_applier.php` | usa `GatewayTerminalStatus` na checagem de transição |
| `system/library/payment/db_gateway_ledger.php` | idem, na escrita |

Nenhuma migração. Nenhuma coluna. Nenhuma tabela. O `payment_intent` já estava gravado.

## O que esta ADR **não** faz

| Fora | Por quê |
| --- | --- |
| **Não** estorna nada | R2/R3 (D01) continuam abertos. Aqui só se **lê**. |
| **Não** usa `metadata[jow_reference]` na varredura | o aviso assinado confere a referência contra a metadata porque é o que ele tem; a varredura já partiu do registro do CMS e confere a **identidade da cobrança** (`id` pedido = `id` devolvido). Duas checagens diferentes para duas origens diferentes. |
| **Não** captura nada de `payment_intent` | `GET /v1/charges/{pi_…}` resolve o *intent* para a cobrança; não há captura, confirmação nem criação. |
| **Não** mexe em Mercado Pago nem PayPal | os dois já respondem estorno na varredura desde a ADR 0123; o problema era só o Stripe. |
| **Não** decide D01 | estornar pela tela é outra release, com estorno de teste executado de verdade. |

## Riscos residuais

| Risco | Situação |
| --- | --- |
| Custo: uma requisição a mais por tentativa paga na janela | só para sessão **paga**, e a janela é curta por padrão (30 dias). Sessão aberta não paga nada. |
| `payment_intent` ausente em tentativas antigas | cai para o estado da sessão, sem inventar id. É o mesmo comportamento de antes desta ADR para esse caso. |
| Stripe fora do ar na leitura da cobrança | `retry('provider_unavailable')`, como as outras leituras; nada é gravado. |
| A transição nova mascara um erro de fiação? | não: ela só aceita `→ refunded`, e o `paid` perdido continua sendo procurado pela varredura aberta (7 dias). Se a tentativa sair de `requires_action` direto para `refunded`, o `paid` nunca existiu no CMS — que é exatamente o caso que se está registrando. |
| Sandbox | provado com dublês e leitura de contrato, **não** contra o Stripe de verdade. Depende das credenciais (P4). |

## Prova

| O que | Como |
| --- | --- |
| estorno no Stripe descoberto pela varredura | sessão paga + cobrança com `amount_refunded` → `VERIFIED`/`partially_refunded`, e a cobrança lida **com o id que a sessão informou** |
| estorno total | `amount == amount_refunded` → `refunded` |
| sem estorno | cobrança consultada, veredicto continua o da sessão (`paid`) |
| sessão aberta | nenhuma chamada de cobrança |
| cobrança de outro pagamento | `ignorado`/`charge_not_reconciled` |
| provedor fora do ar | `retry`/`provider_unavailable` |
| sessão paga sem `payment_intent` | vale o estado da sessão, sem chamada de cobrança |
| estorno total antes do `paid` | applier **e** ledger aceitam e gravam `refunded`; parcial no mesmo estado continua recusado; `refunded` continua terminal |
| ponta a ponta | varredura → Stripe → `GatewayEventApplier` → `status_before=paid` / `status_after=partially_refunded` |
| nada regrediu | suíte **1957 testes / 46.415 asserções** verde, PHPStan sem erro, `verify-checksums` 3 PASS / 0 FAIL |
