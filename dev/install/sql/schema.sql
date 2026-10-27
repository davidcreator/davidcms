-- ============================================================================
-- ReamurCMS — Schema Inicial v1.0
-- ----------------------------------------------------------------------------
-- @package   ReamurCMS
-- @author    David L. Almeida
-- @license   GPL-3.0 (https://opensource.org/licenses/GPL-3.0)
--
-- FONTE DA VERDADE: docs/Dicionario-de-Dados.md (não alterar colunas sem ADR)
--
-- NOTAS DE INSTALAÇÃO (wizard install/):
--   1. O prefixo `rms_` é o placeholder padrão — o wizard substitui pelo
--      prefixo escolhido pelo usuário (constante DB_PREFIX) antes de executar;
--   2. Engine/charset fixos: InnoDB + utf8mb4 (convenções do Dicionário §1);
--   3. NÃO há FOREIGN KEY constraints: a integridade referencial é garantida
--      na camada Model (cascata manual — ver agents-ia/modelos/model.php.tpl).
--      Índices de junção existem em todas as colunas de ligação;
--   4. Todas as tabelas usam CREATE TABLE IF NOT EXISTS (idempotente — ADR 0004);
--   5. Seeds incluídos: idiomas (ADR 0001) e status de pedido (inglês, padrão).
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

CREATE TABLE IF NOT EXISTS `rms_user` (
  `user_id`       INT          NOT NULL AUTO_INCREMENT,
  `user_group_id` INT          NOT NULL,
  `username`      VARCHAR(32)  NOT NULL,
  `password`      VARCHAR(255) NOT NULL COMMENT 'password_hash()',
  `email`         VARCHAR(96)  NOT NULL,
  `firstname`     VARCHAR(32)  NOT NULL,
  `lastname`      VARCHAR(32)  NOT NULL,
  `image`         VARCHAR(255) NOT NULL,
  `status`        TINYINT(1)   NOT NULL DEFAULT '0',
  `date_added`    DATETIME     NOT NULL,
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `username` (`username`),
  KEY `user_group_id` (`user_group_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
-- 4. MARKETING / HOME
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
-- 5. FERRAMENTAS CONECTADAS
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
-- 6. PEDIDOS E ORÇAMENTOS (SALE)
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
-- 7. COMUNICAÇÃO
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
-- 8. WEBHOOKS
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
-- 9. INFRA
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

-- ============================================================================
-- SEEDS
-- ----------------------------------------------------------------------------
-- Idiomas (ADR 0001): en-us é o PADRÃO (id=1, ativo). pt-br e es-eu entram
-- INATIVOS (status=0) até as traduções estarem completas — o go-live ativa.
-- ----------------------------------------------------------------------------

INSERT INTO `rms_language` (`language_id`, `name`, `code`, `locale`, `sort_order`, `status`) VALUES
(1, 'English (US)',    'en-us', 'en_US.UTF-8', 1, 1),
(2, 'Português (BR)',  'pt-br', 'pt_BR.UTF-8', 2, 1),
(3, 'Español (ES)',    'es-eu', 'es_ES.UTF-8', 3, 1);

-- Conteúdo editorial associado às rotas públicas (F10-01). Os campos começam
-- vazios; o Admin Pages passa a editá-los por idioma. Dados de domínio não são copiados.
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

-- Status de pedido/orçamento em inglês (language_id = 1, padrão ADR 0001).
-- As traduções pt-br/es-eu serão inseridas junto com a ativação dos idiomas.

INSERT INTO `rms_order_status` (`order_status_id`, `language_id`, `name`) VALUES
(1, 1, 'Received'),
(2, 1, 'Under Analysis'),
(3, 1, 'Quote Sent'),
(4, 1, 'Approved'),
(5, 1, 'Completed'),
(6, 1, 'Canceled');

-- ----------------------------------------------------------------------------
-- 9. HARDENING (F2-18) — rate limit de POSTs públicos e login
-- ----------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `rms_rate_limit` (
  `scope`        VARCHAR(32)  NOT NULL COMMENT 'login | contact | quote',
  `ip`           VARCHAR(40)  NOT NULL,
  `attempts`     INT          NOT NULL DEFAULT 0,
  `window_start` DATETIME     NOT NULL,
  PRIMARY KEY (`scope`, `ip`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- F10-02 — Catalog Themes (canonical install schema).
CREATE TABLE IF NOT EXISTS `rms_design_theme` (
  `theme_id` INT NOT NULL AUTO_INCREMENT, `name` VARCHAR(120) NOT NULL, `slug` VARCHAR(48) NOT NULL,
  `config` LONGTEXT NOT NULL COMMENT 'JSON allowlisted visual tokens and approved Three.js scene identifier',
  `status` TINYINT(1) NOT NULL DEFAULT '0', `date_added` DATETIME NOT NULL, `date_modified` DATETIME NOT NULL,
  PRIMARY KEY (`theme_id`), UNIQUE KEY `slug` (`slug`), KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `rms_design_theme_revision` (
  `revision_id` BIGINT NOT NULL AUTO_INCREMENT, `theme_id` INT NOT NULL, `snapshot` LONGTEXT NOT NULL,
  `user_id` INT NOT NULL, `date_added` DATETIME NOT NULL,
  PRIMARY KEY (`revision_id`), KEY `theme_date` (`theme_id`,`revision_id`), KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
