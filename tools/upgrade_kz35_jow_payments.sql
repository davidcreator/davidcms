-- KZ-35 — JOW-payments (ferramenta INTERNA): cobranças, chaves PIX e recebimentos
CREATE TABLE IF NOT EXISTS `rms_jow_charge` (
  `charge_id`   INT NOT NULL AUTO_INCREMENT,
  `description` VARCHAR(128) NOT NULL,
  `amount`      DECIMAL(10,2) NOT NULL,
  `currency`    VARCHAR(3) NOT NULL DEFAULT 'BRL',
  `method`      VARCHAR(8) NOT NULL DEFAULT 'pix',
  `status`      VARCHAR(16) NOT NULL DEFAULT 'pending',
  `pix_payload` TEXT NULL,
  `payer_email` VARCHAR(96) NOT NULL DEFAULT '',
  `due_date`    DATE NULL,
  `paid_at`     DATETIME NULL,
  `date_added`  DATETIME NOT NULL,
  PRIMARY KEY (`charge_id`), KEY `status` (`status`), KEY `date_added` (`date_added`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `rms_jow_pix_key` (
  `key_id`   INT NOT NULL AUTO_INCREMENT,
  `type`     VARCHAR(16) NOT NULL,
  `key`      VARCHAR(128) NOT NULL,
  `holder`   VARCHAR(128) NOT NULL DEFAULT '',
  `status`   TINYINT(1) NOT NULL DEFAULT 1,
  `date_added` DATETIME NOT NULL,
  PRIMARY KEY (`key_id`), UNIQUE KEY `key` (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `rms_setting` (`code`, `key`, `value`, `serialized`) VALUES
 ('jow', 'jow_store_name', 'ReamurCMS', 0),
 ('jow', 'jow_city', 'Sao Paulo', 0),
 ('jow', 'jow_merchant_category', '0000', 0),
 ('jow', 'jow_default_method', 'pix', 0)
ON DUPLICATE KEY UPDATE `value` = VALUES(`value`);

UPDATE `rms_user_group` SET `permission` = JSON_SET(`permission`,
  '$.access', JSON_ARRAY_APPEND(COALESCE(JSON_EXTRACT(`permission`,'$.access'), JSON_ARRAY()), '$', 'payments/jow'),
  '$.modify', JSON_ARRAY_APPEND(COALESCE(JSON_EXTRACT(`permission`,'$.modify'), JSON_ARRAY()), '$', 'payments/jow')
) WHERE `user_group_id` = 1;

INSERT INTO `rms_jow_pix_key` (`type`,`key`,`holder`,`status`,`date_added`)
SELECT 'random', UPPER(REPLACE(UUID(),'-','')), 'ReamurCMS', 1, NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `rms_jow_pix_key` WHERE `type`='random');

SELECT `code`, `key`, LEFT(`value`,60) AS value FROM `rms_setting` WHERE `code`='jow';
