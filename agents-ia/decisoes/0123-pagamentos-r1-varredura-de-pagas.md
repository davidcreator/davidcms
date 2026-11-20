# ADR 0123 — Pagamentos: R1 — a reconciliação passa a varrer tentativas já pagas

| | |
| --- | --- |
| **Status** | ✅ executada (KZ-64, 2026-11-20) — o **R1** da ADR 0121, na parte que não move dinheiro |
| **Data** | 2026-11-20 |
| **Agente proponente** | system (proposta) · humano (mandato: executar o R1) |
| **Contexto** | A ADR 0121 mediu o estorno em três degraus e recomendou o **R1** — fazer o CMS **enxergar** o estorno feito no painel do provedor — como o único sem risco de dinheiro. O R1 estava estimado em ~7 arquivos e a decisão D07 (janela da varredura) estava aberta. Esta ADR registra o que foi construído, a janela escolhida e por quê, e o que continua deliberadamente fora. Também corrige uma suposição da 0121 que o código desmentiu: o problema não era só "a consulta filtra estado aberto", era que **a tentativa paga nunca era relida**, em nenhum caminho do cron. |
| **Relacionadas** | ADR 0121 (estorno — escopo do primeiro passo), ADR 0118 (reconciliação ativa), ADR 0119 (fluxo do pedido no ledger novo), ADR 0120 (decidiu: tudo na plataforma, sem split) |

## O defeito, em uma frase

`DbGatewayLedger::pendingAttempts()` filtra `status IN ('pending','requires_action','authorized')`. Uma tentativa que chegou a `paid` sai da fila para sempre. O cron `cron/reconcile` **nunca mais olha para ela** — então um estorno feito no painel do Mercado Pago ou do PayPal só chega ao CMS se o provedor reenviar o webhook. Se o webhook for perdido (e o próprio reconciledor de avisos perdidos não olha para pagas), o CMS continua mostrando "pago" para um dinheiro que já voltou.

Isso é o R1: **leitura**, não captura. Nenhuma linha desta ADR chama estorno no provedor.

## Decisão

**1. Uma varredura nova, separada da aberta — não um alargamento do filtro.**

O filtro de `pendingAttempts()` **não** foi alterado. A varredura de pagas é um método novo, `settledAttempts()`, com consulta própria:

| | `pendingAttempts()` | `settledAttempts()` (novo) |
| --- | --- | --- |
| Estados | `pending`, `requires_action`, `authorized` | `paid`, `partially_refunded` |
| O que procura | aviso perdido, pagamento que não entrou | **estorno, disputa — o mundo mudou depois de pago** |
| Janela padrão | 7 dias | **30 dias** |
| Teto da janela | 90 dias | **180 dias** |

O motivo de separar em vez de alargar: são perguntas diferentes com custos diferentes. Alargar o filtro aberto faria o ciclo de 5 minutos reconsultar trinta dias de histórico no provedor; uma varredura separada só custa o que a janela dela vale, e o operador pode desligá-la sem perder a proteção contra aviso perdido.

**2. `partially_refunded` entra na varredura.**

Um segundo estorno parcial leva a tentativa de `partially_refunded` para `refunded`. Varrer só `paid` deixaria o caso "dois estornos parciais" invisível a partir do primeiro.

**3. A janela é própria e maior — 30 dias por padrão, teto 180.** (decisão D07)

- **30 dias** cobre o caso real: estorno semanas depois do pagamento. Varrer o histórico inteiro a cada ciclo não paga o que custa.
- **180 dias** é o teto porque é a janela de estorno do Mercado Pago: pedir mais que isso é consultar o provedor sobre um período em que ele não aceita mais estorno, e só gera ruído e requisição. PayPal aceita mais tempo (180 dias a partir da captura é o prazo dele também), e o Stripe não tem prazo — mas o Stripe **não é alcançado por esta varredura**, ver limitação abaixo.
- A janela é configurável no cron por `settled_lookback` (1..180, padrão 30), validada com 400 antes de qualquer chamada ao provedor. O serviço valida de novo, porque o cron não é o único chamador.

**4. Mesmo caminho de aplicação, nenhum caminho novo.**

As duas varreduras passam pelo **mesmo** `reconcileRecord()`: mesma consulta ao provedor, mesma verificação de referência, mesmo `GatewayEventApplier`, mesma máquina de estados. Não existe regra nova de transição e não existe escrita nova no ledger. A única diferença entre as duas varreduras é **qual lista de tentativas** é oferecida ao ciclo.

Consequência direta: o ledger já aceitava `paid → refunded`, `partially_refunded → refunded` e `disputed ↔` desde antes desta ADR. Esta ADR não mudou a máquina de estados — ela só passou a **alimentar** a máquina com o que o provedor já sabia.

## O que muda em cada arquivo

| Arquivo | Mudança |
| --- | --- |
| `system/library/payment/gateway_reconcilable_ledger.php` | método `settledAttempts()` na interface, com o contrato documentado (UTC, mais antiga primeiro, `$limit`) |
| `system/library/payment/db_gateway_ledger.php` | implementação espelhando `pendingAttempts()`: mesma validação de `$limit`, mesmo `assertUtc`, mesma ordem, linha malformada **não** é pulada |
| `system/library/payment/gateway_reconciliation_service.php` | parâmetro `$settledLookbackDays`, consts `DEFAULT_SETTLED_LOOKBACK_DAYS = 30` / `MAX_SETTLED_LOOKBACK_DAYS = 180`, segunda varredura depois da aberta em `reconcileAccount()` e repasse em `reconcileAccountList()` |
| `catalog/controller/cron/reconcile.php` | `settled_lookback` (1..180, padrão 30), 400 fora da faixa, documentado no bloco de uso |

Quatro arquivos de produção. A estimativa da 0121 era ~7; a diferença é que a 0121 contava também o lado do Stripe e a tela do painel, que **não** entraram (abaixo).

## O que esta ADR deliberadamente **não** faz

| Fora | Por quê |
| --- | --- |
| **Não** chama estorno no provedor | R2/R3 continuam abertos na D01. Esta ADR é leitura. |
| **Não** resolve o Stripe | `StripeCheckoutAdapter::reconcile()` lê a **sessão** de checkout, e a sessão **não muda** quando há estorno. Varrer a tentativa paga do Stripe consultaria um objeto que responde "pago" para sempre. O Stripe precisa de leitura por *payment intent* / *charge* — é outro trabalho, não é janela. |
| **Não** compara `rms_jow_charge.status` com `rms_payment_transaction.status` | Fato 6 da 0121. O painel ainda grava `refunded` sem estornar nada, e as duas fontes ainda divergem em silêncio. É o próximo candidato natural, e é mais barato que o Stripe. |
| **Não** mexe no caminho legado | O webhook legado já é encaminhado para o serviço novo (ADR 0122/F2). Nada novo aqui. |
| **Não** cria tabela, coluna ou migração | As âncoras do estorno já estavam guardadas (fato 4 da 0121). |

## Custo operacional

O ciclo passa a fazer **duas** consultas ao banco por conta habilitada, não uma. Com a janela padrão de 30 dias e `limit` 25, a segunda lista é curta na operação normal de um CMS de conteúdo — e o custo é pago em requisição ao provedor, não em escrita. Quem quiser desligar a varredura sem perder a proteção de aviso perdido usa `settled_lookback=1`.

**Atenção:** `limit` é compartilhado pelas duas varreduras. Um `limit` pequeno com muitas tentativas abertas pode consumir o orçamento antes de a varredura de pagas rodar. Isso é aceitável — a fila aberta é a que perde dinheiro mais rápido — mas é o primeiro lugar a olhar se alguém relatar estorno não detectado.

## Prova

| O que | Como |
| --- | --- |
| Estorno no painel é descoberto | tentativa só na lista de pagas + provedor respondendo `refunded` → `scanned=1`, `applied=1`, `status_after=refunded`, sem depender de webhook |
| Segundo estorno parcial continua visível | tentativa em `partially_refunded` na lista de pagas + provedor `refunded` → aplica |
| Janela própria | com NOW `2026-11-20 12:00:00` e padrão 30, o corte enviado ao banco é `2026-10-21 12:00:00` |
| Teto respeitado | `settled_lookback=181` → `InvalidArgumentException` (e 400 no cron) |
| Nada regrediu | suíte global **1944 testes / 46.383 asserções** verde, PHPStan sem erro, dublês dos três testes atualizados |

## Riscos residuais

| Risco | Situação |
| --- | --- |
| `limit` compartilhado pode esfomear a varredura de pagas | conhecido, documentado acima; resolver quando aparecer no volume real |
| Stripe continua cego para estorno | **não** é regressão desta ADR — já era assim; precisa de leitura por charge |
| Estorno mais antigo que a janela | invisível até alguém rodar `settled_lookback` maior à mão; o teto é a janela do provedor |
| Painel grava `refunded` sem estornar | **continua**; é o próximo item depois do Stripe/divergência |
| Sandbox | a varredura foi provada com dublês e leitura real de contrato, **não** contra o Mercado Pago/PayPal de verdade — depende das credenciais (P4) |
