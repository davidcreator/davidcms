# ADR 0122 — Pagamentos: o caminho legado do pedido e a dívida D2

| | |
| --- | --- |
| **Status** | ✅ executada (KZ-64, 2026-11-20) — decisão do dono do produto: **as três fases** |
| **Data** | 2026-11-20 |
| **Agente proponente** | system (proposta) · humano (decisão) |
| **Contexto** | A ADR 0119 tirou a **criação** do link de pedido do contrato legado: novos links nascem em `rms_payment_transaction`. Mas ela deixou, de propósito, três coisas vivas — e o diagnóstico chama isso de dívida **D2**: (a) o **webhook** do Mercado Pago ainda entra por `checkout/payment.notify`, que pertence ao contrato `Payment\Checkout`/`Mercadopago`/`DbLedger` (ADR 0097); (b) o **checkout público de vendedor** (`rms_mp_payment`, `mp_seller`) continua existindo inteiro, com tela em `setting/marketplace`; (c) `rms_mp_payment` continua sendo leitura de compatibilidade. Enquanto isso não é decidido, o CMS tem dois caminhos de pagamento com dois ledgers — e nenhuma das duas decisões de negócio que sobraram (ADR 0120: sem vendedor) foi propagada para o código que ainda serve o vendedor. |
| **Relacionadas** | ADR 0119 (fluxo do pedido no ledger novo), ADR 0120 (**decidiu**: a plataforma não cobra em nome de terceiro), ADR 0118 (núcleo multi-gateway), ADR 0097 (contrato de meio de pagamento), ADR 0034/0035 (Mercado Pago marketplace) |

## O que existe hoje, verificado no código

| Peça | Linhas | Ainda serve | Por quê |
| --- | --- | --- | --- |
| `catalog/controller/checkout/payment.php` | 182 | **webhook** (rota `checkout/payment.notify`) e a leitura antiga de link (`pay\|CHAVE`) | é o endereço que o Mercado Pago tem configurado; **link já enviado a cliente continua abrindo por aqui** |
| `system/library/payment/checkout.php` | 167 | regras do link legado | usado por `setting/marketplace::paylink` e pelo `payment.php` |
| `system/library/payment/db_ledger.php` | 131 | leitura/escrita de `rms_mp_payment` | usado por `payment.php`, `marketplace.php` e `payments/jow` |
| `system/library/payment/mercadopago.php` | 307 | `charge()` com token de **vendedor** + `marketplace_fee` | **a ADR 0120 diz que isso não deve mais acontecer** |
| `system/library/payment/method.php` | 50 | meio de pagamento legado | idem |
| `catalog/controller/checkout/gateway.php` | 108 | **webhook novo** (`checkout/gateway.notify`) | é o caminho que o fluxo novo usa |
| `admin/controller/setting/marketplace.php` | 562 | tela de vendedores + `paylink` (link de vendedor) | **a ADR 0120 tornou essa operação obsoleta** |

O que a ADR 0119 **já migrou**: criar link de pedido (`OrderPaymentLink`), ler link (ledger novo primeiro, `rms_mp_payment` como queda), registrar pagamento (um dono por pagamento), reconciliar (tentativas de pedido entram na varredura).

## O que a ADR 0120 mudou no problema

Antes da 0120, a pergunta era "como migrar o vendedor para o ledger novo". **A 0120 respondeu que não há vendedor.** Isso transforma a D2 de "migração grande" em **aposentadoria**: o código de vendedor não precisa ser reescrito, precisa parar.

Mas há uma armadilha que a 0120 **criou** e que ninguém decidiu: a tela `setting/marketplace` **ainda oferece** o botão que gera link de vendedor com `marketplace_fee`. Quem entrar lá amanhã cria, com a melhor das intenções, um pagamento no modelo que o dono do produto acabou de recusar.

## Fases

### F1 — parar de oferecer o que não se faz mais (aditivo, sem remoção)

- A tela `setting/marketplace` deixa de oferecer **gerar link de vendedor** (`paylink`); a ação responde com aviso claro: "o JOW cobra na conta da plataforma; pedido de terceiro não é atendido" (três idiomas).
- O código de `paylink` **não é apagado** nesta fase: vira caminho fechado, não caminho removido (régua de reversão).
- Documentação: `MercadoPago-Marketplace.md` ganha o aviso no topo.
- **Custo:** ~4 arquivos (controller, template, i18n, doc) + teste de fiação. **Zero migração. Zero mudança de dinheiro** — o que muda é a tela parar de convidar ao erro.

### F2 — o webhook do Mercado Pago sai do contrato legado

Hoje há **dois** webhooks: `checkout/payment.notify` (legado, formato antigo, um meio) e `checkout/gateway.notify` (novo, multi-provedor). O do MP é o legado.

- `checkout/payment.notify` passa a ser **encaminhador fino**: monta a `Notification` e entrega ao `GatewayWebhookService`, mantendo a mesma URL (o MP não precisa ser reconfigurado, e não há janela de aviso perdido).
- `Payment\Method` deixa de ser necessário nesse caminho.
- **Custo:** ~3 arquivos + testes de webhook. **Risco:** médio — é o caminho que confirma pagamento. Precisa do teste B (webhook assinado aplica `paid`) rodando **antes e depois**.

### F3 — aposentar `rms_mp_payment` (só depois de F2 estabilizar)

Não é código: é **janela**. A tabela é aposentada por drenagem (ADR 0119 §5). Só depois que nenhum link antigo estiver vivo.

- Consulta de conferência: quantos links de `rms_mp_payment` foram abertos nos últimos 90 dias.
- Se zero, a tabela pode ficar intocada (custo de mantê-la é nulo) — **apagar não é objetivo**.
- **Custo:** uma consulta e uma decisão. **Migração:** nenhuma, ou uma remoção manual autorizada.

## O que **não** entra em nenhuma fase

- Remover `mercadopago.php`/`method.php`/`checkout.php`/`db_ledger.php`: o webhook de F2 usa parte disso até estabilizar, e apagar biblioteca de dinheiro por estética é o pior negócio possível.
- Migrar dados de `rms_mp_payment` para o ledger novo: links antigos não têm `provider_reference` no formato novo; migrar criaria registro falso.
- Mexer no checkout público de vendedor (`catalog/controller/checkout/`) além do encaminhamento do webhook.

## Decisão

**Pendente.** A recomendação é **F1 agora** e **F2 quando houver credencial para o teste B** — o mesmo motivo de sempre: mexer no caminho que confirma pagamento sem poder observá-lo é trocar um código que funciona por um que ninguém viu rodar.

**F1 não tem esse problema**: ela não toca em dinheiro, só fecha um botão.

### O que precisa de resposta

| # | Decisão | Opções | Bloqueia |
| --- | --- | --- | --- |
| **D08** | Fecho o botão de link de vendedor (F1)? | sim, com aviso · sim, removendo a ação · não, deixar como está | F1 |
| **D09** | Unifico os dois webhooks (F2)? | agora · depois do teste B · nunca (deixar os dois) | F2 |
| D10 | `rms_mp_payment`: drenar e apagar, ou deixar para sempre? | deixar (recomendado) · apagar após 90 dias sem abertura | F3 |

## Consequências

- ✅ F1 faz a tela parar de convidar ao modelo que a ADR 0120 recusou — hoje ela convida.
- ✅ F2 deixa **um** caminho de webhook, com as mesmas checagens, em vez de dois com regras paralelas.
- ⚠️ F2 encosta no caminho que confirma pagamento: exige teste B antes e depois, e não é release para fazer junto com outra coisa.
- ⚠️ Enquanto F2 não acontece, todo aviso do Mercado Pago passa por código do contrato aposentado — que continua coberto por teste (`CheckoutTest`, `DbLedgerTest`, `MercadopagoTest`), mas não é onde o time olha primeiro.
- ⚠️ Nada aqui remove `rms_mp_payment`. A tabela é a rede de segurança de links já enviados.

## Execução (2026-11-20)

O dono do produto decidiu executar as três fases. O que mudou:

### F1 — o link de vendedor deixou de ser oferecido ✅

- O botão estava na **lista de pedidos** (`sale/order`), não na tela do marketplace: `can_mp_paylink` passa a `false` e a linha do pedido não monta mais o endereço de link.
- A ação `paylink` **recusa com aviso** ("o link de vendedor foi aposentado; use JOW Payments"), nos três idiomas. A guarda vem **depois** de permissão e CSRF — um POST forjado não deve nem aprender que a ação existe.
- O código de `paylink` **continua no lugar**: caminho fechado, não removido. Reabrir é uma linha.
- A lista de pedidos mostra uma faixa explicando por que o botão não está lá.
- **Os 8 testes que davam cobertura fina ao caminho aposentado** (pedido pago, link pendente, sem vendedor, token que não abre, valor zero, recusa do provedor, pedido inexistente, POST+CSRF) foram **convertidos, não apagados**: agora provam que a recusa vale em todos esses casos e que nenhum grava nada.

### F2 — os dois webhooks passam a ser um ✅

- `LegacyWebhookForwarder` (novo): recebe o aviso que chega em `checkout/payment.notify` e o entrega ao `GatewayWebhookService` — mesma verificação de assinatura, mesmo `GatewayEventApplier`, mesmas checagens de valor, id e transição monotônica.
- **A URL não muda.** O Mercado Pago continua batendo no endereço que já tem configurado; trocar abriria janela de aviso perdido.
- A escolha da conta é conservadora e explícita: **1)** a conta dona da tentativa com aquele `provider_reference`; **2)** sem tentativa, a conta `mp` habilitada; **3)** sem nenhuma, devolve `null` e o caminho legado responde. Adivinhar conta aqui significaria verificar assinatura com o segredo errado e recusar pagamento legítimo.
- Resposta HTTP preservada: `503 + Retry-After` só em retry, `200 ok` no resto.

### F3 — a tabela legada é medida, não apagada ✅

- `tools/legacy-payment-drain.php` (novo): responde quantos links existem, quantos foram tocados na janela (padrão 90 dias) e qual o link mais novo. Códigos de saída na convenção do `payment-schema-drift`: `0` nada na janela ou tabela ausente, `1` há link vivo (**não apagar**), `2` erro de uso/conexão.
- O script **não tem `DELETE`, `DROP` nem `TRUNCATE`**, e um teste fixa isso. O veredicto `drained` diz em palavras que **não autoriza apagar**.
- Decisão D10 tomada na prática: `rms_mp_payment` **fica**. Mantê-la custa nada; é a rede de segurança de um cliente que abra um e-mail antigo.

### O que continua valendo

- Nada de remoção das bibliotecas (`checkout.php`, `db_ledger.php`, `mercadopago.php`, `method.php`): o caminho legado ainda responde quando não há conta de gateway, e apagar biblioteca de dinheiro por estética continua sendo o pior negócio possível.
- Nenhuma migração de dados.
- **O teste B continua pendente**: F2 encosta no caminho que confirma pagamento e só credencial prova que ele funciona. O que foi feito tem cobertura de unidade (7 testes do encaminhador), não de sandbox.

## O que foi entregue

Código de F1 e F2, ferramenta de F3, testes convertidos e novos, i18n nos três idiomas. Ver o CHANGELOG (16ª parte) para a lista arquivo por arquivo.

Proposta. Nenhuma linha de código, nenhuma remoção, nenhuma migração.
