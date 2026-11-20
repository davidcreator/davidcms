-- ════════════════════════════════════════════════════════════════════════════
-- JOW-Payments — reconciliação ativa (P0-b)
--
-- ADITIVA e IDEMPOTENTE. Não altera nem apaga dados existentes.
-- Adiciona uma coluna opcional ao ledger neutro já criado por
-- tools/upgrade_jow_payments_multigateway_core.sql.
--
-- Para quê: Mercado Pago guarda no ledger o id da ordem (consultável). Stripe e
-- PayPal guardavam apenas a NOSSA chave de idempotência, que não é consultável
-- no provedor — sem um id do provedor não existe reconciliação possível.
-- `provider_checkout_id` passa a guardar esse id (Stripe session, PayPal order).
--
-- O código funciona SEM esta migração:
--   · Mercado Pago reconcilia normalmente (usa provider_reference);
--   · Stripe/PayPal ficam "missing_provider_anchor" no relatório;
--   · nenhuma cobrança deixa de ser criada ou verificada.
-- Aplicar esta migração habilita a reconciliação de Stripe e PayPal para as
-- tentativas criadas A PARTIR de então.
--
-- ⚠ PREFIXO: este script escreve `rms_` fixo no texto. Confira o DB_PREFIX REAL da
-- instalação antes de aplicar (catalog/config.php ou system/config.php). Se for outro,
-- substitua `rms_` em TODO o arquivo — ou use a versão que monta o nome pelo prefixo.
-- Verificação sem abrir o config: php tools/payment-schema-drift.php --config=catalog/config.php
-- (0 = tudo certo ou só migração pendente; 1 = divergência de prefixo; 2 = erro de uso).
-- Alternativa rápida: se a instalação usa outro DB_PREFIX, troque `rms_` abaixo.
-- Datas/execução: manual, no momento aprovado pelo operador. O código não
-- executa migração automaticamente.
-- ════════════════════════════════════════════════════════════════════════════

-- 1. Coluna (MySQL 5.7+/8.x e MariaDB 10.3+ — guardada por information_schema)
SET @has_column := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'rms_payment_transaction'
    AND COLUMN_NAME = 'provider_checkout_id'
);

SET @sql := IF(@has_column = 0,
  'ALTER TABLE `rms_payment_transaction`
     ADD COLUMN `provider_checkout_id` VARCHAR(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NULL
       COMMENT ''id do recurso no provedor, consultável na reconciliação (sessão/ordem); nunca é segredo''
       AFTER `provider_reference`',
  'DO 0');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 2. Índice de apoio (consulta por gateway + id do provedor)
SET @has_index := (
  SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'rms_payment_transaction'
    AND INDEX_NAME = 'provider_checkout'
);

SET @sql := IF(@has_index = 0,
  'ALTER TABLE `rms_payment_transaction`
     ADD KEY `provider_checkout` (`gateway_id`, `provider_checkout_id`)',
  'DO 0');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 3. Verificação (o operador confere a saída antes de seguir)
SELECT 'column' AS kind, COLUMN_NAME AS name, COLUMN_TYPE AS detail
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'rms_payment_transaction'
  AND COLUMN_NAME = 'provider_checkout_id'
UNION ALL
SELECT 'index', INDEX_NAME, GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX)
FROM information_schema.STATISTICS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'rms_payment_transaction'
  AND INDEX_NAME = 'provider_checkout'
GROUP BY INDEX_NAME;

-- Nenhum backfill: as tentativas antigas continuam sem âncora (e o relatório
-- diz isso). Nenhuma tabela legada (rms_jow_charge, rms_mp_payment) é tocada.
