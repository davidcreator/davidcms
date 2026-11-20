# KZ-64 — Validação em sandbox dos gateways de pagamento

| | |
| --- | --- |
| **Status** | ✅ aceita — roteiro operacional (KZ-64) |
| **Data** | 2026-11-20 |
| **Escopo** | Mercado Pago (Orders), Stripe (Checkout) e PayPal (Orders) em **ambiente de teste** |
| **Pré-requisitos de código** | `main` atual (núcleo + reconciliação + leitura de estorno, ADRs 0123/0124/0125) |
| **Antes de começar** | `php tools/payment-piloto.php --config=catalog/config.php --url=https://SEU-SITE/catalog/index.php` |
| **Relacionados** | [JOW-Payments-Reconciliacao.md](JOW-Payments-Reconciliacao.md) (ativação do cron e parâmetros), [ADR 0118](../../agents-ia/decisoes/0118-pagamentos-multigateway-e-reconciliacao-ativa.md), [Dicionário de Dados §9](../arquitetura/Dicionario-de-Dados.md) |

> **Regra de ouro:** este roteiro roda **só com credenciais de teste** (`TEST-…`,
> `sk_test_…`, `client_id` de sandbox). Conta criada com ambiente `sandbox` no
> painel não deve receber chave de produção em hipótese alguma — e chave nenhuma
> vai para issue, commit, chat ou print.

---

## 0. O que este roteiro prova — e o que não prova

**Prova:** que uma tentativa é criada no provedor, que a notificação assinada é verificada e aplicada no ledger, que a cobrança da ferramenta interna sai de `pending` (o P0-a), que a notificação repetida não aplica duas vezes, que a **reconciliação devolve um pagamento cujo webhook se perdeu** (o P0-b) e que as guardas da rota `cron/reconcile` respondem 403/400/200 como documentado.

**Prova também (desde as ADRs 0123/0124/0125):** que um **estorno feito no painel do provedor** chega ao CMS pela varredura de pagas, sem depender do aviso — nos três provedores (teste N da bateria).

**Não prova:** valor divergente e transição proibida (só o teste unitário consegue forjar isso), **estorno executado pelo CMS** (não existe: o R1 é leitura, R2/R3 dependem da D01), captura de ordem PayPal pelo cron (decisão de produto: não existe), câmbio real, e nada sobre o fluxo `order` público (ainda no contrato da ADR 0097).

---

## 1. Antes de começar

1. **Host público com HTTPS.** O provedor não entrega notificação para `localhost`. Em máquina local, exponha o site com túnel (`cloudflared`/`ngrok`) e use o host do túnel na URL de webhook — mas mantenha o `config.php` coerente, porque `HTTP_SERVER`/HTTPS entram na política de cookies e no HSTS (ADR 0089/0093).
2. **Token do cron** em `config.php` (ou equivalente), 32 caracteres ou mais:
   `php -r "echo bin2hex(random_bytes(24)), PHP_EOL;"`
3. **Migração opcional** (`tools/upgrade_jow_payments_reconcile.sql`): só é necessária para os testes do Stripe/PayPal do §9. O Mercado Pago reconcilia sem ela.
4. **Contas de teste do Mercado Pago:** no painel de desenvolvedores, crie a aplicação e **dois usuários de teste** (um vendedor, um comprador). O `access_token` do **vendedor de teste** (começa com `TEST-`) é o que vai no painel do CMS.

> O adapter **recusa** (e registra no log) criar checkout em sandbox com e-mail de pagador que não termine em `@testuser.com`. É a proteção que impede teste de sandbox virar cobrança real por engano.

---

## 2. Configurar o Mercado Pago

**No painel do Mercado Pago** (aplicação → *Webhooks*):

- **URL de produção e de teste:** `https://SEU-SITE/catalog/index.php?route=checkout/gateway.notify&gateway_id=<N>`
- **Evento:** **Ordens** (`orders`). O adapter descarta qualquer outro tipo como `not_order_event` — se você marcar só "Pagamentos", nada chega.
- Copie a **chave secreta** do webhook (é ela que valida a assinatura `x-signature`).

**No CMS** (*Pagamentos › JOW* → conta de gateway):

| Campo | Valor no teste |
| --- | --- |
| Provedor | `Mercado Pago` (`mp`) |
| Rótulo da conta | `JOW platform` (é o rótulo usado pelo código) |
| Ambiente | `sandbox` |
| `access_token` | token do **vendedor de teste** (`TEST-…`) |
| `webhook_secret` | chave secreta copiada acima |

A conta só fica habilitada quando **todos** os campos listados para o provedor existem e abrem (ADR 0094). Para descobrir o `<N>` da URL:

```sql
SELECT gateway_id, provider_code, account_label, environment, status
FROM rms_jow_gateway ORDER BY gateway_id;
```

---

## 3. Teste A — a tentativa é criada

Em *Pagamentos › JOW › Nova cobrança*: provedor **Mercado Pago**, valor `10,00`, descrição livre e **e-mail do comprador de teste** (`algo@testuser.com`). Salve e abra o link de checkout gerado.

```sql
-- cobrança legada da ferramenta interna
SELECT charge_id, method, status, amount, paid_at FROM rms_jow_charge ORDER BY charge_id DESC LIMIT 3;

-- tentativa no ledger neutro
SELECT transaction_id, provider_code, status, charge_amount, provider_reference,
       provider_checkout_id, LEFT(checkout_url, 48) AS checkout_url, date_added
FROM rms_payment_transaction ORDER BY transaction_id DESC LIMIT 3;
```

**Esperado:** `jow_charge.method = 'mp'`, `status = 'pending'`; tentativa em `pending` ou `requires_action`; `provider_reference` preenchido com o id da ordem (`provider_checkout_id` fica `NULL` no Mercado Pago — ele usa `provider_reference`).

---

## 4. Teste B — pagamento aprovado aplica pelo webhook

Pague o checkout com o **cartão de teste de status `APRO`** (a lista e os números estão na doc de cartões de teste do MP; confirme a validade — cartão de teste com data vencida é recusado como um cartão real).

Em até ~30 s (o MP reenvia se necessário):

```sql
SELECT transaction_id, status, provider_payment_id, date_modified FROM rms_payment_transaction ORDER BY transaction_id DESC LIMIT 1;
SELECT charge_id, method, status, paid_at FROM rms_jow_charge ORDER BY charge_id DESC LIMIT 1;
SELECT gateway_event_id, provider_event_id, event_type, result_code, status, received_at, processed_at
FROM rms_payment_gateway_event ORDER BY gateway_event_id DESC LIMIT 3;
```

**Esperado:** tentativa `paid` com `provider_payment_id` preenchido; `jow_charge.status = 'paid'` e `paid_at` preenchido (é o P0-a: até o `03a69212` o `UPDATE` filtrava `method = 'mp'` fixo, então cobrança paga por Stripe ou PayPal nunca saía de `pending`); evento com `result_code = 'applied'`, `status = 'processed'`, `processed_at` preenchido.

**Se `jow_charge` não mudou e a tentativa está `paid`:** o filtro por provedor não casou — confira `jow_charge.method` (tem que ser exatamente o código do provedor, `mp`) e veja §11.

**Se a tabela de eventos está vazia:** a notificação não chegou (URL/evento errado) ou foi descartada por assinatura. O log do catálogo diz qual dos dois. **Não** conclua "webhook não funciona" sem olhar `payment_gateway_event` primeiro.

---

## 5. Teste C — notificação repetida não aplica duas vezes

Reenvie a mesma notificação (botão de reenvio no painel do MP, ou o mesmo aviso).

**Esperado:** continua **uma** linha em `payment_gateway_event` para aquele estado, o status não regride e `jow_charge.paid_at` não muda.

Detalhe que explica o comportamento: no Mercado Pago o `provider_event_id` é derivado do **estado** da ordem (`mp-order-<hash>`). Reenvio do mesmo estado gera o mesmo id e é descartado pelo `UNIQUE(gateway_id, provider_event_id)`; **mudança de estado gera id novo** e é aplicada — é assim que `pending → paid` entra e `paid → paid` não.

---

## 6. Teste D — pagamento recusado

Nova cobrança, pague com o cartão de status `OTHE`.

**Esperado:** tentativa `failed`; `jow_charge.status = 'canceled'` — **com uma consoante**: o vocabulário legado da cobrança escreve `canceled`, o ledger escreve `cancelled` (Dicionário §9). Depois disso, nova notificação de aprovação não deve ser aceita (`unsafe_status_transition`) — `failed` é estado final.

---

## 7. Teste E — o webhook se perde e a reconciliação resolve

Este é o teste que justifica o P0-b.

1. **Desligue o webhook** no painel do MP (apague a URL ou aponte para um host que não existe).
2. Crie uma cobrança nova e **pague com `APRO`**.
3. Confira que nada foi aplicado: tentativa `pending`, `jow_charge` `pending`, nenhuma linha nova em `payment_gateway_event`.
4. Rode o cron pedindo 1 minuto de idade mínima (o padrão de 15 min existe para não competir com o webhook que ainda vai chegar):

```bash
curl -sS -H "Authorization: Bearer $REAMUR_RECONCILE_TOKEN" \
  "https://SEU-SITE/catalog/index.php?route=cron/reconcile&format=json&min_age=1&limit=50"
```

**Esperado no JSON:** `scanned >= 1`, `applied = 1`, e no detalhe `status_before: "pending" → status_after: "paid"`; `jow_charge` vira `paid` e uma linha nova aparece em `payment_gateway_event` com `result_code='applied'`.

> **Desde a ADR 0123 o cron faz duas varreduras** por conta: a **aberta** (`min_age`/`lookback`, padrão 7
> dias) e a de tentativas **já pagas** (`settled_lookback`, padrão 30 dias), onde se procura estorno. O
> `scanned` do relatório soma as duas. Para este teste o que importa é a transição `pending → paid`; a
> segunda varredura pode trazer linhas `unchanged` de tentativas pagas de testes anteriores — não é erro.
> Se quiser silêncio absoluto, rode com `settled_lookback=1`.

5. Rode **de novo**: agora `applied = 0` e `unchanged = 1` (nada a fazer). Se vier `applied = 1` de novo, o ledger não está sendo lido como fonte da verdade — pare e me avise.
6. **Religue o webhook** no painel do MP.

> Use `min_age=1` **só no teste**. Em produção o padrão (15 min) é o que evita chamada dupla ao provedor para um pagamento que acabou de acontecer.

---

## 8. Teste F — guardas da rota do cron

| Chamada | Esperado |
| --- | --- |
| sem token | `403 forbidden` (e **nenhuma** query ao banco) |
| token com 31 caracteres | `403 forbidden` |
| token errado | `403 forbidden` |
| `limit=0`, `limit=201`, `min_age=0`, `lookback=0`, `settled_lookback=0`, `settled_lookback=181` | `400` (`invalid parameters`) |
| `format=text` | relatório legível, uma linha por tentativa |
| `format=json` | JSON com `scanned/applied/unchanged/skipped/retry/failed/details` |
| provedor fora do ar | `503` + `Retry-After: 60` |
| tabelas do núcleo ausentes | `503` com `schema_missing` |

Há um script pronto no pacote (`cron-testes.sh`) que roda as seis primeiras e imprime o código HTTP de cada uma.

---

## 9. Teste G (opcional) — Stripe, PayPal e a âncora consultável

**Sem a migração** (`provider_checkout_id` ausente):
- crie uma cobrança com provedor **Stripe** e rode o cron → no detalhe deve aparecer `skipped` com `missing_provider_anchor`. Isso é o comportamento **correto** e declarado, não um defeito.

**Com a migração aplicada:**
- Stripe: crie a cobrança, pague com `4242 4242 4242 4242` (qualquer validade futura) → `provider_checkout_id` passa a ser preenchido (`cs_test_…`) e a reconciliação deixa de pular essas tentativas. Cartão de recusa: `4000 0000 0000 0002`.
- PayPal: use a conta **pessoal de sandbox** (sem cartão). Pelo caminho normal (aviso assinado), a aprovação é **capturada** e a tentativa vira `paid`. O caso que o teste olha é o do aviso perdido: a ordem fica `authorized` e **continua** `authorized` depois do cron — a reconciliação é read-only por contrato e não captura (ADR 0118). Se o cron capturar, é bug; se a captura do webhook não acontecer, o relatório aponta `authorized`.
- PayPal, com a ADR 0125: quando a ordem está **paga** e a resposta **não** traz o bloco de capturas, o cron
  segue a link `up` e lê a **captura** (`GET /v2/payments/captures/{id}`) para ver o estado de estorno. Isso
  é uma requisição a mais, só nesse caso. Ordem com capturas embutidas não gasta nada.

---

## 10. Conferência consolidada

```sql
-- 1) o bug do P0-a em uma query: tentativa paga com a cobrança ainda em aberto
SELECT t.transaction_id, t.provider_code, t.status AS ledger,
       c.charge_id, c.method, c.status AS cobranca, c.paid_at
FROM rms_payment_transaction t
JOIN rms_jow_charge c ON c.charge_id = t.source_id
WHERE t.source_type = 'jow_charge' AND t.status = 'paid' AND c.status <> 'paid';

-- 2) o que o cron enxerga como fila aberta (mesma janela do serviço)
SELECT provider_code, status, COUNT(*) AS total, MIN(date_modified) AS mais_antiga
FROM rms_payment_transaction
WHERE status IN ('pending','requires_action','authorized')
  AND provider_code <> ''
GROUP BY provider_code, status;

-- 3) avisos recebidos × aplicados
SELECT result_code, status, COUNT(*) FROM rms_payment_gateway_event GROUP BY result_code, status;
```

A consulta **1** tem que voltar vazia depois dos testes.

```sql
-- 4) desde a ADR 0123: o que a varredura de PAGAS vai consultar (janela de 30 dias)
SELECT provider_code, status, COUNT(*) AS total, MIN(date_modified) AS mais_antiga
FROM rms_payment_transaction
WHERE status IN ('paid','partially_refunded')
  AND date_added >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 30 DAY)
GROUP BY provider_code, status;
```

A consulta **4** é o que explica um `scanned` maior do que o número de tentativas que você criou: a
varredura de pagas entra nela. Depois do teste **N**, a tentativa estornada aparece como
`status = 'refunded'` e sai dessa lista.

---

## 11. Interpretando o `reason` do relatório

| `reason` | Significa | O que fazer |
| --- | --- | --- |
| `missing_provider_anchor` | Stripe/PayPal sem id consultável (tentativa anterior à migração) | esperado; aplicar a migração resolve só para tentativas novas |
| `provider_unavailable` | provedor respondeu 5xx/timeout | o cron devolve 503 e pede retry; investigar o provedor |
| `credentials_missing` | campo vazio, ou segredo que não abre (chave `REAMUR_SECRET_KEY` trocada) | reconfigurar as credenciais da conta |
| `signature_rejected` | segredo do webhook ≠ o do painel do provedor | recolar a chave secreta |
| `not_order_event` | o webhook está inscrito em evento errado (ex.: "Pagamentos" em vez de "Ordens") | corrigir a inscrição |
| `reference_mismatch` | o provedor devolveu outra referência | não é esperado; guardar o relatório e abrir investigação |
| `amount_mismatch` | valor do provedor ≠ `charge_amount` gravado | conferir taxa/câmbio; **nunca** "ajustar" o valor no banco |
| `unsafe_status_transition` | notificação atrasada tentando voltar estado | esperado; ignorar |
| `payment_not_found` | evento de tentativa que não é desta instalação | conferir `gateway_id` na URL do webhook |
| `provider_not_reconcilable` | adapter sem `reconcile()` | só aparece se um adapter novo esquecer o contrato |
| `charge_not_reconciled` | a cobrança devolvida pelo Stripe não é a que se pediu | não é esperado; guardar o relatório (ADR 0124) |
| `charge_not_refunded` | Stripe: cobrança lida sem estorno | interno; a varredura devolve o estado da sessão, este código não aparece no relatório |
| `refund_amount_exceeds_charge` | Stripe: `amount_refunded` > `amount` | **não é esperado**; é sinal de leitura errada do provedor. Pare |
| `capture_not_reconciled` | PayPal: a captura devolvida não é a que se pediu | não é esperado (ADR 0125) |
| `unknown_capture_status` | PayPal: captura com estado que o código não conhece | vocabulário novo no provedor; atualizar o adapter |
| `capture_without_order` | PayPal: aviso de captura sem vínculo com ordem | evento fora do nosso fluxo; ignorar |
| `order_id_mismatch` / `order_not_reconciled` | MP/PayPal: ordem devolvida ≠ pedida, ou moeda fora de BRL/USD/EUR | não é esperado; guardar o relatório |
| `verification_inconclusive` | PayPal: a verificação de assinatura não respondeu SUCCESS | provedor instável; o aviso é reenviado |
| `unhandled_event_type` | aviso de tipo que não tratamos | esperado (o provedor manda outros tipos) |
| `invalid_provider_amount` | valor do provedor ilegível no formato esperado | investigar; nunca "ajustar" valor no banco |

---

## 12. Critérios de aceite

- [ ] Teste A: tentativa criada com `provider_reference` e link de checkout;
- [ ] Teste B: webhook aplica `paid` no ledger **e** sincroniza `jow_charge` (`paid_at` preenchido);
- [ ] Teste C: reenvio não duplica evento nem regride status;
- [ ] Teste D: recusa vira `failed`/`canceled` e não é revertida;
- [ ] Teste E: cron recupera pagamento com webhook perdido (`applied=1`) e é idempotente na segunda execução;
- [ ] Teste F: 403/400/200 e mensagem de erro conferem;
- [ ] Teste N: estorno feito no painel do provedor sai de `paid` para `refunded`/`partially_refunded` **pelo cron**, sem `payment_gateway_event` nova, e a segunda execução dá `applied=0`;
- [ ] §10 consulta 1 volta vazia;
- [ ] (se feito) §9: `missing_provider_anchor` antes da migração e reconciliação depois; PayPal continua `authorized`;
- [ ] conta de sandbox **desligada** ao fim (`UPDATE rms_jow_gateway SET status = 0 WHERE environment = 'sandbox';`), para o cron não bater no provedor de teste à toa.

## 13. Se algo falhar — triagem em quatro passos

1. **A tentativa existe?** Se não, o problema é a criação (credencial do provedor, ou a regra `@testuser.com`).
2. **O evento chegou?** `payment_gateway_event` diz: vazio = notificação; linha com `status=received` e nunca `processed` = aplicação falhou.
3. **O log do catálogo** diz o motivo (`JOW platform gateway webhook failed: …`) — é lá que a exceção aparece, o corpo da resposta do webhook é sempre `ok`/`retry` de propósito.
4. **O cron vê a tentativa?** `reason` do relatório (§11) aponta o caminho. Se o cron não varre nada, confira `min_age` (padrão 15 min) e `lookback` (padrão 7 dias).

## 14. Limpeza depois do teste

- Desligar a conta de sandbox (§12).
- Apagar as tentativas de teste é **opcional** e não recomendado: elas são o registro do que aconteceu. Se for apagar, apague por `transaction_id` específico, nunca por `provider_code`:

```sql
-- exemplo comentado; conferir os ids antes
-- DELETE FROM rms_payment_transaction WHERE transaction_id IN (…);
-- DELETE FROM rms_payment_gateway_event WHERE gateway_id = … AND received_at < '2026-11-21';
```

- Nenhuma credencial de teste no repositório, no issue ou no `CHANGELOG`.
