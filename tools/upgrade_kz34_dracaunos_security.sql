-- KZ-34 — Dracaunos-Security (ferramenta INTERNA) + remocao da exposicao publica de JOW/Dracaunos
-- 1) As ferramentas internas nao devem aparecer no catalogo publico (rms_tool):
DELETE d FROM `rms_tool_description` d JOIN `rms_tool` t ON t.`tool_id` = d.`tool_id` WHERE t.`vendor` IN ('JOW','Dracaunos');
DELETE FROM `rms_tool` WHERE `vendor` IN ('JOW','Dracaunos');

-- 2) Tabelas do modulo Dracaunos-Security:
CREATE TABLE IF NOT EXISTS `rms_dracaunos_login_log` (
  `log_id` INT NOT NULL AUTO_INCREMENT,
  `user_id` INT NOT NULL DEFAULT 0,
  `username` VARCHAR(32) NOT NULL,
  `ip` VARCHAR(45) NOT NULL,
  `user_agent` VARCHAR(255) NOT NULL,
  `success` TINYINT(1) NOT NULL DEFAULT 0,
  `date_added` DATETIME NOT NULL,
  PRIMARY KEY (`log_id`), KEY `user_id` (`user_id`), KEY `date_added` (`date_added`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `rms_dracaunos_2fa` (
  `user_id` INT NOT NULL,
  `secret` VARCHAR(64) NOT NULL,          -- base32 (TOTP RFC 6238)
  `enabled` TINYINT(1) NOT NULL DEFAULT 0,
  `date_added` DATETIME NOT NULL,
  PRIMARY KEY (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `rms_dracaunos_recovery` (
  `recovery_id` INT NOT NULL AUTO_INCREMENT,
  `user_id` INT NOT NULL DEFAULT 0,
  `email` VARCHAR(96) NOT NULL,
  `type` VARCHAR(16) NOT NULL,            -- 'password' | 'account' | 'verification'
  `token` VARCHAR(64) NOT NULL,
  `ip` VARCHAR(45) NOT NULL,
  `status` VARCHAR(16) NOT NULL DEFAULT 'pending',  -- pending|approved|denied|expired
  `date_added` DATETIME NOT NULL,
  `date_handled` DATETIME NULL,
  PRIMARY KEY (`recovery_id`), KEY `token` (`token`), KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `rms_dracaunos_ip_rule` (
  `rule_id` INT NOT NULL AUTO_INCREMENT,
  `ip` VARCHAR(45) NOT NULL,              -- IP ou CIDR
  `action` VARCHAR(8) NOT NULL,           -- 'allow' | 'deny'
  `scope` VARCHAR(16) NOT NULL DEFAULT 'admin',
  `note` VARCHAR(128) NOT NULL DEFAULT '',
  `status` TINYINT(1) NOT NULL DEFAULT 1,
  `date_added` DATETIME NOT NULL,
  PRIMARY KEY (`rule_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3) Configuracoes (code 'dracaunos'):
INSERT INTO `rms_setting` (`code`, `key`, `value`, `serialized`) VALUES
 ('dracaunos', 'dracaunos_admin_route', 'admin', 0),
 ('dracaunos', 'dracaunos_2fa_required', '0', 0),
 ('dracaunos', 'dracaunos_password_policy', '{"min_length":10,"require_upper":1,"require_digit":1,"require_symbol":1,"max_age_days":90}', 1),
 ('dracaunos', 'dracaunos_login_log_days', '30', 0)
ON DUPLICATE KEY UPDATE `value` = VALUES(`value`);

-- 4) Permissao do modulo para o grupo administrador (user_group_id=1):
UPDATE `rms_user_group` SET `permission` = JSON_SET(`permission`,
  '$.access', JSON_ARRAY_APPEND(COALESCE(JSON_EXTRACT(`permission`,'$.access'), JSON_ARRAY()), 'security/dracaunos'),
  '$.modify', JSON_ARRAY_APPEND(COALESCE(JSON_EXTRACT(`permission`,'$.modify'), JSON_ARRAY()), 'security/dracaunos')
) WHERE `user_group_id` = 1;

-- Verificacao:
SELECT `code`, `key`, LEFT(`value`,80) AS value FROM `rms_setting` WHERE `code`='dracaunos';
