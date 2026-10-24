-- ════════════════════════════════════════════════════════════════════
-- F9-12 — sidecar para documentos de blocos multilíngues do Blog.
-- Migration aditiva e manual; NÃO executar neste pacote.
-- Não altera blog_post_description.description (HTML legado/snapshot), não
-- migra posts e não concede ACL. Prefixo rms_ é placeholder padrão.
-- Upgrade idempotente via CREATE TABLE IF NOT EXISTS; sem FKs.
-- ════════════════════════════════════════════════════════════════════

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
