-- ════════════════════════════════════════════════════════════════════
-- F9 Tarefa 05 — Campos personalizados: grant dormente para a rota futura
-- `custom_field/definition` (admin). A rota/controller/UI entram na F9-06;
-- aqui entram somente ACL de instalações existentes e seed de novas.
-- IDEMPOTENTE: pode rodar mais de uma vez sem duplicar permissões.
-- Alternativa equivalente pela UI: editar Administrator em Usuários →
-- Grupos de Usuários e marcar 'custom_field/definition'.
-- Ajuste o prefixo das tabelas se não for rms_. Não executa schema nem dados.
-- ════════════════════════════════════════════════════════════════════

UPDATE `rms_user_group`
SET `permission` = JSON_ARRAY_APPEND(`permission`, '$.access', 'custom_field/definition')
WHERE `name` = 'Administrator'
  AND JSON_SEARCH(`permission`, 'one', 'custom_field/definition', NULL, '$.access') IS NULL;

UPDATE `rms_user_group`
SET `permission` = JSON_ARRAY_APPEND(`permission`, '$.modify', 'custom_field/definition')
WHERE `name` = 'Administrator'
  AND JSON_SEARCH(`permission`, 'one', 'custom_field/definition', NULL, '$.modify') IS NULL;
