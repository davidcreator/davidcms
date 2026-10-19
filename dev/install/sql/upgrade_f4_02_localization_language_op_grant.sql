-- ═══════════════════════════════════════════════════════════════════
-- F4-02 (ADR 0025): Gestão de idiomas — localization/language
-- Execução: sudo mysql <database> < install/sql/upgrade_f4_02_localization_language_op_grant.sql
-- Idempotente: pode ser executado N vezes sem efeito colateral.
--
--   1) grants JSON access+modify 'localization/language' (guarda JSON_SEARCH)
--   2) seeds config_language_catalog/admin via INSERT IGNORE (NUNCA pisa
--      configuração já salva — padrão do seed F4-01, ver ADR 0024)
--   3) normalização de STATUS: os 3 códigos semeados pelo schema têm
--      diretórios de idioma REAIS no filesystem (en-us/pt-br/es-eu) e o
--      site público já publica versões nos 3 idiomas; antes desta tela a
--      coluna status era morta — agora que ela passa a barregar o switcher
--      e a pre-ação, os existentes entram como ATIVOS.
-- ═══════════════════════════════════════════════════════════════════

-- 1) Grants (idempotente por JSON_SEARCH NULL)
UPDATE `rms_user_group`
   SET `permission` = JSON_ARRAY_APPEND(`permission`, '$.access', 'localization/language')
 WHERE `name` = 'Administrator'
   AND JSON_SEARCH(`permission`, 'one', 'localization/language', NULL, '$.access') IS NULL;

UPDATE `rms_user_group`
   SET `permission` = JSON_ARRAY_APPEND(`permission`, '$.modify', 'localization/language')
 WHERE `name` = 'Administrator'
   AND JSON_SEARCH(`permission`, 'one', 'localization/language', NULL, '$.modify') IS NULL;

-- 2) Defaults por app (INSERT IGNORE — re-rodar não pisa valor salvo)
INSERT IGNORE INTO `rms_setting` (`code`,`key`,`value`,`serialized`) VALUES
 ('config','config_language_catalog','en-us',0),
 ('config','config_language_admin','en-us',0);

-- 3) Normalização: os 3 idiomas cujos diretórios existem entram ATIVOS.
--    (idempotente nativo do UPDATE; governança fina fica na tela F4-02)
UPDATE `rms_language` SET `status` = 1 WHERE `code` IN ('en-us','pt-br','es-eu');
