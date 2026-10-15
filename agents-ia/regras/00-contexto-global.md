# 00 — Contexto Global (Fonte da Verdade)

> **Leitura obrigatória de TODOS os agentes antes de qualquer tarefa.** Estes são os **fatos verificados** do ReamurCMS. Se algo que você "sabe" conflitar com este documento, **este documento vence**. Se algo não estiver aqui nem nos docs, **pergunte — não invente**.

---

## 1. Identidade do sistema

| Fato | Valor |
| --- | --- |
| Nome do sistema | **ReamurCMS** |
| Pacote Composer | `reamurcms/reamurcms` |
| Repositório | `github.com/davidcreator/davidcms` |
| Licença | GPL-3.0-or-later |
| Autor | David L. Almeida — [davidalmeida.xyz](https://davidalmeida.xyz) |
| Inspiração arquitetural | OpenCart (padrão **MVCL**) |
| Namespace raiz | `Reamur\` |
| Constante de diretório raiz | `DIR_REAMUR` (**não** existe `DIR_OPENCART`) |
| Nome da sessão | `RMSSESSION` |
| Prefixo de tabelas | `DB_PREFIX` (ex.: `rms_`) |
| Tag atual | `v1.0.0` |
| Branches | `main` (estável) · `staging` (homologação) · `feature/*` |
| Idiomas da estrutura | `pt-br` (padrão), `en-us`, `es-eu` |
| Estado do projeto | **Esqueleto**: pastas + `index.html` placeholders; engine/library/webhook já têm código PHP real |
| PHP mínimo | `>=8.0.0` |
| Composer | manifesto em `dev/system/storage/composer.json` — vendor em `dev/system/storage/vendor/` (**não commitar**) |

---

## 2. Mapa das aplicações

```text
dev/
├── admin/     → painel (gestão de conteúdo, clientes, pedidos, settings)
├── catalog/   → site público (o novo davidalmeida.xyz)
├── install/   → wizard de instalação/upgrade + install/sql/
└── system/    → núcleo compartilhado (engine, library, helper, config, storage, webhook)
```

Cada aplicação segue **MVCL**: `controller/`, `model/`, `view/`, `language/` + ponto de entrada próprio (`index.php` — hoje placeholder `index.html`).

---

## 3. Núcleo — fatos verificados

- `system/engine/`: `autoloader`, `registry`, `config`, `loader`, `router`, `action`, `actionhook`, `controller`, `model`, `proxy`, `event`, `webhook`.
- `system/library/`: `db` (mysqli, mariadb, pdo, pgsql, nosql), `cache` (apcu, file, mem, memcached, redis), `session` (db, file, redis), `template` (**twig** padrão + mustache, latte, blade, hbs, tpl, php, phtml), `mail` (mail, smtp, capture), `cart`, `language`, `image`, `log`, `request`, `response`, `document`, `pagination`, `url`, `encryption`, `security/upload_security`, `ai`, `mercadopago_marketplace`, `googleshopping`, `openbay`, `squareup`.
- `system/helper/`: `utf8`, `general`, `db_schema`, `seo`, `slug`, `bbcode`, `admin_appearance`, `config_loader`.
- `system/config/`: `default.php` (sempre) + `{catalog|admin|install|api}.php` (override) + `api/schemas/*` + `api/settings/ip_blocked.php` + `googleshopping/`.
- Bootstrap: `startup.php` → `framework.php` (monta o Registry) + `vendor.php` (autoload do Composer).
- Webhooks: engine em `system/engine/webhook.php`; módulo dispatcher em `system/webhook/` (controllers `sale/order` e `catalog/product`, model `advanced/webhook`).

## 4. Serviços acessíveis via Registry

Dentro de Controller/Model, via `$this->`:

`config`, `log`, `event`, `load`, `request`, `response`, `db`, `cache`, `session`, `language`, `url`, `document`, `autoloader`, `webhook` (quando registrado).

> Antes de chamar **qualquer** método desses serviços, **leia o arquivo da engine/library correspondente** para confirmar que o método existe e sua assinatura.

## 5. Dependências (composer.json real)

`twig/twig ^3.3.7` (padrão de view) · `scssphp/scssphp ^1.10` · `guzzlehttp/guzzle ^7` + promises/psr7/uri-template · `aws/aws-sdk-php ^3.142` · `mustache/mustache ^3` · `latte/latte ^3` · `phpdocumentor/phpdocumentor ^3.4`.

## 6. Documentação viva (`docs/`)

| Doc | Para quê |
| --- | --- |
| `Analise-DavidAlmeida.md` | O que estamos migrando e o plano de fases |
| `Arquitetura-MVCL.md` | Como o MVCL funciona + ciclo de vida da requisição |
| `Estrutura-de-Pastas.md` | **Onde cada arquivo mora — consulte antes de criar arquivo** |
| `Guia-PHP8.md` · `Guia-Composer.md` · `Guia-Twig.md` | Padrões técnicos |
| `Guia-ThreeJS.md` + `Implementacao-ThreeJS.md` | Front 3D do site |
| `Webhooks.md` | Sistema de webhooks |
| `Guia-CMS.md` | Visão de produto/módulos |

**Obrigação:** se uma tarefa mudar comportamento/estrutura, **atualizar o doc correspondente no mesmo commit**.

## 7. Pendências conhecidas (não "corrigir" sem tarefa explícita)

1. `config/default.php` declara `en-gb`, mas as pastas de idioma são `en-us/es-eu/pt-br` → padronização prevista na Fase 1;
2. Workflow de CI precisa de `working-directory: dev/system/storage` (correção documentada em `docs/Guia-Composer.md` §5);
3. `.gitignore` precisa cobrir `dev/system/storage/vendor/` (documentado em §6 do mesmo guia);
4. `phpdocumentor` candidato a `require-dev` (avaliação pendente).

---

<p align="center">Duvidou? Leia o arquivo. Não achou o arquivo? Consulte docs/Estrutura-de-Pastas.md. Ainda não achou? Pergunte.</p>
