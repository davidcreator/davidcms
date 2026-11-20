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
| `page`, `page_description`, `cms_block_pattern` | `cms/` | catalog `information/`; biblioteca BDRE privada por administrador |
| `custom_field_group*`, `custom_field*`, `custom_field_value` | `custom_field/definition`, F9 | CMS admin + catalog `cms/page` (somente visibility=public) |
| `blog_post`, `blog_post_description`, `blog_post_content` | `blog/blog` | admin Blog + catalog Blog; conteúdo estruturado F9-12 |
| `category`, `category_description` | `catalog/` | catalog `product/` (portfólio) |
| `project`, `project_description`, `project_image`, `project_to_category` | `catalog/` | catalog (portfólio) |
| `testimonial`, `testimonial_description` · `client_logo` | `marketing/` | home |
| `tool`, `tool_description` | `tool/` | catalog `tool/` |
| `order`, `order_product`, `order_status`, `order_history` | `sale/` | catalog `checkout/`, webhooks |
| `jow_gateway`, `payment_transaction`, `payment_gateway_event` | `payments/jow` (núcleo em `system/library/payment/`) | catalog `checkout/gateway`, rota `cron/reconcile`, webhooks de provedor |
| `jow_charge`, `jow_pix_key` | `payments/jow` | painel *Pagamentos › JOW*; `status` sincronizado pelo checklist de pagamento |
| `mp_payment`, `mp_seller` | `setting/marketplace` | catalog `checkout/mp_payment` (pedido público), notificação MP |
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

### `page` — páginas de informação e conteúdo editorial de rotas do Catalog (F10-01)
`page_id` PK · `system_key` VARCHAR(64) NULL UNIQUE *(chave estável da rota pública; NULL nas páginas CMS livres)* · `status` · `sort_order` · `date_added` · `date_modified`. A migration `upgrade_f10_01_cms_catalog_routes.sql` cria a coluna e semeia as rotas de conteúdo sem alterar páginas existentes.

### `page_description`
`page_id` FK · `language_id` FK · `title` VARCHAR(255) · `description` MEDIUMTEXT *(HTML ou documento de blocos do editor)* · `meta_title` VARCHAR(255) · `meta_description` VARCHAR(255) · PK composta (`page_id`,`language_id`). Para páginas com `system_key`, o conteúdo é mostrado na rota pública correspondente; o conteúdo de negócio continua nos módulos especializados.


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

## 9. Pagamentos

Duas camadas de pagamento convivem no sistema (ADR 0118) e **nenhuma coluna é repetida entre elas** — o que decide qual ler é o que se está olhando:

| Camada | Contrato | Tabela principal | Quem escreve |
| --- | --- | --- | --- |
| Pedido público do site | ADR 0097 (`Method` → `Checkout` → `DbLedger`) | `mp_payment` | `catalog/model/checkout/mp_payment.php` |
| Tentativa por provedor (núcleo novo) | `GatewayAdapter` → `GatewayPaymentService` → `GatewayLedger` | `payment_transaction` | `system/library/payment/`, `admin/model/payments/jow`, `catalog/controller/checkout/gateway` |
| Cobrança da ferramenta interna JOW | contrato simples, anterior ao núcleo | `jow_charge` | `admin/model/payments/jow.php`; o `status` é sincronizado por `catalog/model/checkout/gateway_payment.php` |

Regras que valem para **todas** as tabelas desta seção:

1. **Sem FK declarada** — a integridade é mantida pelos Models (padrão do projeto, §1);
2. **Datas em UTC**, `DATETIME NOT NULL`; `date_added`/`date_modified` em toda entidade editável;
3. **Migração manual e separada**, nunca edição retroativa do `schema-complete.sql`: `tools/upgrade_kz35_jow_payments.sql` (`jow_charge`, `jow_pix_key`), `tools/upgrade_jow_payments_multigateway_core.sql` (`jow_gateway`, `payment_transaction`, `payment_gateway_event`) e `tools/upgrade_jow_payments_reconcile.sql` (coluna `provider_checkout_id`, opcional);
4. **Nenhum segredo em texto claro**: credencial de provedor só em `jow_gateway.credentials_encrypted_json`, valor a valor cifrado por `Security\SecretBox` (ADR 0094).

### `jow_gateway` — contas de provedor instaladas

Uma linha por provedor × rótulo de conta × ambiente. Sem nenhuma linha, nenhum provedor está instalado; a tela *Pagamentos › JOW* e o `GatewayRegistry` leem daqui.

| Coluna | Tipo | Notas |
| --- | --- | --- |
| `gateway_id` | INT UNSIGNED PK AI | |
| `provider_code` | VARCHAR(32) | código fixo do adapter (`mp`, `stripe`, `paypal`); **nunca** nome de classe livre |
| `account_label` | VARCHAR(64) DEFAULT `''` | rótulo humano da conta |
| `environment` | VARCHAR(8) DEFAULT `sandbox` | `sandbox` \| `live` |
| `status` | TINYINT(1) DEFAULT 0 | 0 desligado / 1 ligado. Conta desligada **continua sendo varrida** pela reconciliação: pode ter pagamento em voo |
| `public_config_json` | LONGTEXT NULL | só configuração não secreta (ex.: publishable key, modo) |
| `credentials_encrypted_json` | LONGTEXT NULL | JSON em que **cada valor** é cifrado (ADR 0094); nenhum segredo em texto claro |
| `date_added`,`date_modified` | DATETIME | |
| **Índices** | UNIQUE(`provider_code`,`account_label`,`environment`) · KEY(`provider_code`,`status`) | |

### `payment_transaction` — ledger neutro das tentativas

Uma linha por **tentativa** de cobrança num provedor (não por pedido: o mesmo pedido pode ter várias). É a fonte da verdade do núcleo novo.

| Coluna | Tipo | Notas |
| --- | --- | --- |
| `transaction_id` | BIGINT UNSIGNED PK AI | |
| `source_type` | VARCHAR(24) | `order` \| `jow_charge` (lista fechada, validada no Model) |
| `source_id` | BIGINT UNSIGNED | id na tabela de origem (`order_id` ou `charge_id`) |
| `gateway_id` | INT UNSIGNED | FK lógica → `jow_gateway` |
| `provider_code` | VARCHAR(32) | cópia do código do provedor no momento da tentativa |
| `method_code` | VARCHAR(32) | forma de cobrança **do adapter**, validada por `GatewayCapabilities::supports()`: `checkout_pro` (Mercado Pago), `checkout_session` (Stripe), `checkout_orders` (PayPal) |
| `source_currency` | CHAR(3) | moeda de origem (ISO-4217) |
| `source_fraction_digits` | TINYINT UNSIGNED | casas decimais da moeda de origem |
| `source_amount` | DECIMAL(28,8) | valor na moeda de origem, em unidade maior |
| `charge_currency` | CHAR(3) | moeda efetivamente cobrada |
| `charge_fraction_digits` | TINYINT UNSIGNED | casas da moeda cobrada |
| `charge_amount` | DECIMAL(28,8) | valor cobrado; **é comparado ao centavo** contra o aviso do provedor |
| `fx_provider_code` | VARCHAR(32) DEFAULT `''` | provedor de câmbio usado, quando há |
| `fx_quote_reference` | VARCHAR(128) NULL | `utf8mb4_bin` — referência da cotação |
| `fx_rate` | DECIMAL(36,18) NULL | taxa aplicada |
| `fx_quoted_at`,`fx_expires_at` | DATETIME NULL | validade da cotação |
| `provider_reference` | VARCHAR(128) NULL | `utf8mb4_bin` — referência que **nós** enviamos ao provedor. No Mercado Pago é o id da ordem (consultável: é o que a reconciliação usa); no Stripe e no PayPal é a nossa chave de idempotência (não consultável) |
| `provider_checkout_id` | VARCHAR(128) NULL | **aditivo e opcional** (`upgrade_jow_payments_reconcile.sql`): id do recurso consultável no provedor — sessão do Stripe, ordem do PayPal. Sem a migração a coluna não existe e essas duas tentativas ficam `missing_provider_anchor` no relatório |
| `provider_payment_id` | VARCHAR(128) NULL | id do pagamento no provedor; **imutável** depois de gravado |
| `checkout_url` | TEXT NULL | link para o cliente concluir |
| `customer_action_json` | TEXT NOT NULL | só instrução segura para o cliente; **nunca** resposta bruta do provedor |
| `public_token` | CHAR(48) `ascii_bin` UNIQUE | token bearer do link público; não registrar em log |
| `idempotency_key` | VARCHAR(128) `ascii_bin` | nossa chave; entra em UNIQUE com origem e gateway |
| `status` | VARCHAR(24) DEFAULT `pending` | vocabulário de `GatewayPaymentStatus` (ver abaixo) |
| `date_added`,`date_modified` | DATETIME | `date_modified` muda **quando o estado muda** — é o que a janela da reconciliação usa |
| **Índices** | UNIQUE(`public_token`) · UNIQUE(`source_type`,`source_id`,`gateway_id`,`idempotency_key`) · UNIQUE(`gateway_id`,`provider_reference`) · KEY(`source_type`,`source_id`,`transaction_id`) · KEY(`gateway_id`,`provider_payment_id`) · KEY(`gateway_id`,`provider_checkout_id`) · KEY(`status`,`date_added`) | |

### `payment_gateway_event` — deduplicação de avisos do provedor

Uma linha por aviso recebido. Existe para que reenvio do provedor não aplique o mesmo evento duas vezes; **o corpo do aviso não é guardado** — só o hash.

| Coluna | Tipo | Notas |
| --- | --- | --- |
| `gateway_event_id` | BIGINT UNSIGNED PK AI | |
| `gateway_id` | INT UNSIGNED | FK lógica → `jow_gateway` |
| `provider_event_id` | VARCHAR(128) `utf8mb4_bin` | id do evento no provedor; UNIQUE junto com o gateway |
| `event_type` | VARCHAR(64) DEFAULT `''` | tipo normalizado |
| `event_sha256` | CHAR(64) | SHA-256 do corpo bruto ou do evento normalizado; `payload` não é armazenado |
| `result_code` | VARCHAR(64) DEFAULT `''` | veredito da aplicação (ex.: `applied`, `duplicate`, `amount_mismatch`) |
| `status` | VARCHAR(16) DEFAULT `received` | `received` \| `processed` \| `ignored` \| `failed` |
| `received_at` | DATETIME | |
| `processed_at` | DATETIME NULL | |
| **Índices** | UNIQUE(`gateway_id`,`provider_event_id`) · KEY(`status`,`received_at`) | |

### `jow_charge` — cobrança da ferramenta interna (KZ-35)

| Coluna | Tipo | Notas |
| --- | --- | --- |
| `charge_id` | INT PK AI | |
| `description` | VARCHAR(128) | |
| `amount` | DECIMAL(10,2) | moeda maior, 2 casas |
| `currency` | VARCHAR(3) DEFAULT `BRL` | |
| `method` | VARCHAR(8) DEFAULT `pix` | **duas naturezas**: método manual (`pix`, `card`, `boleto`) quando a cobrança é manual, ou o **código do provedor** (`mp`, `stripe`, `paypal`) quando foi criada por gateway. É esse campo que `syncJowChargeStatus()` usa como filtro |
| `status` | VARCHAR(16) DEFAULT `pending` | vocabulário legado (ver abaixo) |
| `pix_payload` | TEXT NULL | BR Code copia-e-cola da cobrança Pix |
| `payer_email` | VARCHAR(96) DEFAULT `''` | |
| `due_date` | DATE NULL | |
| `paid_at` | DATETIME NULL | preenchido no primeiro estado liquidado (`COALESCE`) |
| `date_added` | DATETIME | |
| **Índices** | PK(`charge_id`) · KEY(`status`) · KEY(`date_added`) | |

### `jow_pix_key` — chaves Pix da ferramenta
`key_id` INT PK AI · `type` VARCHAR(16) *(ex.: `random`)* · `key` VARCHAR(128) UNIQUE · `holder` VARCHAR(128) DEFAULT `''` · `status` TINYINT(1) DEFAULT 1 · `date_added` DATETIME.

### `mp_payment` — pedido público (ADR 0097)
`mp_payment_id` INT UNSIGNED PK · `order_id` INT *(pedido dono)* · `mp_seller_id` INT UNSIGNED *(vendedor que recebe o líquido)* · `preference_id` VARCHAR(64) UNIQUE *(id da preference no MP, único por tentativa)* · `payment_id` BIGINT DEFAULT 0 *(0 = ainda sem aviso)* · `public_token` CHAR(48) UNIQUE · `amount` DECIMAL(15,4) · `marketplace_fee` DECIMAL(15,2) · `currency_id` CHAR(3) DEFAULT `BRL` · `status` VARCHAR(32) DEFAULT `pending` *(status do MP: `pending`/`approved`/`rejected`/`cancelled`/`refunded`)* · `init_point` TEXT · `date_added`,`date_modified`. Índices: PK, UNIQUE(`preference_id`), UNIQUE(`public_token`), KEY(`order_id`), KEY(`mp_seller_id`), KEY(`payment_id`), KEY(`status`).

### `mp_seller` — vendedor conectado via OAuth (ADR 0034/0035)
`mp_seller_id` INT UNSIGNED PK · `mp_user_id` BIGINT UNIQUE *(user_id no Mercado Pago)* · `nickname` VARCHAR(190) · `email` VARCHAR(190) · `access_token` TEXT **cifrado** (ADR 0094) · `refresh_token` TEXT **cifrado** · `scope` VARCHAR(255) · `token_expires_at` DATETIME · `status` TINYINT(1) DEFAULT 1 *(1 = conexão utilizável)* · `date_added`,`date_modified`.

### Vocabulários de `status` — e a pegadinha do `cancelled`

| Onde | Valores | Definido em |
| --- | --- | --- |
| `payment_transaction.status` | `pending`, `requires_action`, `authorized` *(aprovado no provedor e **não capturado** — no PayPal é o que sobra quando o aviso de aprovação se perde, porque a reconciliação não captura)*, `paid`, `failed`, `cancelled`, `expired`, `refunded`, `partially_refunded`, `disputed` | `GatewayPaymentStatus` (transição monotônica por `canTransition()`: `paid` não volta para `failed`; `failed`/`cancelled`/`expired`/`refunded` são finais) |
| `jow_charge.status` | `pending`, `paid`, `partial`, `canceled`, `expired`, `refunded`, `disputed` | mapa de `GatewayPayment::syncJowChargeStatus()` |
| `mp_payment.status` | status do MP: `pending`, `approved`, `rejected`, `cancelled`, `refunded` | resposta do Mercado Pago |

⚠️ **`cancelled` (duas consoantes) no ledger × `canceled` (uma) em `jow_charge`.** Não é erro de digitação: são vocabulários diferentes, e o mapa entre eles está em `syncJowChargeStatus()`. Model novo que escreva em `jow_charge` deve usar `canceled`; o que escreve em `payment_transaction` usa a constante de `GatewayPaymentStatus`.

## 10. Comunicação

### `contact_message` — formulário "Let's have a chat?"
`contact_message_id` PK · `name` VARCHAR(128) · `email` VARCHAR(96) · `telephone` VARCHAR(32) · `subject` VARCHAR(128) · `message` TEXT · `ip` VARCHAR(40) · `status` TINYINT *(0 novo, 1 lido, 2 respondido)* · `date_added`

---

## 11. Webhooks

### `webhook`
`webhook_id` PK · `trigger` VARCHAR(64) *(ex.: `order_add`, `product_*`)* · `url` VARCHAR(255) *(HTTPS obrigatório)* · `secret` VARCHAR(64) *(HMAC)* · `description` VARCHAR(255) · `status` · `date_added` · índice UNIQUE(`trigger`,`url`)

### `webhook_log` — entregas (retry/idempotência)
`webhook_log_id` PK · `webhook_id` FK · `delivery` VARCHAR(64) *(id único por disparo)* · `payload` MEDIUMTEXT · `http_code` SMALLINT · `duration` FLOAT *(segundos)* · `attempt` TINYINT · `date_added` · índice (`webhook_id`,`delivery`)

---

## 12. Infra

### `seo_url`
`seo_url_id` PK · `key` VARCHAR(64) *(rota/parâmetro, ex.: `project_id=7`)* · `value` VARCHAR(255) *(keyword, ex.: `jwa`)* · `language_id` FK · `sort_order` · UNIQUE(`key`,`language_id`) · UNIQUE(`value`,`language_id`)

### `session`
`session_id` VARCHAR(64) PK · `data` MEDIUMTEXT · `expire` DATETIME · índice(`expire`)

### `import_contact` *(staging de migração — F4-08/ADR 0032)*
`legacy_id` INT UNSIGNED PK · `name` VARCHAR(190) · `email` VARCHAR(190) · `phone` VARCHAR(60) · `subject` VARCHAR(255) · `message` TEXT · `status` VARCHAR(40) *(aceitos na importação: `new`·`replied`·`archived`·`closed`; outros ignorados)* · `ip_address` VARCHAR(64) · `created_at` DATETIME · `updated_at` DATETIME
Uso: bloco INSERT do dump legado é colado pelo usuário nesta tabela; `upgrade_f4_08_*.sql` move para `rms_order` com guarda `NOT EXISTS`. Nenhum Model/catalog a lê — não possui `*_description`.

---

## 13. Regras de uso para os agentes

1. **Antes de escrever qualquer SQL ou Model**, conferir aqui: nome da tabela, nome exato da coluna e a `*_description` correspondente;
2. Entidade nova/Coluna nova → **ADR + atualização deste doc + migração SQL** (fluxo do `agente-install`), nunca direto no Model;
3. Nomes "oficiais" destacados (`image` = capa, `name` = título de projeto) existem para acabar com a divergência `name`×`title`, `image`×`cover`;
4. As tabelas F9 de custom fields seguem migração/versionamento próprios; não modificar migration F9-02 já aplicada.
5. Este dicionário cobre o **núcleo do lançamento**, incluindo pagamentos (§9 — duas camadas, ADR 0097 e ADR 0118); e-commerce completo (produtos vendáveis, estoque, impostos) entra por ADR futuro, se o escopo crescer.

---

## Veja também

- [Auditoria-Fundacao.md](Auditoria-Fundacao.md) — lacuna L2 fechada por este doc
- [Guia-CMS.md](../guias/Guia-CMS.md) — módulos que consomem estas tabelas
- [Contrato-Bootstrap.md](Contrato-Bootstrap.md) — `DB_PREFIX` e constantes de banco
- `agents-ia/agentes/agente-install.md` — protocolo de migrações


## F10-03 — Design Themes

- `rms_design_theme`: catálogo de temas; `config`/`draft_config` contém o rascunho e `published_config` preserva a última publicação consumida pelo Catalog.
- `rms_design_theme_revision`: snapshots para auditoria e restauração sob ACL/CSRF.
- Templates são armazenados nos payloads versionados e aceitam apenas chaves fixas; não há caminhos arbitrários nem arquivos editáveis.


### `cms_block_pattern` (F9-25 — padrões pessoais BDRE)

Uma linha representa um padrão reutilizável pertencente a exatamente um administrador. Pages e Blog usam a mesma biblioteca privada e endpoints compartilhados, mas cada operação exige a permissão de edição do contexto ativo; conteúdo, CRUD e ACL de Pages/Blog não são mesclados. O idioma de origem é metadata, sem tradução automática.

| Coluna | Tipo | Regra |
| --- | --- | --- |
| `pattern_id` | `BIGINT AUTO_INCREMENT` | PK |
| `owner_user_id` | `INT` | Dono; obtido exclusivamente da sessão autenticada, sem FK |
| `name` | `VARCHAR(80)` | Obrigatório; único por proprietário |
| `source_language` | `VARCHAR(8)` | Código do idioma de origem, sem alterar conteúdo |
| `root_type` | `VARCHAR(64)` | Tipo validado da raiz; auxilia listagem e filtragem estrutural |
| `format_version` | `SMALLINT` | Versão BDRE validada |
| `content_json` | `MEDIUMTEXT` | Documento BDRE canônico, máximo 4 MiB; rejeita `custom_js` |
| `date_added`, `date_modified` | `DATETIME` | Datas de auditoria |

**Índices:** `PRIMARY(pattern_id)`, `UNIQUE(owner_user_id,name)`, `KEY(owner_user_id,date_modified)`, `KEY(owner_user_id,root_type)`. Limite de aplicação: 50 padrões por administrador. Exclusão de usuário remove linhas pertencentes na camada Model, com verificação protegida quando a tabela ainda não existe. Não declarar foreign keys; respeitar o padrão global de integridade manual.
