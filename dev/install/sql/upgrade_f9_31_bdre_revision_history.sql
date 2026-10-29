-- F9-31 — Armazenamento de revisões persistentes do BDRE.
-- Migration aditiva; revisar prefixo e executar somente pelo fluxo de upgrade autorizado.
-- Não executada neste pacote.

CREATE TABLE IF NOT EXISTS `rms_bdre_revision` (
  `revision_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `entity_type` VARCHAR(16) NOT NULL COMMENT 'page ou blog; fluxos isolados',
  `entity_id` INT UNSIGNED NOT NULL,
  `language_id` INT UNSIGNED NOT NULL,
  `content_hash` CHAR(64) NOT NULL,
  `snapshot_json` MEDIUMTEXT NOT NULL,
  `date_added` DATETIME NOT NULL,
  PRIMARY KEY (`revision_id`),
  KEY `entity_language_revision` (`entity_type`, `entity_id`, `language_id`, `revision_id`),
  KEY `entity_hash` (`entity_type`, `entity_id`, `language_id`, `content_hash`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
