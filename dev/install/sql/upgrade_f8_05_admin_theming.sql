-- ════════════════════════════════════════════════════════════════════
-- F8-V — Aparência do Painel (ADR 0047): sistema de personalização do
-- admin (skins WCAG-validadas, accent curado/custom com trava de
-- contraste, sidebar/densidade/fonte/modo), com padrão GLOBAL em
-- rms_setting e OVERRIDE POR USUÁRIO em rms_user.theme_prefs.
-- IDEMPOTENTE: roda mais de uma vez sem efeito colateral.
-- Inclui o grant da rota nova setting/appearance ao grupo Administrator.
-- ════════════════════════════════════════════════════════════════════

-- ── 1) Preferências de aparência por usuário (JSON; when null → global) ──
SET @col_exists := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rms_user' AND COLUMN_NAME = 'theme_prefs'
);
SET @ddl := IF(@col_exists = 0,
  'ALTER TABLE `rms_user` ADD COLUMN `theme_prefs` TEXT NULL DEFAULT NULL AFTER `image`',
  'SELECT 1'
);
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ── 2) Defaults globais (padrão institucional do painel) ──
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

-- ── 3) Grant da rota setting/appearance (v2: idiom com JSON_EXTRACT, testado) ──
-- NOTA v2: a forma anterior com JSON_SEARCH(permission,...,NULL,'$.access')
-- era silenciosamente ineficaz em MariaDB ≥10.4 (path 4º arg com escape NULL).
-- Alvo: grupo Administrator (por id OU nome). Idempotente.
UPDATE `rms_user_group`
SET `permission` = JSON_ARRAY_APPEND(`permission`, '$.access', 'setting/appearance')
WHERE (`name` = 'Administrator' OR `user_group_id` = 1)
  AND JSON_SEARCH(JSON_EXTRACT(`permission`, '$.access'), 'one', 'setting/appearance') IS NULL;

UPDATE `rms_user_group`
SET `permission` = JSON_ARRAY_APPEND(`permission`, '$.modify', 'setting/appearance')
WHERE (`name` = 'Administrator' OR `user_group_id` = 1)
  AND JSON_SEARCH(JSON_EXTRACT(`permission`, '$.modify'), 'one', 'setting/appearance') IS NULL;

-- ── 3b) Falback: se as UPDATE acima não tocaram nenhuma linha porque o grupo
--        tem outro nome/slug, rode manualmente (phpMyAdmin → rms_user_group):
--        UPDATE rms_user_group
--        SET permission = JSON_ARRAY_APPEND(permission, '$.access', 'setting/appearance')
--        WHERE user_group_id = <id_do_seu_grupo_admin>
--          AND JSON_SEARCH(JSON_EXTRACT(permission,'$.access'),'one','setting/appearance') IS NULL;
--        (idem para '$.modify'.)
