-- ════════════════════════════════════════════════════════════════
-- upgrade_f7_03_blog_import.sql — F7-03: grant 'blog/import' (ADR 0044)
-- Idempotente (JSON_SEARCH guard) — padrão dos upgrades F2-09..F5-03/F7-01.
-- Necessário porque o middleware startup/permission valida a rota CORRENTE
-- (blog/import) e NÃO o gate de módulo no controller. Executar após
-- upgrade_f7_01_blog.sql.
-- ════════════════════════════════════════════════════════════════
UPDATE `rms_user_group`
   SET `permission` = JSON_ARRAY_APPEND(
        `permission`, '$.access', 'blog/import'
   )
 WHERE `name` = 'Administrator'
   AND `permission` IS NOT NULL
   AND JSON_SEARCH(JSON_EXTRACT(`permission`, '$.access'), 'one', 'blog/import') IS NULL;

UPDATE `rms_user_group`
   SET `permission` = JSON_ARRAY_APPEND(
        `permission`, '$.modify', 'blog/import'
   )
 WHERE `name` = 'Administrator'
   AND `permission` IS NOT NULL
   AND JSON_SEARCH(JSON_EXTRACT(`permission`, '$.modify'), 'one', 'blog/import') IS NULL;
