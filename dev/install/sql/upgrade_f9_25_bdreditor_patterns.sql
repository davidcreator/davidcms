-- F9-25 — Biblioteca de padrões BDRE privados por administrador.
-- Idempotente; NÃO executar automaticamente sem o fluxo de upgrade aprovado.
-- Schema deliberately uses no foreign keys; user deletion cascades in User model.

CREATE TABLE IF NOT EXISTS `rms_cms_block_pattern` (
  `pattern_id` BIGINT NOT NULL AUTO_INCREMENT,
  `owner_user_id` INT NOT NULL COMMENT 'Administrador proprietário; derivado da sessão, não do cliente',
  `name` VARCHAR(80) NOT NULL,
  `source_language` VARCHAR(8) NOT NULL DEFAULT '' COMMENT 'Idioma de origem; sem tradução automática',
  `root_type` VARCHAR(64) NOT NULL,
  `format_version` SMALLINT NOT NULL,
  `content_json` MEDIUMTEXT NOT NULL COMMENT 'Documento BDRE canônico, máximo 4 MiB, sem custom_js',
  `date_added` DATETIME NOT NULL,
  `date_modified` DATETIME NOT NULL,
  PRIMARY KEY (`pattern_id`),
  UNIQUE KEY `owner_name` (`owner_user_id`,`name`),
  KEY `owner_modified` (`owner_user_id`,`date_modified`),
  KEY `owner_root` (`owner_user_id`,`root_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

UPDATE `rms_user_group`
SET `permission` = JSON_ARRAY_APPEND(`permission`, '$.access', 'cms/block_pattern')
WHERE (`name` = 'Administrator' OR `user_group_id` = 1)
  AND JSON_SEARCH(JSON_EXTRACT(`permission`, '$.access'), 'one', 'cms/block_pattern') IS NULL;
UPDATE `rms_user_group`
SET `permission` = JSON_ARRAY_APPEND(`permission`, '$.modify', 'cms/block_pattern')
WHERE (`name` = 'Administrator' OR `user_group_id` = 1)
  AND JSON_SEARCH(JSON_EXTRACT(`permission`, '$.modify'), 'one', 'cms/block_pattern') IS NULL;
