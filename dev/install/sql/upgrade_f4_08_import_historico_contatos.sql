-- ════════════════════════════════════════════════════════════════════
-- F4 Tarefa 08 — Importação do histórico de contatos/pedidos do CMS legado
-- (davidalmeida.xyz, banco `u163008030_almeida`) para `rms_order`.
-- ADR 0032 — constatação: o dump legado (20/10/2026) NÃO contém tabela de
-- pedidos/orçamentos; o único "histórico" é contact_messages, cujas 9 linhas
-- eram 100% spam → NADA foi migrado nessa data. Esta rotina deixa a
-- maquinaria PRONTA: quando um dump real existir, basta colar os INSERTs
-- na staging `rms_import_contact` e rodar este arquivo N vezes (idempotente).
-- Ajuste o prefixo das tabelas se não for rms_.
-- ════════════════════════════════════════════════════════════════════

-- ─── 1. STAGING — espelho mínimo da `contact_messages` legada ───────────────
-- O bloco INSERT abaixo (marcado) é onde as linhas do dump legado entram.
-- Guarda de re-execução: PK legacy_id + INSERT IGNORE → rodar N vezes não
-- duplica a staging.

CREATE TABLE IF NOT EXISTS `rms_import_contact` (
  `legacy_id`   INT UNSIGNED  NOT NULL COMMENT 'id na contact_messages legada',
  `name`        VARCHAR(190)  NOT NULL,
  `email`       VARCHAR(190)  NOT NULL,
  `phone`       VARCHAR(60)   NOT NULL DEFAULT '',
  `subject`     VARCHAR(255)  NOT NULL DEFAULT '',
  `message`     TEXT          NOT NULL,
  `status`      VARCHAR(40)   NOT NULL DEFAULT 'new' COMMENT 'new|replied|archived|closed (outros = ignorados)',
  `ip_address`  VARCHAR(64)   NOT NULL DEFAULT '',
  `created_at`  DATETIME      NOT NULL,
  `updated_at`  DATETIME      NOT NULL,
  PRIMARY KEY (`legacy_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ▼▼▼ DADOS DO DUMP LEGADO — colar aqui (INSERT IGNORE, legacy_id = id) ▼▼▼
-- Exemplo:
-- INSERT IGNORE INTO `rms_import_contact` (`legacy_id`,`name`,`email`,`phone`,`subject`,`message`,`status`,`ip_address`,`created_at`,`updated_at`) VALUES
-- (12,'Maria Silva','maria@example.com','11999998888','Orçamento de identidade visual','Preciso de logo + paleta.','replied','201.0.12.34','2026-03-14 10:22:00','2026-03-15 09:01:00');
-- ▲▲▲ FIM DO BLOCO DE DADOS ▲▲▲▲▲▲▲▲▲▲▲▲▲▲▲▲▲▲▲▲▲▲▲▲▲▲▲▲▲▲▲▲▲▲▲▲▲▲


-- ─── 2. IMPORTAÇÃO — staging → rms_order (mapeamento ADR 0032) ─────────────
-- name      → firstname (1ª palavra, trunc 32) + lastname (restante, trunc 32)
--             sem nome em 2 partes → lastname = '-'
-- subject+message → comment ('Assunto: X' + 2×nova linha + corpo)
-- status    → new=1 Received | replied=3 Quote Sent | archived|closed=5 Completed
--             (status fora da lista é IGNORADO — ex.: spam não vira pedido)
-- phone/ip  → truncados ao limite da coluna; customer_id=0; total=0.0000
-- IDEMPOTENTE: NOT EXISTS (email + date_added + comment) → re-execução = 0 linhas.

INSERT INTO `rms_order`
  (`customer_id`,`firstname`,`lastname`,`email`,`telephone`,`total`,`order_status_id`,`comment`,`ip`,`date_added`,`date_modified`)
SELECT
  0,
  LEFT(TRIM(SUBSTRING_INDEX(s.`name`,' ', 1)), 32),
  IF(LOCATE(' ',TRIM(s.`name`)) = 0,
     '-',
     LEFT(TRIM(SUBSTRING(s.`name`, LOCATE(' ',TRIM(s.`name`)) + 1)), 32)),
  LEFT(TRIM(s.`email`), 96),
  LEFT(TRIM(s.`phone`), 32),
  0.0000,
  CASE s.`status`
    WHEN 'new'      THEN 1
    WHEN 'replied'  THEN 3
    WHEN 'archived' THEN 5
    WHEN 'closed'   THEN 5
  END,
  CONCAT('Assunto: ', s.`subject`, '\n\n', s.`message`),
  LEFT(s.`ip_address`, 40),
  s.`created_at`,
  s.`updated_at`
FROM `rms_import_contact` s
WHERE s.`status` IN ('new','replied','archived','closed')
  AND NOT EXISTS (
    SELECT 1 FROM `rms_order` o
     WHERE o.`email`       = LEFT(TRIM(s.`email`), 96)
       AND o.`date_added`  = s.`created_at`
       AND o.`comment`     = CONCAT('Assunto: ', s.`subject`, '\n\n', s.`message`)
  );


-- ─── 3. HISTÓRICO INICIAL — 1 entrada por pedido importado ──────────────────
-- Notifica em rms_order_history a origem (auditoria da migração). Idempotente
-- pelo mesmo predicado de correspondência usado no passo 2.

INSERT INTO `rms_order_history` (`order_id`,`order_status_id`,`comment`,`date_added`)
SELECT o.`order_id`, o.`order_status_id`, 'Migrado do CMS legado (F4-08/ADR 0032)', o.`date_added`
FROM `rms_import_contact` s
JOIN `rms_order` o
  ON o.`email`      = LEFT(TRIM(s.`email`), 96)
 AND o.`date_added` = s.`created_at`
 AND o.`comment`    = CONCAT('Assunto: ', s.`subject`, '\n\n', s.`message`)
WHERE s.`status` IN ('new','replied','archived','closed')
  AND NOT EXISTS (
    SELECT 1 FROM `rms_order_history` h
     WHERE h.`order_id` = o.`order_id`
       AND h.`comment`  = 'Migrado do CMS legado (F4-08/ADR 0032)'
  );
