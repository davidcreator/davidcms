# ADR 0119 — Pagamentos: o fluxo do pedido passa a nascer no ledger novo

| | |
| --- | --- |
| **Status** | ✅ aceita (KZ-64, 2026-11-20) |
| **Data** | 2026-11-20 |
| **Contexto** | A ADR 0118 declarou a convivência de dois núcleos de pagamento e registrou a migração do fluxo `order` como dívida própria: o pedido público nasce em `Method` → `Checkout` → `DbLedger` sobre `rms_mp_payment`, enquanto as tentativas por provedor vivem em `GatewayAdapter` → `GatewayPaymentService` → `GatewayLedger` sobre `rms_payment_transaction`. Na prática, criar o link de pagamento de um pedido **só era possível pelo Mercado Pago**: o caminho novo existia, era testado, sabia Stripe e PayPal — e nenhum pedido público o alcançava. As consequências de não migrar: (a) dois vocabulários de situação para o mesmo dinheiro; (b) o painel do JOW e o pedido público sem ponto comum de conferência; (c) `rms_mp_payment` preso a nomes de um provedor (`preference_id`, `init_point`) para guardar dado genérico; (d) qualquer provedor novo exigia reimplementar a criação de link de pedido no contrato legado. |
| **Relacionadas** | ADR 0118 (núcleo multi-gateway e reconciliação), ADR 0097 (contrato de meio de pagamento — aposentado para `order` por esta ADR), ADR 0034/0035 (Mercado Pago marketplace), ADR 0094 (segredos cifrados) |

## Decisão

**1. O link de pagamento de um pedido nasce no ledger novo.**
`OrderPaymentLink` (novo, `dev/system/library/payment/order_payment_link.php`) é o ponto único: resolve a conta de gateway **habilitada** do provedor escolhido, monta a `GatewayPaymentRequest` com fonte `order`, chama `GatewayPaymentService::createPayment()` e devolve a `Link` da ADR 0097 (`created`/`reused`/`paid`/`blocked`/`no_total`/`failed`), para que a tela e as mensagens existentes continuem valendo sem tradução nova.

**2. `OrderFlowLedger` é o adaptador entre os dois vocabulários — e nada além disso.**
O que o `Checkout` da ADR 0097 precisa saber é: o link mais recente do pedido, o link pela chave pública, e o registro de um pagamento confirmado. `OrderFlowLedger` (`implode`) responde a primeira e a segunda **lendo `rms_payment_transaction`**, e a terceira **parando de escrever `rms_mp_payment`**: quando o pagamento é de gateway (`provider_payment_id` presente), grava no ledger novo; quando é pagamento **manual** (`pix`/`card`/`boleto`, sem provedor), delega ao `DbLedger` legado, porque `rms_mp_payment` continua sendo a tabela desses registros até a limpeza final. A migration de/para os dois vocabulários fica **em uma classe pequena e testável**, não espalhada pelo controller.

**3. A tela continua igual; a decisão de qual conta usar é explícita.**
`pay()`/`link()` do checkout público e a ação `charge_gateway_link` do painel passam a chamar `OrderPaymentLink::link()`. A conta usada é a **primeira habilitada por ordem de `gateway_id`**, o que para o Mercado Pago é exatamente o comportamento atual (uma conta por provedor em produção). Escolha de conta por site/pedido **não** entra aqui: é recurso de tela e fica registrada como limite.

**4. O link antigo continua abrindo o cliente, sem redirecionar para o vazio.**
`target()` lê primeiro o ledger novo; não achando, lê `rms_mp_payment`. Sem essa leitura de compatibilidade, todo link enviado antes do deploy passaria a devolver " página não encontrada" no momento em que o cliente abrisse o e-mail — o pior momento possível para uma regressão. `rms_mp_payment` vira **somente leitura** pelo código novo.

**5. Nenhuma migração de dados nesta etapa.**
`rms_mp_payment` não é apagada, nem copiada, nem alterada: são centenas de linhas de produção que podem estar em uso por link já enviado ao cliente. A tabela é aposentada por **drenagem** (novos links nascem no ledger novo; os antigos expiram). A remoção é uma etapa posterior, com consulta de conferência e janela de segurança.

**6. Um pagamento tem um dono; dois ledgers nunca são escritos no mesmo caminho.**
Tentativa de gateway → somente `rms_payment_transaction`. Pagamento manual → somente `rms_mp_payment`. O `GatewayEventApplier` segue sendo o único que aplica evento verificado, e a sincronização da cobrança legada continua em `syncJowChargeStatus()`. A invariante da ADR 0118 (nada escreve nos dois) é preservada por construção: `OrderFlowLedger::record()` escolhe **um** destino por chamada.

## Alternativas consideradas

1. **Manter o fluxo legado e só documentar a dívida de novo** — rejeitada: é a segunda vez que ela é adiada, e a assimetria já custa (provedor novo não atende pedido).
2. **Big bang**: migrar as linhas de `rms_mp_payment` para `rms_payment_transaction` e apagar a tabela no mesmo deploy — rejeitada: os links já enviados apontam para `init_point` gravado lá; apagar a tabela quebra o cliente exatamente quando ele vai pagar. Migração de dado com dinheiro no meio exige etapa própria, com conferência linha a linha.
3. **Fazer o `OrderPaymentLink` escrever nos dois ledgers "para não perder nada"** — rejeitada: é a violação explícita da invariante da ADR 0118 e produz o pior estado possível (dois registros que podem divergir sem ninguém saber qual é o verdadeiro).
4. **Manter `Mercadopago::charge()` no caminho de pedido e só trocar a tabela de destino** — rejeitada: preservaria `preference_id`/`init_point` como nomes de dado genérico e deixaria o Stripe/PayPal de fora, que é exatamente o defeito que a migração resolve.
5. **Escolher a conta no painel já nesta entrega** — adiada: exige tela, i18n e regra de precedência; a primeira conta habilitada preserva o comportamento atual sem decisão nova.

## Limites conhecidos

- **`rms_mp_payment` continua existindo e sendo lida** para links emitidos antes do deploy (leitura de compatibilidade) e para pagamentos manuais. A remoção exige consulta de conferência e janela de expiração dos links antigos.
- **Uma conta por provedor.** Não há seletor de conta por site/pedido; a primeira habilitada vence.
- **Pedido com pagamento manual registrado depois do deploy** continua em `rms_mp_payment` — a tela de cobranças manuais não muda nesta entrega.
- **A tabela `rms_payment_transaction` precisa da migração aplicada** (`tools/upgrade_jow_payments_multigateway_core.sql`). Sem ela, o link de pedido falha com erro claro (`order_gateway_schema_missing`) em vez de criar um registro pela metade.
- **Relatório do JOW × pedido público**: os dois passam a ter a mesma origem de dado, mas o relatório consolidado (uma linha por pedido pago) é trabalho da próxima etapa.

## Consequências

- **Arquivos novos:** `dev/system/library/payment/order_gateway_ledger.php` (cruza `GatewayLedger` com o ledger legado), `dev/system/library/payment/order_payment_link.php` (regra do link do pedido), `dev/system/library/payment/order_flow_ledger.php` (adaptador `Ledger` da ADR 0097 para a tela e para o `notify`).
- **Arquivos alterados:** `dev/catalog/controller/checkout/payment.php` (usa o novo ponto único; `Mercadopago`/`Method`/`Checkout` deixam de ser instanciados no fluxo de pedido), `dev/admin/controller/payments/jow.php` (ação de link de cobrança de pedido), `dev/system/library/payment/db_ledger.php` (marcado como legado no docblock, sem mudança de comportamento).
- **Tabelas:** nenhuma nova. `rms_payment_transaction` passa a receber fonte `order` (a coluna `source_type` já aceitava `order` desde a migração do núcleo).
- **Interfaces:** `GatewayLedger` não muda. `Ledger` (ADR 0097) continua igual — a troca é de implementação, o que mantém `Checkout` e seus testes válidos.
- **Testes:** novos testes de `OrderPaymentLink` (criação, reaproveitamento, bloqueio, schema ausente, provedor sem conta habilitada), de `OrderFlowLedger` (leitura no ledger novo com queda para o legado, `record()` escolhendo um dono) e de `OrderGatewayLedger` (consulta por fonte `order`). Suíte completa precisa continuar verde.
- ⚠️ **Leitura dupla tem custo**: `target()` pode fazer duas consultas quando o token é antigo. É o preço da compatibilidade; a segunda consulta só acontece quando a primeira não acha nada.
- ⚠️ **Duas tabelas para o mesmo conceito** até a drenagem: quem for ler o código precisa saber que `rms_mp_payment` é legado (links antigos + manual) e `rms_payment_transaction` é o presente.

## Documentos atualizados

- `docs/integracoes/JOW-Payments-MultiGateway-Core.md` — o fluxo `order` deixa de ser "antes de ligar"; seção nova sobre o link do pedido.
- `docs/integracoes/JOW-Payments-Reconciliacao.md` — a fila de reconciliação passa a incluir tentativas de pedido; nota sobre `rms_mp_payment` somente leitura.
- `docs/arquitetura/Dicionario-de-Dados.md` §9 — `payment_transaction.source_type = order` ganha significado explícito e `mp_payment` é marcada como **legado, somente leitura** (drenagem pendente).
- `docs/historico/KZ-64-Relatorio.md` — item 3 dos próximos passos marcado como feito.
- `docs/CHANGELOG.md` — entrada da etapa.
- Este pacote: `MIGRACAO-ORDER.md` (o que fazer no servidor), `conferencia-order.sql` (consultas de antes/depois) e `roteiro-teste-pedido.md` (como provar em sandbox).
