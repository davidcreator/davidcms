# ADR 0121 — Pagamentos: estorno (reembolso) — escopo do primeiro passo

| | |
| --- | --- |
| **Status** | 🟡 proposta, **parcialmente executada** (KZ-64, 2026-11-20) — o R1 foi implementado na parte que não move dinheiro e que conserta defeito existente; a decisão D01 sobre R2/R3 continua aberta |
| **Data** | 2026-11-20 |
| **Agente proponente** | system |
| **Contexto** | A ADR 0118 deixou reembolso fora do escopo, e a ADR 0119 migrou o fluxo do pedido sem tocar nele. Hoje **nenhum** código do CMS estorna dinheiro — e a estimativa anterior (`DECISAO-REEMBOLSO.md`, fora do repositório) dava o caminho mais barato em ~3 arquivos, apoiada em duas suposições que **não se confirmaram** no código atual: (a) que a reconciliação traria o estorno de Mercado Pago e PayPal; (b) que o Stripe era o único ponto cego. Verificado no código desta data: a reconciliação **nunca revisita tentativa paga**, então ela não traz estorno de provedor nenhum. O custo real do primeiro passo é maior, e a diferença importa porque é dinheiro. |
| **Relacionadas** | ADR 0118 (reconciliação ativa), ADR 0119 (fluxo do pedido), ADR 0120 (destino do dinheiro — decidiu: tudo na plataforma, sem split), `docs/integracoes/JOW-Payments-Reconciliacao-Operacao.md` |

## Fatos, verificados no código de hoje

| # | Fato | Onde | Consequência |
| --- | --- | --- | --- |
| 1 | O ledger **já aceita** estorno: `paid → refunded`/`partially_refunded`, `partially_refunded → refunded`, `disputed ↔` | `GatewayPaymentStatus::canTransition()` | **Nenhuma** mudança na máquina de estados nem no `GatewayEventApplier` |
| 2 | **A reconciliação só olha tentativa não paga** | `DbGatewayLedger::pendingAttempts()` filtra `status IN ('pending','requires_action','authorized')` | **O cron nunca revisita uma tentativa paga.** Estorno feito no painel do MP/PayPal **não** é descoberto pelo cron — só pelo webhook |
| 3 | O Stripe é pior: a reconciliação lê a **sessão** | `StripeCheckoutAdapter::reconcile()` → `getSession()` | Sessão **não muda** quando há estorno. O estorno é invisível mesmo se a tentativa fosse varrida |
| 4 | As âncoras do estorno **já estão guardadas** nos três | `provider_reference` (MP: id da ordem), `provider_payment_id` (Stripe: `pi_…`, PayPal: id da captura) | **Nenhuma migração de dados** para estornar |
| 5 | O painel **grava `refunded` sem estornar nada** | `setChargeStatus()` aceita `refunded` na lista; o `<select>` da tela oferece a opção | O relatório de recebimentos pode **mentir**, e o operador não é avisado |
| 6 | Duas fontes de situação, **sem comparação** | `rms_jow_charge.status` (operador) × `rms_payment_transaction.status` (provedor) | Divergem em silêncio. Nada aponta a contradição |
| 7 | Stripe sem `charge.refunded` assinado = invisível | `verifyRefundEvent()` só roda nesse evento | Depende de assinatura de webhook correta — item de configuração, não de código |

### Endpoints (documentação oficial, conferida antes)

| Provedor | Endpoint | Id necessário | Já temos? |
| --- | --- | --- | --- |
| Mercado Pago (Orders) | `POST /v1/orders/{id}/refund` + `X-Idempotency-Key` | id da **ordem** | `provider_reference` |
| Stripe | `POST /v1/refunds` (`payment_intent=pi_…`) | `payment_intent` | `provider_payment_id` |
| PayPal | `POST /v2/payments/captures/{id}/refund` + `PayPal-Request-Id` | id da **captura** | `provider_payment_id` |

Notas que mudam o desenho: na integração por Orders do Mercado Pago **não** se usa a Payments API; só ordem `processed` aceita estorno; a janela é de **180 dias** (algumas páginas citam 360); corpo vazio = total, `{"transactions":[{"id":"PAY…","amount":"…"}]}` = parcial.

## Opções, com custo corrigido

### R1 — Estorno no painel do provedor; o CMS só registra a verdade

O CMS **não move dinheiro**. O estorno é feito no painel do MP/Stripe/PayPal; o CMS passa a **enxergar** o
estado novo e a **parar de mentir** sobre ele.

| Item | Por que | Arquivos |
| --- | --- | --- |
| Varredura de tentativas **pagas** (janela própria) | hoje o cron nunca as revisita; sem isso, estorno de MP/PayPal só aparece por webhook | 2 (`db_gateway_ledger`, `gateway_reconciliation_service`) |
| Stripe: ler `payment_intent → latest_charge.amount_refunded` | a sessão não muda no estorno | 2 (`stripe_checkout_client`, `stripe_checkout_adapter`) |
| `charge_status`: aviso explícito + log de quem mudou | gravar `refunded` sem estorno é a maior fonte de relatório errado | 2 + i18n (3 idiomas) |
| Conferência entre as duas fontes (avisar divergência) | fato 6 | 1 |

**Total: ~7 arquivos** (a estimativa anterior dizia 3). **Tabela nova:** nenhuma. **Migração:** nenhuma.
**Risco de dinheiro:** zero — o CMS não tem como estornar.
**Resolve:** estorno aparece no CMS; fim do relatório que mente; Stripe deixa de ser cego.
**Não resolve:** estornar sem sair do CMS; estorno parcial pelo CMS.

### R2 — Estorno **total** pela tela do CMS

Botão na cobrança paga → o CMS chama o provedor → o resultado entra como **evento verificado**, no mesmo
caminho do webhook.

- Interface `RefundableGatewayAdapter::refund()`, método de estorno nos três clients, ação `charge_refund`
  (ACL + CSRF + confirmação dupla + motivo obrigatório no log), i18n, testes com transporte falso.
- **Idempotência de graça:** o id do reembolso do provedor vira `provider_event_id` — a dedup existente
  (`UNIQUE(gateway_id, provider_event_id)`) impede estorno dobrado se o operador clicar duas vezes.
- Só tentativa `paid`; nunca `authorized`, `pending` ou `disputed`.
- **Total: ~11 a 12 arquivos** (antes: ~10). Nenhuma tabela. **Risco:** real, mas único e do valor cheio.
- **Não resolve:** parcial — não há onde registrar dois estornos da mesma tentativa.

### R3 — R2 + estorno **parcial**

Exige registrar cada estorno: valor, id no provedor, autor, motivo.

- Tabela nova `payment_refund` → **ADR própria, dicionário de dados, migração aditiva**.
- Soma dos estornos decide `partially_refunded` × `refunded`; UI com valor e limite; conferência contra
  `charge_amount`.
- **Total: ~17 a 18 arquivos** (antes: ~16) + 1 tabela. **Risco:** o maior — é a única opção em que um erro
  de soma estorna acima do cobrado. Exige teste de propriedade (soma ≤ pago).

## Comparativo

| | R1 | R2 | R3 |
| --- | --- | --- | --- |
| Move dinheiro pelo CMS | não | total | total + parcial |
| Tabela nova / migração | 0 | 0 | 1 tabela + migração |
| Arquivos tocados | **~7** | ~11–12 | ~17–18 |
| Testes novos (estimativa) | 8–10 | 22–28 | 38–48 |
| Corrige a cegueira do Stripe | ✅ | ✅ | ✅ |
| Fecha o botão que marca estornado sem estornar | ✅ | ✅ | ✅ |
| Estorna sem sair do CMS | ❌ | ✅ | ✅ |
| Risco de erro de dinheiro | **nulo** | baixo | médio |

## Decisão

**Pendente do dono do produto.**

Recomendação, com a correção de custo: **R1 agora, R2 quando o volume justificar, R3 só se parcial for
necessidade real de negócio.** O motivo de R1 primeiro não é o preço — é que **dois dos três problemas do
R1 são defeitos de hoje, não ausência de recurso**: o painel aceita escrever `refunded` sem estorno
nenhum, e o Stripe é invisível por desenho. R1 conserta o que já está errado; R2 e R3 **acrescentam**
capacidade.

### O que precisa de resposta (D01 é a única que bloqueia)

| # | Decisão | Opções | Bloqueia |
| --- | --- | --- | --- |
| **D01** | Escopo do primeiro passo | `R1` · `R2` · `R3` | **sim** |
| **D07** | Janela da varredura de pagos (novo, R1) | 7 dias · 30 dias · 180 (limite do MP) · sem varredura, só webhook | sim, se R1 |
| D02 | Quem pode estornar (R2/R3) | grupo com `modify` em `payments/jow` (padrão) · ACL própria | não, se R1 |
| D03 | Estorno automático em falha? | **nunca** (recomendado) | não |
| D04 | Janela e limite próprios | sem limite próprio (usa o do provedor) · 90 dias | não |
| D05 | `charge_status` do painel | manter com aviso + log (recomendado) · remover `refunded` da lista · remover a ação | não |
| D06 | Estorno em disputa (`disputed`) | bloqueado (recomendado) · permitido com aprovação dupla | não |

Uma resposta basta para começar: **R1, e qual janela (D07)**.

## Consequências

- ✅ O CMS deixa de poder afirmar "estornado" sem que ninguém tenha estornado.
- ✅ Nenhuma migração em R1 e R2 — as âncoras do estorno já existem nos três provedores.
- ⚠️ Varrer tentativas pagas aumenta a chamada de API por ciclo. É leitura barata e o provedor cobra por
  transação, não por consulta — mas a janela (D07) precisa ser escolhida com isso em vista.
- ⚠️ O Stripe depende de `charge.refunded` **assinado** no painel do Stripe; sem isso, a varredura cobre o
  que a sessão não mostra, mas um estorno pode demorar até o próximo ciclo.
- ⚠️ R2 move dinheiro: merece release própria, roteiro de sandbox estendido e **um estorno de teste
  executado de verdade** — não é item que se aceita por leitura de código.
- ⚠️ Com a ADR 0120 (sem split), um estorno mexe em **uma conta só**: a estimativa de risco caiu, mas a
  de trabalho não.

## O que foi executado em 2026-11-20 (R1, na parte que não move dinheiro)

O R1 tem quatro itens; **dois** deles são defeitos do código de hoje e foram corrigidos. Os outros dois
— varrer tentativas pagas e ler o `payment_intent` no Stripe — **mexem em chamada de API por ciclo** e
ficam para quando houver credencial para observar o comportamento em sandbox.

**Feito:**

1. **A tela deixou de esconder a situação digitada pelo operador.** Para cobrança de gateway a coluna
   mostrava só `gateway_status`; o que o operador escrevia em `jow_charge.status` desaparecia. Agora a
   divergência aparece na própria linha ("Digitado aqui: refunded — difere da situação do provedor").
   A comparação vive em `ChargeStatusConcordance` (classe pura, 16 testes), com lista **fechada** de
   concordância: vocabulário novo do provedor nunca passa como "concorda".
2. **O `select` avisa que não estorna dinheiro.** O formulário da situação ganhou `data-rms-confirm`
   com o texto "alterá-la NÃO estorna dinheiro no provedor" — nos três idiomas. O projeto recusa
   `onclick` (política de CSP), então o aviso usa o mecanismo que o admin já tem.

**Não feito, de propósito:** a varredura de tentativas pagas e a leitura de estorno no Stripe. Fazer
isso agora seria escrever chamada de provedor que ninguém pode observar — e a janela (D07) muda o custo.
Quando houver credencial, é a próxima peça.

**O que continua sendo decisão sua:** `D01` (R1 completo, R2 ou R3) e `D07` (janela da varredura).

## Nada de estorno foi implementado

Nenhuma chamada de estorno em provedor, nenhuma tabela, nenhuma migração. O que foi escrito **conserta o
que já estava errado** e **avisa antes de gravar** — não move dinheiro.

Nenhuma linha de código de estorno, nenhuma tabela, nenhuma migração. Esta proposta existe para a decisão
ser registrada antes do código — e para corrigir, com número, uma estimativa que estava otimista.
