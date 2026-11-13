-- ═══════════════════════════════════════════════════════════════════
-- ROLLBACK da ACL da EX-01 (ADR 0095). Remove somente a rota
-- `extension/extension` das listas access/modify do grupo Administrator.
-- Não apaga o estado das extensões (linhas code = 'extension' de rms_setting):
-- sem a tela, o que estava ligado CONTINUA ligado. Para desligar tudo sem a
-- tela, use no config.php:  define('REAMUR_EXTENSIONS', false);
-- Para apagar o estado:     DELETE FROM `rms_setting` WHERE `code` = 'extension';
-- O caminho do item é montado a partir da lista extraída (mesma forma de busca
-- do upgrade), que funciona igual em MySQL e MariaDB.
-- Ajuste o prefixo das tabelas se não for rms_.
-- ═══════════════════════════════════════════════════════════════════

UPDATE `rms_user_group`
SET `permission` = JSON_REMOVE(
  `permission`,
  CONCAT('$.access', SUBSTRING(JSON_UNQUOTE(JSON_SEARCH(JSON_EXTRACT(`permission`, '$.access'), 'one', 'extension/extension')), 2))
)
WHERE (`name` = 'Administrator' OR `user_group_id` = 1)
  AND JSON_SEARCH(JSON_EXTRACT(`permission`, '$.access'), 'one', 'extension/extension') IS NOT NULL;

UPDATE `rms_user_group`
SET `permission` = JSON_REMOVE(
  `permission`,
  CONCAT('$.modify', SUBSTRING(JSON_UNQUOTE(JSON_SEARCH(JSON_EXTRACT(`permission`, '$.modify'), 'one', 'extension/extension')), 2))
)
WHERE (`name` = 'Administrator' OR `user_group_id` = 1)
  AND JSON_SEARCH(JSON_EXTRACT(`permission`, '$.modify'), 'one', 'extension/extension') IS NOT NULL;
