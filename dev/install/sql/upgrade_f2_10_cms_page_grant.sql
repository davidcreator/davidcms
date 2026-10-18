-- ════════════════════════════════════════════════════════════════════
-- F2 Tarefa 10 — Páginas de Conteúdo: grants para instalações EXISTENTES
-- (instalações novas já nascem com o grant — seed do wizard passo 3).
-- IDEMPOTENTE: pode rodar mais de uma vez sem duplicar permissões.
-- Alternativa equivalente pela UI: editar o grupo Administrator em
-- Usuários → Grupos de Usuários e marcar 'cms/page'.
-- Ajuste o prefixo das tabelas se não for rms_.
-- ════════════════════════════════════════════════════════════════════

UPDATE `rms_user_group`
SET `permission` = JSON_ARRAY_APPEND(`permission`, '$.access', 'cms/page')
WHERE `name` = 'Administrator'
  AND JSON_SEARCH(`permission`, 'one', 'cms/page', NULL, '$.access') IS NULL;

UPDATE `rms_user_group`
SET `permission` = JSON_ARRAY_APPEND(`permission`, '$.modify', 'cms/page')
WHERE `name` = 'Administrator'
  AND JSON_SEARCH(`permission`, 'one', 'cms/page', NULL, '$.modify') IS NULL;
