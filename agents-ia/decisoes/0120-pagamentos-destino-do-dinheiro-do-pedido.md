# ADR 0120 — Pagamentos: para onde vai o dinheiro de um pedido de vendedor

| | |
| --- | --- |
| **Status** | 📝 proposta (KZ-64, 2026-11-20) — aguarda decisão do dono do produto |
| **Data** | 2026-11-20 |
| **Agente proponente** | system |
| **Contexto** | A ADR 0119 migrou o fluxo do pedido para o ledger novo e, ao fazê-lo, deixou o dinheiro cair **inteiro na conta da plataforma**: o `MercadoPagoGatewayAdapter` cria a ordem com o token de `jow_gateway` e não envia `marketplace_fee`. Isso é **correto** para pedido da plataforma e **errado** para pedido de vendedor — e a ADR 0119 não decidiu qual dos dois o CMS atende. O fluxo legado decidia: `rms_mp_payment.mp_seller_id` é `NOT NULL`, `Mercadopago::charge()` usa o token OAuth do vendedor e aplica `marketplace_fee` = `mp_marketplace_fee_percent` (padrão 10%). O núcleo novo não tem onde guardar vendedor: `rms_order` e `rms_order_product` não têm coluna de vendedor, e `GatewayPaymentRequest`, `GatewayAccount` e `GatewayCredentials` não carregam conta de recebedor — a conta é escolhida por `gateway_id` (a primeira habilitada). Sem esta ADR não há como ir a `live` com pedido de terceiro sem risco de pagar a pessoa errada. |
| **Relacionadas** | ADR 0119 (fluxo do pedido no ledger novo), ADR 0118 (núcleo multi-gateway), ADR 0034/0035 (Mercado Pago marketplace), ADR 0094 (segredos cifrados), `docs/integracoes/MercadoPago-Marketplace.md`, `docs/integracoes/JOW-Payments-Testes-Antes-da-Producao.md` §4.1 |

## O que é fato, verificado no código e na API

**1. O que o núcleo novo faz hoje.** `MercadoPagoGatewayAdapter::createPayment()` lê `access_token` da conta de `jow_gateway` (conta da plataforma) e monta a ordem **sem** `marketplace_fee`. Teste próprio fixa isso: `MercadoPagoGatewayAdapterTest` linha 148 — *"JOW charges use the Reamur platform account, not a seller split"*.

**2. O que o fluxo legado fazia.** `Mercadopago::charge()` recebe o vendedor, usa o `access_token` dele (`rms_mp_seller`, cifrado por `SecretBox::CTX_MP_ACCESS`) e envia `marketplace_fee` calculado de `mp_marketplace_fee_percent`, com trava de 0 a 100 (padrão **10**). O valor ia para `rms_mp_payment.marketplace_fee` e o vendedor ia para `mp_seller_id`.

**3. Quem é o vendedor de um pedido.** Hoje, ninguém: `rms_order` guarda cliente, total e situação. O vendedor vinha do contexto do link (`mp_seller_id` da tentativa), não do pedido.

**4. Como o provedor faz split** (documentação oficial do Mercado Pago, conferida em 2026-11-20):

- o split **exige o token do vendedor** (OAuth por vendedor); criar a cobrança com o token da plataforma **não** divide nada;
- na Orders API / Checkout Pro o campo é `marketplace_fee` — **o mesmo nome que o legado já usa**, em valor absoluto na moeda local, não em percentual;
- a ordem de desconto é: **comissão do Mercado Pago primeiro**, depois `marketplace_fee`; o vendedor recebe `total − taxa MP − marketplace_fee`;
- o modelo 1:1 exige **conta de vendedor com KYC** e um **aplicativo de marketplace**; elegibilidade é do provedor, não se assume.

**5. O que isso custa no estorno.** Com split, um estorno mexe em **duas** contas. As opções R1/R2/R3 da decisão de reembolso (`DECISAO-REEMBOLSO.md`) foram estimadas **sem** split; com split, todas crescem.

## Opções

### A. Só pedido da plataforma (não aceitar vendedor)

O CMS vende o que é dele. Nenhum pedido de terceiro é cobrado pelo núcleo novo.

- **Custo:** zero código. Fecha P1 com uma linha de documentação.
- **Efeito:** o comportamento de hoje (dinheiro integral na plataforma) está **certo por definição**.
- **Contra:** se um dia vender em nome de terceiro, o caminho é criar do zero — e o risco de alguém criar o link por engano existe enquanto o CMS aceitar pedido de terceiro sem vendedor.
- Se esta for a escolha, a decisão técnica é apenas **tornar visível**: a tela de configuração de gateway diz que a conta é a recebedora, e a documentação diz que pedido de terceiro **não** é atendido.

### B. Conta de vendedor por pedido (recomendada, em duas fases)

**B1 — identidade do recebedor (aditiva, não move dinheiro).**
`rms_payment_transaction` ganha `seller_ref VARCHAR(64) NULL` (o `mp_user_id` do vendedor, não o `mp_seller_id`: o vendedor sobrevive à reconexão OAuth). `GatewayPaymentRequest` ganha `sellerRef` opcional, com validação de formato e default vazio. Pedido sem `sellerRef` → conta da plataforma (comportamento atual). Pedido com `sellerRef` e **sem** conta de vendedor habilitada → **fail closed** (`no_seller_account`), nunca cai silenciosamente para a conta da plataforma — cair é o pior erro possível: o dinheiro entra e depois não sai.

**B2 — o dinheiro (só depois de elegibilidade confirmada).**
O adapter `mp` passa a receber credencial do vendedor e a enviar `marketplace_fee` **calculado no servidor**; o ledger grava **taxa** e **valor líquido** do vendedor (nunca recalculados no cliente) e a reconciliação/estorno passam a conhecer os dois lados.

- **Custo:** B1 ≈ 6 arquivos (migração aditiva, request, ledger, serviço, `OrderPaymentLink`, testes). B2 ≈ 8 a 10 arquivos, mais o que o estorno exigir.
- **A favor:** é o único caminho que atende vendedor **sem** a plataforma virar dona do dinheiro de terceiro; reusa `rms_mp_seller` como origem de credencial (já cifrado, já com refresh), em vez de criar um terceiro vocabulário.
- **Contra:** B1 sozinha **não** cobra de ninguém de forma diferente — ela só prepara o terreno. O valor de B1 sem B2 é: o pedido já sabe de quem é o dinheiro, e o erro fica visível em vez de silencioso.
- **Aberto:** a taxa é **por pedido** ou **global** (`mp_marketplace_fee_percent`)? Por pedido é mais correto e mais caro (coluna + tela). Global reusa a chave atual.

### C. Receber na plataforma e repassar por fora

Cobrar tudo na conta da plataforma e pagar o vendedor por transferência, manual ou agendada.

- **Custo:** baixo em código, **alto** em operação e risco.
- **Contra:** a plataforma passa a ser **merchant of record** do dinheiro de terceiro — com implicação fiscal e contábil, conciliação manual de cada repasse, e passivo de estorno/chargeback caindo inteiro nela. O próprio Mercado Pago desaconselha esse modelo quando existe split.
- **Não recomendo** sem parecer contábil.

## Decisão

**Pendente do dono do produto.** A recomendação técnica é **B**, com B2 condicionada à elegibilidade de split confirmada pelo Mercado Pago (conta com KYC + aplicativo de marketplace). Se a resposta à pergunta 1 for "não vendo em nome de terceiro", a escolha passa a ser **A**, e P1 fecha sem código.

### Três perguntas que destravam (e só o dono responde)

1. **O CMS vai cobrar em nome de terceiro?** Não → **A**, P1 encerra. Sim → **B**.
2. Se sim: **taxa por pedido** ou **percentual global** (hoje 10%)?
3. Se sim: no **estorno** com split, quem absorve a taxa da plataforma — ela ou o vendedor?

## Consequências

- ✅ A pergunta "para onde vai o dinheiro" deixa de estar implícita no código e passa a estar escrita.
- ✅ B1 é aditiva, sem migração de dados e sem tocar `rms_mp_payment`.
- ⚠️ Enquanto a decisão não for tomada, **nenhum pedido de terceiro deve ser cobrado** — `live` com vendedor continua bloqueado. Pedido da plataforma não é afetado.
- ⚠️ B2 muda assinatura pública do núcleo (`GatewayPaymentRequest`, `GatewayAccount`/`GatewayCredentials`), o que exige revisar os três adapters, não só o `mp`.
- ⚠️ Estorno (D01) precisa ser decidido **depois** de 1 e 2: com split, o escopo R2/R3 cresce.
- ⚠️ `mp_marketplace_fee_percent` continua sendo a chave do **fluxo legado**; se B2 for aprovada, a mesma chave passa a valer para os dois caminhos e isso tem de ser dito explicitamente para ninguém configurar uma e mexer na outra por engano.

## Nada disso foi implementado

Esta ADR é **proposta**: nenhuma linha de código, nenhuma migração, nenhuma coluna. Ela existe para a decisão ser registrada antes do código, não depois.
