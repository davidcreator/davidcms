# Webhooks — ReamurCMS

> **Objetivo:** documentar o sistema de **webhooks** do ReamurCMS (engine + controllers de pedidos e produtos), como criar novos gatilhos e as práticas de segurança e confiabilidade.

---

## Sumário

1. [Visão geral](#1-visão-geral)
2. [A engine (`system/engine/webhook.php`)](#2-a-engine-systemenginewebhookphp)
3. [Estrutura de pastas](#3-estrutura-de-pastas)
4. [Controllers existentes](#4-controllers-existentes)
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
2. **Módulo dispatcher** (`dev/system/webhook/`) — controllers que consultam os hooks cadastrados (banco) e despacham as requisições HTTP.

> Diferença crucial: **Events** agem *dentro* do CMS; **Webhooks** avisam o mundo *lá fora*.

---

## 2. A engine (`system/engine/webhook.php`)

Implementação real do repositório (trechos comentados):

```php
namespace Reamur\System\Engine;

class Webhook {
    protected $registry;
    protected array $data = [];

    public function __construct(object $registry) {
        $this->registry = $registry;
    }

    /** Registra um webhook (trigger, ação, prioridade) */
    public function register(string $trigger, object $action, int $priority = 0): void {
        $this->data[] = [
            'trigger'  => $trigger,
            'action'   => $action,
            'priority' => $priority
        ];

        // Menor prioridade executa primeiro
        usort($this->data, fn($a, $b) => $a['priority'] <=> $b['priority']); // arrow fn (PHP 8)
    }

    /** Dispara um webhook pelo nome do evento (suporta * e ?) */
    public function trigger(string $event, array $args = []): mixed {
        foreach ($this->data as $value) {
            if (preg_match('/^' . str_replace(['\*', '\?'], ['.*', '.'], preg_quote($value['trigger'], '/')) . '/', $event)) {
                $result = $value['action']->execute($this->registry, $args);

                // Primeiro resultado válido (não-nulo, não-Exception) interrompe a cadeia
                if ($result !== null && !($result instanceof \Exception)) {
                    return $result;
                }
            }
        }

        return null;
    }

    /** Remove um webhook específico pela rota da ação */
    public function unregister(string $trigger, string $route): void { ... }
}
```

### API de referência

| Método | Assinatura | Descrição |
| --- | --- | --- |
| `register()` | `register(string $trigger, object $action, int $priority = 0): void` | Associa um trigger (com wildcards `*`/`?`) a uma Action, ordenada por prioridade |
| `trigger()` | `trigger(string $event, array $args = []): mixed` | Executa as ações cujo trigger casa com o evento; retorna o primeiro resultado válido |
| `unregister()` | `unregister(string $trigger, string $route): void` | Remove o registro de um hook específico |

**Wildcards:** `order_*` casa com `order_add`, `order_edit`; `order_?dd` casa com `order_add`.

---

## 3. Estrutura de pastas

```text
dev/system/webhook/
├── controller/
│   ├── catalog/
│   │   └── product.php        # triggers de produto/portfólio
│   └── sale/
│       └── order.php          # triggers de pedido/orçamento
└── model/
    └── advanced/
        └── webhook.php        # consulta os hooks cadastrados (tabela `webhook`)
```

Os controllers seguem o estilo ponte legado OpenCart (`ControllerWebhookSaleOrder extends Controller`) e usam `$this->load->modelWebhook()` para carregar o model `advanced/webhook`.

---

## 4. Controllers existentes

### 4.1 `sale/order.php` — pedidos

```php
class ControllerWebhookSaleOrder extends Controller {

    // Chamado quando um pedido é CRIADO
    public function add($router, $args, $order_id) {
        try {
            $this->load->modelWebhook('advanced/webhook');
            $hooks = $this->model_webhook_advanced_webhook->getHooks('order_add');

            $data = [
                'order_id'  => (int)$order_id,
                'action'    => 'order_add',
                'timestamp' => time()
            ];

            $this->dispatchRequests('order_add', $hooks, $data);
        } catch (\Exception $e) {
            $this->log->write('Webhook Error (add): ' . $e->getMessage());
        }
    }

    // Chamado quando um pedido é EDITADO
    public function edit($router, $args) {
        try {
            $order_id = (int)$args[0];
            $this->load->modelWebhook('advanced/webhook');
            $hooks = $this->model_webhook_advanced_webhook->getHooks('order_edit');

            $data = [
                'order_id'  => $order_id,
                'action'    => 'order_edit',
                'timestamp' => time()
            ];

            $this->dispatchRequests('order_edit', $hooks, $data);
        } catch (\Exception $e) {
            $this->log->write('Webhook Error (edit): ' . $e->getMessage());
        }
    }
}
```

### Payload padrão

```json
{
  "order_id": 123,
  "action": "order_add",
  "timestamp": 1760461200
}
```

> **Recomendação (Fase 5):** enriquecer o payload com dados públicos do pedido (status, total, itens resumidos) — nunca dados sensíveis (cartão, senha, endereço completo) — e adicionar `event_id` único para idempotência no receptor.

### 4.2 Tabela de hooks (sugerida)

O model `advanced/webhook` lê os hooks de uma tabela própria. Schema sugerido para o instalador:

```sql
CREATE TABLE `rms_webhook` (
  `webhook_id`  INT AUTO_INCREMENT PRIMARY KEY,
  `trigger`     VARCHAR(64)  NOT NULL,           -- order_add, order_edit, product_*
  `url`         VARCHAR(255) NOT NULL,           -- endpoint receptor (HTTPS)
  `secret`      VARCHAR(64)  NOT NULL,           -- segredo HMAC por endpoint
  `description` VARCHAR(255) NOT NULL DEFAULT '',
  `status`      TINYINT(1)   NOT NULL DEFAULT 1, -- 1 = ativo
  `date_added`  DATETIME     NOT NULL,
  KEY `trigger_status` (`trigger`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

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
6. **Validação de URL** ao cadastrar hook (somente `https://`, sem IPs privados em produção — anti-SSRF).

---

## 6. Criando um novo webhook

Exemplo completo: **`order_delete`** (pedido excluído → avisar o financeiro — Fluxa!).

**Passo 1 — Controller** (`dev/system/webhook/controller/sale/order.php`):

```php
public function delete($router, $args) {
    try {
        $order_id = (int)$args[0];

        $this->load->modelWebhook('advanced/webhook');
        $hooks = $this->model_webhook_advanced_webhook->getHooks('order_delete');

        $data = [
            'order_id'  => $order_id,
            'action'    => 'order_delete',
            'timestamp' => time()
        ];

        $this->dispatchRequests('order_delete', $hooks, $data);
    } catch (\Exception $e) {
        $this->log->write('Webhook Error (delete): ' . $e->getMessage());
    }
}
```

**Passo 2 — Amarrar ao evento do model de pedidos** (registro no admin, conceito `admin/model/sale/order/deleteOrder/after`):

```php
// admin/controller/startup/ (ou via admin → Design > Eventos)
$this->event->register(
    'admin/model/sale/order/deleteOrder/after',
    new \Reamur\System\Engine\Action('webhook/sale/order.delete')
);
```

**Passo 3 — Cadastrar o hook receptor** no banco:

```sql
INSERT INTO `rms_webhook` (`trigger`, `url`, `secret`, `description`, `status`)
VALUES ('order_delete', 'https://fluxa.exemplo.com/hooks/reamur', 'SEGREDO_FORTE_AQUI', 'Fluxa — baixa financeira', 1);
```

**Pronto:** ao excluir um pedido, o dispatcher assina o payload com HMAC e faz POST para a URL cadastrada.

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

- [Arquitetura MVCL §10](Arquitetura-MVCL.md#10-events-e-webhooks) — Events × Webhooks
- [Guia Composer §3](Guia-Composer.md#3-dependências) — Guzzle (cliente HTTP do dispatcher)
- [Guia CMS §8](Guia-CMS.md#8-segurança) — hardening geral
- [Análise §8](Analise-DavidAlmeida.md#8-plano-de-migração) — Fase 5 (Integrações)
