-- F10-02: Catalog theme registry and immutable snapshots. Prepare locally; do not execute without explicit approval.
CREATE TABLE IF NOT EXISTS `rms_design_theme` (
  `theme_id` INT NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(120) NOT NULL,
  `slug` VARCHAR(48) NOT NULL,
  `config` LONGTEXT NOT NULL COMMENT 'JSON allowlisted visual tokens and approved Three.js scene identifier',
  `status` TINYINT(1) NOT NULL DEFAULT '0' COMMENT 'At most one active theme, maintained by admin model',
  `date_added` DATETIME NOT NULL,
  `date_modified` DATETIME NOT NULL,
  PRIMARY KEY (`theme_id`), UNIQUE KEY `slug` (`slug`), KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `rms_design_theme_revision` (
  `revision_id` BIGINT NOT NULL AUTO_INCREMENT,
  `theme_id` INT NOT NULL,
  `snapshot` LONGTEXT NOT NULL COMMENT 'Full JSON snapshot for audit/rollback',
  `user_id` INT NOT NULL,
  `date_added` DATETIME NOT NULL,
  PRIMARY KEY (`revision_id`), KEY `theme_date` (`theme_id`,`revision_id`), KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Grant design/theme to the built-in administrator group only; other groups require deliberate ACL assignment.
UPDATE `rms_user_group`
SET `permission` = JSON_ARRAY_APPEND(`permission`, '$.access', 'design/theme')
WHERE (`name`='Administrator' OR `user_group_id`=1)
  AND JSON_SEARCH(JSON_EXTRACT(`permission`, '$.access'), 'one', 'design/theme') IS NULL;
UPDATE `rms_user_group`
SET `permission` = JSON_ARRAY_APPEND(`permission`, '$.modify', 'design/theme')
WHERE (`name`='Administrator' OR `user_group_id`=1)
  AND JSON_SEARCH(JSON_EXTRACT(`permission`, '$.modify'), 'one', 'design/theme') IS NULL;
