-- ════════════════════════════════════════════════════════════════════
-- F7-01 — Blog interno (blog/blog): tabelas + grants (ADR 0042).
-- Migração da ADR 0002 adiada virou bloco oficial: blog deixa davidcreator.com
-- (migração de conteúdo e 301s ficam com F7-03).
-- IDEMPOTENTE (CREATE IF NOT EXISTS + JSON_SEARCH guard) — pode rodar N vezes.
-- Modelo multilingual = o do Dicionario-de-Dados (cms/page): tabela principal
-- + *_description por idioma; keywords no seo_url GLOBAL (key 'blog_id=N').
-- Ajuste o prefixo das tabelas se não for rms_.
-- ════════════════════════════════════════════════════════════════════

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

-- Grants para instalações existentes (instalação nova nasce com 11 rotas-semente;
-- esta entrega chega DEPOIS do go-live do schema — grants via upgrade sempre).
UPDATE `rms_user_group`
SET `permission` = JSON_ARRAY_APPEND(`permission`, '$.access', 'blog/blog')
WHERE `name` = 'Administrator'
  AND JSON_SEARCH(`permission`, 'one', 'blog/blog', NULL, '$.access') IS NULL;

UPDATE `rms_user_group`
SET `permission` = JSON_ARRAY_APPEND(`permission`, '$.modify', 'blog/blog')
WHERE `name` = 'Administrator'
  AND JSON_SEARCH(`permission`, 'one', 'blog/blog', NULL, '$.modify') IS NULL;
