-- ═══════════════════════════════════════════════════════════════════
-- EX-01 (ADR 0095) — Extensões: permissão da tela nova `extension/extension`
-- (admin > Extensões) para o grupo Administrator de uma instalação EXISTENTE.
-- Instalação nova já nasce com a permissão (wizard + schema-complete.sql).
--
-- Só ACL: não cria tabela nem coluna. O estado das extensões usa a tabela
-- rms_setting que já existe (code = 'extension').
-- IDEMPOTENTE: pode rodar mais de uma vez sem duplicar permissões.
-- Alternativa equivalente pela tela: Usuários → Grupos de usuários → editar
-- o grupo e marcar 'extension/extension' em acessar e em modificar.
-- Ajuste o prefixo das tabelas se não for rms_.
-- Desfazer: rollback_ex01_extension_grant.sql
-- ═══════════════════════════════════════════════════════════════════

UPDATE `rms_user_group`
SET `permission` = JSON_ARRAY_APPEND(`permission`, '$.access', 'extension/extension')
WHERE (`name` = 'Administrator' OR `user_group_id` = 1)
  AND JSON_SEARCH(JSON_EXTRACT(`permission`, '$.access'), 'one', 'extension/extension') IS NULL;

UPDATE `rms_user_group`
SET `permission` = JSON_ARRAY_APPEND(`permission`, '$.modify', 'extension/extension')
WHERE (`name` = 'Administrator' OR `user_group_id` = 1)
  AND JSON_SEARCH(JSON_EXTRACT(`permission`, '$.modify'), 'one', 'extension/extension') IS NULL;
