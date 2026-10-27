-- F10-01 — vincula conteúdo CMS às rotas públicas do Catalog.
-- Aditiva e idempotente. Não apaga páginas, conteúdo, SEO ou permissões.
-- Prefixo rms_ é placeholder: ajuste se o banco usar outro DB_PREFIX.
-- Aplicação manual posterior; este arquivo não é executado pelo patch.
-- Rollback seguro: reverter o código e manter a coluna/dados; não há DROP automático.

SET @f10_has_system_key := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rms_page' AND COLUMN_NAME = 'system_key'
);
SET @f10_sql := IF(@f10_has_system_key = 0,
  'ALTER TABLE `rms_page` ADD COLUMN `system_key` VARCHAR(64) NULL DEFAULT NULL COMMENT ''route key estável para conteúdo editorial do Catalog'', ADD UNIQUE KEY `system_key` (`system_key`)',
  'SELECT 1'
);
PREPARE f10_stmt FROM @f10_sql;
EXECUTE f10_stmt;
DEALLOCATE PREPARE f10_stmt;

-- Se a coluna existia sem o índice (por instalação parcial), assegure a unicidade.
SET @f10_has_index := (
  SELECT COUNT(*) FROM information_schema.STATISTICS
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rms_page' AND INDEX_NAME = 'system_key'
);
SET @f10_sql := IF(@f10_has_index = 0,
  'CREATE UNIQUE INDEX `system_key` ON `rms_page` (`system_key`)',
  'SELECT 1'
);
PREPARE f10_stmt FROM @f10_sql;
EXECUTE f10_stmt;
DEALLOCATE PREPARE f10_stmt;

CREATE TEMPORARY TABLE IF NOT EXISTS `tmp_f10_catalog_page` (
  `system_key` VARCHAR(64) NOT NULL PRIMARY KEY,
  `title_en` VARCHAR(255) NOT NULL,
  `title_pt` VARCHAR(255) NOT NULL,
  `title_es` VARCHAR(255) NOT NULL,
  `sort_order` INT NOT NULL
) ENGINE=MEMORY DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO `tmp_f10_catalog_page` (`system_key`,`title_en`,`title_pt`,`title_es`,`sort_order`) VALUES
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
SELECT s.`system_key`, 1, s.`sort_order`, NOW(), NOW()
  FROM `tmp_f10_catalog_page` s;

INSERT IGNORE INTO `rms_page_description`
  (`page_id`,`language_id`,`title`,`description`,`meta_title`,`meta_description`)
SELECT p.`page_id`, l.`language_id`,
       CASE l.`code` WHEN 'pt-br' THEN s.`title_pt` WHEN 'es-eu' THEN s.`title_es` ELSE s.`title_en` END,
       '', '', ''
  FROM `rms_page` p
  JOIN `tmp_f10_catalog_page` s ON s.`system_key` = p.`system_key`
 CROSS JOIN `rms_language` l
 WHERE l.`code` IN ('en-us','pt-br','es-eu');

DROP TEMPORARY TABLE IF EXISTS `tmp_f10_catalog_page`;
