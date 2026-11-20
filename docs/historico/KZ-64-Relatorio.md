# RELATORIO KZ-64 — P0: status por provedor + reconciliação ativa

> **Documento de entrega do pacote.** O que era para fazer está na `main` desde
> o commit `03a69212`; o passo a passo operacional ficou em
> [`../integracoes/KZ-64-Como-Aplicar.md`](../integracoes/KZ-64-Como-Aplicar.md)
> e a decisão arquitetural na
> [ADR 0118](../../agents-ia/decisoes/0118-pagamentos-multigateway-e-reconciliacao-ativa.md).

**Data:** 2026-11-20 · **Base:** `main` @ `536a7f4` · **Pacote:** `ReamurCMS-KZ64-P0-reconciliacao.zip`
**Escopo:** P0-a (bug funcional) e P0-b (reconciliação ativa) do tópico 4 do relatório de análise.
**Fora de escopo:** P1/P2 do relatório, migração aplicada, chamada real a provedor, deploy.

---

## 1. P0-a — a cobrança paga não saía de `pending`

### Causa raiz

`dev/catalog/model/checkout/gateway_payment.php` filtrava a linha legada com um provedor fixo:

```php
"UPDATE ... `rms_jow_charge` SET `status` = '...' WHERE `charge_id` = X AND `method` = 'mp'"
```

Com Stripe ou PayPal, o webhook era verificado, o ledger ficava correto e o `UPDATE` casava **zero linhas**. A cobrança continuava `pending` no painel e no relatório de recebimentos — dinheiro recebido aparecendo como pendente.

O defeito nasceu da mesma coisa que o P0-b corrige na raiz: **a regra "um evento de provedor só altera a cobrança daquele provedor" estava implementada em dois lugares** (a verificação no adapter e o filtro na escrita), e o segundo lugar tinha o provedor cravado.

### Correção

`syncJowChargeStatus(int $chargeId, string $providerStatus, string $providerCode)`:

- o código do provedor vem do registro **verificado no ledger** (`$record->providerCode` no controller público), nunca do request;
- validado por regex antes de qualquer query (`^[a-z][a-z0-9_]{0,31}$`);
- usado escapado no `WHERE`, preservando a invariante: cobrança manual (`pix`/`card`/`boleto`) **nunca** é alcançada, porque nenhum código de provedor instalado é igual a um método manual;
- tabela via `$this->t('jow_charge')`, como no resto do model.

### Prova

`tests/Unit/Payment/JowChargeStatusSyncTest.php` — 5 testes: os três provedores endereçando a própria linha (e não a de outro), o mapa completo de status para o vocabulário legado (`pending`/`paid`/`partial`/`canceled`/`expired`/`refunded`/`disputed`), `paid_at` só nos estados liquidados, status desconhecido sem query, e entrada inválida (id ≤ 0, código vazio/maiúsculo/com espaço/33 chars) sem query.

---

## 2. P0-b — reconciliação ativa

### O problema

O webhook é *gatilho*, o provedor é a fonte da verdade — mas nada garantia que o gatilho chegasse. Notificação perdida (WAF, firewall, timeout, app fora) = transação `pending` para sempre, sem alerta e sem nova consulta.

### Desenho

```
cron/reconcile (token)
        │
        ├─ GatewayAccountStore::all()      todas as contas (inclusive desativadas: pagamento em voo)
        │
        └─ GatewayReconciliationService
               ├─ pendingAttempts(gateway, até T-min_age, desde T-lookback, limit)
               ├─ adapter->reconcile(record, account, credentials)     ← READ-ONLY
               └─ GatewayEventApplier::apply()                          ← MESMO caminho do webhook
                        ├─ byProviderReference (o evento fala da tentativa certa?)
                        ├─ valor exato
                        ├─ provider_payment_id imutável
                        ├─ transição monotônica (nada de paid → failed)
                        └─ recordWebhookOnce + applyVerifiedEvent (dedup + optimistic update)
```

### Decisões de segurança

| Decisão | Motivo |
| --- | --- |
| `reconcile()` é read-only por contrato | Nenhum adapter cria cobrança, captura ou reembolsa. **Ordem PayPal aprovada e não capturada fica `authorized`** e aparece no relatório. Mover dinheiro a partir de cron é decisão de produto, não rede de segurança |
| Token obrigatório, mínimo 32 chars, `hash_equals` | Endpoint público na internet; sem token configurado responde 403 e não toca no banco (fail closed) |
| Janela dupla `min_age` + `lookback` | Não compete com o webhook que está chegando e não vira polling eterno de cobrança velha |
| `limit` 1..200 e tudo o mais validado antes de qualquer query | Uma execução não vira varredura ilimitada |
| Falha por transação isolada | Um provedor fora do ar não impede reconciliar os outros |
| Referência conferida antes de aplicar | Um adapter não consegue aplicar evento de outra tentativa |
| Relatório sem payload/credencial | `safeReason()` mascara e-mail e sequência longa, trunca em 120 chars |
| 503 só para falha real | `retry` de provedor é esperado e não deve pintar o cron de vermelho |

### Refatoração que sustenta o resto

`GatewayEventApplier` concentra as checagens pós-verificação. `GatewayWebhookService::process()` passou a delegar nele — comportamento idêntico (provado pelos 26 testes existentes de webhook/ledger, verdes sem alteração). Sem isso, a reconciliação seria a **terceira** cópia da regra de dinheiro: exatamente o que produziu o P0-a.

### Contrato novo (aditivo)

| Símbolo | Onde | Natureza |
| --- | --- | --- |
| `ReconcilableGatewayAdapter::reconcile()` | `reconcilable_gateway_adapter.php` | interface nova e opcional |
| `GatewayReconcilableLedger::pendingAttempts()` | `gateway_reconcilable_ledger.php` | interface nova que **estende** `GatewayLedger` — nenhum implementador existente quebra |
| `GatewayAccountStore::all()` | `gateway_account_store.php` | método novo na interface (único implementador: `DbGatewayAccountStore`) |
| `GatewayPaymentResult`/`GatewayPaymentRecord::$providerCheckoutId` | value objects | último parâmetro, com default — chamadas existentes intactas |

---

## 3. Por que a coluna `provider_checkout_id`

Mercado Pago grava no ledger o **id da ordem** (`GET /v1/orders/{id}` funciona hoje, sem migração).
Stripe grava `client_reference_id` = **nossa** chave de idempotência, e PayPal grava `custom_id` = a mesma chave. Nenhum dos dois é consultável por essa chave: o id da sessão (Stripe) e o id da ordem (PayPal) **não eram persistidos em lugar nenhum** — descobri isso olhando `createPayment()` dos dois adapters.

Sem um id do provedor não existe reconciliação: só adivinhação. Daí a migração aditiva `tools/upgrade_jow_payments_reconcile.sql` (`provider_checkout_id` + índice `provider_checkout`), idempotente via `information_schema` + `PREPARE`, compatível com MySQL 5.7/8 e MariaDB.

**Sem a migração o sistema funciona**: `DbGatewayLedger` detecta a coluna uma vez por instância (`SHOW COLUMNS`, cache por instância — não global, justamente para um deploy que aplica a migração ser visto no request seguinte), não a usa, e o relatório mostra `missing_provider_anchor` para Stripe/PayPal. Mercado Pago reconcilia de qualquer forma.

> Corrigi no caminho um detalhe que o próprio teste pegou: eu tinha usado `static` no cache da detecção, e o cache vazava entre instâncias no mesmo processo. Agora é propriedade de instância.

---

## 4. Evidências de verificação

Executado em PHP 8.3.14 (build estático) com `composer install` feito do `composer.lock`, **depois** de aplicar os dois patches num clone limpo da `main`:

| Checagem | Resultado |
| --- | --- |
| `php -l` em `dev/` (448 arquivos, sem vendor) | 0 erros |
| PHPUnit, suíte completa | **1881 testes / 46.162 assertions**, 1 erro **de ambiente** (`imagejpeg()` inexistente no build estático, em `CoreServicesSecurityTest`) |
| PHPUnit, só `Payment` | 347 testes, todos verdes |
| PHPStan nível 2 (`composer analyse`) | `[OK] No errors` |
| `tools/verify-checksums.sh` | 3 PASS / 0 FAIL, 33 arquivos conferidos |
| `tools/verify-deps-layout.sh` | 18 PASS / 0 FAIL |
| `tools/verify-ci-workflow.sh` | 35 PASS / 0 FAIL |
| `git am` dos dois patches em clone limpo | aplicam sem conflito; `diff` contra a árvore de trabalho = **0 divergências** |
| `tools/verify-checkers-inventory.py` | REGRESSÃO pré-existente em `check_threejs_thumb.cjs` e `e2e_f906.cjs` — **reproduzida idêntica na `main` limpa**, nada a ver com este patch |

Testes novos: **53** (5 do P0-a + 48 da reconciliação: serviço 17, adapters 13, ledger 9, rota do cron 9).

Sobre a rota do cron: dois testes usam `#[RunInSeparateProcess]` porque `Model::hasTable()` guarda o resultado de `SHOW TABLES` num cache `static` de processo — sem isolamento, um teste “schema ausente” envenenaria os seguintes. É limitação do teste, não do código (em produção a requisição é curta).

---

## 5. Limites conhecidos (declarados, não escondidos)

1. **Stripe não reconcilia reembolso.** O estado vem da sessão; `charge.refunded` continua sendo a fonte. Reembolso com webhook perdido só aparece na próxima notificação. Próximo passo: `GET /v1/payment_intents/{id}` → `latest_charge`.
2. **Stripe/PayPal criados antes desta entrega ficam fora** (`missing_provider_anchor`). Não há backfill possível — o id nunca foi gravado.
3. **Sem throttle por transação.** `date_modified` só muda quando o estado muda, então a tentativa mais antiga da janela é reconsultada em toda execução (o `limit` contém o custo). Se a fila aberta passar de algumas centenas, avaliar `last_checked_at`.
4. **Sem lock entre execuções concorrentes.** Duas execuções simultâneas podem consultar a mesma tentativa; a aplicação é idempotente pelo ledger, então o efeito é chamada duplicada ao provedor.
5. **Ordem PayPal aprovada sem captura** fica `authorized` e visível. Se virar rotina, o certo é uma ação explícita no admin, não um cron.
6. **Os SQL de migração escrevem `rms_` fixo** — numa instalação com outro `DB_PREFIX`, aplicar sem trocar cria tabela que o código não encontra, e colar uma consulta de conferência devolve `#1109 Tabela desconhecida` (que parece erro de sintaxe e não é). Desde a 5ª parte existe `tools/payment-schema-drift.php` para detectar isso antes, com três veredictos distintos (`ok`, `migration_pending`, `prefix_mismatch`). A correção definitiva — SQL gerado a partir do prefixo — não entrou: exigiria mexer em script já publicado.

## 6. Próximos passos sugeridos (na ordem)

1. **ADR 0118** registrando a frente multi-gateway + reconciliação, e destravar o `decision-matrix.json` (parado em `1f864b80`). É o único item do DoD que este pacote não fecha.
2. **`refund()` no contrato** + botão no painel (P1 do relatório).
3. ~~**Levar `order` para o ledger novo**~~ — **feito** (KZ-64, 5ª parte: ADR 0119, `OrderPaymentLink` + `OrderFlowLedger`; `rms_mp_payment` fica somente leitura para links antigos e pagamento manual).
4. Validação sandbox real de Mercado Pago (vendedor de teste + pagador `@testuser.com`) — único elo nunca exercitado contra o mundo real.
5. ~~`import` das tabelas de pagamento no `Dicionario-de-Dados.md`~~ — **feito** (KZ-64, 2ª parte: §9 do dicionário).

## 7. Como reverter

- Sem push: `git reset --hard 536a7f4`.
- Com push: `git revert` na ordem inversa (P0-b, depois P0-a).
- Migração: a coluna é inofensiva sem o código novo; se quiser remover, `DROP KEY provider_checkout` + `DROP COLUMN provider_checkout_id` (SQL no `LEIA-ME.md`).
