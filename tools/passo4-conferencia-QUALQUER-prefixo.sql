-- ══════════════════════════════════════════════════════════════════════════════
-- PASSO 4 (versão universal) — conferência, com o prefixo no SET @p
--
-- Somente leitura. Troque @p pelo seu prefixo e execute o arquivo inteiro.
--
-- Cada bloco só consulta a tabela DEPOIS de confirmar que ela existe: assim
-- "tabela ausente" aparece como mensagem, e não como #1109 no meio da tela.
-- ══════════════════════════════════════════════════════════════════════════════

SET @p := 'rms_';   -- ←←← seu prefixo

SELECT CONCAT('prefixo em uso: ', @p) AS nota;

-- ── 4.1 As três tabelas do núcleo novo existem? (esperado: 3) ─────────────
SELECT table_name AS tabela_encontrada
  FROM information_schema.tables
 WHERE table_schema = DATABASE()
   AND table_name IN (CONCAT(@p,'jow_gateway'), CONCAT(@p,'payment_transaction'), CONCAT(@p,'payment_gateway_event'))
 ORDER BY table_name;

-- ── 4.2 A tabela de pedidos existe com este prefixo? (esperado: 1) ────────
SELECT table_name AS tabela_de_pedidos
  FROM information_schema.tables
 WHERE table_schema = DATABASE() AND table_name = CONCAT(@p, 'order');

-- ── 4.3 A coluna virtualmente calculada da reconciliação (esperado: 1) ────
SELECT column_name, column_type
  FROM information_schema.columns
 WHERE table_schema = DATABASE()
   AND table_name = CONCAT(@p, 'payment_transaction')
   AND column_name = 'provider_checkout_id';

-- ── 4.4 Retrato do legado: quanto há hoje por situação ────────────────────
-- (os links que já estão na mão dos clientes; roda só se a tabela existir)
SET @has_mp := (SELECT COUNT(*) FROM information_schema.tables
                 WHERE table_schema = DATABASE() AND table_name = CONCAT(@p, 'mp_payment'));

SET @sql := IF(@has_mp = 1,
  CONCAT('SELECT status, COUNT(*) AS n, MIN(date_added) AS mais_antiga, MAX(date_added) AS mais_nova
            FROM `', @p, 'mp_payment` GROUP BY status'),
  'SELECT ''tabela <prefixo>mp_payment não existe nesta instalação'' AS aviso');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ── 4.5 Tentativas de pedido no ledger novo (esperado: 0 antes do 1º link) ─
SET @has_tx := (SELECT COUNT(*) FROM information_schema.tables
                 WHERE table_schema = DATABASE() AND table_name = CONCAT(@p, 'payment_transaction'));

SET @sql := IF(@has_tx = 1,
  CONCAT('SELECT COUNT(*) AS tentativas_de_pedido FROM `', @p, 'payment_transaction` WHERE source_type = ''order'''),
  'SELECT ''tabela <prefixo>payment_transaction não existe — passo 2 pendente'' AS aviso');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ── 4.6 Depois do primeiro link: pedido pago x CMS em aberto (deve vir vazio)
-- Descomente quando houver tentativas de pedido.
-- SET @sql := CONCAT('SELECT t.transaction_id, t.source_id AS order_id, t.status AS ledger,
--        o.order_status_id, os.name AS situacao_do_pedido
--   FROM `', @p, 'payment_transaction` t
--   JOIN `', @p, 'order` o ON o.order_id = t.source_id
--   LEFT JOIN `', @p, 'order_status` os ON os.order_status_id = o.order_status_id
--  WHERE t.source_type = ''order'' AND t.status = ''paid'' AND o.order_status_id <> 4');
-- PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
