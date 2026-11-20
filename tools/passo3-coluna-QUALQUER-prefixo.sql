-- ══════════════════════════════════════════════════════════════════════════════
-- PASSO 3 (versão universal) — coluna consultável da reconciliação
--
-- Rode DEPOIS do passo 2, com o MESMO prefixo no SET @p abaixo.
--
-- Sem esta coluna o sistema funciona: Mercado Pago reconcilia normalmente e
-- Stripe/PayPal aparecem como `missing_provider_anchor` no relatório do cron.
-- Com ela, a reconciliação de Stripe e PayPal passa a funcionar para as
-- tentativas criadas a partir de então.
--
-- SEGURO: confere information_schema antes de cada ALTER; rodar duas vezes não
-- faz nada na segunda. Nenhum dado existente é alterado.
-- ══════════════════════════════════════════════════════════════════════════════

SET @p := 'rms_';   -- ←←← O MESMO prefixo do passo 2

-- ── 3.1 Coluna provider_checkout_id ────────────────────────────────────────
SET @has_column := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = CONCAT(@p, 'payment_transaction')
    AND COLUMN_NAME = 'provider_checkout_id'
);

SET @sql := IF(@has_column = 0,
  CONCAT('ALTER TABLE `', @p, 'payment_transaction`
     ADD COLUMN `provider_checkout_id` VARCHAR(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NULL
       COMMENT ''id do recurso no provedor, consultável na reconciliação (sessão/ordem); nunca é segredo''
       AFTER `provider_reference`'),
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ── 3.2 Índice de apoio ────────────────────────────────────────────────────
SET @has_index := (
  SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = CONCAT(@p, 'payment_transaction')
    AND INDEX_NAME = 'provider_checkout'
);

SET @sql := IF(@has_index = 0,
  CONCAT('ALTER TABLE `', @p, 'payment_transaction`
     ADD KEY `provider_checkout` (`gateway_id`, `provider_checkout_id`)'),
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ── 3.3 Conferência: coluna e índice ──────────────────────────────────────
SELECT 'coluna' AS tipo, COLUMN_NAME AS nome, COLUMN_TYPE AS detalhe
  FROM information_schema.COLUMNS
 WHERE TABLE_SCHEMA = DATABASE()
   AND TABLE_NAME = CONCAT(@p, 'payment_transaction')
   AND COLUMN_NAME = 'provider_checkout_id'
UNION ALL
SELECT 'indice', INDEX_NAME, GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX)
  FROM information_schema.STATISTICS
 WHERE TABLE_SCHEMA = DATABASE()
   AND TABLE_NAME = CONCAT(@p, 'payment_transaction')
   AND INDEX_NAME = 'provider_checkout'
 GROUP BY INDEX_NAME;
-- ESPERADO: 2 linhas (coluna + índice).
