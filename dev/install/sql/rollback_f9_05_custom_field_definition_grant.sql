-- ════════════════════════════════════════════════════════════════════
-- ROLLBACK da ACL dormente da F9-05. Remove somente a rota
-- `custom_field/definition` dos arrays access/modify do grupo Administrator.
-- Reversão segura: não apaga tabelas, modelos, definições ou valores. Usar
-- somente se a tarefa for revertida antes/depois da rota ficar disponível.
-- ════════════════════════════════════════════════════════════════════

UPDATE `rms_user_group`
SET `permission` = JSON_REMOVE(
  `permission`,
  JSON_UNQUOTE(JSON_SEARCH(`permission`, 'one', 'custom_field/definition', NULL, '$.access'))
)
WHERE `name` = 'Administrator'
  AND JSON_SEARCH(`permission`, 'one', 'custom_field/definition', NULL, '$.access') IS NOT NULL;

UPDATE `rms_user_group`
SET `permission` = JSON_REMOVE(
  `permission`,
  JSON_UNQUOTE(JSON_SEARCH(`permission`, 'one', 'custom_field/definition', NULL, '$.modify'))
)
WHERE `name` = 'Administrator'
  AND JSON_SEARCH(`permission`, 'one', 'custom_field/definition', NULL, '$.modify') IS NOT NULL;
