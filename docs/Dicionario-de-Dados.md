# Dicionário de Dados — ReamurCMS

> **Objetivo:** schema canônico das entidades do ReamurCMS. É a **fonte da verdade de colunas e relações** — resolve a lacuna **L2** da [Auditoria](Auditoria-Fundacao.md): nenhum agente "assume" nomes de coluna; todos consultam aqui (ou propõem mudança via ADR).

| | |
| --- | --- |
| **Status** | ✅ Contrato aceito (ADR 0004) — base do `install/sql/schema.sql` (Fase 2) |
| **Dono** | `agente-install` (mantém o SQL) · demais agentes (consomem) |
| **Convenções** | `ENGINE=InnoDB` · `CHARSET=utf8mb4` · prefixo `rms_` via `DB_PREFIX` · PKs `INT AUTO_INCREMENT` · datas `DATETIME NOT NULL` |

---

## 1. Convenções do schema

1. **Padrão `*_description`** para conteúdo traduzível: tabela principal guarda dados neutros (status, ordem, datas); a `*_description` guarda textos **por `language_id`** (pt-br, en-us, es-eu);
2. **`status`** = `TINYINT(1) NOT NULL DEFAULT '1'` (1 ativo / 0 inativo) — rascunho/publicado usa o mesmo campo;
3. **`sort_order`** = `INT NOT NULL DEFAULT '0'` para ordenação manual em listas;
4. **`date_added` / `date_modified`** sempre presentes em entidades editáveis;
5. Toda FK referencia PK com o **mesmo nome de coluna** (`language_id`, `customer_id`...) — nunca sinônimos (`lang_id`, `cliente_id`);
6. Mudança neste dicionário = **ADR + migração nova** (`install/sql/upgrade/`), nunca edição retroativa.

---

## 2. Mapa de entidades × donos

| Tabela | Módulo dono (admin) | Consumidores |
| --- | --- | --- |
| `setting` | `setting/` | todas as apps |
| `language` | `localization/` | catalog/admin/install |
| `user_group`, `user` | `user/` | admin, api |
| `customer_group`, `customer` | `customer/` | catalog `account/`, sale |
| `page`, `page_description` | `cms/` | catalog `information/` |
| `custom_field_group*`, `custom_field*`, `custom_field_value` | `custom_field/definition`, F9 | CMS admin + catalog `cms/page` (somente visibility=public) |
| `blog_post`, `blog_post_description`, `blog_post_content` | `blog/blog` | admin Blog + catalog Blog; conteúdo estruturado F9-12 |
| `category`, `category_description` | `catalog/` | catalog `product/` (portfólio) |
| `project`, `project_description`, `project_image`, `project_to_category` | `catalog/` | catalog (portfólio) |
| `testimonial`, `testimonial_description` · `client_logo` | `marketing/` | home |
| `tool`, `tool_description` | `tool/` | catalog `tool/` |
| `order`, `order_product`, `order_status`, `order_history` | `sale/` | catalog `checkout/`, webhooks |
| `contact_message` | `communication/` | formulário de contato |
| `webhook`, `webhook_log` | `marketing/` ou `setting/` | `system/webhook/` |
| `seo_url` | `design/` | todas as rotas amigáveis |
| `session` | (engine) | `session_engine=db` |
| `import_contact` | (migração — staging) | F4-08/ADR 0032: espelho da `contact_messages` legada; **não é lida por nenhum Model** |

---

## 3. Núcleo

### `setting` — configurações editáveis no admin
| Coluna | Tipo | Notas |
| --- | --- | --- |
| `setting_id` | INT PK AI | |
| `code` | VARCHAR(64) | grupo: `config`, `mail`, `seo`, `theme`... |
| `key` | VARCHAR(128) | ex.: `config_meta_title` |
| `value` | TEXT | valor (UTF-8) |
| `serialized` | TINYINT(1) | 1 = `value` é JSON |
| **Índice** | UNIQUE(`code`,`key`) | |

### `language` — idiomas instalados
`language_id` PK · `name` VARCHAR(32) · `code` VARCHAR(8) *(ex.: `en-us`; **padrão do sistema: `en-us`** — ADR 0001)* · `locale` VARCHAR(32) · `sort_order` · `status`

### `user_group` — grupos do painel (ACL)
`user_group_id` PK · `name` VARCHAR(64) · `permission` TEXT *(JSON: `{"access": [...rotas], "modify": [...rotas]}`)*

### `user` — usuários do painel
`user_id` PK · `user_group_id` FK→user_group · `username` VARCHAR(32) UNIQUE · `password` VARCHAR(255) *(password_hash)* · `email` VARCHAR(96) · `firstname`,`lastname` VARCHAR(32) · `image` VARCHAR(255) · `status` · `date_added`

### `customer_group` / `customer` — clientes do site
`customer_group_id` PK · `name` VARCHAR(64) · `description` VARCHAR(255)

`customer_id` PK · `customer_group_id` FK · `firstname`,`lastname` VARCHAR(32) · `email` VARCHAR(96) UNIQUE · `telephone` VARCHAR(32) · `password` VARCHAR(255) · `status` · `date_added`

---

## 4. Conteúdo (CMS)

### `page` — páginas de informação (Cookies, Políticas, Sobre)
`page_id` PK · `status` · `sort_order` · `date_added` · `date_modified`

### `page_description`
`page_id` FK · `language_id` FK · `title` VARCHAR(255) · `description` MEDIUMTEXT *(HTML do editor)* · `meta_title` VARCHAR(255) · `meta_description` VARCHAR(255) · PK composta (`page_id`,`language_id`)


### Campos personalizados — Fase 9 / CMS page (ADR 0058, migração F9-02)

As cinco tabelas abaixo são criadas por `dev/install/sql/upgrade_f9_02_custom_fields_schema.sql`; migration separada e manual, sem FKs. O primeiro alvo implementado é `entity_type='cms/page'`. Chaves de grupo/campo são estáveis; renomear ou mudar tipo exige migração nova. Valores compartilhados usam `language_id=0`; valores localizados usam o ID real de idioma.

#### `custom_field_group`
`group_id` INT PK AI · `entity_type` VARCHAR(32) · `key` VARCHAR(64) · `name` VARCHAR(128) · `status` TINYINT(1) DEFAULT 0 · `sort_order` INT · `date_added`,`date_modified` DATETIME · UNIQUE(`entity_type`,`key`) · índice (`entity_type`,`status`,`sort_order`).

#### `custom_field_group_description`
`group_id` INT · `language_id` INT · `name` VARCHAR(128) · PK (`group_id`,`language_id`) · índice (`language_id`).

#### `custom_field`
`field_id` INT PK AI · `entity_type` VARCHAR(32) · `group_id` INT · `key` VARCHAR(64) · `type` VARCHAR(16) (`text`,`textarea`,`number`,`boolean`,`url`,`select`) · `localized` TINYINT(1) DEFAULT 0 · `required` TINYINT(1) DEFAULT 0 · `visibility` VARCHAR(16) DEFAULT `admin_only` (ou `public`) · `options` TEXT (JSON estrito para select; `[]` nos demais) · `sort_order` INT · `date_added`,`date_modified` DATETIME · UNIQUE(`entity_type`,`key`) · índices (`group_id`) e (`entity_type`,`visibility`).

#### `custom_field_description`
`field_id` INT · `language_id` INT · `label` VARCHAR(128) · `help` VARCHAR(255) DEFAULT '' · PK (`field_id`,`language_id`) · índice (`language_id`).

#### `custom_field_value`
`value_id` BIGINT PK AI · `entity_type` VARCHAR(32) · `entity_id` INT · `field_id` INT · `language_id` INT (0 = compartilhado) · `value_text` TEXT · `value_number` DECIMAL(38,6) NULL · UNIQUE(`entity_type`,`entity_id`,`field_id`,`language_id`) · índices (`entity_type`,`entity_id`), (`field_id`,`language_id`) e (`language_id`).

Integridade entre grupo/campo/valor é mantida pelos Models (sem FK). No frontend, só páginas publicadas, grupos ativos e campos `visibility='public'` são elegíveis; o rendering deve escapar valores como texto. `admin_only` permanece privado. A aplicação/rollback segue as notas da F9-02 e deve ser executada com backup em ambiente controlado.


### Blog multilíngue e documento de blocos (F7-01 / F9-12)

O schema base do Blog está em `upgrade_f7_01_blog.sql`. O editor legado salva HTML em `blog_post_description.description`; a tabela de F9-12 é sidecar aditivo e não troca nem remove esse campo.

#### `blog_post`
`post_id` INT PK AI · `image` VARCHAR(255) DEFAULT '' · `status` TINYINT(1) DEFAULT 0 · `date_published` DATETIME · `date_added`,`date_modified` DATETIME · índice (`status`,`date_published`).

#### `blog_post_description`
`post_id` INT · `language_id` INT · `title` VARCHAR(255) · `description` MEDIUMTEXT *(snapshot HTML compatível/legacy)* · `meta_title` VARCHAR(255) · `meta_description` VARCHAR(255) · PK (`post_id`,`language_id`) · índice (`language_id`).

#### `blog_post_content` — F9-12, sidecar versionado por idioma
`post_id` INT · `language_id` INT · `format_version` SMALLINT UNSIGNED DEFAULT 1 · `content_json` MEDIUMTEXT *(documento de blocos JSON validado pela aplicação)* · `source_hash` CHAR(64) DEFAULT '' *(SHA-256 do HTML antes da primeira conversão)* · `legacy_html_backup` MEDIUMTEXT NULL *(cópia recuperável da fonte anterior; preservada durante edições subsequentes)* · `date_added`,`date_modified` DATETIME · PK (`post_id`,`language_id`) · índice (`language_id`). Sem FK deliberadamente; integridade/cascata é mantida pelo Model Blog.

Uma linha representa um documento por post e idioma. `blog_post_description.description` permanece snapshot HTML para fallback/compatibilidade pública; `content_json` é a fonte editável após conversão. A tabela é criada por migration separada `upgrade_f9_12_blog_block_content.sql`, nunca pela edição retroativa de `schema.sql`. Rollback inicial é não destrutivo: reverter a aplicação para o leitor HTML e manter sidecar até backup/retention aprovados. Não apagar blocos por script sem export/backup.

---

## 5. Portfólio

### `category` — Visual Identity, Brand, Illustration
`category_id` PK · `parent_id` INT DEFAULT 0 · `image` VARCHAR(255) · `status` · `sort_order` · `date_added` · `date_modified`

### `category_description`
`category_id` · `language_id` · `name` VARCHAR(255) · `description` TEXT · `meta_title` · `meta_description` · PK (`category_id`,`language_id`)

### `project` — projeto do portfólio
| Coluna | Tipo | Notas |
| --- | --- | --- |
| `project_id` | INT PK AI | |
| `image` | VARCHAR(255) | **capa** (WebP) — nome oficial: `image` |
| `client` | VARCHAR(128) | nome do cliente (texto) |
| `url` | VARCHAR(255) | link externo opcional (ex.: Hiperion Gamer) |
| `status` | TINYINT(1) | rascunho/publicado |
| `featured` | TINYINT(1) | destaque na home |
| `sort_order` | INT | |
| `date_added`,`date_modified` | DATETIME | |

### `project_description`
`project_id` · `language_id` · **`name`** VARCHAR(255) *(título — nome oficial: `name`)* · `summary` VARCHAR(255) · `description` TEXT · `tag` VARCHAR(255) *(CSV de tags)* · `meta_title` · `meta_description` · PK (`project_id`,`language_id`)

### `project_image` — galeria do projeto
`project_image_id` PK · `project_id` FK · `image` VARCHAR(255) · `alt` VARCHAR(255) · `sort_order`

### `project_to_category`
`project_id` · `category_id` · PK composta — relação N:N

---

## 6. Marketing / Home

### `testimonial` + `testimonial_description`
`testimonial_id` PK · `image` VARCHAR(255) · `status` · `sort_order` · `date_added`
`testimonial_id` · `language_id` · `author` VARCHAR(128) *(ex.: Bruno Reis)* · `company` VARCHAR(128) · `text` TEXT

### `client_logo` — clientes (SEVEN A, AJL…)
`client_logo_id` PK · `name` VARCHAR(128) · `image` VARCHAR(255) · `url` VARCHAR(255) · `status` · `sort_order`

---

## 7. Ferramentas conectadas

### `tool` — Fluxa, Quotia, Vertex, Solis
`tool_id` PK · `image` VARCHAR(255) *(ícone)* · `vendor` VARCHAR(64) *(ex.: DavidCreator)* · `url` VARCHAR(255) · `status` · `sort_order` · `date_added`

### `tool_description`
`tool_id` · `language_id` · `name` VARCHAR(128) · `description` VARCHAR(255) — PK (`tool_id`,`language_id`)

---

## 8. Pedidos e orçamentos (sale)

### `order_status`
`order_status_id` PK · `language_id` FK · `name` VARCHAR(64) *(Recebido, Em análise, Orçamento enviado, Aprovado, Concluído, Cancelado)*

### `order`
| Coluna | Tipo | Notas |
| --- | --- | --- |
| `order_id` | INT PK AI | id público do pedido/webhook |
| `customer_id` | INT | 0 = visitante (orçamento sem cadastro) |
| `firstname`,`lastname` | VARCHAR(32) | snapshot do contato |
| `email` | VARCHAR(96) | snapshot |
| `telephone` | VARCHAR(32) | snapshot (WhatsApp) |
| `total` | DECIMAL(15,4) | 0.0000 para orçamento sem preço |
| `order_status_id` | FK→order_status | |
| `comment` | TEXT | briefing/observações |
| `ip` | VARCHAR(40) | anti-spam/auditoria |
| `date_added`,`date_modified` | DATETIME | disparo de webhooks (`order_add`/`order_edit`) |

### `order_product`
`order_product_id` PK · `order_id` FK · `name` VARCHAR(255) *(serviço/projeto)* · `quantity` INT · `price` DECIMAL(15,4) · `total` DECIMAL(15,4)

### `order_history`
`order_history_id` PK · `order_id` FK · `order_status_id` FK · `comment` TEXT · `date_added` *(cada mudança de status vira registro)*

---

## 9. Comunicação

### `contact_message` — formulário "Let's have a chat?"
`contact_message_id` PK · `name` VARCHAR(128) · `email` VARCHAR(96) · `telephone` VARCHAR(32) · `subject` VARCHAR(128) · `message` TEXT · `ip` VARCHAR(40) · `status` TINYINT *(0 novo, 1 lido, 2 respondido)* · `date_added`

---

## 10. Webhooks

### `webhook`
`webhook_id` PK · `trigger` VARCHAR(64) *(ex.: `order_add`, `product_*`)* · `url` VARCHAR(255) *(HTTPS obrigatório)* · `secret` VARCHAR(64) *(HMAC)* · `description` VARCHAR(255) · `status` · `date_added` · índice UNIQUE(`trigger`,`url`)

### `webhook_log` — entregas (retry/idempotência)
`webhook_log_id` PK · `webhook_id` FK · `delivery` VARCHAR(64) *(id único por disparo)* · `payload` MEDIUMTEXT · `http_code` SMALLINT · `duration` FLOAT *(segundos)* · `attempt` TINYINT · `date_added` · índice (`webhook_id`,`delivery`)

---

## 11. Infra

### `seo_url`
`seo_url_id` PK · `key` VARCHAR(64) *(rota/parâmetro, ex.: `project_id=7`)* · `value` VARCHAR(255) *(keyword, ex.: `jwa`)* · `language_id` FK · `sort_order` · UNIQUE(`key`,`language_id`) · UNIQUE(`value`,`language_id`)

### `session`
`session_id` VARCHAR(64) PK · `data` MEDIUMTEXT · `expire` DATETIME · índice(`expire`)

### `import_contact` *(staging de migração — F4-08/ADR 0032)*
`legacy_id` INT UNSIGNED PK · `name` VARCHAR(190) · `email` VARCHAR(190) · `phone` VARCHAR(60) · `subject` VARCHAR(255) · `message` TEXT · `status` VARCHAR(40) *(aceitos na importação: `new`·`replied`·`archived`·`closed`; outros ignorados)* · `ip_address` VARCHAR(64) · `created_at` DATETIME · `updated_at` DATETIME
Uso: bloco INSERT do dump legado é colado pelo usuário nesta tabela; `upgrade_f4_08_*.sql` move para `rms_order` com guarda `NOT EXISTS`. Nenhum Model/catalog a lê — não possui `*_description`.

---

## 12. Regras de uso para os agentes

1. **Antes de escrever qualquer SQL ou Model**, conferir aqui: nome da tabela, nome exato da coluna e a `*_description` correspondente;
2. Entidade nova/Coluna nova → **ADR + atualização deste doc + migração SQL** (fluxo do `agente-install`), nunca direto no Model;
3. Nomes "oficiais" destacados (`image` = capa, `name` = título de projeto) existem para acabar com a divergência `name`×`title`, `image`×`cover`;
4. As tabelas F9 de custom fields seguem migração/versionamento próprios; não modificar migration F9-02 já aplicada.
5. Este dicionário cobre o **núcleo do lançamento**; e-commerce completo (produtos vendáveis, estoque, impostos) entra por ADR futuro, se o escopo crescer.

---

## Veja também

- [Auditoria-Fundacao.md](Auditoria-Fundacao.md) — lacuna L2 fechada por este doc
- [Guia-CMS.md](Guia-CMS.md) — módulos que consomem estas tabelas
- [Contrato-Bootstrap.md](Contrato-Bootstrap.md) — `DB_PREFIX` e constantes de banco
- `agents-ia/agentes/agente-install.md` — protocolo de migrações
