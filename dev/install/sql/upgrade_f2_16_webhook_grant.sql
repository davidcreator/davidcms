-- ════════════════════════════════════════════════════════════════════
-- F2 Tarefa 16 — Webhooks + log de entregas (setting/webhook): grants
-- para instalações EXISTENTES (instalações novas já nascem com o grant —
-- seed do wizard passo 3).
-- IDEMPOTENTE: pode rodar mais de uma vez sem duplicar permissões.
-- Alternativa equivalente pela UI: editar o grupo Administrator em
-- Usuários → Grupos de Usuários e marcar 'setting/webhook'.
-- Ajuste o prefixo das tabelas se não for rms_.
-- ════════════════════════════════════════════════════════════════════

UPDATE `rms_user_group`
SET `permission` = JSON_ARRAY_APPEND(`permission`, '$.access', 'setting/webhook')
WHERE `name` = 'Administrator'
  AND JSON_SEARCH(`permission`, 'one', 'setting/webhook', NULL, '$.access') IS NULL;

UPDATE `rms_user_group`
SET `permission` = JSON_ARRAY_APPEND(`permission`, '$.modify', 'setting/webhook')
WHERE `name` = 'Administrator'
  AND JSON_SEARCH(`permission`, 'one', 'setting/webhook', NULL, '$.modify') IS NULL;
