# ADR 0097 — Contrato de meio de pagamento; Mercado Pago atrás dele, sem mudar o comportamento

| | |
| --- | --- |
| **Status** | ✅ aceita (etapa EX-03, 2026-11-13) |
| **Data** | 2026-11-13 |
| **Contexto** | Terceira etapa da frente de extensões (EX-01 base → EX-02 aviso de cookies → EX-03). Regras de origem do Mercado Pago: ADR 0034 (conexão de vendedores), ADR 0035 (link de pagamento e aviso), ADR 0094 (segredos cifrados). |

## O problema

O pagamento do CMS era o Mercado Pago escrito direto em dois controllers e dois models. O que é do Mercado Pago (vendedores, preference, formato do aviso, assinatura) e o que vale para qualquer meio (chave do link, link pendente reaproveitado, aviso repetido, situação do pedido, histórico, evento de pedido pago) estavam no mesmo método. Para ter Stripe ou PayPal, o caminho seria copiar tudo e trocar as chamadas, e cada cópia poderia divergir numa regra que mexe com dinheiro.

Esta etapa separa as duas coisas. Não acrescenta meio de pagamento, tela, tabela nem configuração.

## Decisão

### 1. Um meio de pagamento responde a três perguntas

Interface `Reamur\System\Library\Payment\Method` (`dev/system/library/payment/method.php`):

| Pergunta | Método | Resposta |
| --- | --- | --- |
| Está pronto para cobrar? | `blockedBy(): string` | `''` = sim; senão o motivo, numa palavra-chave do próprio meio |
| Crie a cobrança deste pedido | `charge(ChargeRequest): Charge` | `Charge::created(identificador, endereço, comissão, conta, moeda)` ou `Charge::failed(texto)` |
| Este aviso é verdadeiro? De qual pagamento ele fala? | `notification(Notification): Verdict` | `Verdict::answer(rótulo)`, `Verdict::retry(rótulo, log)` ou `Verdict::confirmed(pedido, pagamento, situação)` |

Mais `code()`, o nome curto do meio, usado no começo das linhas de log.

O meio só fala com o provedor. Ele não grava em pedido, não escreve histórico e não responde HTTP.

### 2. O que vale para qualquer meio fica num lugar só

Classe `Payment\Checkout`:

| Método | Regras |
| --- | --- |
| `link()` | Nesta ordem: pedido já pago não ganha link; link pendente é devolvido de novo, sem outra cobrança no provedor; o meio precisa estar pronto; o valor, arredondado a centavos, precisa ser maior que zero; cobrança sem identificador ou sem endereço não é gravada; a chave do link é sorteada (48 caracteres), nunca deduzida do pedido |
| `target()` | O link só leva ao provedor se a cobrança existe, não está paga, recusada nem cancelada, e o endereço é `https` válido |
| `notify()` | Pagamento confirmado sem cobrança no site: só log. Mesmo pagamento com a mesma situação: nada muda. `approved` leva o pedido a Aprovado (4); `rejected` e `cancelled`, a Cancelado (6); qualquer outra situação só fica anotada na cobrança. O evento de pedido pago sai só quando houve mudança real para `approved`. Falha local vira resposta `ok:error` (200) e uma linha de log |

`Checkout` não cita nenhum provedor (há teste para isso).

### 3. As cobranças continuam na mesma tabela

`Payment\Ledger` é a interface do que o `Checkout` grava e lê; `Payment\DbLedger` a cumpre sobre `rms_mp_payment`, `rms_order` e `rms_order_history`, com o mesmo SQL de antes. Nenhuma mudança de banco. As colunas com nome do Mercado Pago guardam o dado geral (`preference_id` = identificador da cobrança, `init_point` = endereço de pagamento, `mp_seller_id` = conta que recebe, `marketplace_fee` = comissão).

`payment_id` é numérico. `DbLedger` recusa identificador em texto em vez de gravar zero.

### 4. Mercado Pago é o primeiro meio, com o comportamento de antes

`Payment\Mercadopago` cumpre o contrato com as regras que já existiam: o primeiro vendedor ativo recebe; comissão de `mp_marketplace_fee_percent` (padrão 10, entre 0 e 100); o número do pagamento sai do formato novo ou do antigo; a assinatura é conferida antes de qualquer chamada; o pagamento só vale depois de consultado no Mercado Pago com o token do vendedor.

Ficou igual: os endereços (`checkout/payment.pay|<chave>`, `checkout/payment.notify`), a tela *Mercado Pago* e o botão na lista de pedidos, as tabelas, as configurações `mp_*`, os textos de resposta ao Mercado Pago (`ok:processed`, `retry:token-locked`...), os avisos na tela e as linhas de log (`mp notify: ...`). Links já enviados a clientes e avisos de cobranças antigas continuam valendo.

Os controllers `catalog/controller/checkout/payment.php` e `admin/controller/setting/marketplace.php` (ação `paylink`) passaram a só juntar as peças, responder HTTP e escolher o texto da tela.

### 5. O que saiu dos models

As regras de cobrança existiam duas vezes, uma em cada aplicação. Saíram:

- `catalog/model/checkout/mp_payment.php`: `addPayment`, `getPaymentByToken`, `getPaymentByOrder`, `reflectPaymentStatus`;
- `admin/model/setting/marketplace.php`: `addPayment`, `getPaymentByOrder`, `getActiveSeller`.

O model do admin ganhou `getActiveSellers()` e `getSellerByMpUserId()`, iguais aos do catálogo, para o Mercado Pago responder às três perguntas nas duas aplicações. Código de fora que chamasse os métodos retirados precisa passar a usar `Payment\Checkout`.

### 6. O que fica para a etapa do segundo meio

- Lista de meios e escolha do meio por cobrança. Hoje os dois controllers criam `Mercadopago` direto.
- Tabela geral de cobranças (com a coluna do meio e identificador de pagamento em texto). Só `DbLedger` muda.
- Endereço de aviso por meio. `checkout/payment.notify` sem nada continua sendo o do Mercado Pago.
- Tela de configuração do meio novo, e a decisão de ele morar no núcleo ou em `dev/extension/`.
- Passos que o Mercado Pago não tem, como a captura na volta do cliente (PayPal).

## Efeito

| Para quem | O que muda |
| --- | --- |
| Cliente e quem administra | Nada |
| Site instalado, ao atualizar | Sobem os arquivos. Nenhum SQL, nenhuma linha nova no `config.php`, nenhuma configuração |
| Quem escreve o núcleo | Regra de cobrança nova entra em `Payment\Checkout`; o que é de um provedor, na classe dele |
| Quem for escrever outro meio | Implementa `Payment\Method`. `tests/Unit/Payment/FictitiousMethod.php` é um exemplo completo e pequeno |

Arquivos: `system/library/payment/` (11 arquivos); os dois controllers e os dois models citados; testes em `tests/Unit/Payment/`. Uma linha de teste existente mudou (`SecretStorageTest`, de `getActiveSeller()` para `getActiveSellers()[0]`).

## Verificação

**Mesmo roteiro, antes e depois.** `apoio-testes/ex03/roteiro.sh` percorre o painel e o site público num ambiente local (PHP 8.2, MariaDB) contra um Mercado Pago de mentira (`mp-falso.php`), e escreve um relatório com: cada resposta HTTP e cada aviso de tela, as linhas de `rms_mp_payment`, `rms_mp_seller`, `rms_order`, `rms_order_history` e `rms_webhook_log`, o log do site, o que uma extensão de teste recebeu no evento de pedido, e cada chamada que chegou ao Mercado Pago de mentira, com o corpo. Rodado duas vezes no código de `origin/main` e duas no código novo, os quatro relatórios são idênticos byte a byte (365 linhas).

O roteiro cobre: conectar dois vendedores e renovar token; gerar link (novo, repetido, pedido pago, pedido recusado, sem valor, valor com mais de duas casas, comissão em texto, com vírgula, acima de 100, negativa e ausente, Mercado Pago recusando, fora do ar, devolvendo endereço `http`, sem endereço, só endereço de teste, por GET, sem token de formulário); abrir o link (válido, pago, recusado, devolvido, chave inexistente, vazia, longa, com aspas); aviso nos dois formatos, por GET, POST e corpo; aprovado, recusado, pendente, em análise, cancelado, devolvido; o mesmo pagamento mudando de pendente para aprovado e para devolvido; aviso repetido; pagamento sem pedido, de pedido inexistente, de pedido sem cobrança; Mercado Pago respondendo 500 ou sem situação; pagamento de outro vendedor; vendedor desativado e desconectado; assinatura certa, em maiúsculas, de outro segredo, ausente, malformada; chave do `config.php` errada no site público e no painel.

**Testes.** `tests/Unit/Payment/`: as regras gerais são testadas com um meio fictício, de propósito diferente do Mercado Pago (sem vendedores, em dólar, sem comissão, identificador de pagamento em texto, aviso em JSON com assinatura em cabeçalho próprio, palavras próprias de situação).

**Prova por mutação.** `apoio-testes/ex03/mutacoes.py` faz 137 trocas no código, cada uma retirando uma regra. Os testes acusam 131; as 6 restantes são a ligação dos controllers com o resto do sistema, e o roteiro acusa as 6. O roteiro sozinho acusa 101 das 137: um relatório igual antes e depois não cobre, por si, os casos de borda que só os testes exercitam (entrada malformada, escape, falha do log).

## Limites

- **Só contra um Mercado Pago de mentira.** O ambiente de teste do Mercado Pago real não consegue enviar avisos a este ambiente. O comportamento do código foi comparado antes e depois; a conversa com o Mercado Pago real não foi exercitada nesta etapa.
- **Avisos simultâneos não foram testados.** O roteiro manda um aviso por vez.
- **O contrato foi provado com um meio fictício**, não com Stripe ou PayPal. É esperado que o primeiro meio real peça ajustes nele.

## Comportamentos que existiam e foram mantidos

Não são desta etapa, mas apareceram no roteiro e mexem com pedido pago:

1. **Vale o último aviso.** Um aviso `rejected` de outro pagamento, chegando depois de um `approved`, leva o pedido de Aprovado para Cancelado.
2. **Devolução não mexe no pedido.** Com `refunded`, o pedido continua Aprovado e o link do cliente volta a abrir.
3. **Link "criado" que não abre.** Se o Mercado Pago devolver endereço que não é `https`, a tela diz "link criado" e o cliente cai em "não encontrado".
4. **Mensagem trocada.** Cobrança devolvida sem endereço aparece como "Mercado Pago OAuth request returned HTTP 201".
