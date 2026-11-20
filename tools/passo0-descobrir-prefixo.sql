-- ══════════════════════════════════════════════════════════════════════════════
-- PASSO 0 — Qual é o prefixo REAL das suas tabelas?
--
-- Cole isto no phpMyAdmin, com o banco `davidcms` selecionado.
-- 100% leitura. Não precisa saber prefixo nenhum para rodar.
--
-- Por que este arquivo existe: as consultas anteriores usavam `rms_` como
-- prefixo e o servidor respondeu #1109 para `rms_payment_transaction` E para
-- `rms_order`. Isso significa que o prefixo desta instalação não é `rms_`.
-- O prefixo fica em /system/config.php (ou catalog/config.php), na linha
-- define('DB_PREFIX', '...').
-- ══════════════════════════════════════════════════════════════════════════════

-- ── 0.1 Todas as tabelas do banco, em ordem alfabética ─────────────────────
-- Encontre as que terminam em `_order`, `_payment_transaction`, `_mp_payment`.
-- O texto antes do primeiro `_` dessas é o seu prefixo.
SELECT table_name, table_rows, engine, table_collation
  FROM information_schema.tables
 WHERE table_schema = DATABASE()
 ORDER BY table_name;

-- ── 0.2 Atalho: só as tabelas que interessam para pagamentos ──────────────
SELECT table_name, table_rows
  FROM information_schema.tables
 WHERE table_schema = DATABASE()
   AND (table_name LIKE '%payment\_transaction' ESCAPE '\\'
        OR table_name LIKE '%payment\_gateway\_event' ESCAPE '\\'
        OR table_name LIKE '%jow\_gateway' ESCAPE '\\'
        OR table_name LIKE '%mp\_payment' ESCAPE '\\'
        OR table_name LIKE '%jow\_charge' ESCAPE '\\')
 ORDER BY table_name;

-- ── 0.3 Atalho: a tabela de pedidos (é a que sempre existe) ────────────────
SELECT table_name
  FROM information_schema.tables
 WHERE table_schema = DATABASE()
   AND table_name LIKE '%order'
 ORDER BY table_name;

-- ── 0.4 Qual é o nome da coluna de pessoa na tabela de pedidos? ────────────
-- O código grava/le `firstname` (ver Dicionario-de-Dados.md §3). Aqui se
-- confirma se o install criou exatamente isso.
-- Troque PREFIXO pelo que você encontrou em 0.1/0.3 (ex.: rms_, oc_, reamur_).
-- SELECT column_name, column_type
--   FROM information_schema.columns
--  WHERE table_schema = DATABASE()
--    AND table_name = 'PREFIXOorder'
--  ORDER BY ordinal_position;
