-- Conferência do fluxo do pedido no ledger novo (ADR 0119).
-- Somente leitura. Rode no banco do site, um bloco por vez.
-- Troque rms_ pelo prefixo real da instalação, se for diferente.

-- ── 1. As três tabelas do núcleo novo existem? ──────────────────────────────
SELECT table_name
  FROM information_schema.tables
 WHERE table_schema = DATABASE()
   AND table_name IN ('rms_jow_gateway','rms_payment_transaction','rms_payment_gateway_event')
 ORDER BY table_name;
-- esperado: 3 linhas. Menos que isso = migração não aplicada (o link de pedido não funciona).

-- ── 2. A âncora da reconciliação (Stripe/PayPal) existe? ───────────────────
SELECT column_name, column_type
  FROM information_schema.columns
 WHERE table_schema = DATABASE()
   AND table_name = 'rms_payment_transaction'
   AND column_name IN ('provider_checkout_id');
-- vazio = migração opcional não aplicada; MP reconcilia, Stripe/PayPal não.

-- ── 3. Retrato por ledger (guarde antes e depois do deploy) ────────────────
SELECT 'payment_transaction' AS origem, source_type, status, COUNT(*) AS n
  FROM rms_payment_transaction GROUP BY source_type, status
UNION ALL
SELECT 'mp_payment' AS origem, '-' AS source_type, status, COUNT(*) AS n
  FROM rms_mp_payment GROUP BY status
 ORDER BY origem, status;

-- ── 4. Tentativas de PEDIDO: as últimas 20 ────────────────────────────────
SELECT transaction_id, source_id AS order_id, provider_code, method_code,
       status, charge_amount, charge_currency,
       provider_reference, provider_payment_id,
       LEFT(public_token, 8) AS token_inicio, date_added, date_modified
  FROM rms_payment_transaction
 WHERE source_type = 'order'
 ORDER BY transaction_id DESC LIMIT 20;

-- ── 5. Pedido pago no ledger e pedido ainda em aberto no CMS? (deve vir vazio)
SELECT t.transaction_id, t.source_id AS order_id, t.status AS ledger,
       o.order_status_id, os.name AS situacao_do_pedido, t.date_modified
  FROM rms_payment_transaction t
  JOIN rms_order o ON o.order_id = t.source_id
  LEFT JOIN rms_order_status os ON os.order_status_id = o.order_status_id
 WHERE t.source_type = 'order'
   AND t.status = 'paid'
   AND o.order_status_id <> 4
 ORDER BY t.date_modified DESC;

-- ── 6. Tentativa aberta antiga com aviso perdido (fila do cron) ────────────
-- Estes são os que a reconciliação deve resolver sozinha.
SELECT transaction_id, source_id AS order_id, provider_code, status,
       TIMESTAMPDIFF(MINUTE, date_modified, UTC_TIMESTAMP()) AS minutos_sem_aviso
  FROM rms_payment_transaction
 WHERE source_type = 'order'
   AND status IN ('pending','requires_action','authorized')
   AND date_modified <= UTC_TIMESTAMP() - INTERVAL 15 MINUTE
 ORDER BY date_modified ASC LIMIT 25;

-- ── 7. Eventos recebidos x aplicados (por gateway) ────────────────────────
SELECT gateway_id,
       SUM(status = 'processed') AS aplicados,
       SUM(status = 'ignored')   AS ignorados,
       SUM(status = 'failed')    AS falhas,
       COUNT(*)                  AS total
  FROM rms_payment_gateway_event
 WHERE received_at >= UTC_TIMESTAMP() - INTERVAL 7 DAY
 GROUP BY gateway_id;

-- ── 8. Link antigo do legado ainda pendente (compatibilidade) ──────────────
-- Estes precisam continuar abrindo; abra um no navegador e confira o 302.
SELECT mp_payment_id, order_id, status, LEFT(public_token, 8) AS token_inicio,
       date_added, LEFT(init_point, 60) AS endereco
  FROM rms_mp_payment
 WHERE status = 'pending'
 ORDER BY mp_payment_id DESC LIMIT 10;

-- ── 9. Pedido novo nasceu no legado depois do deploy? (esperado: 0) ────────
-- Ajuste a data/hora do deploy.
SELECT COUNT(*) AS pedidos_no_legado_desde_o_deploy
  FROM rms_mp_payment
 WHERE date_added > '2026-11-20 00:00:00';

-- ── 10. Chaves de idempotência de pedido (formato e unicidade) ────────────
SELECT idempotency_key, COUNT(*) AS repeticoes
  FROM rms_payment_transaction
 WHERE source_type = 'order'
 GROUP BY idempotency_key
HAVING repeticoes > 1;
-- vazio = nenhuma chave repetida entre tentativas (a UNIQUE já garante por gateway).
