-- ════════════════════════════════════════════════════════════════════
-- F2 Tarefa 12 — Logos de Clientes (marketing/client_logo): grants para
-- instalações EXISTENTES (instalações novas já nascem com o grant —
-- seed do wizard passo 3).
-- IDEMPOTENTE: pode rodar mais de uma vez sem duplicar permissões.
-- Alternativa equivalente pela UI: editar o grupo Administrator em
-- Usuários → Grupos de Usuários e marcar 'marketing/client_logo'.
-- Ajuste o prefixo das tabelas se não for rms_.
-- ════════════════════════════════════════════════════════════════════

UPDATE `rms_user_group`
SET `permission` = JSON_ARRAY_APPEND(`permission`, '$.access', 'marketing/client_logo')
WHERE `name` = 'Administrator'
  AND JSON_SEARCH(`permission`, 'one', 'marketing/client_logo', NULL, '$.access') IS NULL;

UPDATE `rms_user_group`
SET `permission` = JSON_ARRAY_APPEND(`permission`, '$.modify', 'marketing/client_logo')
WHERE `name` = 'Administrator'
  AND JSON_SEARCH(`permission`, 'one', 'marketing/client_logo', NULL, '$.modify') IS NULL;
