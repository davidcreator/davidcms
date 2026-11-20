-- ══════════════════════════════════════════════════════════════════════════════
-- PASSO 4 — Conferência (as consultas do pacote, com mensagem de erro melhor)
--
-- Rode depois dos passos 2 e 3. Somente leitura.
-- Aqui o erro #1109 não acontece: cada bloco checa antes se a tabela existe,
-- então "tabela ausente" vira uma mensagem clara em vez de erro do MySQL.
-- ══════════════════════════════════════════════════════════════════════════════

-- ── 4.1 As três tabelas do núcleo novo existem? ─────────────────────────────
SELECT table_name AS tabela_encontrada
  FROM information_schema.tables
 WHERE table_schema = DATABASE()
   AND table_name IN ('rms_jow_gateway','rms_payment_transaction','rms_payment_gateway_event')
 ORDER BY table_name;
-- ESPERADO: 3 linhas. Se voltar menos, o passo 2 não foi aplicado (ou o prefixo
-- desta instalação não é rms_ — confira com o passo 1).

-- ── 4.2 A coluna da reconciliação existe? ──────────────────────────────────
SELECT column_name, column_type
  FROM information_schema.columns
 WHERE table_schema = DATABASE()
   AND table_name = 'rms_payment_transaction'
   AND column_name = 'provider_checkout_id';
-- ESPERADO: 1 linha. Vazio = passo 3 não aplicado (Stripe/PayPal não reconciliam).

-- ── 4.3 Retrato por ledger (guarde este resultado antes de seguir) ─────────
SELECT 'payment_transaction' AS origem, source_type, status, COUNT(*) AS n
  FROM rms_payment_transaction GROUP BY source_type, status
UNION ALL
SELECT 'mp_payment' AS origem, '-' AS source_type, status, COUNT(*) AS n
  FROM rms_mp_payment GROUP BY status
 ORDER BY origem, status;
-- ESPERADO agora: só as linhas de mp_payment (o ledger novo está vazio, correto).

-- ── 4.4 Tentativas de PEDIDO (deve ser 0 antes do primeiro link) ───────────
SELECT COUNT(*) AS tentativas_de_pedido
  FROM rms_payment_transaction
 WHERE source_type = 'order';

-- ── 4.5 Quanto há hoje no legado, por situação ─────────────────────────────
SELECT status, COUNT(*) AS n, MIN(date_added) AS mais_antiga, MAX(date_added) AS mais_nova
  FROM rms_mp_payment GROUP BY status;
-- Estes são os links que já estão na mão dos clientes: anote os números.

-- ══════════════════════════════════════════════════════════════════════════════
-- DEPOIS do deploy e do primeiro link de pedido, use o bloco abaixo.
-- ══════════════════════════════════════════════════════════════════════════════

-- ── 4.6 Tentativas de pedido criadas ──────────────────────────────────────
SELECT transaction_id, source_id AS order_id, provider_code, method_code,
       status, charge_amount, charge_currency,
       provider_reference, LEFT(public_token, 8) AS token_inicio, date_added
  FROM rms_payment_transaction
 WHERE source_type = 'order'
 ORDER BY transaction_id DESC LIMIT 20;

-- ── 4.7 Pedido pago no ledger mas ainda em aberto no CMS? (deve vir vazio) ─
SELECT t.transaction_id, t.source_id AS order_id, t.status AS ledger,
       o.order_status_id, os.name AS situacao_do_pedido
  FROM rms_payment_transaction t
  JOIN rms_order o ON o.order_id = t.source_id
  LEFT JOIN rms_order_status os ON os.order_status_id = o.order_status_id
 WHERE t.source_type = 'order'
   AND t.status = 'paid'
   AND o.order_status_id <> 4;

-- ── 4.8 Link antigo ainda pendente (compatibilidade — teste F do roteiro) ─
SELECT mp_payment_id, order_id, status, LEFT(public_token, 8) AS token_inicio,
       date_added, LEFT(init_point, 60) AS endereco
  FROM rms_mp_payment
 WHERE status = 'pending'
 ORDER BY mp_payment_id DESC LIMIT 10;
