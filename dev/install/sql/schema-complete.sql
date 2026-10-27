-- ============================================================================
-- ReamurCMS — Schema COMPLETO consolidado (schema-complete.sql)
-- ----------------------------------------------------------------------------
-- @package   ReamurCMS
-- @author    David L. Almeida
-- @license   GPL-3.0 (https://opensource.org/licenses/GPL-3.0)
--
-- FONTE DA VERDADE: docs/Dicionario-de-Dados.md (não alterar colunas sem ADR)
--
-- Consolida, num único arquivo executável e IDEMPOTENTE:
--   • schema.sql                                  (schema base v1.0)
--   • upgrade_f2_8…f2_18  (grants de módulo + hardening rate limit)
--   • upgrade_f3_01       (seed do conteúdo real davidalmeida.xyz)
--   • upgrade_f4_01…f4_08 (grants/seed de config + staging de importação)
--   • upgrade_f5_02/f5_03 (Mercado Pago — seller + payment)
--   • upgrade_f7_01/f7_03 (Blog + grant de importação)
--   • upgrade_f8_01       (normalização canônica de ACL + session_expire)
--   • upgrade_f8_05       (aparência do painel — ADR 0047)
--   • upgrade_f9_02/f9_05/f9_12 (campos personalizados + sidecar de blocos)
--   • upgrade_f10_01 (conteúdo editorial vinculado a rotas Catalog)
--
-- COMO LER ESTE ARQUIVO
--   1. O prefixo `rms_` é o placeholder padrão — o wizard substitui pelo
--      prefixo escolhido (constante DB_PREFIX) antes de executar;
--   2. Engine/charset fixos: InnoDB + utf8mb4 (convenções do Dicionário §1);
--   3. NÃO há FOREIGN KEY constraints: a integridade referencial é garantida
--      na camada Model (cascata manual). Índices de junção existem em todas
--      as colunas de ligação;
--   4. Todas as tabelas usam CREATE TABLE IF NOT EXISTS (ADR 0004) e todos os
--      seeds usam INSERT IGNORE — rodar N vezes não gera erro nem duplica;
--   5. ATENÇÃO — o bloco "SEEDS DE CONTEÚDO (F3-01)" faz DELETE das âncoras
--      de id antes de inserir: em base com conteúdo editorial já editado,
--      comente esse bloco. Os demais seeds são estritamente aditivos;
--   6. O bloco "ACL" só tem efeito se o grupo Administrator já existir (ele é
--      semeado pelo wizard, passo 3); em base nova, os grants nascem por lá.
-- ============================================================================


-- ----------------------------------------------------------------------------
-- 1. NÚCLEO
-- ----------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `rms_setting` (
  `setting_id` INT NOT NULL AUTO_INCREMENT,
  `code`       VARCHAR(64)  NOT NULL,
  `key`        VARCHAR(128) NOT NULL,
  `value`      TEXT         NOT NULL,
  `serialized` TINYINT(1)   NOT NULL DEFAULT '0',
  PRIMARY KEY (`setting_id`),
  UNIQUE KEY `code` (`code`, `key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `rms_language` (
  `language_id` INT NOT NULL AUTO_INCREMENT,
  `name`        VARCHAR(32) NOT NULL,
  `code`        VARCHAR(8)  NOT NULL,
  `locale`      VARCHAR(32) NOT NULL,
  `sort_order`  INT         NOT NULL DEFAULT '0',
  `status`      TINYINT(1)  NOT NULL DEFAULT '1',
  PRIMARY KEY (`language_id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `rms_user_group` (
  `user_group_id` INT         NOT NULL AUTO_INCREMENT,
  `name`          VARCHAR(64) NOT NULL,
  `permission`    TEXT        NOT NULL COMMENT 'JSON: {"access": [...rotas], "modify": [...rotas]}',
  PRIMARY KEY (`user_group_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- `theme_prefs` incorporado da ADR 0047 (upgrade_f8_05): apareceu como ALTER
-- ADD COLUMN em instalação existente; aqui nasce na definição canônica.
CREATE TABLE IF NOT EXISTS `rms_user` (
  `user_id`       INT          NOT NULL AUTO_INCREMENT,
  `user_group_id` INT          NOT NULL,
  `username`      VARCHAR(32)  NOT NULL,
  `password`      VARCHAR(255) NOT NULL COMMENT 'password_hash()',
  `email`         VARCHAR(96)  NOT NULL,
  `firstname`     VARCHAR(32)  NOT NULL,
  `lastname`      VARCHAR(32)  NOT NULL,
  `image`         VARCHAR(255) NOT NULL,
  `theme_prefs`   TEXT         NULL DEFAULT NULL COMMENT 'JSON de aparência por usuário; NULL = usa o padrão global (ADR 0047)',
  `status`        TINYINT(1)   NOT NULL DEFAULT '0',
  `date_added`    DATETIME     NOT NULL,
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `username` (`username`),
  KEY `user_group_id` (`user_group_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Instalação EXISTENTE (tabela já criada sem a coluna): adiciona de forma
-- idempotente, preservando o comportamento de upgrade_f8_05.
SET @col_exists := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rms_user' AND COLUMN_NAME = 'theme_prefs'
);
SET @ddl := IF(@col_exists = 0,
  'ALTER TABLE `rms_user` ADD COLUMN `theme_prefs` TEXT NULL DEFAULT NULL AFTER `image`',
  'SELECT 1'
);
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

CREATE TABLE IF NOT EXISTS `rms_customer_group` (
  `customer_group_id` INT          NOT NULL AUTO_INCREMENT,
  `name`              VARCHAR(64)  NOT NULL,
  `description`       VARCHAR(255) NOT NULL,
  PRIMARY KEY (`customer_group_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `rms_customer` (
  `customer_id`       INT         NOT NULL AUTO_INCREMENT,
  `customer_group_id` INT         NOT NULL,
  `firstname`         VARCHAR(32) NOT NULL,
  `lastname`          VARCHAR(32) NOT NULL,
  `email`             VARCHAR(96) NOT NULL,
  `telephone`         VARCHAR(32) NOT NULL,
  `password`          VARCHAR(255) NOT NULL COMMENT 'password_hash()',
  `status`            TINYINT(1)  NOT NULL DEFAULT '0',
  `date_added`        DATETIME    NOT NULL,
  PRIMARY KEY (`customer_id`),
  UNIQUE KEY `email` (`email`),
  KEY `customer_group_id` (`customer_group_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ----------------------------------------------------------------------------
-- 2. CONTEÚDO (CMS)
-- ----------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `rms_page` (
  `page_id`       INT      NOT NULL AUTO_INCREMENT,
  `system_key`    VARCHAR(64) NULL DEFAULT NULL COMMENT 'route key estável para conteúdo editorial do Catalog',
  `status`        TINYINT(1) NOT NULL DEFAULT '0',
  `sort_order`    INT      NOT NULL DEFAULT '0',
  `date_added`    DATETIME NOT NULL,
  `date_modified` DATETIME NOT NULL,
  PRIMARY KEY (`page_id`),
  UNIQUE KEY `system_key` (`system_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `rms_page_description` (
  `page_id`          INT         NOT NULL,
  `language_id`      INT         NOT NULL,
  `title`            VARCHAR(255) NOT NULL,
  `description`      MEDIUMTEXT  NOT NULL,
  `meta_title`       VARCHAR(255) NOT NULL,
  `meta_description` VARCHAR(255) NOT NULL,
  PRIMARY KEY (`page_id`, `language_id`),
  KEY `language_id` (`language_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ----------------------------------------------------------------------------
-- 3. PORTFÓLIO
-- ----------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `rms_category` (
  `category_id`   INT          NOT NULL AUTO_INCREMENT,
  `parent_id`     INT          NOT NULL DEFAULT '0',
  `image`         VARCHAR(255) NOT NULL,
  `status`        TINYINT(1)   NOT NULL DEFAULT '0',
  `sort_order`    INT          NOT NULL DEFAULT '0',
  `date_added`    DATETIME     NOT NULL,
  `date_modified` DATETIME     NOT NULL,
  PRIMARY KEY (`category_id`),
  KEY `parent_id` (`parent_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `rms_category_description` (
  `category_id`      INT          NOT NULL,
  `language_id`      INT          NOT NULL,
  `name`             VARCHAR(255) NOT NULL,
  `description`      TEXT         NOT NULL,
  `meta_title`       VARCHAR(255) NOT NULL,
  `meta_description` VARCHAR(255) NOT NULL,
  PRIMARY KEY (`category_id`, `language_id`),
  KEY `language_id` (`language_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `rms_project` (
  `project_id`    INT          NOT NULL AUTO_INCREMENT,
  `image`         VARCHAR(255) NOT NULL COMMENT 'capa (WebP)',
  `client`        VARCHAR(128) NOT NULL,
  `url`           VARCHAR(255) NOT NULL,
  `status`        TINYINT(1)   NOT NULL DEFAULT '0',
  `featured`      TINYINT(1)   NOT NULL DEFAULT '0',
  `sort_order`    INT          NOT NULL DEFAULT '0',
  `date_added`    DATETIME     NOT NULL,
  `date_modified` DATETIME     NOT NULL,
  PRIMARY KEY (`project_id`),
  KEY `featured_status` (`featured`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `rms_project_description` (
  `project_id`       INT          NOT NULL,
  `language_id`      INT          NOT NULL,
  `name`             VARCHAR(255) NOT NULL COMMENT 'título do projeto',
  `summary`          VARCHAR(255) NOT NULL,
  `description`      TEXT         NOT NULL,
  `tag`              VARCHAR(255) NOT NULL COMMENT 'CSV de tags',
  `meta_title`       VARCHAR(255) NOT NULL,
  `meta_description` VARCHAR(255) NOT NULL,
  PRIMARY KEY (`project_id`, `language_id`),
  KEY `language_id` (`language_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `rms_project_image` (
  `project_image_id` INT          NOT NULL AUTO_INCREMENT,
  `project_id`       INT          NOT NULL,
  `image`            VARCHAR(255) NOT NULL,
  `alt`              VARCHAR(255) NOT NULL,
  `sort_order`       INT          NOT NULL DEFAULT '0',
  PRIMARY KEY (`project_image_id`),
  KEY `project_id` (`project_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `rms_project_to_category` (
  `project_id`  INT NOT NULL,
  `category_id` INT NOT NULL,
  PRIMARY KEY (`project_id`, `category_id`),
  KEY `category_id` (`category_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ----------------------------------------------------------------------------
-- 4. BLOG (F7-01 + F9-12)
-- ----------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `rms_blog_post` (
  `post_id`        INT          NOT NULL AUTO_INCREMENT,
  `image`          VARCHAR(255) NOT NULL DEFAULT '' COMMENT 'imagem destaque (path relativo ou URL)',
  `status`         TINYINT(1)   NOT NULL DEFAULT '0',
  `date_published` DATETIME     NOT NULL COMMENT 'ordenação pública (F7-02); rascunho = NOW()',
  `date_added`     DATETIME     NOT NULL,
  `date_modified`  DATETIME     NOT NULL,
  PRIMARY KEY (`post_id`),
  KEY `status_published` (`status`, `date_published`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- `description` permanece como snapshot HTML legado (compatibilidade do leitor).
CREATE TABLE IF NOT EXISTS `rms_blog_post_description` (
  `post_id`          INT          NOT NULL,
  `language_id`      INT          NOT NULL,
  `title`            VARCHAR(255) NOT NULL,
  `description`      MEDIUMTEXT   NOT NULL,
  `meta_title`       VARCHAR(255) NOT NULL,
  `meta_description` VARCHAR(255) NOT NULL,
  PRIMARY KEY (`post_id`, `language_id`),
  KEY `language_id` (`language_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Sidecar do editor de blocos (F9-12): aditivo; não altera nem migra o HTML legado.
CREATE TABLE IF NOT EXISTS `rms_blog_post_content` (
  `post_id`            INT               NOT NULL,
  `language_id`        INT               NOT NULL,
  `format_version`     SMALLINT UNSIGNED NOT NULL DEFAULT '1',
  `content_json`       MEDIUMTEXT        NOT NULL COMMENT 'árvore JSON de blocos validada pela aplicação',
  `source_hash`         CHAR(64)          NOT NULL DEFAULT '' COMMENT 'SHA-256 da fonte HTML no instante da primeira conversão',
  `legacy_html_backup` MEDIUMTEXT        NULL COMMENT 'fonte HTML preservada; não sobrescrever em edições seguintes',
  `date_added`         DATETIME          NOT NULL,
  `date_modified`      DATETIME          NOT NULL,
  PRIMARY KEY (`post_id`, `language_id`),
  KEY `language_id` (`language_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ----------------------------------------------------------------------------
-- 5. MARKETING / HOME
-- ----------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `rms_testimonial` (
  `testimonial_id` INT          NOT NULL AUTO_INCREMENT,
  `image`          VARCHAR(255) NOT NULL,
  `status`         TINYINT(1)   NOT NULL DEFAULT '0',
  `sort_order`     INT          NOT NULL DEFAULT '0',
  `date_added`     DATETIME     NOT NULL,
  PRIMARY KEY (`testimonial_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `rms_testimonial_description` (
  `testimonial_id` INT          NOT NULL,
  `language_id`    INT          NOT NULL,
  `author`         VARCHAR(128) NOT NULL,
  `company`        VARCHAR(128) NOT NULL,
  `text`           TEXT         NOT NULL,
  PRIMARY KEY (`testimonial_id`, `language_id`),
  KEY `language_id` (`language_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `rms_client_logo` (
  `client_logo_id` INT          NOT NULL AUTO_INCREMENT,
  `name`           VARCHAR(128) NOT NULL,
  `image`          VARCHAR(255) NOT NULL,
  `url`            VARCHAR(255) NOT NULL,
  `status`         TINYINT(1)   NOT NULL DEFAULT '0',
  `sort_order`     INT          NOT NULL DEFAULT '0',
  PRIMARY KEY (`client_logo_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ----------------------------------------------------------------------------
-- 6. FERRAMENTAS CONECTADAS
-- ----------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `rms_tool` (
  `tool_id`     INT          NOT NULL AUTO_INCREMENT,
  `image`       VARCHAR(255) NOT NULL COMMENT 'ícone',
  `vendor`      VARCHAR(64)  NOT NULL COMMENT 'ex.: DavidCreator',
  `url`         VARCHAR(255) NOT NULL,
  `status`      TINYINT(1)   NOT NULL DEFAULT '0',
  `sort_order`  INT          NOT NULL DEFAULT '0',
  `date_added`  DATETIME     NOT NULL,
  PRIMARY KEY (`tool_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `rms_tool_description` (
  `tool_id`     INT          NOT NULL,
  `language_id` INT          NOT NULL,
  `name`        VARCHAR(128) NOT NULL,
  `description` VARCHAR(255) NOT NULL,
  PRIMARY KEY (`tool_id`, `language_id`),
  KEY `language_id` (`language_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ----------------------------------------------------------------------------
-- 7. PEDIDOS E ORÇAMENTOS (SALE)
-- ----------------------------------------------------------------------------
-- NOTA (suposição declarada — regra 03 §5): `rms_order_status` usa PK composta
-- (order_status_id, language_id) para que o mesmo status possa ter nomes por
-- idioma, no mesmo padrão das demais tabelas *_description do sistema.

CREATE TABLE IF NOT EXISTS `rms_order_status` (
  `order_status_id` INT         NOT NULL AUTO_INCREMENT,
  `language_id`     INT         NOT NULL,
  `name`            VARCHAR(64) NOT NULL,
  PRIMARY KEY (`order_status_id`, `language_id`),
  KEY `language_id` (`language_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `rms_order` (
  `order_id`        INT            NOT NULL AUTO_INCREMENT,
  `customer_id`     INT            NOT NULL DEFAULT '0' COMMENT '0 = visitante (orçamento sem cadastro)',
  `firstname`       VARCHAR(32)    NOT NULL,
  `lastname`        VARCHAR(32)    NOT NULL,
  `email`           VARCHAR(96)    NOT NULL,
  `telephone`       VARCHAR(32)    NOT NULL COMMENT 'WhatsApp',
  `total`           DECIMAL(15,4)  NOT NULL DEFAULT '0.0000' COMMENT '0.0000 = orçamento sem preço',
  `order_status_id` INT            NOT NULL DEFAULT '0',
  `comment`         TEXT           NOT NULL COMMENT 'briefing/observações',
  `ip`              VARCHAR(40)    NOT NULL,
  `date_added`      DATETIME       NOT NULL,
  `date_modified`   DATETIME       NOT NULL,
  PRIMARY KEY (`order_id`),
  KEY `customer_id` (`customer_id`),
  KEY `order_status_id` (`order_status_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `rms_order_product` (
  `order_product_id` INT           NOT NULL AUTO_INCREMENT,
  `order_id`         INT           NOT NULL,
  `name`             VARCHAR(255)  NOT NULL COMMENT 'serviço/projeto',
  `quantity`         INT           NOT NULL DEFAULT '1',
  `price`            DECIMAL(15,4) NOT NULL DEFAULT '0.0000',
  `total`            DECIMAL(15,4) NOT NULL DEFAULT '0.0000',
  PRIMARY KEY (`order_product_id`),
  KEY `order_id` (`order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `rms_order_history` (
  `order_history_id` INT      NOT NULL AUTO_INCREMENT,
  `order_id`         INT      NOT NULL,
  `order_status_id`  INT      NOT NULL,
  `comment`          TEXT     NOT NULL,
  `date_added`       DATETIME NOT NULL,
  PRIMARY KEY (`order_history_id`),
  KEY `order_id` (`order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ----------------------------------------------------------------------------
-- 8. COMUNICAÇÃO
-- ----------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `rms_contact_message` (
  `contact_message_id` INT          NOT NULL AUTO_INCREMENT,
  `name`               VARCHAR(128) NOT NULL,
  `email`              VARCHAR(96)  NOT NULL,
  `telephone`          VARCHAR(32)  NOT NULL,
  `subject`            VARCHAR(128) NOT NULL,
  `message`            TEXT         NOT NULL,
  `ip`                 VARCHAR(40)  NOT NULL,
  `status`             TINYINT(1)   NOT NULL DEFAULT '0' COMMENT '0 novo, 1 lido, 2 respondido',
  `date_added`         DATETIME     NOT NULL,
  PRIMARY KEY (`contact_message_id`),
  KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ----------------------------------------------------------------------------
-- 9. WEBHOOKS
-- ----------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `rms_webhook` (
  `webhook_id`  INT          NOT NULL AUTO_INCREMENT,
  `trigger`     VARCHAR(64)  NOT NULL COMMENT 'ex.: order_add, product_*',
  `url`         VARCHAR(255) NOT NULL COMMENT 'HTTPS obrigatório',
  `secret`      VARCHAR(64)  NOT NULL COMMENT 'segredo HMAC por endpoint',
  `description` VARCHAR(255) NOT NULL,
  `status`      TINYINT(1)   NOT NULL DEFAULT '1',
  `date_added`  DATETIME     NOT NULL,
  PRIMARY KEY (`webhook_id`),
  UNIQUE KEY `trigger_url` (`trigger`, `url`),
  KEY `trigger_status` (`trigger`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `rms_webhook_log` (
  `webhook_log_id` INT         NOT NULL AUTO_INCREMENT,
  `webhook_id`     INT         NOT NULL,
  `delivery`       VARCHAR(64) NOT NULL COMMENT 'id único por disparo (idempotência)',
  `payload`        MEDIUMTEXT  NOT NULL,
  `http_code`      SMALLINT    NOT NULL DEFAULT '0',
  `duration`       FLOAT       NOT NULL DEFAULT '0' COMMENT 'segundos',
  `attempt`        TINYINT     NOT NULL DEFAULT '1',
  `date_added`     DATETIME    NOT NULL,
  PRIMARY KEY (`webhook_log_id`),
  KEY `webhook_delivery` (`webhook_id`, `delivery`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ----------------------------------------------------------------------------
-- 10. MERCADO PAGO (F5-02 / F5-03)
-- ----------------------------------------------------------------------------
-- Tokens em TEXTO PLANO nesta fase — decisão DOCUMENTADA no ADR 0034 §6:
-- não existe chave-mestra na casa para cifragem reversível; gestão de chave
-- (KMS/env) fica para tarefa de hardening futura.

CREATE TABLE IF NOT EXISTS `rms_mp_seller` (
  `mp_seller_id`     INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `mp_user_id`       BIGINT        NOT NULL COMMENT 'user_id do vendedor no Mercado Pago',
  `nickname`         VARCHAR(190)  NOT NULL DEFAULT '' COMMENT 'apelido público (/users/me)',
  `email`            VARCHAR(190)  NOT NULL DEFAULT '',
  `access_token`     TEXT          NOT NULL COMMENT 'token OAuth do vendedor (ver ADR 0034 §6)',
  `refresh_token`    TEXT          NOT NULL,
  `scope`            VARCHAR(255)  NOT NULL DEFAULT '',
  `token_expires_at` DATETIME      NOT NULL COMMENT 'NOW() + expires_in no momento da concessão',
  `status`           TINYINT(1)    NOT NULL DEFAULT '1' COMMENT '1 = conexão utilizável',
  `date_added`       DATETIME      NOT NULL,
  `date_modified`    DATETIME      NOT NULL,
  PRIMARY KEY (`mp_seller_id`),
  UNIQUE KEY `mp_user_id` (`mp_user_id`),
  KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Relação: um pedido pode ter ao longo da vida várias tentativas (pending
-- renew), mas apenas UMA vigente por `preference_id` (UNIQUE do MP). O link
-- público usa `public_token` (não expõe order_id na URL).
CREATE TABLE IF NOT EXISTS `rms_mp_payment` (
  `mp_payment_id`    INT UNSIGNED   NOT NULL AUTO_INCREMENT,
  `order_id`         INT            NOT NULL COMMENT 'pedido/orçamento dono do pagamento',
  `mp_seller_id`     INT UNSIGNED   NOT NULL COMMENT 'vendedor (rms_mp_seller) que recebe o líquido',
  `preference_id`    VARCHAR(64)    NOT NULL COMMENT 'id da preference no MP (único por tentativa)',
  `payment_id`       BIGINT         NOT NULL DEFAULT '0' COMMENT 'preenchido quando o MP notifica (0 = ainda sem)',
  `public_token`     CHAR(48)       NOT NULL COMMENT 'token do link público checkout/payment.pay|TOKEN',
  `amount`           DECIMAL(15,4)  NOT NULL DEFAULT '0.0000',
  `marketplace_fee`  DECIMAL(15,2)  NOT NULL DEFAULT '0.00' COMMENT 'comissão da plataforma enviada ao MP',
  `currency_id`      CHAR(3)        NOT NULL DEFAULT 'BRL',
  `status`           VARCHAR(32)    NOT NULL DEFAULT 'pending' COMMENT 'pending|approved|rejected|cancelled|refunded (status MP)',
  `init_point`       TEXT           NOT NULL COMMENT 'URL do Checkout Pro devolvida pelo MP',
  `date_added`       DATETIME       NOT NULL,
  `date_modified`    DATETIME       NOT NULL,
  PRIMARY KEY (`mp_payment_id`),
  UNIQUE KEY `preference_id` (`preference_id`),
  UNIQUE KEY `public_token` (`public_token`),
  KEY `order_id` (`order_id`),
  KEY `mp_seller_id` (`mp_seller_id`),
  KEY `payment_id` (`payment_id`),
  KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ----------------------------------------------------------------------------
-- 11. CAMPOS PERSONALIZADOS (F9-02 — ADR 0057/0058)
-- ----------------------------------------------------------------------------
-- Sem FKs (integridade no Model) e sem grant/dado/seed neste bloco.

CREATE TABLE IF NOT EXISTS `rms_custom_field_group` (
  `group_id` INT NOT NULL AUTO_INCREMENT,
  `entity_type` VARCHAR(32) NOT NULL COMMENT 'tem que ser 100% livre + implementável depois',
  `key` VARCHAR(64) NOT NULL COMMENT 'snake_case estável, nunca gerado de label',
  `name` VARCHAR(128) NOT NULL COMMENT 'nome técnico base, sem tradução',
  `status` TINYINT(1) NOT NULL DEFAULT '0',
  `sort_order` INT NOT NULL DEFAULT '0',
  `date_added` DATETIME NOT NULL,
  `date_modified` DATETIME NOT NULL,
  PRIMARY KEY (`group_id`),
  UNIQUE KEY `entity_key` (`entity_type`, `key`),
  KEY `entity_status` (`entity_type`, `status`, `sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `rms_custom_field_group_description` (
  `group_id` INT NOT NULL,
  `language_id` INT NOT NULL,
  `name` VARCHAR(128) NOT NULL,
  PRIMARY KEY (`group_id`, `language_id`),
  KEY `language_id` (`language_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `rms_custom_field` (
  `field_id` INT NOT NULL AUTO_INCREMENT,
  `entity_type` VARCHAR(32) NOT NULL,
  `group_id` INT NOT NULL,
  `key` VARCHAR(64) NOT NULL,
  `type` VARCHAR(16) NOT NULL COMMENT 'text, textarea, number, boolean, url, select',
  `localized` TINYINT(1) NOT NULL DEFAULT '0',
  `required` TINYINT(1) NOT NULL DEFAULT '0',
  `visibility` VARCHAR(16) NOT NULL DEFAULT 'admin_only' COMMENT 'admin_only ou public',
  `options` TEXT NOT NULL COMMENT 'JSON estrito se type=select; [] nos demais',
  `sort_order` INT NOT NULL DEFAULT '0',
  `date_added` DATETIME NOT NULL,
  `date_modified` DATETIME NOT NULL,
  PRIMARY KEY (`field_id`),
  UNIQUE KEY `entity_field_key` (`entity_type`, `key`),
  KEY `group_id` (`group_id`),
  KEY `entity_type_status` (`entity_type`, `visibility`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `rms_custom_field_description` (
  `field_id` INT NOT NULL,
  `language_id` INT NOT NULL,
  `label` VARCHAR(128) NOT NULL,
  `help` VARCHAR(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`field_id`, `language_id`),
  KEY `language_id` (`language_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `rms_custom_field_value` (
  `value_id` BIGINT NOT NULL AUTO_INCREMENT,
  `entity_type` VARCHAR(32) NOT NULL,
  `entity_id` INT NOT NULL,
  `field_id` INT NOT NULL,
  `language_id` INT NOT NULL COMMENT '0 = valor compartilhado; não confundir com ID de idioma real',
  `value_text` TEXT NOT NULL,
  `value_number` DECIMAL(38,6) NULL DEFAULT NULL,
  PRIMARY KEY (`value_id`),
  UNIQUE KEY `entity_field_language` (`entity_type`, `entity_id`, `field_id`, `language_id`),
  KEY `entity_list` (`entity_type`, `entity_id`),
  KEY `field_language` (`field_id`, `language_id`),
  KEY `language_id` (`language_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ----------------------------------------------------------------------------
-- 12. IMPORTAÇÃO DO CMS LEGADO (F4-08 — ADR 0032)
-- ----------------------------------------------------------------------------
-- Staging que espelha a `contact_messages` legada. O dump real entra no bloco
-- INSERT marcado; a rotina abaixo é idempotente (roda N vezes sem duplicar).

CREATE TABLE IF NOT EXISTS `rms_import_contact` (
  `legacy_id`   INT UNSIGNED  NOT NULL COMMENT 'id na contact_messages legada',
  `name`        VARCHAR(190)  NOT NULL,
  `email`       VARCHAR(190)  NOT NULL,
  `phone`       VARCHAR(60)   NOT NULL DEFAULT '',
  `subject`     VARCHAR(255)  NOT NULL DEFAULT '',
  `message`     TEXT          NOT NULL,
  `status`      VARCHAR(40)   NOT NULL DEFAULT 'new' COMMENT 'new|replied|archived|closed (outros = ignorados)',
  `ip_address`  VARCHAR(64)   NOT NULL DEFAULT '',
  `created_at`  DATETIME      NOT NULL,
  `updated_at`  DATETIME      NOT NULL,
  PRIMARY KEY (`legacy_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ▼▼▼ DADOS DO DUMP LEGADO — colar aqui (INSERT IGNORE, legacy_id = id) ▼▼▼
-- Exemplo:
-- INSERT IGNORE INTO `rms_import_contact` (`legacy_id`,`name`,`email`,`phone`,`subject`,`message`,`status`,`ip_address`,`created_at`,`updated_at`) VALUES
-- (12,'Maria Silva','maria@example.com','11999998888','Orçamento de identidade visual','Preciso de logo + paleta.','replied','201.0.12.34','2026-03-14 10:22:00','2026-03-15 09:01:00');
-- ▲▲▲ FIM DO BLOCO DE DADOS ▲▲▲▲▲▲▲▲▲▲▲▲▲▲▲▲▲▲▲▲▲▲▲▲▲▲▲▲▲▲▲▲▲▲▲▲▲▲

-- staging → rms_order (mapeamento ADR 0032)
-- name → firstname (1ª palavra, trunc 32) + lastname (restante, trunc 32);
-- subject+message → comment; status new=1 | replied=3 | archived|closed=5;
-- phone/ip truncados; customer_id=0; total=0.0000.
-- IDEMPOTENTE: NOT EXISTS (email + date_added + comment) → re-execução = 0 linhas.
INSERT INTO `rms_order`
  (`customer_id`,`firstname`,`lastname`,`email`,`telephone`,`total`,`order_status_id`,`comment`,`ip`,`date_added`,`date_modified`)
SELECT
  0,
  LEFT(TRIM(SUBSTRING_INDEX(s.`name`,' ', 1)), 32),
  IF(LOCATE(' ',TRIM(s.`name`)) = 0,
     '-',
     LEFT(TRIM(SUBSTRING(s.`name`, LOCATE(' ',TRIM(s.`name`)) + 1)), 32)),
  LEFT(TRIM(s.`email`), 96),
  LEFT(TRIM(s.`phone`), 32),
  0.0000,
  CASE s.`status`
    WHEN 'new'      THEN 1
    WHEN 'replied'  THEN 3
    WHEN 'archived' THEN 5
    WHEN 'closed'   THEN 5
  END,
  CONCAT('Assunto: ', s.`subject`, '\n\n', s.`message`),
  LEFT(s.`ip_address`, 40),
  s.`created_at`,
  s.`updated_at`
FROM `rms_import_contact` s
WHERE s.`status` IN ('new','replied','archived','closed')
  AND NOT EXISTS (
    SELECT 1 FROM `rms_order` o
     WHERE o.`email`       = LEFT(TRIM(s.`email`), 96)
       AND o.`date_added`  = s.`created_at`
       AND o.`comment`     = CONCAT('Assunto: ', s.`subject`, '\n\n', s.`message`)
  );

-- Histórico inicial — 1 entrada por pedido importado (auditoria da migração).
INSERT INTO `rms_order_history` (`order_id`,`order_status_id`,`comment`,`date_added`)
SELECT o.`order_id`, o.`order_status_id`, 'Migrado do CMS legado (F4-08/ADR 0032)', o.`date_added`
FROM `rms_import_contact` s
JOIN `rms_order` o
  ON o.`email`      = LEFT(TRIM(s.`email`), 96)
 AND o.`date_added` = s.`created_at`
 AND o.`comment`    = CONCAT('Assunto: ', s.`subject`, '\n\n', s.`message`)
WHERE s.`status` IN ('new','replied','archived','closed')
  AND NOT EXISTS (
    SELECT 1 FROM `rms_order_history` h
     WHERE h.`order_id` = o.`order_id`
       AND h.`comment`  = 'Migrado do CMS legado (F4-08/ADR 0032)'
  );


-- ----------------------------------------------------------------------------
-- 13. INFRA
-- ----------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `rms_seo_url` (
  `seo_url_id`  INT          NOT NULL AUTO_INCREMENT,
  `key`         VARCHAR(64)  NOT NULL COMMENT 'rota/parâmetro, ex.: project_id=7',
  `value`       VARCHAR(255) NOT NULL COMMENT 'keyword, ex.: jwa',
  `language_id` INT          NOT NULL,
  `sort_order`  INT          NOT NULL DEFAULT '0',
  PRIMARY KEY (`seo_url_id`),
  UNIQUE KEY `key_language` (`key`, `language_id`),
  UNIQUE KEY `value_language` (`value`, `language_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `rms_session` (
  `session_id` VARCHAR(64) NOT NULL,
  `data`       MEDIUMTEXT  NOT NULL,
  `expire`     DATETIME    NOT NULL,
  PRIMARY KEY (`session_id`),
  KEY `expire` (`expire`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Hardening F2-18 (ADR 0018) — rate limit de POSTs públicos e login.
CREATE TABLE IF NOT EXISTS `rms_rate_limit` (
  `scope`        VARCHAR(32)  NOT NULL COMMENT 'login | contact | quote',
  `ip`           VARCHAR(40)  NOT NULL,
  `attempts`     INT          NOT NULL DEFAULT 0,
  `window_start` DATETIME     NOT NULL,
  PRIMARY KEY (`scope`, `ip`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================================
-- SEEDS BASE
-- ----------------------------------------------------------------------------
-- Idiomas (ADR 0001): en-us é o PADRÃO (id=1). Os três códigos já possuem
-- diretórios reais no filesystem, então entram ATIVOS (normalização F4-02 §3).
-- ----------------------------------------------------------------------------

INSERT IGNORE INTO `rms_language` (`language_id`, `name`, `code`, `locale`, `sort_order`, `status`) VALUES
(1, 'English (US)',    'en-us', 'en_US.UTF-8', 1, 1),
(2, 'Português (BR)',  'pt-br', 'pt_BR.UTF-8', 2, 1),
(3, 'Español (ES)',    'es-eu', 'es_ES.UTF-8', 3, 1);

-- Status de pedido/orçamento em inglês (language_id = 1, padrão ADR 0001).
-- As traduções pt-br/es-eu serão inseridas junto com a ativação dos idiomas.
INSERT IGNORE INTO `rms_order_status` (`order_status_id`, `language_id`, `name`) VALUES
(1, 1, 'Received'),
(2, 1, 'Under Analysis'),
(3, 1, 'Quote Sent'),
(4, 1, 'Approved'),
(5, 1, 'Completed'),
(6, 1, 'Canceled');

-- Normalização F4-02 §3: os 3 idiomas cujos diretórios existem entram ATIVOS.
UPDATE `rms_language` SET `status` = 1 WHERE `code` IN ('en-us','pt-br','es-eu');


-- ============================================================================
-- SEEDS DE CONFIG (rms_setting)
-- ----------------------------------------------------------------------------
-- Todos usam INSERT IGNORE: re-rodar NUNCA pisa valor já salvo pela tela
-- (padrão ADR 0024/0025/0026). Consolidado de: F4-01, F4-02, F4-03, F5-02,
-- F5-03, F8-01 e F8-05.
-- ============================================================================

-- F4-01 — configurações operacionais (setting/setting)
INSERT IGNORE INTO `rms_setting` (`code`,`key`,`value`,`serialized`) VALUES
 ('config','config_name','DavidCreator',0),
 ('config','config_meta_title','David Almeida — Creative Developer',0),
 ('config','config_meta_description','Creative Developer — Sites com foco em performance e identidade visual forte para marcas e negócios.',0),
 ('config','mail_engine','mail',0),
 ('config','cache_engine','file',0),
 ('config','cache_expire','3600',0);

-- F4-02 — defaults por app (localization/language)
INSERT IGNORE INTO `rms_setting` (`code`,`key`,`value`,`serialized`) VALUES
 ('config','config_language_catalog','en-us',0),
 ('config','config_language_admin','en-us',0);

-- F4-03 — informações do site (setting/site_info); valores antes hardcoded em
-- catalog/language/{en-us,pt-br,es-eu}/common/{home,footer}.php (ADR 0026).
INSERT IGNORE INTO `rms_setting` (`code`, `key`, `value`, `serialized`) VALUES
 -- Contato (globais)
 ('config', 'config_contact_email',          'contato@davidalmeida.xyz', 0),
 ('config', 'config_contact_whatsapp_text',  '+55 11 98393-8347',        0),
 ('config', 'config_contact_whatsapp_href',  '5511983938347',            0),
 -- Endereço (por idioma — decisão do mantenedor na Tarefa F4-03)
 ('config', 'config_contact_address_en-us',  'Home and Office: São Paulo, Brazil', 0),
 ('config', 'config_contact_address_pt-br',  'Home and Office: São Paulo, Brasil', 0),
 ('config', 'config_contact_address_es-eu',  'Home and Office: São Paulo, Brasil', 0),
 -- Copyright do rodapé (por idioma; placeholder %s = ano corrente)
 ('config', 'config_copyright_en-us', '© %s All rights reserved. David L. Almeida — Creative Developer.',          0),
 ('config', 'config_copyright_pt-br', '© %s Todos os direitos reservados. David L. Almeida — Desenvolvedor Criativo.',  0),
 ('config', 'config_copyright_es-eu', '© %s Todos los derechos reservados. David L. Almeida — Desarrollador Creativo.',   0),
 -- Redes sociais (globais)
 ('config', 'config_social_facebook_href',        'https://www.facebook.com/davidalmeidadesigner/', 0),
 ('config', 'config_social_instagram_href',       'https://www.instagram.com/davidalmeidades/',      0),
 ('config', 'config_social_pinterest_href',       'https://br.pinterest.com/davidalmeidades/',       0),
 ('config', 'config_social_x_href',               'https://x.com/DavidAlmeidaDes',                   0),
 ('config', 'config_social_google_business_href', 'https://www.google.com/search?q=David+Creator',   0),
 ('config', 'config_social_redbubble_href',       'https://www.redbubble.com/people/DavidCreator/',  0);

-- F5-02 — app do Mercado Pago Marketplace (credenciais NUNCA no git)
INSERT IGNORE INTO `rms_setting` (`code`, `key`, `value`, `serialized`) VALUES
 ('config', 'mp_app_id',        '',      0),  -- client_id do app no painel MP
 ('config', 'mp_client_secret', '',      0),  -- client_secret do app (segredo do painel)
 ('config', 'mp_env',           'test', 0),  -- test | prod (ADR 0034 §4)
 ('config', 'mp_redirect_uri',  '',      0);  -- callback cadastrado no app do MP

-- F5-03 — checkout com split (marketplace_fee) + webhook (ADR 0035)
INSERT IGNORE INTO `rms_setting` (`code`, `key`, `value`, `serialized`) VALUES
 ('config', 'mp_marketplace_fee_percent', '10', 0),  -- comissão % da plataforma sobre o pedido (ADR 0035 §2)
 ('config', 'mp_webhook_secret',          '',   0);  -- segredo "assinatura de webhooks" do painel MP (opcional — ADR 0035 §6)

-- F8-01 — sessão db (adapter session/db ganha fallback defensivo)
INSERT IGNORE INTO `rms_setting` (`code`, `key`, `value`, `serialized`)
SELECT 'config', 'session_expire', '3600', 0 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `rms_setting` WHERE `code`='config' AND `key`='session_expire');

-- F8-05 — aparência do painel (ADR 0047); padrão GLOBAL institucional
INSERT IGNORE INTO `rms_setting` (`code`, `key`, `value`, `serialized`)
SELECT 'config', 'config_admin_skin', 'light', 0 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `rms_setting` WHERE `code`='config' AND `key`='config_admin_skin');

INSERT IGNORE INTO `rms_setting` (`code`, `key`, `value`, `serialized`)
SELECT 'config', 'config_admin_accent', '', 0 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `rms_setting` WHERE `code`='config' AND `key`='config_admin_accent');

INSERT IGNORE INTO `rms_setting` (`code`, `key`, `value`, `serialized`)
SELECT 'config', 'config_admin_accent_dark', '', 0 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `rms_setting` WHERE `code`='config' AND `key`='config_admin_accent_dark');

INSERT IGNORE INTO `rms_setting` (`code`, `key`, `value`, `serialized`)
SELECT 'config', 'config_admin_sidebar_variant', 'auto', 0 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `rms_setting` WHERE `code`='config' AND `key`='config_admin_sidebar_variant');

INSERT IGNORE INTO `rms_setting` (`code`, `key`, `value`, `serialized`)
SELECT 'config', 'config_admin_density', 'comfortable', 0 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `rms_setting` WHERE `code`='config' AND `key`='config_admin_density');

INSERT IGNORE INTO `rms_setting` (`code`, `key`, `value`, `serialized`)
SELECT 'config', 'config_admin_font_scale', '100', 0 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `rms_setting` WHERE `code`='config' AND `key`='config_admin_font_scale');

INSERT IGNORE INTO `rms_setting` (`code`, `key`, `value`, `serialized`)
SELECT 'config', 'config_admin_theme_mode', 'system', 0 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `rms_setting` WHERE `code`='config' AND `key`='config_admin_theme_mode');


-- ============================================================================
-- SEEDS DE CONTEÚDO (F3-01)
-- ----------------------------------------------------------------------------
-- Conteúdo REAL do davidalmeida.xyz (Produção 18/10/2026) — raspagem autorizada
-- de TEXTOS públicos; imagens não migradas (placeholders até upload via admin).
-- IDEMPOTENTE por âncora de id: DELETE + INSERT ⇒ rodar N vezes dá o mesmo
-- resultado. ATENÇÃO: em base com conteúdo editorial já editado, comente o
-- bloco inteiro — ele substitui os registros de id 1..N pelos da produção.
-- Descrições/keywords: somente EN (language_id=1); coalesce atende pt/es.
-- ============================================================================

-- ─── CATEGORIAS (3 — nomes reais da produção) ────────────────────────────────
DELETE FROM `rms_category` WHERE `category_id` BETWEEN 1 AND 3;
INSERT INTO `rms_category` (`category_id`,`parent_id`,`image`,`status`,`sort_order`,`date_added`,`date_modified`) VALUES
 (1,0,'',1,1,NOW(),NOW()),
 (2,0,'',1,2,NOW(),NOW()),
 (3,0,'',1,3,NOW(),NOW());
DELETE FROM `rms_category_description` WHERE `category_id` BETWEEN 1 AND 3;
INSERT INTO `rms_category_description` (`category_id`,`language_id`,`name`,`description`,`meta_title`,`meta_description`) VALUES
 (1,1,'Visual Identity','Logos, brand systems and identity manuals.','Visual Identity','Visual identity projects by David L. Almeida.'),
 (2,1,'Brand','Brand design, art direction and campaign look.','Brand','Brand design projects by David L. Almeida.'),
 (3,1,'Illustration','Digital art and illustration work.','Illustration','Illustration projects by David L. Almeida.');

-- ─── PROJETOS (10 — nomes/slugs reais da produção; keywords preservadas p/ SEO) ─
DELETE FROM `rms_project` WHERE `project_id` BETWEEN 1 AND 10;
INSERT INTO `rms_project` (`project_id`,`image`,`client`,`url`,`status`,`featured`,`sort_order`,`date_added`,`date_modified`) VALUES
 (1,'','Juvenile World Academy','',1,1,1,NOW(),NOW()),
 (2,'','','',1,1,2,NOW(),NOW()),
 (3,'','','',1,1,3,NOW(),NOW()),
 (4,'','','',1,1,4,NOW(),NOW()),
 (5,'','Hiperion Gamer','https://hiperiongamer.com/',1,0,5,NOW(),NOW()),
 (6,'','','',1,0,6,NOW(),NOW()),
 (7,'','','',1,0,7,NOW(),NOW()),
 (8,'','','',1,0,8,NOW(),NOW()),
 (9,'','','',1,0,9,NOW(),NOW()),
 (10,'','','',1,0,10,NOW(),NOW());

DELETE FROM `rms_project_description` WHERE `project_id` BETWEEN 1 AND 10;
INSERT INTO `rms_project_description` (`project_id`,`language_id`,`name`,`summary`,`description`,`tag`,`meta_title`,`meta_description`) VALUES
 (1,1,'JWA','Visual identity for Juvenile World Academy.','Complete visual identity for the Juvenile World Academy — manual, stationery and applications, from the logo to colors, typography and graphics.','visual-identity,logo,brand-manual','JWA - Juvenile World Academy','Visual identity project for Juvenile World Academy by David L. Almeida.'),
 (2,1,'Steel of Death','Visual identity for the band Steel of Death.','Visual identity for Steel of Death — logo, album cover and brand applications.','visual-identity,band,album-cover','Steel of Death','Visual identity for the band Steel of Death by David L. Almeida.'),
 (3,1,'Farmer Life Simulation','Brand design for the game Farmer Life Simulation.','Brand design for Farmer Life Simulation — logo and communication look for the farming simulator game.','brand,game,logo','Farmer Life Simulation','Brand design for the game Farmer Life Simulation by David L. Almeida.'),
 (4,1,'Chameleon Digital Art','Digital art illustration project.','Chameleon digital art — color study and character illustration.','illustration,digital-art,chameleon','Chameleon Digital Art','Chameleon digital art illustration by David L. Almeida.'),
 (5,1,'Hiperion Gamer','Brand design for Hiperion Gamer.','Brand design for Hiperion Gamer — external gaming portal.','brand,gaming,logo','Hiperion Gamer','Brand design for Hiperion Gamer by David L. Almeida.'),
 (6,1,'RC - Rogério Consultoria','Visual identity for RC Rogério Consultoria.','Visual identity for RC - Rogério Consultoria (consulting brand) — logo and corporate applications.','visual-identity,consulting,logo','RC - Rogério Consultoria','Visual identity for RC Rogério Consultoria by David L. Almeida.'),
 (7,1,'Pitbull Colors','Digital art for Pitbull Colors.','Pitbull color tint study — digital artwork.','illustration,digital-art,color-study','Pitbull Colors','Pitbull Colors digital art by David L. Almeida.'),
 (8,1,'Cat Fe - Digital Art','Digital art portrait.','Cat Fe digital art — character portrait study.','illustration,digital-art,portrait','Cat Fe - Digital Art','Cat Fe digital art by David L. Almeida.'),
 (9,1,'Classic Car Race','Digital art of a classic race car.','Classic car race artwork — vehicle illustration study.','illustration,digital-art,vehicle','Classic Car Race','Classic car race digital art by David L. Almeida.'),
 (10,1,'Selva Dev','Visual identity for Selva Dev.','Visual identity for the Selva Dev developer brand — logo and brand applications.','visual-identity,developer,logo','Selva Dev','Visual identity for Selva Dev by David L. Almeida.');

DELETE FROM `rms_project_to_category` WHERE `project_id` BETWEEN 1 AND 10;
INSERT INTO `rms_project_to_category` (`project_id`,`category_id`) VALUES
 (1,1),(2,1),(6,1),(10,1),   -- Visual Identity
 (3,2),(5,2),                -- Brand
 (4,3),(7,3),(8,3),(9,3);    -- Illustration

-- SEO do portfólio: keywords EXATAS da produção (inclui o legado "pitbul-color"
-- e as curtas "farmer"/"chameleon"/"rc"/"classic-car" — preservadas p/ §9 da Análise).
DELETE FROM `rms_seo_url` WHERE `key` LIKE 'project_id=%' OR `key` IN ('page_id=1','page_id=2') OR `value` IN ('cookies','politicas-internas');
INSERT INTO `rms_seo_url` (`key`,`value`,`language_id`,`sort_order`) VALUES
 ('project_id=1','jwa',1,0),
 ('project_id=2','steel-of-death',1,0),
 ('project_id=3','farmer',1,0),
 ('project_id=4','chameleon',1,0),
 ('project_id=5','hiperion-gamer',1,0),
 ('project_id=6','rc',1,0),
 ('project_id=7','pitbul-color',1,0),
 ('project_id=8','cat-fe',1,0),
 ('project_id=9','classic-car',1,0),
 ('project_id=10','selva-dev',1,0);

-- ─── DEPOIMENTOS (3 — textos REAIS da produção) ─────────────────────────────
DELETE FROM `rms_testimonial` WHERE `testimonial_id` BETWEEN 1 AND 3;
INSERT INTO `rms_testimonial` (`testimonial_id`,`image`,`status`,`sort_order`,`date_added`) VALUES
 (1,'',1,1,NOW()),(2,'',1,2,NOW()),(3,'',1,3,NOW());
DELETE FROM `rms_testimonial_description` WHERE `testimonial_id` BETWEEN 1 AND 3;
INSERT INTO `rms_testimonial_description` (`testimonial_id`,`language_id`,`author`,`company`,`text`) VALUES
 (1,1,'Silvio Robson B. Lima','GR Produtora','Best professional in the field I''ve ever met. Tact for details, technical eye and fair price.'),
 (2,1,'Pedrinho Seus Brinquedos','','David is a sensational professional, I highly recommend him!'),
 (3,1,'Bruno Reis','','About the Work I have no words to describe the work of this excellent professional, knowledge attention incredible quality a person with enormous knowledge willing to always give the best of what you ask for, calmly and coherently showing that your idea can be even better.');

-- ─── CLIENTES (5 — nomes reais; logos sobem via admin depois) ────────────────
DELETE FROM `rms_client_logo` WHERE `client_logo_id` BETWEEN 1 AND 5;
INSERT INTO `rms_client_logo` (`client_logo_id`,`name`,`image`,`url`,`status`,`sort_order`) VALUES
 (1,'SEVEN A','','',1,1),
 (2,'Alex Cristopher','','',1,2),
 (3,'BDRProPlayer','','',1,3),
 (4,'AJL Sorvete Express','','',1,4),
 (5,'Horror na Veia','','',1,5);

-- ─── FERRAMENTAS (4 — descrições reais, traduzidas p/ EN padrão ADR 0001) ────
DELETE FROM `rms_tool` WHERE `tool_id` BETWEEN 1 AND 4;
-- URLs externas reais ficam a cargo do mantenedor (produção resolve via
-- /catalog/tools/open?id=N — destino final não consta no HTML público; NÃO inventar).
INSERT INTO `rms_tool` (`tool_id`,`image`,`vendor`,`url`,`status`,`sort_order`,`date_added`) VALUES
 (1,'','DavidCreator','',1,1,NOW()),
 (2,'','DavidCreator','',1,2,NOW()),
 (3,'','DavidCreator','',1,3,NOW()),
 (4,'','DavidCreator','',1,4,NOW());
DELETE FROM `rms_tool_description` WHERE `tool_id` BETWEEN 1 AND 4;
INSERT INTO `rms_tool_description` (`tool_id`,`language_id`,`name`,`description`) VALUES
 (1,1,'Fluxa','Financial management and indicator tracking tool.'),
 (2,1,'Quotia','Platform to request and organize quotes in a single flow.'),
 (3,1,'Vertex','Free resume builder focused on clarity and professional presentation.'),
 (4,1,'Solis','Strategic generator for social media content planning and posting.');
-- Keywords SEO das páginas dedicadas (F3-04): /fluxa … → tool/tool.info|N.
DELETE FROM `rms_seo_url` WHERE `key` IN ('tool_id=1','tool_id=2','tool_id=3','tool_id=4') OR `value` IN ('fluxa','quotia','vertex','solis');
INSERT INTO `rms_seo_url` (`key`,`value`,`language_id`,`sort_order`) VALUES
 ('tool_id=1','fluxa',1,0),
 ('tool_id=2','quotia',1,0),
 ('tool_id=3','vertex',1,0),
 ('tool_id=4','solis',1,0);

-- ─── PÁGINAS LEGAIS (2 — conteúdo real da produção) ──────────────────────────
DELETE FROM `rms_page` WHERE `page_id` BETWEEN 1 AND 2;
INSERT INTO `rms_page` (`page_id`,`status`,`sort_order`,`date_added`,`date_modified`) VALUES
 (1,1,1,NOW(),NOW()),(2,1,2,NOW(),NOW());
DELETE FROM `rms_page_description` WHERE `page_id` BETWEEN 1 AND 2;
INSERT INTO `rms_page_description` (`page_id`,`language_id`,`title`,`description`,`meta_title`,`meta_description`) VALUES
 (1,1,'Cookie Policy','<h3>1. What cookies are</h3><p>Cookies are small files stored in your browser to keep essential features running and, when allowed, collect usage data for analytics.</p><h3>2. How we use cookies</h3><p>We use necessary cookies for technical operation and analytics cookies only when you allow that category.</p><h3>3. Categories and examples</h3><p><strong>Necessary</strong> — PHPSESSID, dc_cookie_consent: session, security, session language and storing your consent choice (session / up to 180 days).</p><p><strong>Analytics (optional)</strong> — _ga, _gid (Google Analytics): measure traffic, performance and aggregated behavior to improve the site (retention per provider policy).</p><p><strong>Marketing (optional)</strong> — currently not used.</p><h3>4. Your rights and control</h3><p>You can accept, reject or update your preferences at any time using the cookie button in the corner of the page.</p><h3>5. Privacy contact</h3><p>For privacy and personal data questions, use the <a href="/#contact">site contact area</a>.</p>','Cookie Policy','How cookies are used on davidalmeida.xyz.'),
 (2,1,'Internal Policies','<h3>1. Operational security</h3><p>We apply access controls, event monitoring and periodic reviews to reduce technical risk and protect the platform.</p><h3>2. Data handling and retention</h3><p>Contact data is used only for support and professional relationship. Storage follows minimization and necessary retention principles.</p><h3>3. Incident response</h3><p>In relevant incidents, we activate technical analysis, containment, traceability and communication according to impact level.</p><h3>4. Transparency and rights</h3><p>Privacy and personal data requests can be sent through the official contact channels of this website.</p>','Internal Policies','Internal policies of davidalmeida.xyz.');
INSERT INTO `rms_seo_url` (`key`,`value`,`language_id`,`sort_order`) VALUES
 ('page_id=1','cookies',1,0),
 ('page_id=2','politicas-internas',1,0);

-- Rotas públicas do Catalog que recebem conteúdo editorial administrável.
-- O conteúdo de negócio continua nos módulos Blog, Portfolio, Ferramentas e Formulários.
CREATE TEMPORARY TABLE `tmp_f10_catalog_page` (
  `system_key` VARCHAR(64) NOT NULL PRIMARY KEY,
  `title_en` VARCHAR(255) NOT NULL,
  `title_pt` VARCHAR(255) NOT NULL,
  `title_es` VARCHAR(255) NOT NULL,
  `sort_order` INT NOT NULL
) ENGINE=MEMORY DEFAULT CHARSET=utf8mb4;
INSERT INTO `tmp_f10_catalog_page` VALUES
('home','Home','Início','Inicio',10),
('portfolio','Portfolio','Portfólio','Portafolio',20),
('portfolio_detail','Project detail','Detalhe do projeto','Detalle del proyecto',30),
('blog','Blog','Blog','Blog',40),
('blog_post','Blog post','Post do Blog','Artículo del Blog',50),
('tools','Tools','Ferramentas','Herramientas',60),
('tool_detail','Tool detail','Detalhe da ferramenta','Detalle de herramienta',70),
('contact','Contact','Contato','Contacto',80),
('quote','Quote request','Pedido de orçamento','Solicitud de presupuesto',90),
('not_found','Not found','Não encontrado','No encontrado',100);
INSERT IGNORE INTO `rms_page` (`system_key`,`status`,`sort_order`,`date_added`,`date_modified`)
SELECT `system_key`,1,`sort_order`,NOW(),NOW() FROM `tmp_f10_catalog_page`;
INSERT IGNORE INTO `rms_page_description` (`page_id`,`language_id`,`title`,`description`,`meta_title`,`meta_description`)
SELECT p.`page_id`,l.`language_id`,
       CASE l.`code` WHEN 'pt-br' THEN s.`title_pt` WHEN 'es-eu' THEN s.`title_es` ELSE s.`title_en` END,
       '', '', ''
  FROM `rms_page` p JOIN `tmp_f10_catalog_page` s ON s.`system_key`=p.`system_key`
 CROSS JOIN `rms_language` l WHERE l.`code` IN ('en-us','pt-br','es-eu');
DROP TEMPORARY TABLE `tmp_f10_catalog_page`;


-- ============================================================================
-- ACL — ROTAS DO GRUPO ADMINISTRATOR
-- ----------------------------------------------------------------------------
-- Consolida os grants esparsos dos upgrades F2-8…F2-16, F4-01..F4-03, F5-02,
-- F7-01/F7-03, F8-05 e F9-05. Todo o bloco é IDEMPOTENTE (guarda JSON_SEARCH).
--
-- Observações:
--   • Alvo: `name='Administrator' OR user_group_id=1` (idem upgrade_f8_05),
--     cobrindo instalações cujo grupo foi renomeado/slugado;
--   • Guarda com JSON_EXTRACT (idiom v2): a forma antiga
--     JSON_SEARCH(permission,'one',X,NULL,'$.access') era silenciosamente
--     ineficaz em MariaDB ≥10.4 (4º arg de path com escape NULL);
--   • Em instalação NOVA o grupo Administrator é semeado pelo wizard (passo 3)
--     já com as 12 rotas canônicas — este bloco serve para base EXISTENTE.
-- ============================================================================

-- Hygiene F8-01: sanea "user\/user" (backslash literal) gravado por wizard que
-- caiu no branch mysqli. Inócuo quando não há match.
UPDATE `rms_user_group`
   SET `permission` = REPLACE(`permission`, '\\/', '/')
 WHERE `permission` LIKE '%\\\\/%';

-- user/user (seed wizard) — user/user_group (seed wizard)
UPDATE `rms_user_group`
SET `permission` = JSON_ARRAY_APPEND(`permission`, '$.access', 'user/user')
WHERE (`name` = 'Administrator' OR `user_group_id` = 1)
  AND JSON_SEARCH(JSON_EXTRACT(`permission`, '$.access'), 'one', 'user/user') IS NULL;
UPDATE `rms_user_group`
SET `permission` = JSON_ARRAY_APPEND(`permission`, '$.modify', 'user/user')
WHERE (`name` = 'Administrator' OR `user_group_id` = 1)
  AND JSON_SEARCH(JSON_EXTRACT(`permission`, '$.modify'), 'one', 'user/user') IS NULL;

UPDATE `rms_user_group`
SET `permission` = JSON_ARRAY_APPEND(`permission`, '$.access', 'user/user_group')
WHERE (`name` = 'Administrator' OR `user_group_id` = 1)
  AND JSON_SEARCH(JSON_EXTRACT(`permission`, '$.access'), 'one', 'user/user_group') IS NULL;
UPDATE `rms_user_group`
SET `permission` = JSON_ARRAY_APPEND(`permission`, '$.modify', 'user/user_group')
WHERE (`name` = 'Administrator' OR `user_group_id` = 1)
  AND JSON_SEARCH(JSON_EXTRACT(`permission`, '$.modify'), 'one', 'user/user_group') IS NULL;

-- catalog/project (F2-08)
UPDATE `rms_user_group`
SET `permission` = JSON_ARRAY_APPEND(`permission`, '$.access', 'catalog/project')
WHERE (`name` = 'Administrator' OR `user_group_id` = 1)
  AND JSON_SEARCH(JSON_EXTRACT(`permission`, '$.access'), 'one', 'catalog/project') IS NULL;
UPDATE `rms_user_group`
SET `permission` = JSON_ARRAY_APPEND(`permission`, '$.modify', 'catalog/project')
WHERE (`name` = 'Administrator' OR `user_group_id` = 1)
  AND JSON_SEARCH(JSON_EXTRACT(`permission`, '$.modify'), 'one', 'catalog/project') IS NULL;

-- catalog/category (F2-09)
UPDATE `rms_user_group`
SET `permission` = JSON_ARRAY_APPEND(`permission`, '$.access', 'catalog/category')
WHERE (`name` = 'Administrator' OR `user_group_id` = 1)
  AND JSON_SEARCH(JSON_EXTRACT(`permission`, '$.access'), 'one', 'catalog/category') IS NULL;
UPDATE `rms_user_group`
SET `permission` = JSON_ARRAY_APPEND(`permission`, '$.modify', 'catalog/category')
WHERE (`name` = 'Administrator' OR `user_group_id` = 1)
  AND JSON_SEARCH(JSON_EXTRACT(`permission`, '$.modify'), 'one', 'catalog/category') IS NULL;

-- cms/page (F2-10)
UPDATE `rms_user_group`
SET `permission` = JSON_ARRAY_APPEND(`permission`, '$.access', 'cms/page')
WHERE (`name` = 'Administrator' OR `user_group_id` = 1)
  AND JSON_SEARCH(JSON_EXTRACT(`permission`, '$.access'), 'one', 'cms/page') IS NULL;
UPDATE `rms_user_group`
SET `permission` = JSON_ARRAY_APPEND(`permission`, '$.modify', 'cms/page')
WHERE (`name` = 'Administrator' OR `user_group_id` = 1)
  AND JSON_SEARCH(JSON_EXTRACT(`permission`, '$.modify'), 'one', 'cms/page') IS NULL;

-- marketing/testimonial (F2-11)
UPDATE `rms_user_group`
SET `permission` = JSON_ARRAY_APPEND(`permission`, '$.access', 'marketing/testimonial')
WHERE (`name` = 'Administrator' OR `user_group_id` = 1)
  AND JSON_SEARCH(JSON_EXTRACT(`permission`, '$.access'), 'one', 'marketing/testimonial') IS NULL;
UPDATE `rms_user_group`
SET `permission` = JSON_ARRAY_APPEND(`permission`, '$.modify', 'marketing/testimonial')
WHERE (`name` = 'Administrator' OR `user_group_id` = 1)
  AND JSON_SEARCH(JSON_EXTRACT(`permission`, '$.modify'), 'one', 'marketing/testimonial') IS NULL;

-- marketing/client_logo (F2-12)
UPDATE `rms_user_group`
SET `permission` = JSON_ARRAY_APPEND(`permission`, '$.access', 'marketing/client_logo')
WHERE (`name` = 'Administrator' OR `user_group_id` = 1)
  AND JSON_SEARCH(JSON_EXTRACT(`permission`, '$.access'), 'one', 'marketing/client_logo') IS NULL;
UPDATE `rms_user_group`
SET `permission` = JSON_ARRAY_APPEND(`permission`, '$.modify', 'marketing/client_logo')
WHERE (`name` = 'Administrator' OR `user_group_id` = 1)
  AND JSON_SEARCH(JSON_EXTRACT(`permission`, '$.modify'), 'one', 'marketing/client_logo') IS NULL;

-- tool/tool (F2-13)
UPDATE `rms_user_group`
SET `permission` = JSON_ARRAY_APPEND(`permission`, '$.access', 'tool/tool')
WHERE (`name` = 'Administrator' OR `user_group_id` = 1)
  AND JSON_SEARCH(JSON_EXTRACT(`permission`, '$.access'), 'one', 'tool/tool') IS NULL;
UPDATE `rms_user_group`
SET `permission` = JSON_ARRAY_APPEND(`permission`, '$.modify', 'tool/tool')
WHERE (`name` = 'Administrator' OR `user_group_id` = 1)
  AND JSON_SEARCH(JSON_EXTRACT(`permission`, '$.modify'), 'one', 'tool/tool') IS NULL;

-- sale/order (F2-14)
UPDATE `rms_user_group`
SET `permission` = JSON_ARRAY_APPEND(`permission`, '$.access', 'sale/order')
WHERE (`name` = 'Administrator' OR `user_group_id` = 1)
  AND JSON_SEARCH(JSON_EXTRACT(`permission`, '$.access'), 'one', 'sale/order') IS NULL;
UPDATE `rms_user_group`
SET `permission` = JSON_ARRAY_APPEND(`permission`, '$.modify', 'sale/order')
WHERE (`name` = 'Administrator' OR `user_group_id` = 1)
  AND JSON_SEARCH(JSON_EXTRACT(`permission`, '$.modify'), 'one', 'sale/order') IS NULL;

-- communication/contact_message (F2-15)
UPDATE `rms_user_group`
SET `permission` = JSON_ARRAY_APPEND(`permission`, '$.access', 'communication/contact_message')
WHERE (`name` = 'Administrator' OR `user_group_id` = 1)
  AND JSON_SEARCH(JSON_EXTRACT(`permission`, '$.access'), 'one', 'communication/contact_message') IS NULL;
UPDATE `rms_user_group`
SET `permission` = JSON_ARRAY_APPEND(`permission`, '$.modify', 'communication/contact_message')
WHERE (`name` = 'Administrator' OR `user_group_id` = 1)
  AND JSON_SEARCH(JSON_EXTRACT(`permission`, '$.modify'), 'one', 'communication/contact_message') IS NULL;

-- setting/webhook (F2-16)
UPDATE `rms_user_group`
SET `permission` = JSON_ARRAY_APPEND(`permission`, '$.access', 'setting/webhook')
WHERE (`name` = 'Administrator' OR `user_group_id` = 1)
  AND JSON_SEARCH(JSON_EXTRACT(`permission`, '$.access'), 'one', 'setting/webhook') IS NULL;
UPDATE `rms_user_group`
SET `permission` = JSON_ARRAY_APPEND(`permission`, '$.modify', 'setting/webhook')
WHERE (`name` = 'Administrator' OR `user_group_id` = 1)
  AND JSON_SEARCH(JSON_EXTRACT(`permission`, '$.modify'), 'one', 'setting/webhook') IS NULL;

-- setting/setting (F4-01)
UPDATE `rms_user_group`
SET `permission` = JSON_ARRAY_APPEND(`permission`, '$.access', 'setting/setting')
WHERE (`name` = 'Administrator' OR `user_group_id` = 1)
  AND JSON_SEARCH(JSON_EXTRACT(`permission`, '$.access'), 'one', 'setting/setting') IS NULL;
UPDATE `rms_user_group`
SET `permission` = JSON_ARRAY_APPEND(`permission`, '$.modify', 'setting/setting')
WHERE (`name` = 'Administrator' OR `user_group_id` = 1)
  AND JSON_SEARCH(JSON_EXTRACT(`permission`, '$.modify'), 'one', 'setting/setting') IS NULL;

-- localization/language (F4-02)
UPDATE `rms_user_group`
SET `permission` = JSON_ARRAY_APPEND(`permission`, '$.access', 'localization/language')
WHERE (`name` = 'Administrator' OR `user_group_id` = 1)
  AND JSON_SEARCH(JSON_EXTRACT(`permission`, '$.access'), 'one', 'localization/language') IS NULL;
UPDATE `rms_user_group`
SET `permission` = JSON_ARRAY_APPEND(`permission`, '$.modify', 'localization/language')
WHERE (`name` = 'Administrator' OR `user_group_id` = 1)
  AND JSON_SEARCH(JSON_EXTRACT(`permission`, '$.modify'), 'one', 'localization/language') IS NULL;

-- setting/site_info (F4-03)
UPDATE `rms_user_group`
SET `permission` = JSON_ARRAY_APPEND(`permission`, '$.access', 'setting/site_info')
WHERE (`name` = 'Administrator' OR `user_group_id` = 1)
  AND JSON_SEARCH(JSON_EXTRACT(`permission`, '$.access'), 'one', 'setting/site_info') IS NULL;
UPDATE `rms_user_group`
SET `permission` = JSON_ARRAY_APPEND(`permission`, '$.modify', 'setting/site_info')
WHERE (`name` = 'Administrator' OR `user_group_id` = 1)
  AND JSON_SEARCH(JSON_EXTRACT(`permission`, '$.modify'), 'one', 'setting/site_info') IS NULL;

-- setting/marketplace (F5-02)
UPDATE `rms_user_group`
SET `permission` = JSON_ARRAY_APPEND(`permission`, '$.access', 'setting/marketplace')
WHERE (`name` = 'Administrator' OR `user_group_id` = 1)
  AND JSON_SEARCH(JSON_EXTRACT(`permission`, '$.access'), 'one', 'setting/marketplace') IS NULL;
UPDATE `rms_user_group`
SET `permission` = JSON_ARRAY_APPEND(`permission`, '$.modify', 'setting/marketplace')
WHERE (`name` = 'Administrator' OR `user_group_id` = 1)
  AND JSON_SEARCH(JSON_EXTRACT(`permission`, '$.modify'), 'one', 'setting/marketplace') IS NULL;

-- blog/blog (F7-01)
UPDATE `rms_user_group`
SET `permission` = JSON_ARRAY_APPEND(`permission`, '$.access', 'blog/blog')
WHERE (`name` = 'Administrator' OR `user_group_id` = 1)
  AND JSON_SEARCH(JSON_EXTRACT(`permission`, '$.access'), 'one', 'blog/blog') IS NULL;
UPDATE `rms_user_group`
SET `permission` = JSON_ARRAY_APPEND(`permission`, '$.modify', 'blog/blog')
WHERE (`name` = 'Administrator' OR `user_group_id` = 1)
  AND JSON_SEARCH(JSON_EXTRACT(`permission`, '$.modify'), 'one', 'blog/blog') IS NULL;

-- blog/import (F7-03)
UPDATE `rms_user_group`
SET `permission` = JSON_ARRAY_APPEND(`permission`, '$.access', 'blog/import')
WHERE (`name` = 'Administrator' OR `user_group_id` = 1)
  AND JSON_SEARCH(JSON_EXTRACT(`permission`, '$.access'), 'one', 'blog/import') IS NULL;
UPDATE `rms_user_group`
SET `permission` = JSON_ARRAY_APPEND(`permission`, '$.modify', 'blog/import')
WHERE (`name` = 'Administrator' OR `user_group_id` = 1)
  AND JSON_SEARCH(JSON_EXTRACT(`permission`, '$.modify'), 'one', 'blog/import') IS NULL;

-- setting/appearance (F8-05 — ADR 0047)
UPDATE `rms_user_group`
SET `permission` = JSON_ARRAY_APPEND(`permission`, '$.access', 'setting/appearance')
WHERE (`name` = 'Administrator' OR `user_group_id` = 1)
  AND JSON_SEARCH(JSON_EXTRACT(`permission`, '$.access'), 'one', 'setting/appearance') IS NULL;
UPDATE `rms_user_group`
SET `permission` = JSON_ARRAY_APPEND(`permission`, '$.modify', 'setting/appearance')
WHERE (`name` = 'Administrator' OR `user_group_id` = 1)
  AND JSON_SEARCH(JSON_EXTRACT(`permission`, '$.modify'), 'one', 'setting/appearance') IS NULL;

-- custom_field/definition (F9-05 — grant dormente até a rota F9-06)
UPDATE `rms_user_group`
SET `permission` = JSON_ARRAY_APPEND(`permission`, '$.access', 'custom_field/definition')
WHERE (`name` = 'Administrator' OR `user_group_id` = 1)
  AND JSON_SEARCH(JSON_EXTRACT(`permission`, '$.access'), 'one', 'custom_field/definition') IS NULL;
UPDATE `rms_user_group`
SET `permission` = JSON_ARRAY_APPEND(`permission`, '$.modify', 'custom_field/definition')
WHERE (`name` = 'Administrator' OR `user_group_id` = 1)
  AND JSON_SEARCH(JSON_EXTRACT(`permission`, '$.modify'), 'one', 'custom_field/definition') IS NULL;

-- ============================================================================
-- FIM — schema-complete.sql
-- Consolidação aditiva e idempotente de schema.sql + todos os upgrade_*.sql
-- da pasta install/sql. Histórico de alterações: docs/Dicionario-de-Dados.md.
-- ============================================================================
