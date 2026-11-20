-- ════════════════════════════════════════════════════════════════════════════
-- conferencia.sql — KZ-64, validação em sandbox (READ-ONLY)
--
-- Todas as consultas são SELECT. Rodar com o mesmo DB_PREFIX do site:
-- substitua `rms_` se o seu for outro.
--
--   mysql -u USUARIO -p BANCO < conferencia.sql
-- ════════════════════════════════════════════════════════════════════════════

-- 1) Contas de gateway e o <N> que vai na URL do webhook
SELECT gateway_id, provider_code, account_label, environment,
       CASE status WHEN 1 THEN 'ligada' ELSE 'desligada' END AS situacao,
       date_modified
FROM rms_jow_gateway ORDER BY gateway_id;

-- 2) Últimas tentativas (o que o ledger neutro diz)
SELECT transaction_id, source_type, source_id, provider_code, method_code,
       status, charge_amount, provider_reference, provider_checkout_id,
       provider_payment_id IS NOT NULL AS tem_pagamento,
       LEFT(checkout_url, 40) AS checkout, date_added, date_modified
FROM rms_payment_transaction ORDER BY transaction_id DESC LIMIT 20;

-- 3) A query do P0-a: tentativa PAGA com a cobrança da ferramenta ainda em aberto.
--    Depois do commit 03a69212 esta consulta tem de voltar VAZIA.
SELECT t.transaction_id, t.provider_code, t.status AS ledger,
       c.charge_id, c.method, c.status AS cobranca, c.paid_at
FROM rms_payment_transaction t
JOIN rms_jow_charge c ON c.charge_id = t.source_id
WHERE t.source_type = 'jow_charge' AND t.status = 'paid' AND c.status <> 'paid';

-- 4) Avisos do provedor: recebidos, aplicados, descartados
SELECT gateway_event_id, gateway_id, LEFT(provider_event_id, 24) AS evento,
       event_type, result_code, status, received_at, processed_at
FROM rms_payment_gateway_event ORDER BY gateway_event_id DESC LIMIT 20;

SELECT result_code, status, COUNT(*) AS total
FROM rms_payment_gateway_event GROUP BY result_code, status ORDER BY total DESC;

-- 5) A fila que o cron enxerga (mesma janela do serviço: 15 min de idade, 7 dias)
SELECT provider_code, status, COUNT(*) AS total,
       MIN(date_modified) AS mais_antiga, MAX(date_modified) AS mais_recente
FROM rms_payment_transaction
WHERE status IN ('pending','requires_action','authorized')
  AND date_modified <= UTC_TIMESTAMP() - INTERVAL 15 MINUTE
  AND date_added   >= UTC_TIMESTAMP() - INTERVAL 7 DAY
GROUP BY provider_code, status;

-- 6) Divergências possíveis entre a tentativa e a cobrança (só leitura)
SELECT t.transaction_id, t.status AS ledger, c.status AS cobranca,
       CASE
         WHEN t.status = 'paid' AND c.status <> 'paid' THEN 'cobrança não sincronizou'
         WHEN t.status = 'failed' AND c.status = 'pending' THEN 'recusa não propagou'
         WHEN c.status = 'paid' AND t.status <> 'paid' THEN 'cobrança paga sem ledger pago'
         ELSE 'ok'
       END AS diagnostico
FROM rms_payment_transaction t
JOIN rms_jow_charge c ON c.charge_id = t.source_id
WHERE t.source_type = 'jow_charge'
ORDER BY t.transaction_id DESC LIMIT 20;

-- 7) Cobranças da ferramenta interna, com a natureza do `method`
SELECT charge_id, method,
       CASE WHEN method IN ('pix','card','boleto') THEN 'manual' ELSE 'gateway' END AS natureza,
       status, amount, paid_at, date_added
FROM rms_jow_charge ORDER BY charge_id DESC LIMIT 20;

-- 8) Fim do roteiro: desligar a conta de sandbox (COMENTADO de propósito)
-- UPDATE rms_jow_gateway SET status = 0, date_modified = UTC_TIMESTAMP()
-- WHERE environment = 'sandbox';
-- SELECT gateway_id, provider_code, environment, status FROM rms_jow_gateway;
