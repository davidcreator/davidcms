-- ════════════════════════════════════════════════════════════════
-- upgrade_f8_01_permission_canonical.sql — F8-01 (ADR 0045 §bugfix-escape-ACL)
-- Saneia valores "user\/user" (backslash literal) gravados em instalações
-- cujo wizard caiu no branch mysqli (json_encode escapa '/' + real_escape
-- _string dobrava o '\'). Idempotente: REPLACE é inócuo quando não há match.
-- O app também normaliza no decode (defesa dupla), mas bancos de terceiros
-- /ferramentas externas ficam com o JSON canônico.
-- ════════════════════════════════════════════════════════════════
UPDATE `rms_user_group`
   SET `permission` = REPLACE(`permission`, '\\/', '/')
 WHERE `permission` LIKE '%\\\\/%';

-- F8-01 (BUG latente, ADR 0045 II): sessão db expirava instantaneamente para
-- instalações sem session_expire persistido (config->get null → expire=NOW).
-- Seed idempotente: 3600s; adapter session/db ganha fallback defensivo mesmo.
INSERT IGNORE INTO `rms_setting` (`code`, `key`, `value`, `serialized`)
SELECT 'config', 'session_expire', '3600', 0 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `rms_setting` WHERE `code`='config' AND `key`='session_expire');
