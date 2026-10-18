-- ════════════════════════════════════════════════════════════════════
-- F2 Tarefa 18 — Hardening: tabela de rate limit (ADR 0018) para
-- instalações EXISTENTES (instalações novas já nascem com a tabela —
-- seção 9 do schema.sql do wizard).
-- IDEMPOTENTE: CREATE IF NOT EXISTS; pode rodar mais de uma vez.
-- Ajuste o prefixo das tabelas se não for rms_.
-- ════════════════════════════════════════════════════════════════════

CREATE TABLE IF NOT EXISTS `rms_rate_limit` (
  `scope`        VARCHAR(32)  NOT NULL COMMENT 'login | contact | quote',
  `ip`           VARCHAR(40)  NOT NULL,
  `attempts`     INT          NOT NULL DEFAULT 0,
  `window_start` DATETIME     NOT NULL,
  PRIMARY KEY (`scope`, `ip`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
