-- ════════════════════════════════════════════════════════════════════
-- F5 Tarefa 02 — Fundação do Mercado Pago Marketplace (ADR 0034)
--   1) `rms_mp_seller`: vendedores conectados via OAuth (multi-vendedor real)
--   2) grants JSON access+modify 'setting/marketplace' (padrão F2-16/F4-03)
--   3) seeds mp_* em rms_setting (INSERT IGNORE — nunca pisa valor salvo)
-- IDEMPOTENTE: pode rodar N vezes. Ajuste o prefixo se não for rms_.
-- ════════════════════════════════════════════════════════════════════

-- ─── 1. VENDEDORES CONECTADOS ───────────────────────────────────────────────
-- Tokens em TEXTO PLANO nesta fase — decisão DOCUMENTADA no ADR 0034 §6:
-- não existe chave-mestra na casa para cifragem reversível; gestão de chave
-- (KMS/env) fica para tarefa de hardening futura.

CREATE TABLE IF NOT EXISTS `rms_mp_seller` (
  `mp_seller_id`     INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `mp_user_id`       BIGINT        NOT NULL COMMENT 'user_id do vendedor no Mercado Pago',
  `nickname`         VARCHAR(190)  NOT NULL DEFAULT '' COMMENT 'apelido público (/users/me)',
  `email`            VARCHAR(190)  NOT NULL DEFAULT '',
  `access_token`     TEXT          NOT NULL COMMENT 'token OAuth do vendedor (ver ADR 0034 §6)',
  `refresh_token`    TEXT          NOT NULL,
  `scope`            VARCHAR(255)  NOT NULL DEFAULT '',
  `token_expires_at` DATETIME      NOT NULL COMMENT 'NOW() + expires_in no momento da concessão',
  `status`           TINYINT(1)    NOT NULL DEFAULT '1' COMMENT '1 = conexão utilizável',
  `date_added`       DATETIME      NOT NULL,
  `date_modified`    DATETIME      NOT NULL,
  PRIMARY KEY (`mp_seller_id`),
  UNIQUE KEY `mp_user_id` (`mp_user_id`),
  KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── 2. GRANTS (guarda JSON_SEARCH — instal. novas recebem pela seed F2-16+) ──

UPDATE `rms_user_group`
   SET `permission` = JSON_ARRAY_APPEND(`permission`, '$.access', 'setting/marketplace')
 WHERE `name` = 'Administrator'
   AND JSON_SEARCH(`permission`, 'one', 'setting/marketplace', NULL, '$.access') IS NULL;

UPDATE `rms_user_group`
   SET `permission` = JSON_ARRAY_APPEND(`permission`, '$.modify', 'setting/marketplace')
 WHERE `name` = 'Administrator'
   AND JSON_SEARCH(`permission`, 'one', 'setting/marketplace', NULL, '$.modify') IS NULL;

-- ─── 3. SEEDS DE CONFIG (app do marketplace — credenciais NUNCA no git) ─────

INSERT IGNORE INTO `rms_setting` (`code`, `key`, `value`, `serialized`) VALUES
 ('config', 'mp_app_id',        '',      0),  -- client_id do app no painel MP
 ('config', 'mp_client_secret', '',      0),  -- client_secret do app (segredo do painel)
 ('config', 'mp_env',           'test', 0),  -- test | prod (ADR 0034 §4)
 ('config', 'mp_redirect_uri',  '',      0);  -- callback cadastrado no app do MP
