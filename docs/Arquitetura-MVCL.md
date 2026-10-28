# Arquitetura MVCL — ReamurCMS

> **Objetivo:** explicar o padrão **MVCL** (Model – View – Controller – Language), como o núcleo `Reamur\*` está organizado e qual o ciclo de vida de uma requisição — a referência central para programar qualquer módulo do sistema.

---

## Sumário

1. [O que é MVCL](#1-o-que-é-mvcl)
2. [As quatro aplicações](#2-as-quatro-aplicações)
3. [O núcleo (system/)](#3-o-núcleo-system)
4. [Ciclo de vida da requisição](#4-ciclo-de-vida-da-requisição)
5. [Registry e acesso a serviços](#5-registry-e-acesso-a-serviços)
6. [Rotas e Controllers](#6-rotas-e-controllers)
7. [Models e lazy loading](#7-models-e-lazy-loading)
8. [Views e template engines](#8-views-e-template-engines)
9. [Languages](#9-languages)
10. [Events e Webhooks](#10-events-e-webhooks)
11. [Convenções de nomenclatura](#11-convenções-de-nomenclatura)
12. [Exemplo completo: da rota à tela](#12-exemplo-completo-da-rota-à-tela)

---

## 1. O que é MVCL

O **MVCL** é o padrão arquitetural popularizado pelo OpenCart — uma evolução do MVC clássico que adiciona a camada **Language** como primeira classe:

```
┌─────────┐   consulta/persiste   ┌────────┐
│  MODEL  │ ───────────────────── │  BD    │
└────┬────┘                       └────────┘
     │ dados
     ▼
┌────────────┐  monta  ┌───────┐  renderiza  ┌───────────┐
│ CONTROLLER │ ──────► │ VIEW  │ ──────────► │ RESPOSTA  │
└────┬───────┘         └───────┘             └───────────┘
     │ textos traduzidos
     ▼
┌───────────┐
│ LANGUAGE  │  pt-br · en-us · es-eu
└───────────┘
```

| Camada | Responsabilidade | Onde vive |
| --- | --- | --- |
| **Model** | Regras de negócio e acesso a dados (SQL) | `{app}/model/{grupo}/{modulo}.php` |
| **View** | Apresentação (templates Twig + assets) | `{app}/view/template/...` |
| **Controller** | Orquestra a requisição: valida entrada, chama models, escolhe a view | `{app}/controller/{grupo}/{modulo}.php` |
| **Language** | Todos os textos da interface, por idioma | `{app}/language/{idioma}/{grupo}/{modulo}.php` |

> **Regra de ouro:** nenhum texto "solto" em controller ou template — sempre via `language`. Nenhum SQL fora de model.

---

## 2. As quatro aplicações

O ReamurCMS é um monólito **multi-aplicação**: cada pasta de `dev/` é uma aplicação independente que compartilha o mesmo núcleo (`system/`):

| Aplicação | Pasta | Propósito |
| --- | --- | --- |
| **Catalog** | `dev/catalog/` | O site público — portfólio, serviços, ferramentas, contato, conta do cliente |
| **Admin** | `dev/admin/` | Painel administrativo — gestão de conteúdo, clientes, pedidos, configurações |
| **Install** | `dev/install/` | Wizard de instalação/upgrade + schema SQL |
| **Webhook** | `dev/system/webhook/` | Endpoint/rotina de disparo de webhooks (pedidos, produtos) |

Cada aplicação define a constante `APPLICATION` (ex.: `'Catalog'`), carrega seu `config/{aplicacao}.php` e registra seu namespace no autoloader:

```php
// dev/system/framework.php (trecho real)
$autoloader = new \Reamur\System\Engine\Autoloader();
$autoloader->register('Reamur\\' . APPLICATION, DIR_APPLICATION);
$autoloader->register('Reamur\Extension', DIR_EXTENSION);
$autoloader->register('Reamur\System', DIR_SYSTEM);
```

---

## 3. O núcleo (`system/`)

```text
dev/system/
├── engine/          # MÁQUINA do framework
│   ├── autoloader.php   # Autoloader de classes (namespaces Reamur\*)
│   ├── registry.php     # Container central de serviços
│   ├── config.php       # Leitura/escrita dos arquivos de config
│   ├── loader.php       # Loader unificado (model, view, language, helper, library)
│   ├── router.php       # Despacha a rota para a Action correta
│   ├── action.php       # Resolve "grupo/modulo.metodo" → classe/método
│   ├── controller.php   # Classe base Controller (acesso ao registry)
│   ├── model.php        # Classe base Model
│   ├── event.php        # Sistema de eventos interno (hooks por trigger)
│   ├── webhook.php      # Sistema de webhooks externos (ver docs/Webhooks.md)
│   ├── actionhook.php   # Hooks de ação
│   └── proxy.php        # Proxy p/ lazy loading de models via __get
├── library/         # SERVIÇOS reutilizáveis
│   ├── db/ (mysqli, mariadb, pdo, pgsql, nosql)
│   ├── cache/ (apcu, file, mem, memcached, redis)
│   ├── session/ (db, file, redis)
│   ├── template/ (twig, twig_cache, mustache, latte, blade, hbs, tpl, php, phtml)
│   ├── mail/ (mail, smtp, capture)
│   ├── cart/ (cart, currency, customer, user, tax, length, weight)
│   ├── openbay/ (amazon, ebay, etsy, fba) · squareup/ · googleshopping/
│   ├── security/upload_security.php
│   └── ai.php · encryption.php · image.php · language.php · log.php ·
│       request.php · response.php · document.php · pagination.php · url.php ·
│       address_lookup.php · mercadopago_marketplace.php
├── helper/          # Funções utilitárias (utf8, json, general, db_schema, seo, slug, bbcode, admin_appearance, config_loader)
├── config/          # default.php + um por aplicação (catalog, admin, install, api)
│   ├── api/schemas/     # Schemas de formulários da API (order_form, product_form)
│   └── api/settings/    # Config de API (ex.: ip_blocked)
├── storage/         # DADOS VOLÁTEIS (gravável)
│   ├── cache/  logs/  session/  temp/  marketplace/  certs/
│   └── vendor/        # Dependências Composer (Twig, Guzzle, ...) + composer.json
├── vendor.php       # Ponte que inclui o autoload do Composer
├── startup.php      # Bootstrap mínimo (constantes, versão PHP, saída limpa)
├── framework.php    # Monta o Registry e inicializa todos os serviços
└── modification.xml # Sistema de modificações (OCMOD-like)
```

### Papel de cada arquivo de bootstrap

1. **`startup.php`** — valida a versão do PHP, define constantes de diretório (`DIR_REAMUR`, `DIR_SYSTEM`, `DIR_APPLICATION`, `DIR_CONFIG`, `DIR_STORAGE`...) e prepara o ambiente.
2. **`framework.php`** — instancia *Autoloader → Registry → Config → Log → Error/Exception Handlers → Event → Loader → Request → Response → DB → Cache → Session → Template → Language → Url* nesta ordem, registrando tudo no Registry. O **Cache** (F4-06, ADR 0030) resolve o driver pelo `rms_setting` do painel (leitura direta no boot, já que a startup/setting que o injetaria só roda depois — e precisa do cache pronto); engine indisponível no servidor cai em `File` com aviso no log, nunca em 500.
3. **`vendor.php`** — faz `require` do `storage/vendor/autoload.php` (Composer), expondo Twig, Guzzle etc. ao núcleo.

---

## 4. Ciclo de vida da requisição

```
HTTP Request
    │
    ▼
index.php da aplicação (catalog/admin/install)
    │  define APPLICATION, DIR_REAMUR, require startup.php
    ▼
startup.php ──► framework.php
    │             1. Autoloader registra namespaces
    │             2. Registry criado
    │             3. Config: default.php + {application}.php
    │             4. Log + error/exception handlers
    │             5. Event registra listeners do config ('action_event')
    │             6. Loader, Request, Response
    │             7. DB (se db_autostart), Cache (driver do rms_setting — ADR 0030), Session
    │             8. Template engine (twig), Language, Url
    ▼
SEO URL / parâmetro ?route=grupo/modulo
    ▼
Router resolve a Action ──► Event 'controller/*/before'
    ▼
Controller->{método}()  ──► $this->load->model(...) ──► Model (SQL)
    │                        $this->load->language(...) ──► textos
    │                        $this->load->helper(...)
    ▼
$data + template ──► $this->load->view('grupo/modulo', $data)
    │                     Twig compila (cache em storage/cache/template)
    ▼
Event 'controller/*/after' ──► Response->setOutput() ──► HTTP Response
```

**Pré/post ações:** o config permite definir `action_pre_action` (ex.: `startup/seo_url`, `startup/language`, `startup/session`) que rodam antes de qualquer rota — é assim que idioma, sessão e URLs amigáveis se inicializam.

**Conteúdo editorial por rota (F10-01):** no Catalog, `common/header` resolve a rota corrente para uma chave `system_key` allowlisted e consulta `catalog/page::getSystemPage()`. A view compartilhada recebe apenas conteúdo de uma página ativa; documentos BDRE são renderizados pelo `BlockRenderer` validado. Dados de domínio continuam sendo carregados pelos controllers/models originais.

---

## 5. Registry e acesso a serviços

O **Registry** é o container único do sistema. Tanto `Controller` quanto `Model` recebem o registry no construtor e o expõem via `__get` mágico:

```php
// Dentro de qualquer Controller ou Model:
$this->config->get('site_url');
$this->request->get['route'] ?? '';
$this->db->query("SELECT ...");
$this->load->model('catalog/product');
$this->session->data['language'] ?? 'pt-br';
$this->log->write('Mensagem');
$this->url->link('product/product', 'id=' . $id);
```

```php
// system/engine/registry.php — ideia central
class Registry {
    private array $data = [];
    public function get(string $key): mixed { return $this->data[$key] ?? null; }
    public function set(string $key, mixed $value): void { $this->data[$key] = $value; }
    public function has(string $key): bool { return isset($this->data[$key]); }
}
```

> **Princípio:** nunca instancie serviços com `new` dentro de controllers (`new Db()`, `new Twig()`...). Sempre use o Registry/`$this->load` — é o que torna o sistema testável e permite trocar drivers (ex.: cache `file` → `redis`) pelo painel de configurações (`setting/setting`, efetivo desde a F4-06/ADR 0030 — antes era só o `default.php`).
>
> O serviço `three_scene_config` é registrado pelo `system/framework.php` e compartilhado entre aplicações. Ele centraliza apenas normalização/validação allowlisted de cenas; o estado persistente continua no model de settings, sem SQL em controller e sem schema novo.

---

## 6. Rotas e Controllers

**Formato da rota:** `grupo/modulo` (pasta/arquivo) + método público (padrão `index`).

```php
// URL: index.php?route=cms/portfolio.view&id=7
// Arquivo: catalog/controller/cms/portfolio.php

namespace Reamur\Catalog\Controller\Cms;

class Portfolio extends \Reamur\System\Engine\Controller {
    public function index(): void { /* lista projetos */ }

    public function view(): void {
        $id = (int)($this->request->get['id'] ?? 0);
        $this->load->model('cms/portfolio');

        $data['project'] = $this->model_cms_portfolio->getProject($id);
        $data['heading_title'] = $this->language->get('heading_title');

        $this->response->setOutput(
            $this->load->view('cms/portfolio_view', $data)
        );
    }
}
```

**Grupos de controllers já previstos na estrutura:**

| Catalog (site) | Admin (painel) |
| --- | --- |
| `account`, `api`, `chat`, `checkout`, `cms`, `common`, `cron`, `error`, `event`, `information`, `localisation`, `mail`, `product`, `startup`, `tool` | `api`, `catalog`, `cms`, `communication`, `customer`, `design`, `error`, `event`, `extension`, `localization`, `mail`, `marketing`, `report`, `sale`, `setting`, `startup`, `tool`, `user` |

---

## 7. Models e lazy loading

Models são carregados sob demanda pelo Loader e acessados por *propriedade mágica* (`$this->model_grupo_modulo`), implementada via `Proxy` — a classe real só é instanciada no primeiro uso:

```php
$this->load->model('catalog/product');            // registra o proxy
$produtos = $this->model_catalog_product          // instancia aqui, no 1º uso
    ->getProducts(['filter_category' => 3]);
```

```php
// catalog/model/catalog/product.php
namespace Reamur\Catalog\Model\Catalog;

class Product extends \Reamur\System\Engine\Model {
    public function getProducts(array $filters = []): array {
        $sql = "SELECT * FROM `" . DB_PREFIX . "product` WHERE `status` = '1'";
        // ... filtros, sempre com $this->db->escape()
        return $this->db->query($sql)->rows;
    }
}
```

> **Sempre** escape entradas com `$this->db->escape()` ou use bindings do driver. Nunca concatene `$_GET/$_POST` cru em SQL.

---

## 8. Views e template engines

O ReamurCMS tem **adaptadores de template** intercambiáveis em `system/library/template/`: `twig` (padrão), `twig_cache`, `mustache`, `latte`, `blade`, `hbs`, `tpl`, `php`, `phtml`.

A escolha é feita por config:

```php
// system/config/default.php
$_['template_engine']    = 'twig';
$_['template_cache']     = false;   // true em produção
$_['template_extension'] = '.twig';
```

Renderização no controller:

```php
$this->response->setOutput(
    $this->load->view('cms/portfolio_view', $data) // resolve view/template/cms/portfolio_view.twig
);
```

Detalhes de sintaxe e do adaptador Twig estão no [Guia Twig](Guia-Twig.md).

No Catalog, `startup/theme` entrega ao adaptador apenas fontes do tema explicitamente publicadas e cujas chaves constam na allowlist fixa. O `FilesystemLoader` continua como fallback; Admin e Install não ativam overrides. A prévia do editor roda em ambiente Twig sandboxado com dados de amostra, CSP e iframe isolado; fontes persistidas rejeitam o filtro `raw`, `<script>`, atributos de evento e referências a paths arbitrários. Fragmentos HTML gerados por renderizadores/sanitizadores confiáveis são marcados como `Twig\Markup` apenas nos campos fixos que já precisam de HTML; o restante mantém autoescape. As páginas CMS vinculam conteúdo editorial a rotas públicas sem assumir modelos ou fluxos dinâmicos dos módulos.

---

## 9. Languages

Cada aplicação tem sua árvore de idiomas espelhando controllers e models:

```text
catalog/language/
├── pt-br/common/home.php      → $_['heading_title'] = 'Portfólio';
├── en-us/common/home.php      → $_['heading_title'] = 'Portfolio';
└── es-eu/common/home.php      → $_['heading_title'] = 'Portafolio';
```

```php
$this->load->language('common/home');          // carrega o idioma ativo
$data['heading_title'] = $this->language->get('heading_title');
```

Idiomas previstos: **`en-us`** (padrão — [ADR 0001](../agents-ia/decisoes/0001-idioma-padrao-en-us.md)), **pt-br**, **es-eu**.

> ✅ **Decidido (ADR 0001):** o `default.php` declara `language_code = 'en-gb'`; será padronizado para `en-us` na Fase 1 do núcleo. Até lá, nenhum agente "corrige" por conta própria (regra de quirks — `agents-ia/regras/00`).

---

## 10. Events e Webhooks

Dois mecanismos de extensibilidade, complementares:

| | **Event** (interno) | **Webhook** (externo) |
| --- | --- | --- |
| Classe | `Reamur\System\Engine\Event` | `Reamur\System\Engine\Webhook` |
| Propósito | Alterar o comportamento do próprio CMS (ex.: injetar dados antes de renderizar) | Notificar sistemas externos via HTTP (ex.: novo pedido → WhatsApp/CRM) |
| Registro | `$event->register($trigger, $action, $priority)` | `$webhook->register($trigger, $action, $priority)` |
| Wildcards | `catalog/model/*/before`, `controller/*/after` | `order_*`, `product_*` |
| Definido em | config `action_event` / admin | `system/webhook/` + tabela de hooks |

Documentação completa: [Webhooks.md](Webhooks.md).

---

## 11. Convenções de nomenclatura

| Item | Convenção | Exemplo |
| --- | --- | --- |
| Namespaces | `Reamur\{Aplicacao}\{Camada}\{Grupo}` | `Reamur\Catalog\Controller\Cms` |
| Constantes de diretório | `DIR_*` + raiz `DIR_REAMUR` | `DIR_APPLICATION`, `DIR_STORAGE` |
| Prefixo de sessão | `RMS*` | `RMSSESSION` |
| Classes | `PascalCase` herdeiras de `Controller`/`Model` | `class Portfolio extends Controller` |
| Rotas | `grupo/modulo.metodo` (minúsculas) | `cms/portfolio.view` |
| Templates | espelham a rota + `.twig` | `view/template/cms/portfolio_view.twig` |
| Idiomas | `$_['chave'] = 'Texto';` | `$_['button_send'] = 'Enviar';` |
| Tabelas | prefixo via `DB_PREFIX` | `rms_product`, `rms_order` |
| Commits | Conventional Commits em pt-BR | `feat(webhook): adicionar sistema de webhook...` |

---

## 12. Exemplo completo: da rota à tela

**Requisito:** exibir um projeto do portfólio em `/portfolio/jwa`.

1. **SEO URL** (`startup/seo_url`) resolve `portfolio/jwa` → `route=cms/portfolio.view&slug=jwa`.
2. **Router** instancia `Reamur\Catalog\Controller\Cms\Portfolio` e chama `view()`.
3. **Controller** valida o slug, carrega `model_cms_portfolio` e `language('cms/portfolio')`.
4. **Model** busca o projeto + galeria no banco.
5. **Controller** monta `$data` e chama `$this->load->view('cms/portfolio_view', $data)`.
6. **Twig** compila `catalog/view/template/cms/portfolio_view.twig` (com cache em `storage/cache/template/` em produção).
7. **Response** envia o HTML final com os headers configurados.

---

## Veja também

- [Estrutura de Pastas](Estrutura-de-Pastas.md) — mapa anotado de todo o `dev/`
- [Guia PHP 8+](Guia-PHP8.md) — recursos da linguagem usados pela engine
- [Guia Twig](Guia-Twig.md) — camada View em detalhe
- [Webhooks](Webhooks.md) — engine de webhooks em detalhe
