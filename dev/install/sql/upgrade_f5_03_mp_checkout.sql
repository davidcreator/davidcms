-- ════════════════════════════════════════════════════════════════════
-- F5 Tarefa 03 — Checkout com split (marketplace_fee) + notificação de
-- pagamento do Mercado Pago (ADR 0035)
--   1) `rms_mp_payment`: pagamentos gerados por pedido (1 preference MP)
--   2) seeds mp_marketplace_fee_percent / mp_webhook_secret (INSERT IGNORE)
-- IDEMPOTENTE: pode rodar N vezes. Ajuste o prefixo se não for rms_.
-- ════════════════════════════════════════════════════════════════════

-- ─── 1. PAGAMENTOS (preference → checkout → notificação) ────────────────────
-- Relação: um pedido pode ter ao longo da vida várias tentativas (pending
-- renew), mas apenas UMA vigente por `preference_id` (UNIQUE do MP). O link
-- público usa `public_token` (não expõe order_id na URL).

CREATE TABLE IF NOT EXISTS `rms_mp_payment` (
  `mp_payment_id`    INT UNSIGNED   NOT NULL AUTO_INCREMENT,
  `order_id`         INT            NOT NULL COMMENT 'pedido/orçamento dono do pagamento',
  `mp_seller_id`     INT UNSIGNED   NOT NULL COMMENT 'vendedor (rms_mp_seller) que recebe o líquido',
  `preference_id`    VARCHAR(64)    NOT NULL COMMENT 'id da preference no MP (único por tentativa)',
  `payment_id`       BIGINT         NOT NULL DEFAULT '0' COMMENT 'preenchido quando o MP notifica (0 = ainda sem)',
  `public_token`     CHAR(48)       NOT NULL COMMENT 'token do link público checkout/payment.pay|TOKEN',
  `amount`           DECIMAL(15,4)  NOT NULL DEFAULT '0.0000',
  `marketplace_fee`  DECIMAL(15,2)  NOT NULL DEFAULT '0.00' COMMENT 'comissão da plataforma enviada ao MP',
  `currency_id`      CHAR(3)        NOT NULL DEFAULT 'BRL',
  `status`           VARCHAR(32)    NOT NULL DEFAULT 'pending' COMMENT 'pending|approved|rejected|cancelled|refunded (status MP)',
  `init_point`       TEXT           NOT NULL COMMENT 'URL do Checkout Pro devolvida pelo MP',
  `date_added`       DATETIME       NOT NULL,
  `date_modified`    DATETIME       NOT NULL,
  PRIMARY KEY (`mp_payment_id`),
  UNIQUE KEY `preference_id` (`preference_id`),
  UNIQUE KEY `public_token` (`public_token`),
  KEY `order_id` (`order_id`),
  KEY `mp_seller_id` (`mp_seller_id`),
  KEY `payment_id` (`payment_id`),
  KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── 2. SEEDS DE CONFIG (INSERT IGNORE — nunca pisa valor salvo) ────────────

INSERT IGNORE INTO `rms_setting` (`code`, `key`, `value`, `serialized`) VALUES
 ('config', 'mp_marketplace_fee_percent', '10', 0),  -- comissão % da plataforma sobre o pedido (ADR 0035 §2)
 ('config', 'mp_webhook_secret',          '',   0);  -- segredo "assinatura de webhooks" do painel MP (opcional — ADR 0035 §6)
