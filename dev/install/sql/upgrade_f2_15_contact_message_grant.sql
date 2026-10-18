-- ════════════════════════════════════════════════════════════════════
-- F2 Tarefa 15 — Caixa de entrada de contato (communication/contact_message):
-- grants para instalações EXISTENTES (instalações novas já nascem com o
-- grant — seed do wizard passo 3).
-- IDEMPOTENTE: pode rodar mais de uma vez sem duplicar permissões.
-- Alternativa equivalente pela UI: editar o grupo Administrator em
-- Usuários → Grupos de Usuários e marcar 'communication/contact_message'.
-- Ajuste o prefixo das tabelas se não for rms_.
-- ════════════════════════════════════════════════════════════════════

UPDATE `rms_user_group`
SET `permission` = JSON_ARRAY_APPEND(`permission`, '$.access', 'communication/contact_message')
WHERE `name` = 'Administrator'
  AND JSON_SEARCH(`permission`, 'one', 'communication/contact_message', NULL, '$.access') IS NULL;

UPDATE `rms_user_group`
SET `permission` = JSON_ARRAY_APPEND(`permission`, '$.modify', 'communication/contact_message')
WHERE `name` = 'Administrator'
  AND JSON_SEARCH(`permission`, 'one', 'communication/contact_message', NULL, '$.modify') IS NULL;
