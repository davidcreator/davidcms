-- ════════════════════════════════════════════════════════════════════
-- S9 (ADR 0094) — desfaz upgrade_s9_webhook_secret.sql.
-- ATENÇÃO: só rode depois de voltar o código para a versão anterior E de
-- ter regravado os segredos de webhook em texto legível (digitando-os de
-- novo na tela de cada webhook). Um valor cifrado tem mais de 64
-- caracteres: com ele na coluna, este comando falha (modo estrito) ou
-- corta o valor, que deixa de abrir.
-- ════════════════════════════════════════════════════════════════════

ALTER TABLE `rms_webhook`
  MODIFY `secret` VARCHAR(64) NOT NULL COMMENT 'segredo HMAC por endpoint';
