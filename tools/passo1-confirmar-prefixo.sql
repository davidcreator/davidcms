-- ══════════════════════════════════════════════════════════════════════════════
-- PASSO 1 — Descobrir as tabelas REAIS da sua instalação
--
-- Cole no phpMyAdmin, com o banco `davidcms` selecionado.
-- Nada aqui cria, altera ou apaga nada: são apenas consultas de leitura.
-- ══════════════════════════════════════════════════════════════════════════════

-- 1.1 Quais tabelas de pagamento já existem (e com que prefixo)?
SELECT table_name, table_rows
  FROM information_schema.tables
 WHERE table_schema = DATABASE()
   AND (table_name LIKE '%jow_charge'
        OR table_name LIKE '%mp_payment'
        OR table_name LIKE '%payment_transaction'
        OR table_name LIKE '%jow_gateway'
        OR table_name LIKE '%payment_gateway_event')
 ORDER BY table_name;

-- 1.2 Qual é a coluna de pessoa que o install criou? (TEM que se chamar firstname)
SHOW COLUMNS FROM `rms_order` LIKE '%name%';

-- 1.3 A tabela de pedidos existe? (confirma que o prefixo é rms_)
SHOW TABLES LIKE 'rms_order';
SHOW TABLES LIKE 'rms_order_status';

-- 1.4 As tabelas do núcleo novo já existem? (se as duas primeiras voltarem linha,
--     a migração já foi aplicada e você NÃO precisa do passo 2)
SHOW TABLES LIKE 'rms_payment_transaction';
SHOW TABLES LIKE 'rms_jow_gateway';
SHOW TABLES LIKE 'rms_payment_gateway_event';
