-- ════════════════════════════════════════════════════════════════════
-- F9-02 — Campos personalizados: tabelas da proposta aprovada (ADR 0057/0058).
-- Migração NOVA + executável, porém NÃO executada pelo pacote/Tool; aplicar só
-- no laboratório descartável, por fluxo controlado e com backup. Idempotente.
-- Prefixo rms_ é o placeholder padrão: wizard/operador substituem por DB_PREFIX.
-- Sem FKs (integridade no Model) e sem grant/dado/seed/migração de conteúdo.
-- NÃO edite migração aplicada; correções pedem migração nova, conforme regras.
-- Devolução de verdade: docs/Dicionario-de-Dados.md deve citar estas tabelas APÓS
-- a migração ser aplicada/aprovada; este SQL não muda o dicionário sozinho.
-- ════════════════════════════════════════════════════════════════════

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
