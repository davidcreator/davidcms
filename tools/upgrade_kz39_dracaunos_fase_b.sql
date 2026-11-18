-- KZ-39 — Dracaunos-Security Fase B (ferramenta INTERNA, só admin):
-- 1) Auditoria do admin: toda requisição autenticada do painel é registrada.
-- 2) Expiração de senha: carimbo password_changed_at em rms_user (política
--    dracaunos_password_policy.max_age_days; 0 = sem expiração).
-- 3) Rota real do admin: dracaunos_admin_route passa a ser aplicada pelo
--    startup/dracaunos (acesso pela pasta física /admin/ recusado com 403 quando
--    uma rota custom está configurada). Para servir a rota custom, adicione no
--    .htaccess da raiz pública (ex.: rota 'painel'):
--      RewriteRule ^painel/(.*)$ admin/$1 [L,QSA]
--    e ajuste HTTP_SERVER do admin/config.php para .../painel/.

-- 1) Tabela de auditoria do admin:
CREATE TABLE IF NOT EXISTS `rms_dracaunos_audit` (
  `audit_id`   INT NOT NULL AUTO_INCREMENT,
  `user_id`    INT NOT NULL DEFAULT 0,
  `username`   VARCHAR(32) NOT NULL,
  `route`      VARCHAR(128) NOT NULL,
  `method`     VARCHAR(10) NOT NULL DEFAULT 'GET',
  `ip`         VARCHAR(45) NOT NULL,
  `user_agent` VARCHAR(255) NOT NULL,
  `date_added` DATETIME NOT NULL,
  PRIMARY KEY (`audit_id`), KEY `user_id` (`user_id`), KEY `date_added` (`date_added`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2) Carimbo de troca de senha (expiração; backfill = date_added).
--    Idempotente e portátil (MySQL 8 não aceita ADD COLUMN IF NOT EXISTS):
--    só altera se a coluna ainda não existir.
SET @col := (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rms_user' AND COLUMN_NAME = 'password_changed_at');
SET @ddl := IF(@col = 0,
  'ALTER TABLE `rms_user` ADD COLUMN `password_changed_at` DATETIME NULL AFTER `password`',
  'SELECT ''password_changed_at já existe'' AS info');
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

UPDATE `rms_user` SET `password_changed_at` = `date_added` WHERE `password_changed_at` IS NULL;

-- 3) Retenção da auditoria (dias):
INSERT INTO `rms_setting` (`code`, `key`, `value`, `serialized`) VALUES
 ('dracaunos', 'dracaunos_audit_days', '30', 0)
ON DUPLICATE KEY UPDATE `value` = VALUES(`value`);

-- Verificacao:
SELECT `code`, `key`, LEFT(`value`,80) AS value FROM `rms_setting` WHERE `code`='dracaunos';
SHOW COLUMNS FROM `rms_user` LIKE 'password_changed_at';
