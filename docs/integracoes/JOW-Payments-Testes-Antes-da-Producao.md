# JOW Payments — o que precisa ser testado antes de ir para produção

**Estado:** engenharia encerrada até onde é possível sem credenciais. O que falta é **validação
operacional**, e ela depende de contas de teste nos provedores.

**Regra de ouro:** credencial de teste. Conta de gateway em `sandbox`, `REAMUR_PAYMENTS_ALLOW_LIVE`
**não** definida, e nenhum pedido real em nenhum passo.

---

## 1. Antes de qualquer teste: quatro verificações de dez segundos

| # | Verificação | Como | Esperado |
| --- | --- | --- | --- |
| **1.0** | **Pré-voo completo em um comando** | `php tools/payment-piloto.php --config=catalog/config.php --url=https://SEU-SITE/catalog/index.php` | `resumo: N pronto(s), N atenção, 0 bloqueado(s)`. Ele confere schema, coluna de âncora, contas e **quais campos de credencial faltam em cada uma** (nunca o valor), token do cron, URL pública (recusa host local e falta de HTTPS), resíduos de teste no ledger e links legados para o teste K |
| 1.1 | Migração do núcleo novo aplicada | `php tools/payment-schema-drift.php --config=catalog/config.php` | `ok` (código 0), três tabelas do núcleo **e** a tabela de pedidos |
| 1.2 | Corpo do site em ordem | `bash tools/verify-checksums.sh` | `3 PASS / 0 FAIL` |
| 1.3 | Nenhum manifesto de pacote na raiz | o mesmo comando acima | nenhum `AVISO CHECKSUMS-PACOTE.txt` |
| 1.4 | Conta do provedor habilitada | tela **JOW Payments** → *Contas próprias do JOW* | badge verde e credenciais salvas |

Se 1.1 disser `config_mismatch`, **pare**: o `config.php` aponta para outro banco e qualquer teste
dali em diante mede a instalação errada.

---

## 2. Os sete testes de gateway (roteiro completo em `KZ-64-Validacao-Sandbox.md`)

A coluna **estado** foi revisada em 2026-11-20, contra o código de hoje (ADRs 0123, 0124 e 0125
mudaram o que o cron faz). `código` = provado por teste automatizado; `banco` = provado pelo
`tools/payment-piloto.php`; `sandbox` = **só** credencial prova.

| Teste | O que prova | Estado hoje | Credencial |
| --- | --- | --- | --- |
| **A** | a tentativa nasce no provedor (`provider_reference`, endereço de pagamento) | sandbox | conta sandbox |
| **B** | webhook assinado aplica `paid` no ledger **e** sincroniza a cobrança do JOW (P0-a) | código: cadeia assinatura → leitura → `GatewayEventApplier` → `paid` (`WebhookChainToPaidTest`), incluindo assinatura adulterada e valor divergente. **sandbox:** que o provedor assina do jeito que o código espera e que a URL publicada recebe | conta sandbox + webhook configurado |
| **C** | reenvio do mesmo aviso não duplica evento nem regride estado | código (id do evento derivado do estado; `recordWebhookOnce` com `UNIQUE`) · sandbox: o reenvio real | idem |
| **D** | recusa vira `failed`/`canceled` e não volta atrás | código (transição terminal) · sandbox: o cartão de recusa | cartão de recusa |
| **E** | **pagamento com webhook perdido é recuperado pelo cron** (P0-b) | código · banco: token (`payment-piloto`) · sandbox: o cron rodando de verdade | idem + `REAMUR_RECONCILE_TOKEN` |
| **F** | guardas da rota `cron/reconcile`: 403 / 400 / 200 / 503 | código (`ReconcileCronControllerTest`) · sandbox: os quatro códigos no servidor | token |
| **G** | Stripe/PayPal sem a coluna de âncora → `missing_provider_anchor`; PayPal fica `authorized` | código · banco: a coluna existe ou não (`payment-piloto`) | contas Stripe/PayPal sandbox |

### 2.1 O que mudou desde que este roteiro foi escrito (e o teste que não existia)

Três entregas mudaram o que o cron faz. Sem revisar a bateria, dois testes passariam a medir a coisa
errada:

| Entrega | O que mudou | Efeito na bateria |
| --- | --- | --- |
| **ADR 0123** — varredura de pagas | o cron faz **duas** consultas por conta: a aberta (7 dias) **e** a de tentativas já pagas (30 dias, teto 180, `settled_lookback`) | o relatório de **E** e **F** agora traz os dois números. Tentativa paga de teste antigo entra no `scanned` se estiver na janela |
| **ADR 0124** — Stripe | em tentativa **paga**, a reconciliação lê a **cobrança** (`payment_intent` já gravado) para ver `amount_refunded` | custa **uma requisição a mais** por tentativa paga do Stripe. Sessão aberta não gasta |
| **ADR 0124** — estorno antes do `paid` | `pending│requires_action│authorized → refunded` passou a ser aceito | tentativa de teste **estornada no painel do provedor** sai de `pending` para `refunded` no cron. Não é bug |
| **ADR 0125** — PayPal | ordem paga **sem** bloco de capturas → segue a link `up` e lê a captura | custa **uma requisição a mais** só nesse caso. Ordem com captura embutida não gasta |

**O teste que faltava: N — a varredura de pagas encontra um estorno feito no painel do provedor.**
Era o buraco da ADR 0121, fechado nas ADRs 0123/0124/0125 e até agora **sem roteiro de sandbox**. Está
na §3.1.

## 3. Os testes do fluxo de pedido (ADR 0119)

Roteiro em `MIGRACAO-ORDER.md` e no pacote `roteiro-teste-pedido.md`.

| Teste | O que prova | Observação |
| --- | --- | --- |
| **H** | link de pedido criado no ledger novo (`source_type = 'order'`) | não muda a situação do pedido na criação |
| **I** | clicar duas vezes reaproveita o link (`Link::REUSED`) | nenhuma cobrança nova |
| **J** | pagamento confirmado move o pedido e a tentativa para `paid` | caminho de dinheiro completo |
| **K** | **link antigo do legado continua abrindo (302)** | **o mais crítico dos doze**: quebra cliente em produção. `payment-piloto` diz se existe algum link antigo para testar |
| **L** | pedido já pago não ganha segundo link | `Link::PAID` |
| **M** | provedor sem conta habilitada → bloqueado, nada criado | fail closed |

### 3.1 Teste N — estorno feito no painel do provedor é enxergado (ADR 0123/0124/0125)

O único teste da bateria que cobre estorno, e ele é **leitura**: nada é estornado por código do CMS.
É também o teste que fecha a distância entre "o código sabe ler" e "o provedor conta do jeito que o
código espera".

1. Crie e **pague** uma cobrança de sandbox (Mercado Pago `APRO` é o caminho mais curto).
2. **Estorne pelo painel do provedor** — não pelo CMS, que não estorna nada hoje (D01 aberta).
3. Rode o cron com a janela de estorno encurtada, para não varrer o que não interessa:

```bash
curl -f -sS -H "Authorization: Bearer $REAMUR_RECONCILE_TOKEN" \
  "https://SEU-SITE/catalog/index.php?route=cron/reconcile&format=json&min_age=1&settled_lookback=1"
```

**Esperado:** a tentativa sai de `paid` para `refunded` (ou `partially_refunded`, se o estorno foi
parcial) **sem** nenhum webhook ter chegado — confira `payment_gateway_event`: pode estar vazia, e isso
é o ponto. No detalhe do relatório, `status_before: "paid"` e `status_after: "refunded"`.

4. Rode **de novo**: `applied = 0` e a tentativa aparece como `unchanged`.

| Provedor | O que o cron lê | Observação |
| --- | --- | --- |
| Mercado Pago | a **ordem** | o estado de pagamento vem na própria ordem |
| Stripe | a **sessão**, e depois a **cobrança** | a sessão não muda com estorno; a cobrança é que sabe |
| PayPal | a **ordem**, e depois a **captura** pela link `up` | só quando a ordem não traz as capturas embutidas |

**Caso de borda que vale testar uma vez:** estorne **antes** de o CMS processar o `paid` (pague e estorne
em menos de um minuto, com o webhook desligado). O esperado é a tentativa ir de `pending`/`requires_action`
**direto** para `refunded` — é a transição da ADR 0124, e ela existe justamente porque o dinheiro já
voltou. Se o cron recusar com `unsafe_status_transition`, é defeito: pare e registre.

## 4. Decisões de produto antes de `live` — uma tomada, duas em aberto

### 4.1 Para onde vai o dinheiro do pedido — **decidido** (ADR 0120)

**Decisão: o CMS cobra apenas o que é da plataforma.** A plataforma não fecha com pedidos de vendedores
terceiros — só conteúdo próprio e serviços. Portanto:

| | Legado (checkout Marketplace) | Núcleo novo (ADR 0119) |
| --- | --- | --- |
| Token | vendedor (OAuth) | conta da plataforma |
| Taxa da plataforma | 10% (`mp_marketplace_fee_percent`) | **nenhuma** |
| Quem recebe | vendedor | **plataforma, valor integral** |

O comportamento do núcleo novo é o **correto**, não um atalho: não existe pedido de terceiro a dividir.
Nada a implementar.

- **Consequência para o teste:** nenhum teste de split, de repasse ou de conta de vendedor precisa
  existir. A bateria A–M continua valendo como está.
- **Consequência para o estorno (§4.2):** fica **mais simples** do que o estimado — um estorno mexe em
  uma conta só.
- **Se o modelo mudar:** a ADR 0120 registra o caminho (B1/B2) e o custo, para não começar do zero.

### 4.2 Estorno — **R1 entregue; R2/R3 abertos**

`DECISAO-REEMBOLSO.md` (entregue fora do repositório) compara três escopos — R1 (estorno no painel do
provedor), R2 (estorno total pelo CMS), R3 (com parcial, exige tabela nova). **D01 sem resposta** para
R2/R3.

**O que mudou desde que este documento foi escrito:** o **R1 está completo** — o CMS passou a **enxergar**
o estorno feito no painel do provedor, nos três provedores (ADRs 0123, 0124 e 0125), e a §3.1 é o teste
de sandbox que faltava. Continua verdade que **nenhum código do CMS estorna dinheiro**: R1 é leitura.

**Decisão pendente — não bloqueia o go-live de cobrança, mas deixa o estorno manual (pelo painel do
provedor).** Com o R1 entregue, esse estorno manual ao menos **aparece** no CMS, em vez de ficar
invisível até o webhook ser reenviado.

### 4.3 Webhook perdido é rotina ou exceção?

O cron de reconciliação é rede de segurança, **não** gatilho. Ele exige `REAMUR_RECONCILE_TOKEN`
(32+ caracteres) e um crontab do sistema chamando a rota. Sem isso, pagamento com aviso perdido só
aparece quando o provedor reenviar.

**Decisão operacional pendente — precisa de crontab configurado antes de `live`.** Como ligar, testar e
monitorar está em [`JOW-Payments-Reconciliacao-Operacao.md`](JOW-Payments-Reconciliacao-Operacao.md).

---

## 5. Ordem recomendada quando as credenciais chegarem

0. **`tools/payment-piloto.php`** — antes de tudo. Se ele disser `BLOQUEADO`, nenhum teste abaixo mede o
   que promete.
1. Testes **A, B, C** com Mercado Pago sandbox (vendedor de teste + pagador `@testuser.com`).
2. Testes **H, I, K, L, M** — o fluxo de pedido não move dinheiro real e valida a ADR 0119.
3. Teste **E** com o webhook desligado de propósito (`min_age=1`) — prova o P0-b.
4. Teste **N** (estorno no painel do provedor) — prova a leitura de estorno nos três, e é o único teste
   da bateria que toca o assunto. Faça com o webhook ligado de novo: o objetivo é ver o cron aplicando
   **sem** ajuda do aviso.
5. Testes **D** e **F** (cartão de recusa, guardas da rota).
6. Teste **G** se for usar Stripe/PayPal.
7. Teste **J** por último: é o único que exige o caminho completo de pagamento.
8. Só depois: decidir o **4.2** (R2/R3), configurar o crontab (4.3) e reavaliar `live`. O **4.1** já está
   decidido pela ADR 0120 e não bloqueia mais nada.

## 6. O que já está provado (não precisa repetir)

- **Suíte automatizada:** **1964 testes, 46.429 asserções**, verde; PHPStan sem erros.
- **Guards:** `verify-checksums` 3 PASS, `verify-ci-workflow` 35 PASS, `verify-deps-layout` 18 PASS.
- **Migração:** aplicada e confirmada pelas tabelas do núcleo existentes no banco.
- **Idempotência da chave de pedido:** coberta por teste (`OrderPaymentLinkTest`), incluindo chave
  nova por tentativa e reaproveitamento de link vivo.
- **Escrita em um dono por pagamento:** coberta por teste (`OrderFlowLedgerTest`).
- **Cadeia do teste B sem credencial:** `WebhookChainToPaidTest` (3 testes).
- **Leitura de estorno nos três provedores:** `GatewayReconciliationServiceTest`,
  `StripeCheckoutAdapterTest`, `PaypalOrdersAdapterTest` — incluindo a ponta a ponta
  varredura → provedor → applier → ledger.

O que **nenhum** teste automatizado prova, e por isso está nesta lista: conversa real com o provedor,
assinatura de webhook real e o cron rodando de verdade. É exatamente o que as credenciais destravam.
