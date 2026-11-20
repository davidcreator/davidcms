# Webhooks — ReamurCMS

> **Objetivo:** documentar o sistema de **webhooks** do ReamurCMS (engine + controllers de pedidos e produtos), como criar novos gatilhos e as práticas de segurança e confiabilidade.

> ⚠️ **ESTADO VIGENTE — F5-01 / ADR 0033 (out/2026).** O dispatcher descrito nas seções 2–4 (`system/webhook/` + tabelas `webhook_client`/`webhook_request_history`) era o **esqueleto da Fase 0 e nunca rodou** (tabelas inexistentes). O sistema NO AR é o do **§0 abaixo**: `Library\Webhook\Dispatcher` + Models `setting/webhook` + tabelas `rms_webhook`/`rms_webhook_log`. As seções seguintes permanecem como referência histórica da Fase 0 — leia o §0 primeiro.

---

## 0. Protocolo no ar (F5-01 / ADR 0033)

**Fluxo:** orçamento criado no catálogo (`order_add`) ou pedido editado no admin (`order_edit`; `order_history_edit` quando o status muda) → controller lê endpoints ativos no Model `setting/webhook` (`getHooksForEvent`, wildcard `*`/`?` em SQL) → `Library\Webhook\Dispatcher` entrega e o Model grava **1 linha em `rms_webhook_log` por tentativa**.

**Envelope JSON (corpo):**
```json
{
  "event":       "order_add",
  "delivery_id": "9f1c2a7e-4b3d-4f8a-9c1e-7a2b5d8f0c34",
  "timestamp":   1729468800,
  "data": { "order_id": 42, "firstname": "Maria", "lastname": "Silva",
            "email": "maria@x.com", "telephone": "...", "comment": "...",
            "total": 0.0, "status_id": 1, "items": [{"name":"Logo","quantity":1,"price":0}] }
}
```

**Headers:** `Content-Type: application/json` · `User-Agent: ReamurCMS-Webhook/1.0` · `X-Reamur-Event` · `X-Reamur-Delivery` (uuid **estável nos retries** — deduplique por ele) · `X-Reamur-Signature: sha256=<HMAC>`.

**Verificação HMAC no receptor (PHP):**
```php
$esperado = 'sha256=' . hash_hmac('sha256', $corpoCru, $secretCadastrado);
if (!hash_equals($esperado, $_SERVER['HTTP_X_REAMUR_SIGNATURE'] ?? '')) { http_response_code(401); exit; }
```

**Retries:** até **3 tentativas síncronas** com backoff 0s/2s/4s; sucesso = HTTP 2xx. `attempt` crescente no log, mesmo `delivery`. Endpoint esgotado com falha vira linha no `error_log` — e **jamais** impede o orçamento/pedido de ser criado (guarda `try/catch (\Throwable)` nos callers).

**Cadastro:** admin `Configurações → Webhooks` (F2-16): URL **somente https://**, secret 16–64 chars, trigger com wildcard (`order_add`, `order_*`, `*_add`), logs read-only por endpoint.

**Segredo cifrado no banco (ADR 0094):** o secret é gravado cifrado (`rmsenc:v1:…`) e aberto só na hora de assinar o envio; quem recebe continua conferindo com o secret que foi digitado. Ao editar um endpoint, campo de secret em branco mantém o atual. Se a chave `REAMUR_SECRET_KEY` do `config.php` for trocada ou perdida, o secret gravado deixa de abrir: o evento **não é enviado** (linha no log de entregas com código 0), a tela do endpoint avisa e pede um secret novo. Cadastre sempre pela tela: um `INSERT` direto no banco grava o secret legível, e ele só passa a ser cifrado em *Configurações → Proteger segredos*.

---



## Sumário

1. [Visão geral](#1-visão-geral)
2. [A engine do CMS — `Event`](#2-a-engine--hoje-é-o-event)
3. [Estrutura de pastas](#3-estrutura-de-pastas)
4. [Como um evento é disparado](#4-como-um-evento-é-disparado)
5. [Segurança](#5-segurança)
6. [Criando um novo webhook](#6-criando-um-novo-webhook)
7. [Confiabilidade: retries, filas e logs](#7-confiabilidade-retries-filas-e-logs)
8. [Testando webhooks](#8-testando-webhooks)
9. [Roadmap do módulo](#9-roadmap-do-módulo)

---

## 1. Visão geral

Webhooks são **notificações HTTP** que o ReamurCMS dispara para sistemas externos quando algo acontece no CMS — por exemplo:

| Evento | Caso de uso |
| --- | --- |
| `order_add` | Novo pedido/orçamento → notificar WhatsApp/CRM/planilha |
| `order_edit` | Status alterado → e-mail automático para o cliente |
| `product_add` / `product_edit` | Novo projeto no portfólio → invalidar cache CDN, post no Instagram |
| `customer_add` *(planejado)* | Novo cadastro → automação de marketing |

O sistema tem duas metades:

1. **Engine** (`Reamur\System\Engine\Webhook`) — registry de triggers com prioridade e suporte a wildcards.
2. **Transporte de saída** (`dev/system/library/webhook/dispatcher.php`) — entrega HTTP do envelope, com HMAC e retries. A antiga pasta do dispatcher (dev/system/webhook) foi **removida na ADR 0037**; o cadastro de endpoints é a tela `setting/webhook` (Admin).

> Diferença crucial: **Events** agem *dentro* do CMS; **Webhooks** avisam o mundo *lá fora*.

---

## 2. A engine — hoje é o `Event`

> **`system/engine/webhook.php` não existe mais.** A classe `Webhook` (registry em memória, com
> `register/trigger/unregister`) foi substituída pelo **`Event`** do núcleo, e a pasta
> A antiga pasta do dispatcher (dev/system/webhook) foi removida na ADR 0037. Nenhum código registrava naquela classe.

O registry real é `Reamur\System\Engine\Event` (`dev/system/engine/event.php`):

| Método | Assinatura | O que faz |
| --- | --- | --- |
| `register()` | `register(string $trigger, callable $action, int $priority = 0): void` | registra uma ação para um evento, com prioridade |
| `has()` | `has(string $trigger): bool` | diz se há ação registrada |
| `trigger()` | `trigger(string $trigger, array &$args = []): void` | executa as ações do evento |

Isso é o **evento interno** do CMS. O webhook de saída (avisar um sistema de fora) é outra coisa e está
descrito em §3: `Library\Webhook\Dispatcher`, chamado por `dispatchWebhookEvent()`.

---

## 3. Estrutura de pastas

```text
dev/system/library/webhook/
└── dispatcher.php          # transporte: envelope JSON, HMAC, 3 tentativas
```

**A pasta dev/system/webhook/ foi removida na ADR 0037.** Ela continha controllers no estilo ponte
legado que consultavam as tabelas `webhook_client` e `webhook_request_history` — **nenhuma das duas
existe no schema**. Era código que nunca rodou.

| Peça | Onde está hoje | Papel |
| --- | --- | --- |
| cadastro de endpoints | `dev/admin/controller/setting/webhook.php` + model | CRUD de `rms_webhook`, log em `rms_webhook_log` |
| disparo | `dispatchWebhookEvent()` em `dev/system/engine/controller.php` | monta o payload sob demanda e chama o dispatcher; **falha de webhook nunca derruba o fluxo** que a originou |
| transporte | `dev/system/library/webhook/dispatcher.php` | `POST`, `X-Reamur-Signature` (HMAC do corpo), 3 tentativas síncronas |
| eventos que disparam | ex.: `order_add` em `dev/catalog/controller/checkout/quote.php` | o ponto de chamada é o próprio controller do fluxo |

---

## 4. Como um evento é disparado

Não existe controller de webhook. O disparo é uma chamada no ponto do fluxo que gera o evento:

```php
// dev/catalog/controller/checkout/quote.php — depois de gravar o pedido
$this->dispatchWebhookEvent('order_add', function () use ($order_id): array {
    return $this->webhookOrderPayload((int)$order_id);
}, 'pedido #' . (int)$order_id);
```

`dispatchWebhookEvent()` (em `dev/system/engine/controller.php`) faz três coisas, nesta ordem:

1. monta o payload **só se houver endpoint interessado** (o `Closure` existe para isso: sem interessado,
   nenhuma consulta extra ao banco);
2. entrega pelo `Dispatcher`, que assina e tenta até 3 vezes;
3. isola a falha: exceção de webhook vira linha de log e o fluxo de pedido continua.

Também dispara os **eventos internos** do CMS (engine `Event`), que são coisa diferente: eventos agem
dentro do CMS, webhooks avisam o mundo lá fora.

---

## 5. Segurança

1. **HTTPS obrigatório** nas URLs receptoras (payload trafega ID de pedido).
2. **Assinatura HMAC** por endpoint — o receptor valida que o payload veio do ReamurCMS:

```php
// no dispatcher (ao enviar)
$signature = hash_hmac('sha256', json_encode($data), $hook['secret']);

$client = new \GuzzleHttp\Client(['timeout' => 5]);
$client->post($hook['url'], [
    'json'    => $data,
    'headers' => [
        'X-Reamur-Event'     => $event,
        'X-Reamur-Signature' => $signature,
        'X-Reamur-Delivery'  => $deliveryId,   // idempotência
    ],
]);
```

```php
// no receptor (exemplo)
$valid = hash_equals(
    hash_hmac('sha256', $body, $meuSecret),
    $_SERVER['HTTP_X_REAMUR_SIGNATURE'] ?? ''
);
```

3. **Falhar em silêncio controlado:** exceções vão para `storage/logs/error.log` — nunca expor URL/secret em tela.
4. **Bloqueio de IP** para a API interna: `system/config/api/settings/ip_blocked.php`.
5. **Timeout curto (5s)** e limite de payload nos envios.
6. **Validação de URL** ao cadastrar hook (somente `https://`, sem IPs privados em produção — anti-SSRF). No envio, a URL é conferida de novo com DNS e a conexão usa só os endereços conferidos (ADR 0090): um webhook para um nome que resolve para dentro é salvo, mas cada envio é recusado e registrado em log.

---

## 6. Criando um novo webhook

Não se cria controller. Duas coisas, e só:

**Passo 1 — disparar o evento no ponto do fluxo.**

```php
// no controller do fluxo, depois de concluir a ação
$this->dispatchWebhookEvent('order_delete', ['order_id' => (int)$order_id], 'pedido #' . (int)$order_id);
```

O nome do evento é livre, mas por convenção segue `<entidade>_<ação>` (`order_add`, `order_edit`,
`order_delete`), porque o cadastro do endpoint usa **wildcard**: `order_*` casa com os três.

**Passo 2 — cadastrar o endpoint na tela.** Admin → `setting/webhook` → *Adicionar*: URL (só HTTPS, sem IP
privado — ADR 0090 confere de novo no envio), segredo (cifrado, ADR 0094) e o trigger com wildcard.

O receptor recebe o envelope de §1 e deve tratar `X-Reamur-Delivery` como chave de idempotência: a mesma
entrega pode chegar mais de uma vez quando há retry.

---

## 7. Confiabilidade: retries, filas e logs

Webhooks HTTP falham (timeout, 5xx). Planejado para a Fase 5:

| Mecanismo | Implementação sugerida |
| --- | --- |
| **Log de entregas** | tabela `rms_webhook_log` (delivery_id, hook, payload, http_code, duration, tentativa) |
| **Retry com backoff** | re-tentar 3× (1 min → 5 min → 30 min) via `catalog/controller/cron/` |
| **Fila** | disparo síncrono hoje → mover para fila (DB/Redis) cron + worker |
| **Dead letter** | após 3 falhas consecutivas, hook fica `status = 0` e admin é notificado |
| **Idempotência** | cabeçalho `X-Reamur-Delivery` único; receptor deduplica |

---

## 8. Testando webhooks

```bash
# 1. Receptor fake local
php -S localhost:9000 receiver.php   # script que apenas loga body+headers

# 2. Disparo manual via CLI (exemplo)
php -r "
  \$data = ['order_id' => 1, 'action' => 'order_add', 'timestamp' => time()];
  \$sig  = hash_hmac('sha256', json_encode(\$data), 'SEGREDO_FORTE_AQUI');
  echo \$sig, PHP_EOL;
"

# 3. Simular chamada com curl
curl -X POST https://seu-receptor/hook \
  -H "Content-Type: application/json" \
  -H "X-Reamur-Event: order_add" \
  -H "X-Reamur-Signature: <hmac>" \
  -d '{"order_id":1,"action":"order_add","timestamp":1760461200}'
```

Ferramentas úteis: [webhook.site](https://webhook.site) e [ngrok](https://ngrok.com) para expor localhost.

---

## 9. Roadmap do módulo

- [x] Engine `Webhook` com wildcards + prioridade *(commit `feat(webhook)`)*
- [x] Controllers `sale/order` (`add`, `edit`) e `catalog/product`
- [ ] `dispatchRequests()` com Guzzle + HMAC + timeout
- [ ] Tabela `rms_webhook` + CRUD no admin (`admin/controller/marketing` ou `setting`)
- [ ] Log de entregas + retries com backoff via cron
- [ ] Novos triggers: `order_delete`, `customer_add`, `contact_add` (formulário de contato!)
- [ ] Enriquecimento de payload + `event_id` de idempotência

---

## Veja também

- [Arquitetura MVCL §10](../arquitetura/Arquitetura-MVCL.md#10-events-e-webhooks) — Events × Webhooks
- [Guia Composer §3](../guias/Guia-Composer.md#3-dependências) — Guzzle (cliente HTTP do dispatcher)
- [Guia CMS §8](../guias/Guia-CMS.md#8-segurança) — hardening geral
- [Análise §8](../guias/Analise-DavidAlmeida.md#8-plano-de-migração) — Fase 5 (Integrações)
