-- ══════════════════════════════════════════════════════════════════════════════
-- PASSO 2 (versão universal) — cria as três tabelas do núcleo novo no prefixo
-- que você tem, sem precisar editar o arquivo à mão.
--
-- COMO USAR
--   1. Rode o passo 0 e descubra o prefixo (ex.: `oc_`, `reamur_`, `rms_`).
--   2. Troque APENAS a linha do SET abaixo pelo seu prefixo.
--   3. Execute o arquivo inteiro.
--
-- Por que assim: `CREATE TABLE IF NOT EXISTS` não aceita nome dinâmico, então a
-- criação passa por PREPARE — o mesmo recurso que o
-- tools/upgrade_jow_payments_reconcile.sql já usa em produção para ALTER TABLE.
-- O conteúdo das tabelas é exatamente o do SQL oficial, sem alteração.
--
-- SEGURO: nenhum INSERT/UPDATE/DELETE/DROP. Tabela que já existe é ignorada.
-- Não toca em `mp_payment`, `jow_charge`, `order` nem em qualquer tabela sua.
-- ══════════════════════════════════════════════════════════════════════════════

SET @p := 'rms_';   -- ←←← TROQUE AQUI pelo prefixo encontrado no passo 0

-- ── 2.1 rms_jow_gateway → <prefixo>jow_gateway ─────────────────────────────
SET @has := (SELECT COUNT(*) FROM information_schema.tables
              WHERE table_schema = DATABASE() AND table_name = CONCAT(@p, 'jow_gateway'));

SET @sql := IF(@has = 0,
  CONCAT('CREATE TABLE `', @p, 'jow_gateway` (
  `gateway_id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `provider_code`         VARCHAR(32) NOT NULL COMMENT ''código fixo de adapter; nunca nome de classe livre'',
  `account_label`         VARCHAR(64) NOT NULL DEFAULT '''',
  `environment`           VARCHAR(8) NOT NULL DEFAULT ''sandbox'' COMMENT ''sandbox|live'',
  `status`                TINYINT(1) NOT NULL DEFAULT 0,
  `public_config_json`    LONGTEXT NULL COMMENT ''somente configuração não secreta'',
  `credentials_encrypted_json` LONGTEXT NULL COMMENT ''JSON com cada valor cifrado por SecretBox; nenhum segredo em plaintext'',
  `date_added`            DATETIME NOT NULL,
  `date_modified`         DATETIME NOT NULL,
  PRIMARY KEY (`gateway_id`),
  UNIQUE KEY `provider_account_environment` (`provider_code`,`account_label`,`environment`),
  KEY `provider_status` (`provider_code`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'),
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ── 2.2 rms_payment_transaction → <prefixo>payment_transaction ─────────────
SET @has := (SELECT COUNT(*) FROM information_schema.tables
              WHERE table_schema = DATABASE() AND table_name = CONCAT(@p, 'payment_transaction'));

SET @sql := IF(@has = 0,
  CONCAT('CREATE TABLE `', @p, 'payment_transaction` (
  `transaction_id`      BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `source_type`         VARCHAR(24) NOT NULL COMMENT ''order|jow_charge'',
  `source_id`           BIGINT UNSIGNED NOT NULL,
  `gateway_id`          INT UNSIGNED NOT NULL,
  `provider_code`       VARCHAR(32) NOT NULL,
  `method_code`         VARCHAR(32) NOT NULL,
  `source_currency`     CHAR(3) NOT NULL,
  `source_fraction_digits` TINYINT UNSIGNED NOT NULL,
  `source_amount`       DECIMAL(28,8) NOT NULL,
  `charge_currency`     CHAR(3) NOT NULL,
  `charge_fraction_digits` TINYINT UNSIGNED NOT NULL,
  `charge_amount`       DECIMAL(28,8) NOT NULL,
  `fx_provider_code`    VARCHAR(32) NOT NULL DEFAULT '''',
  `fx_quote_reference`  VARCHAR(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NULL,
  `fx_rate`             DECIMAL(36,18) NULL,
  `fx_quoted_at`        DATETIME NULL,
  `fx_expires_at`       DATETIME NULL,
  `provider_reference`  VARCHAR(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NULL,
  `provider_payment_id` VARCHAR(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NULL,
  `checkout_url`        TEXT NULL,
  `customer_action_json` TEXT NOT NULL COMMENT ''somente instrução segura para o cliente; não resposta bruta do provedor'',
  `public_token`        CHAR(48) CHARACTER SET ascii COLLATE ascii_bin NOT NULL COMMENT ''token bearer aleatório; não registrar em logs'',
  `idempotency_key`     VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `status`              VARCHAR(24) NOT NULL DEFAULT ''pending'',
  `date_added`          DATETIME NOT NULL,
  `date_modified`       DATETIME NOT NULL,
  PRIMARY KEY (`transaction_id`),
  UNIQUE KEY `public_token` (`public_token`),
  UNIQUE KEY `source_idempotency` (`source_type`,`source_id`,`gateway_id`,`idempotency_key`),
  UNIQUE KEY `gateway_provider_reference` (`gateway_id`,`provider_reference`),
  KEY `source_latest` (`source_type`,`source_id`,`transaction_id`),
  KEY `provider_payment` (`gateway_id`,`provider_payment_id`),
  KEY `status_date` (`status`,`date_added`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'),
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ── 2.3 rms_payment_gateway_event → <prefixo>payment_gateway_event ─────────
SET @has := (SELECT COUNT(*) FROM information_schema.tables
              WHERE table_schema = DATABASE() AND table_name = CONCAT(@p, 'payment_gateway_event'));

SET @sql := IF(@has = 0,
  CONCAT('CREATE TABLE `', @p, 'payment_gateway_event` (
  `gateway_event_id`  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `gateway_id`        INT UNSIGNED NOT NULL,
  `provider_event_id` VARCHAR(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
  `event_type`        VARCHAR(64) NOT NULL DEFAULT '''',
  `event_sha256`      CHAR(64) NOT NULL COMMENT ''hash do corpo bruto ou evento normalizado; payload não é armazenado'',
  `result_code`       VARCHAR(64) NOT NULL DEFAULT '''',
  `status`            VARCHAR(16) NOT NULL DEFAULT ''received'' COMMENT ''received|processed|ignored|failed'',
  `received_at`       DATETIME NOT NULL,
  `processed_at`      DATETIME NULL,
  PRIMARY KEY (`gateway_event_id`),
  UNIQUE KEY `gateway_provider_event` (`gateway_id`,`provider_event_id`),
  KEY `event_status_date` (`status`,`received_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'),
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ── 2.4 Conferência imediata: tem de voltar 3 linhas ──────────────────────
SELECT table_name AS tabela_criada
  FROM information_schema.tables
 WHERE table_schema = DATABASE()
   AND table_name IN (CONCAT(@p,'jow_gateway'), CONCAT(@p,'payment_transaction'), CONCAT(@p,'payment_gateway_event'))
 ORDER BY table_name;
