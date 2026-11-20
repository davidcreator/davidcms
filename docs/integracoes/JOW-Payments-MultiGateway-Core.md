# JOW-Payments — fundação multi-gateway

**Marco:** núcleo comum para cobranças internas (`jow_charge`) e pagamentos de pedidos (`order`). O JOW continua sendo ferramenta interna/admin. **Atualizado pela ADR 0119:** o fluxo do pedido deixou de ser "antes de ligar" e passou a nascer neste núcleo — ver *Link de pagamento de pedido* abaixo.

## Estado encontrado no repositório

- `payments/jow` grava cobranças locais em BRL, gera PIX Copia e Cola e permite registrar status no admin. As opções Cartão/Boleto da tela ainda não representam gateways integrados.
- O checkout público atual tem Mercado Pago atrás do contrato legado `Payment\Method`/`Checkout` e usa o ledger `rms_mp_payment`.
- Não há adapters Stripe ou PayPal no `main` auditado.
- O ADR 0097 deixou seleção de provedor, ledger genérico, configuração por meio e rota de notificação por provedor para uma etapa futura.

## Núcleo implementado

- `Money`: valor decimal como string + código ISO + precisão declarada, sem `float`, arredondamento implícito ou comparação entre unidades diferentes.
- `GatewayCapabilities`: matriz explícita método × moeda, casas fracionárias e pares cambiais cotados nativamente pelo gateway. Pares não anunciados falham fechados.
- `FxQuote`: cotação emitida pelo gateway selecionado, com moeda/valor de origem, moeda/valor cobrado, taxa, referência, instante e expiração. O valor cobrado pelo provedor é autoritativo; a taxa é trilha de auditoria.
- `GatewayAdapter` / `GatewayRegistry`: separa provedor de método de pagamento. O banco armazena um código fixo; nunca se transforma um valor vindo do banco em nome de classe PHP.
- `GatewayAccount` / `GatewayAccountStore` / `DbGatewayAccountStore`: metadados públicos separados das credenciais. Configuração pública rejeita campos com nomes de segredo. Cadastro começa desativado; só é ativado depois da cifra bem-sucedida. Atualização em branco preserva segredo, remoção é explícita, e segredo bloqueado não permite reativar a conta.
- `GatewayCredentialVault`: cifra cada campo separadamente via `SecretBox` AES-256-GCM, em contexto ligado ao ID da conta, provedor e campo. Recusa plaintext no armazenamento novo, chave ausente, incompatibilidade de chave e tentativa de colocar credenciais na configuração pública. O conteúdo secreto nunca é retornado no debug.
- `GatewayPaymentService`: valida conta ativa, bloqueia ambiente live por padrão, confere capacidade método/moeda, precisão, par cambial, cotação válida do próprio adapter e igualdade exata do valor cobrado. Uma cotação expirada ou incompatível falha; não há conversão local ou fallback silencioso.
- `GatewayLedger` / `DbGatewayLedger`: armazenamento neutro para `order` e `jow_charge`, tentativas com chave idempotente, token público aleatório, instrução de pagamento permitida por lista e eventos webhook deduplicados. Reaplicação de evento é segura e transições que regridem de `paid` para `failed` são recusadas.
- `GatewayWebhookResult`: só o resultado autenticado e reconciliado pelo adapter pode chegar ao ledger. O ledger guarda hash do evento, não corpo bruto.

## Link de pagamento de pedido (ADR 0119)

O pedido deixou de ser território só do Mercado Pago. O caminho é:

```
OrderPaymentLink::link(provider, orderId, total, título, notifyUrl, returnUrl)
        │
        ├─ conta de gateway habilitada (a de menor gateway_id daquele provedor)
        ├─ GatewayPaymentService::createPayment()   ← mesmas travas do JOW:
        │     · moeda/método conferidos por capabilities
        │     · valor exato exigido de volta do provedor
        │     · chave de idempotência derivada do pedido e da tentativa
        │     · trava de ambiente `live`
        └─ GatewayPaymentRequest com source_type = 'order'
```

Cinco resultados, os mesmos da ADR 0097 (`Link`), para que a tela não precise de tradução nova:

| Resultado | Quando |
| --- | --- |
| `created` | tentativa nova gravada em `rms_payment_transaction`; o `token` é a chave do link |
| `reused` | já havia tentativa viva com endereço de pagamento — **nenhuma cobrança nova** |
| `paid` | pedido já pago; não se cria outro link |
| `blocked` | provedor sem conta habilitada (`no_gateway_account`) |
| `no_total` / `failed` | pedido sem valor / provedor não devolveu para onde mandar o cliente |

**Chave de idempotência.** `order:<32 hex>` derivada por `sha1('reamurcms:order:<id>:attempt:<n>')`. Estável enquanto a tentativa está viva, nova depois de `failed`/`cancelled`/`expired` — e o número do pedido **não** aparece na chave que vai ao provedor.

**Quem escreve onde.** Tentativa de gateway → somente `rms_payment_transaction`. Pagamento manual (`pix`/`card`/`boleto`) → somente `rms_mp_payment`, pelo `DbLedger` legado. `OrderFlowLedger::record()` escolhe **um** destino por chamada; a invariante da ADR 0118 (nada escreve nos dois) é estrutural, não uma convenção.

**Link emitido antes do deploy.** `target()` e `Checkout::latestForOrder()` leem o ledger novo primeiro e caem para `rms_mp_payment` quando não há linha nova. `rms_mp_payment` passa a ser **somente leitura** pelo código novo: sem essa compatibilidade, todo link já enviado ao cliente viraria "página não encontrada" no momento do pagamento.

**O que continua fora:** escolha de conta por site/pedido, estorno (ver `DECISAO-REEMBOLSO.md`) e a remoção de `rms_mp_payment` (drenagem, etapa posterior).

## Moeda e conversão

- A moeda de origem inicial do JOW é **BRL**. A transação registra origem e cobrança em colunas separadas.
- A conversão automática usa **a cotação do gateway selecionado**. Esta etapa não consulta APIs de câmbio nem implementa provedor financeiro.
- Cada conta declara os pares fonte/destino que o gateway consegue cotar e os métodos/moedas em que consegue cobrar.
- O adapter deve devolver a referência/taxa/expiração do gateway, e a cobrança deve usar exatamente o valor cotado. O ledger persiste a quote junto com o valor de origem e o valor cobrado.
- Se o par não for suportado ou a quote estiver vencida/futura, o pagamento não é criado. Uma integração futura poderá pedir nova quote antes da chamada; nunca substituirá por uma taxa local.
- A precisão é declarada por moeda; entradas com casas não permitidas são rejeitadas, sem arredondamento silencioso. Tarifas/comissões permanecem responsabilidade explícita do adapter/produto e não são calculadas nesta fundação.

## Armazenamento aditivo

`tools/upgrade_jow_payments_multigateway_core.sql` define:

1. `rms_jow_gateway`: contas por provedor/ambiente; configuração não secreta separada de JSON contendo ciphertext individual por campo.
2. `rms_payment_transaction`: ledger neutro para as fontes `order` e `jow_charge`, método/provedor, moedas/valores/precisões, quote FX, referência, ação de cliente, token público e chave idempotente.
3. `rms_payment_gateway_event`: deduplicação por `(gateway_id, provider_event_id)`; guarda digest e resultado, não o payload bruto.

A migração é **aditiva e não foi aplicada**. Não há FK polimórfica, conta seed, gateway ativo ou segredo inicial. `rms_mp_payment` e `rms_jow_charge` permanecem intactas; backfill, cutover e compatibilidade de leitura são uma etapa separada.

## Regras para as próximas integrações

- Nunca armazenar PAN/CVV. Usar checkout hospedado/tokenização do provedor.
- Adaptadores têm de honrar `idempotencyKey` no provedor (ou mecanismo equivalente com reconciliação), validar host de links do provedor e autenticar/reconciliar notificações antes de devolver evento `verified`.
- Nunca enviar credenciais pela conversa, colocá-las no código, logs, fixtures ou resposta do admin. O formulário futuro deve mostrar apenas se o segredo existe, não o valor cifrado/decriptado.
- Webhook duplicado pode ser reaplicado: a deduplicação evita dois registros e o ledger torna a transição idempotente. O corpo do webhook não é prova de pagamento.
- Começar em sandbox. Nenhuma conta, ambiente live, chamada externa ou endpoint novo foi habilitado.

## Compatibilidade e limites

- Não foram alterados controllers/models do checkout Mercado Pago, JOW atual, instalação, configuração de provedores, telas, URLs ou tabelas legadas.
- O `DbGatewayLedger` ainda **não está ligado** a checkout público nem ao admin JOW; adapters Mercado Pago/Stripe/PayPal não foram adicionados. O contrato legado `Payment\Method` segue em vigor.
- Nenhuma cobrança, consulta real a banco, conexão a gateway, webhook real, migração, ambiente de staging ou deploy foi executado.
- O próximo incremento deve escolher um adapter sandbox, adicionar as telas/rotas administrativas e públicas e então realizar a migração/backfill em mudança separada, com revisão do comportamento existente.
