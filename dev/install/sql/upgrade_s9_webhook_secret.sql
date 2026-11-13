-- ════════════════════════════════════════════════════════════════════
-- S9 (ADR 0094) — segredos cifrados no banco.
-- O segredo de webhook passa a ser gravado cifrado, e o texto cifrado não
-- cabe nos 64 caracteres originais da coluna. Este arquivo só alarga a
-- coluna; não altera nenhum valor gravado.
--
-- Quando usar: o painel tenta fazer esta alteração sozinho (ao salvar um
-- webhook ou em Configurações > "Proteger segredos"). Rode este arquivo à
-- mão, no phpMyAdmin, apenas se a tela avisar que a coluna ainda não
-- comporta o valor cifrado (usuário do banco sem permissão de ALTER).
--
-- Pode ser repetido sem dano. Prefixo rms_ é o padrão: ajuste se o seu
-- for outro.
-- ════════════════════════════════════════════════════════════════════

ALTER TABLE `rms_webhook`
  MODIFY `secret` VARCHAR(512) NOT NULL COMMENT 'segredo HMAC por endpoint, cifrado (ADR 0094)';
