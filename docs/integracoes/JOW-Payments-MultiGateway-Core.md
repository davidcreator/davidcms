# JOW-Payments — fundação multi-gateway

**Marco:** núcleo comum para cobranças internas (`jow_charge`) e pagamentos de pedidos (`order`), antes de ligar provedores novos ou trocar os fluxos existentes. O JOW continua sendo ferramenta interna/admin. Nenhuma integração real é ativada nesta etapa.

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
