-- F10-03: separate unpublished and published Catalog theme payloads.
-- Local preparation only; do not execute without explicit database authorization.
SET @f103_draft := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='rms_design_theme' AND COLUMN_NAME='draft_config');
SET @f103_sql := IF(@f103_draft=0, 'ALTER TABLE `rms_design_theme` ADD COLUMN `draft_config` LONGTEXT NULL COMMENT ''Unpublished theme payload'' AFTER `config`', 'SELECT 1');
PREPARE f103_stmt FROM @f103_sql; EXECUTE f103_stmt; DEALLOCATE PREPARE f103_stmt;
SET @f103_published := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='rms_design_theme' AND COLUMN_NAME='published_config');
SET @f103_sql := IF(@f103_published=0, 'ALTER TABLE `rms_design_theme` ADD COLUMN `published_config` LONGTEXT NULL COMMENT ''Last published theme payload'' AFTER `draft_config`', 'SELECT 1');
PREPARE f103_stmt FROM @f103_sql; EXECUTE f103_stmt; DEALLOCATE PREPARE f103_stmt;
UPDATE `rms_design_theme` SET `draft_config`=`config` WHERE `draft_config` IS NULL;
UPDATE `rms_design_theme` SET `published_config`=CASE WHEN `status`='1' THEN `config` ELSE '{}' END WHERE `published_config` IS NULL;
ALTER TABLE `rms_design_theme` MODIFY COLUMN `draft_config` LONGTEXT NOT NULL COMMENT 'Unpublished theme payload';
ALTER TABLE `rms_design_theme` MODIFY COLUMN `published_config` LONGTEXT NOT NULL COMMENT 'Last published theme payload';
