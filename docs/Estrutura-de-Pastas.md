# Estrutura de Pastas — ReamurCMS

> **Objetivo:** referência anotada de toda a árvore de diretórios do repositório — consulta rápida para saber **onde** criar cada arquivo novo.

> 📌 Estado atual: esqueleto estrutural — as pastas contêm `index.html` vazios (placeholders para versionar a árvore no Git) e serão preenchidas conforme as fases do [plano de migração](Analise-DavidAlmeida.md#8-plano-de-migração).

---

## Sumário

1. [Visão geral](#1-visão-geral)
2. [dev/admin — painel](#2-devadmin--painel)
3. [dev/catalog — site público](#3-devcatalog--site-público)
4. [dev/install — instalador](#4-devinstall--instalador)
5. [dev/system — núcleo](#5-devsystem--núcleo)
6. [dev/system/storage — dados voláteis](#6-devsystemstorage--dados-voláteis)
7. [dev/system/webhook — webhooks](#7-devsystemwebhook--webhooks)
8. [Arquivos de configuração](#8-arquivos-de-configuração)
9. [Onde criar o quê (guia rápido)](#9-onde-criar-o-quê-guia-rápido)

---

## 1. Visão geral

```text
davidcms/
├── .github/workflows/php.yml   # CI — valida e instala Composer (ver Guia-Composer §5)
├── .gitignore
├── README.md                   # Apresentação do projeto
├── docs/                       # 📚 Esta documentação
└── dev/                        # Código-fonte (multi-aplicação MVCL)
    ├── admin/                  # Aplicação 1 — painel administrativo
    ├── catalog/                # Aplicação 2 — site público
    ├── install/                # Aplicação 3 — wizard de instalação/upgrade
    └── system/                 # Núcleo compartilhado do framework
```

---

## 2. `dev/admin/` — Painel

Tudo que é gestão fica aqui. Grupos de controllers/models:

| Grupo | Gerencia... |
| --- | --- |
| `api/` | Endpoints internos consumidos pelo próprio painel |
| `catalog/` | Produtos, categorias, fabricantes → **usado como Portfólio** no novo site |
| `cms/` | Páginas de conteúdo e áreas editoriais do Catalog (Blog continua em ferramenta/controller próprio) |
| `communication/` | Mensagens, contato recebido, notificações |
| `customer/` | Clientes e grupos de clientes |
| `design/` | Temas e layouts do Catalog (ferramenta Temas em implementação nesta fase) |
| `error/` | Páginas de erro do admin (404/permissão) |
| `event/` | Gestão de eventos do sistema (listar/remover listeners) |
| `extension/` | Instalar/configurar extensões |
| `localization/` | Idiomas, moedas, países, zonas, impostos |
| `mail/` | Templates e fila de e-mails |
| `marketing/` | Campanhas, cupons, afiliados → **também depoimentos/logos de clientes** |
| `report/` | Relatórios (acessos, vendas, pedidos) |
| `sale/` | **Pedidos/orçamentos** — coração do negócio |
| `setting/` | Configuração da loja/site, usuários-globais |
| `startup/` | Inicialização do admin (auth, sessão, permissão) |
| `tool/` | Ferramentas → gestão dos cards Fluxa/Quotia/Vertex/Solis |
| `user/` | Usuários do admin, grupos e ACL |

Subpastas fixas:

```text
admin/
├── controller/{grupos acima}
├── model/                     # mesma divisão de grupos (sem error/event/extension/startup/api...)
├── view/
│   ├── template/              # .twig do painel
│   ├── css/ js/ image/ assets/
├── language/{en-us, es-eu, pt-br}/{grupos}
└── index.html                 # → será index.php (ponto de entrada do admin)
```

---

## 3. `dev/catalog/` — Site público

O front-end que o visitante vê (equivalente ao `/catalog/` do site atual):

| Grupo | Serve para... |
| --- | --- |
| `account/` | Área do cliente (login, cadastro, histórico de pedidos) |
| `api/` | API pública (REST interna) |
| `chat/` | Chat/atendimento (futuro) |
| `checkout/` | Fluxo de pedido/orçamento |
| `cms/` | Páginas de conteúdo e **portfólio/blog** |
| `common/` | Home, header, footer, manutenção — a one-page mora aqui |
| `cron/` | Rotinas agendadas (retry de webhooks, limpeza) |
| `error/` | 404 e páginas de erro |
| `event/` | Listeners específicos do catalog |
| `information/` | Páginas institucionais (Cookies, Políticas Internas, contato) |
| `localisation/` | Detecção de idioma/moeda/país do visitante |
| `mail/` | E-mails transacionais do site |
| `product/` | Exibição de itens de catálogo/portfólio |
| `startup/` | Bootstrap da sessão do visitante, SEO URL, idioma |
| `tool/` | **Ferramentas conectadas** (Fluxa, Quotia, Vertex, Solis) |

```text
catalog/
├── controller/{grupos}
├── model/{grupos}
├── view/
│   ├── theme/                 # temas instaláveis (o novo site vive aqui)
│   ├── template/              # templates Twig (ver Guia-Twig §8)
│   ├── css/ js/
├── language/{en-us, es-eu, pt-br}/{grupos}
└── index.html                 # → será index.php (front controller público)
```

Conteúdo editorial das rotas públicas é resolvido pelo `catalog/controller/common/header.php` e `catalog/model/catalog/page.php`; a chave da rota vive em `rms_page.system_key` (migration F10-01). A futura ferramenta Temas permanece no grupo `admin/controller/design/` e não altera os temas do painel.

---

## 4. `dev/install/` — Instalador

Wizard de primeira instalação e upgrades:

```text
install/
├── controller/
│   ├── common/                # header/footer/steps do wizard
│   ├── error/
│   ├── install/               # passos 1..n (licença, requisitos, BD, admin)
│   │   └── extensions/        # instalação de extensões no setup
│   ├── startup/               # bootstrap do instalador
│   └── upgrade/               # rotinas de migração de versão
├── language/{en-us, es-eu, pt-br}/{common, error, install, upgrade}
├── model/{extensions, install}
├── sql/                       # 📄 schema.sql — tabelas do CMS
└── view/{css, images, js, template}
```

---

## 5. `dev/system/` — Núcleo

O framework ReamurCMS propriamente dito (detalhado em [Arquitetura MVCL §3](Arquitetura-MVCL.md#3-o-núcleo-system)):

```text
system/
├── engine/                    # A MÁQUINA
│   ├── autoloader.php         # registra namespaces Reamur\*
│   ├── action.php             # resolve rota → classe/método
│   ├── actionhook.php         # hooks de ação
│   ├── config.php             # lê configs (default + por app)
│   ├── controller.php         # base Controller (registry via __get)
│   ├── event.php              # eventos internos
│   ├── loader.php             # load->model/view/language/helper/library
│   ├── model.php              # base Model
│   ├── proxy.php              # lazy loading de models
│   ├── registry.php           # container central
│   ├── router.php             # despacha rotas
│   └── webhook.php            # engine de webhooks (ver Webhooks.md)
├── library/                   # SERVIÇOS
│   ├── db/{mysqli, mariadb, pdo, pgsql, nosql}.php
│   ├── cache/{apcu, file, mem, memcached, redis}.php
│   ├── session/{db, file, redis}.php
│   ├── template/{twig, twig_cache, mustache, latte, blade, hbs, tpl, php, phtml}.php
│   ├── mail/{mail, smtp, capture}.php
│   ├── cart/{cart, currency, customer, user, tax, length, weight}.php
│   ├── googleshopping/ openbay/ squareup/   # integrações legadas portadas
│   ├── security/upload_security.php
│   ├── three/                  # serviço/config + runtime Three.js canônico self-hosted
│   │   ├── scene_config.php    # política declarativa allowlisted
│   │   ├── asset_manifest.php  # allowlist de módulos servidos pelo Catalog
│   │   └── runtime/            # vendor Three + runner/cenas/thumbs (não exposto direto; inclui portfolio-3d.js e tools-3d.js)
│   ├── ai.php                 # integração com IA (geração de conteúdo)
│   ├── mercadopago_marketplace.php
│   └── encryption, image, language, log, pagination,
│       request, response, document, url, address_lookup...
├── helper/                    # utils: utf8, general, db_schema, seo, slug,
│                              #        bbcode, admin_appearance, config_loader
├── config/                    # ver seção 8
├── storage/                   # ver seção 6
├── webhook/                   # ver seção 7
├── marketplace/               # conector do marketplace de extensões
├── modification.xml           # modificações declarativas (OCMOD-like)
├── startup.php                # bootstrap mínimo
├── framework.php              # monta o Registry inteiro
├── vendor.php                 # ponte p/ autoload do Composer
└── README.md                  # "Área do sistema"
```

---

## 6. `dev/system/storage/` — Dados voláteis

Tudo **gravável** mora aqui (permissão de escrita obrigatória):

```text
storage/
├── cache/            # cache compartilhado
│   └── template/     # templates Twig compilados (template_cache=true)
├── certs/cacert.pem  # bundle CA para chamadas HTTPS (Guzzle)
├── composer.json     # manifesto do Composer (ver Guia-Composer)
├── composer.lock
├── logs/             # error.log, migration_error.log
├── marketplace/      # pacotes baixados do marketplace
├── session/          # sessões em arquivo (session_engine=file)
├── temp/             # temporários (uploads em processamento etc.)
└── vendor/           # dependências Composer — NUNCA commitar (ver Guia-Composer §6)
```

---

## 7. `dev/system/webhook/` — Webhooks

```text
webhook/
├── controller/
│   ├── catalog/product.php    # product_add / product_edit / ...
│   └── sale/order.php         # order_add / order_edit (+ order_delete planejado)
└── model/
    └── advanced/webhook.php   # getHooks($trigger) → lê tabela rms_webhook
```

Documentação completa: [Webhooks.md](Webhooks.md).

---

## 8. Arquivos de configuração

```text
system/config/
├── default.php                # config base: site, DB, mail, cache, session,
│                              #   template, erros, autoloads (SEMPRE carrega)
├── catalog.php                # overrides do site público
├── admin.php                  # overrides do admin
├── install.php                # overrides do instalador
├── api.php                    # config da aplicação API
├── api/
│   ├── schemas/               # order_form.php, product_form.php
│   └── settings/              # ip_blocked.php
└── googleshopping/
    └── googleshopping.php     # config da integração Google Shopping
```

Ordem de carregamento (em `framework.php`): `default.php` → `{application}.php` (mesma chave sobrescreve).

Chaves mais importantes (de `default.php`):

| Chave | Exemplo | Comentário |
| --- | --- | --- |
| `site_url` / `site_ssl` | `https://davidalmeida.xyz/` / `true` | URL base e HTTPS |
| `db_engine` | `mysqli` | `mysqli`, `pdo`, `pgsql`, `mariadb` |
| `cache_engine` | `file` | `apcu`, `file`, `mem`, `memcached`, `redis` |
| `session_engine` / `session_name` | `file` / `RMSSESSION` | `db`, `file`, `redis` |
| `template_engine` / `template_cache` | `twig` / `true` (prod) | ver [Guia Twig](Guia-Twig.md) |
| `error_display` / `error_log` | `false` (prod) / `true` | nunca exibir erro em produção |
| `mail_engine` | `smtp` | configurar SMTP no admin |

---

## 9. Onde criar o quê (guia rápido)

| Preciso criar... | Vai em... |
| --- | --- |
| Nova página pública (ex.: `/sobre`) | `catalog/controller/cms/` + `view/template/cms/` + `language/{idioma}/cms/` |
| Nova seção da home | partial em `catalog/view/template/partial/` incluído em `common/home.twig` |
| CRUD de conteúdo no painel | `admin/controller/{grupo}/` + `admin/model/{grupo}/` + `admin/view/template/` |
| Nova ferramenta conectada | `catalog/controller/tool/` + card gestor em `admin/controller/tool/` |
| Novo endpoint de API | `catalog/controller/api/` ou `admin/controller/api/` |
| Novo webhook | `system/webhook/controller/` + trigger cadastrado ([Webhooks §6](Webhooks.md#6-criando-um-novo-webhook)) |
| Novo serviço de biblioteca | `system/library/meu_servico.php` (classe) — registrado via `$this->load->library()` |
| Novo helper de funções | `system/helper/meu_helper.php` — `$this->load->helper('meu_helper')` |
| Novo driver (cache/db/session) | `system/library/{cache,db,session}/meu_driver.php` + opção no config |
| Novo idioma | pasta `language/xx-xx/` espelhada nos 3 apps + registro em `admin/controller/localization/` |
| Schema de banco | `install/sql/` (migrações versionadas) |
| Assets do front | `catalog/view/{css,js}/` e tema em `catalog/view/theme/` |
| Engine/runtime Three.js compartilhado | `system/library/three/runtime/`; acesso web só pela allowlist `catalog/controller/common/three.php` |
| Cena e markup específicos do site | `catalog/view/` — ver [Guia Three.js](Guia-ThreeJS.md) |
| Config nova | chave em `system/config/default.php` + override por app se necessário |
| Documentação nova | `docs/` (pt-BR, kebab-case) + entrada no [índice](README.md) |

---

## Veja também

- [Arquitetura MVCL](Arquitetura-MVCL.md) — como essas pastas interagem em runtime
- [Análise §8](Analise-DavidAlmeida.md#8-plano-de-migração) — ordem de preenchimento (Fases 1–6)
- [Guia CMS §3](Guia-CMS.md#3-módulos-essenciais-de-um-cms--reamurcms) — módulos em visão de produto

### F10-02 — Temas do Catalog

A ferramenta Admin independente está em `admin/controller/design/theme.php`, `admin/model/design/theme.php`, `admin/view/template/design/` e `admin/language/{en-us,pt-br,es-eu}/design/`. Persistência e snapshots ficam em `rms_design_theme` e `rms_design_theme_revision`. A rota ACL é `design/theme`; não confundir com a aparência do Admin (`setting/appearance`). Templates Twig não são gravados no filesystem por esta primeira fatia.
