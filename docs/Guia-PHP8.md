# Guia PHP 8+ — ReamurCMS

> **Objetivo:** documentar como o ReamurCMS usa o PHP 8+ no núcleo, quais recursos da linguagem adotar (e evitar), padrões de segurança e performance — referência para todo código novo do sistema.

---

## Sumário

1. [Versão e configuração](#1-versão-e-configuração)
2. [Recursos do PHP 8 já usados no núcleo](#2-recursos-do-php-8-já-usados-no-núcleo)
3. [Recursos recomendados para código novo](#3-recursos-recomendados-para-código-novo)
4. [Padrões de código](#4-padrões-de-código)
5. [Tratamento de erros e logs](#5-tratamento-de-erros-e-logs)
6. [Segurança em PHP](#6-segurança-em-php)
7. [Performance](#7-performance)
8. [Documentação de API com phpDocumentor](#8-documentação-de-api-com-phpdocumentor)
9. [Extensões necessárias](#9-extensões-necessárias)

---

## 1. Versão e configuração

O `composer.json` exige:

```json
"require": {
    "php": ">=8.0.0"
}
```

| Ambiente | Versão recomendada |
| --- | --- |
| Desenvolvimento | PHP 8.2+ (paridade com produção) |
| Produção | PHP 8.2 ou 8.3 (suporte ativo e JIT estável) |

`php.ini` sugerido (produção):

```ini
memory_limit = 256M
upload_max_filesize = 32M
post_max_size = 33M
max_execution_time = 60
display_errors = Off            ; o ReamurCMS controla via error_display
log_errors = On
error_log = {caminho}/dev/system/storage/logs/php_error.log
expose_php = Off
session.cookie_httponly = 1
session.cookie_secure = 1       ; sob HTTPS
session.cookie_samesite = Lax   ; padrão do CMS (RMSSESSION)

opcache.enable = 1
opcache.memory_consumption = 128
opcache.interned_strings_buffer = 16
opcache.max_accelerated_files = 20000
opcache.validate_timestamps = 0 ; em produção (deploy invalida o cache)
```

---

## 2. Recursos do PHP 8 já usados no núcleo

Estes recursos **já estão presentes** no código do repositório — o padrão é mantê-los:

### 2.1 Propriedades tipadas

```php
// system/engine/webhook.php
protected array $data = [];

// system/library/template/twig.php
protected string $root;
protected string $directory;
protected array $path = [];
```

### 2.2 Tipos de retorno `mixed` e `void`

```php
// system/engine/webhook.php
public function register(string $trigger, object $action, int $priority = 0): void
public function trigger(string $event, array $args = []): mixed
```

### 2.3 Arrow functions (`fn`)

```php
// Ordenação de webhooks por prioridade
usort($this->data, fn($a, $b) => $a['priority'] <=> $b['priority']);
```

### 2.4 Funções de string do PHP 8

```php
// system/library/template/twig.php
if (str_starts_with($path, $this->root . '/')) {
    return substr($path, strlen($this->root) + 1);
}
```

Use também `str_contains()` e `str_ends_with()` em vez de `strpos(...) !== false`.

### 2.5 Union types / `Throwable`

```php
// system/framework.php
set_exception_handler(function (\Throwable $e) use ($log, $config) { ... });
```

---

## 3. Recursos recomendados para código novo

### 3.1 `match` em vez de `switch` de mapeamento

```php
// Melhor que o switch do framework.php para classificar erros:
$label = match (true) {
    in_array($code, [E_NOTICE, E_USER_NOTICE], true)     => 'Notice',
    in_array($code, [E_WARNING, E_USER_WARNING], true)   => 'Warning',
    in_array($code, [E_ERROR, E_USER_ERROR], true)       => 'Fatal Error',
    default                                              => 'Unknown',
};
```

### 3.2 Operador nullsafe `?->`

```php
$email = $this->customer?->getEmail();
```

### 3.3 Null coalescing `??` (validação de entrada)

```php
$id    = (int)($this->request->get['id'] ?? 0);
$route = (string)($this->request->get['route'] ?? 'common/home');
```

### 3.4 Named arguments (clareza em APIs com muitos opcionais)

```php
$image->resize(width: 800, height: 600, default: 'w');
```

### 3.5 Promoção de propriedades no construtor (8.0+) e `readonly` (8.1+)

```php
final class SeoUrl {
    public function __construct(
        private readonly string $keyword,
        private readonly string $route,
    ) {}
}
```

> Use `readonly` em **value objects** e DTOs (ex.: dados de disparo de webhook). Não use em Models mutáveis.

### 3.6 Enums (8.1+) para status de domínio

```php
enum OrderStatus: int {
    case Pending   = 0;
    case Paid      = 2;
    case Shipped   = 3;
    case Canceled  = 7;
}
```

Ideal para `sale/order`, status de webhook, status de conteúdo (rascunho/publicado).

### 3.7 First-class callable syntax (8.1+)

```php
$handler = $this->process(...); // em vez de fn(...$a) => $this->process(...$a)
```

---

## 4. Padrões de código

| Regra | Padrão do projeto |
| --- | --- |
| Estilo | PSR-12 (indentação 4 espaços ou tab consistente com o arquivo — o núcleo usa ambos; **novos arquivos: 4 espaços**) |
| Tipagem | Declarar tipo em *toda* propriedade, parâmetro e retorno possível |
| Namespaces | `Reamur\{App}\{Camada}\{Grupo}` — ver [Arquitetura MVCL](Arquitetura-MVCL.md#11-convenções-de-nomenclatura) |
| Classes | Um nome = uma responsabilidade; controllers magros, regra de negócio no Model |
| Strings | Aspas simples por padrão; duplas só com interpolação |
| Idioma do código | Variáveis/métodos em inglês; comentários e docblocks em pt-BR (como já ocorre no núcleo) |
| PHPDoc | Obrigatório em classes e métodos públicos (alimenta o phpDocumentor) |

Cabeçalho de arquivo padrão (já em uso):

```php
/**
 * @package   ReamurCMS
 * @author    David L. Almeida
 * @copyright Copyright (c) 2025, ReamurCMS (https://reamurcms.com)
 * @license   https://opensource.org/licenses/GPL-3.0
 * @link      https://reamurcms.com
 */
```

---

## 5. Tratamento de erros e logs

O `framework.php` define handlers globais — **não criar handlers paralelos**:

```php
set_error_handler(function (string $code, string $message, string $file, string $line) use ($log, $config) {
    // grava em storage/logs/error.log se error_log=true
    // exibe na tela se error_display=true (nunca em produção!)
});

set_exception_handler(function (\Throwable $e) use ($log, $config) { ... });
```

Padrão de uso em controllers (exemplo real do webhook de pedidos):

```php
try {
    $this->load->modelWebhook('advanced/webhook');
    $hooks = $this->model_webhook_advanced_webhook->getHooks('order_add');
    $this->dispatchRequests('order_add', $hooks, $data);
} catch (\Exception $e) {
    // Nunca deixe a exceção estourar pro usuário; registre e siga
    $this->log->write('Webhook Error (add): ' . $e->getMessage());
}
```

Regras:
- **Log, não echo:** em produção `error_display = false`; erros vão para `storage/logs/error.log` e `migration_error.log`.
- **Capturar `\Throwable`** ao redor de integrações externas (HTTP, gateway de pagamento).
- **Falhar fechado:** em erro de segurança (upload, auth), negar por padrão.

---

## 6. Segurança em PHP

| Vetor | Obrigatório no ReamurCMS |
| --- | --- |
| SQL Injection | `$this->db->escape()` em todo dado concatenado; preferir prepared statements no driver `pdo` |
| XSS | Autoescape do Twig sempre ligado; `htmlspecialchars()` em saídas fora de template |
| CSRF | Token por sessão em formulários (admin e catalog) validado no controller |
| Upload | Somente via `system/library/security/upload_security.php` — validar MIME real, extensão e tamanho; salvar fora de diretório executável |
| Senhas | `password_hash($senha, PASSWORD_DEFAULT)` + `password_verify()` |
| Sessão | Nome `RMSSESSION`, `SameSite=Lax` (config), `secure` em HTTPS, regenerar ID no login |
| Entrada | Cast + whitelist: `(int)` para IDs, enums/arrays permitidos para status |
- Dicas de headers HTTP e HTTPS: [Guia CMS §8](Guia-CMS.md#8-segurança).

---

## 7. Performance

1. **OPcache sempre ligado** (config do §1) — maior ganho gratuito do PHP.
2. **Cache do CMS:** configure `cache_engine` (`redis` > `memcached` > `apcu` > `file`) e `template_cache = true` em produção — compilação Twig em `storage/cache/template/`.
3. **Autoloader otimizado:** `composer dump-autoload --optimize --classmap-authoritative` no deploy (ver [Guia Composer](Guia-Composer.md#4-comandos-do-dia-a-dia)).
4. **Lazy loading nativo:** models sobem via Proxy só quando usados — não faça `$this->load->model()` "por precaução" no topo do controller.
5. **Banco:** índices nas colunas de slug/foreign keys; paginação com `library/pagination.php`; evitar `SELECT *` em listagens.
6. **Sem N+1:** busque coleções relacionadas em JOIN/`IN (...)` no Model, não em loop de queries.
7. **Profile antes de otimizar:** meça com logs de tempo (`microtime`) ou Xdebug em dev.

---

## 8. Documentação de API com phpDocumentor

O phpDocumentor é usado **via PHAR** (não via Composer — ver adendo da [ADR 0004](../agents-ia/decisoes/0004-contratos-qualidade-e-modelos.md): suas dependências têm advisories). Para gerar a documentação técnica das classes do núcleo:

```bash
cd dev/system/storage
# baixar uma vez: https://github.com/phpDocumentor/phpDocumentor/releases
curl -L -o phpDocumentor.phar https://github.com/phpDocumentor/phpDocumentor/releases/latest/download/phpDocumentor.phar
composer docs -- --title="ReamurCMS Core API"
```

Como os docblocks do núcleo já seguem padrão (`@package`, `@param`, `@return`), a saída fica navegável imediatamente. Publicar `docs/api/` fora do Git (já está no `.gitignore`).

---

## 9. Extensões necessárias

| Extensão | Obrigatória? | Uso |
| --- | --- | --- |
| `json` | ✅ | Configs, APIs |
| `mbstring` | ✅ | UTF-8 (`helper/utf8.php`) |
| `openssl` | ✅ | HTTPS, encryption, HMAC de webhooks |
| `curl` | ✅ | Guzzle, integrações (Mercado Pago, webhooks) |
| `gd` ou `imagick` | ✅ | `library/image.php` |
| `zip` | ✅ | Extensões/marketplace |
| `mysqli` e/ou `pdo_mysql` | ✅ | Drivers `db/mysqli.php`, `db/pdo.php` |
| `pgsql` / `pdo_pgsql` | ⭕ opcional | Driver `db/pgsql.php` |
| `redis` / `memcached` / `apcu` | ⭕ opcional | Cache/session avançados |
| `intl` | ⭕ recomendado | Normalização de slugs e formatação local |
| `xml` / `dom` | ✅ | `modification.xml`, sitemaps, phpDocumentor |

---

## Veja também

- [PHP 8.0 — novidades oficiais](https://www.php.net/releases/8.0/pt_BR.php) · [8.1](https://www.php.net/releases/8.1/pt_BR.php) · [8.2](https://www.php.net/releases/8.2/pt_BR.php)
- [Guia Composer](Guia-Composer.md) — dependências e autoload
- [Arquitetura MVCL](Arquitetura-MVCL.md) — onde este código vive
- [Webhooks](Webhooks.md) — consumo prático de `Throwable`, arrow functions e Guzzle
