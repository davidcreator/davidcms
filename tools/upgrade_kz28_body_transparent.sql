-- KZ-28 — fundo do body transparente para o canvas fixo (z-index:-1) da cena david-universe
-- ficar visivel atras do conteudo. O theme.css do sistema poe body{background:var(--bg)}
-- opaco, que pinta POR CIMA de qualquer elemento com z-index negativo.
-- Idempotente: funciona esteja ou nao o KZ-26 aplicado.

UPDATE `rms_design_theme`
SET `config` = REPLACE(REPLACE(REPLACE(`config`,
      '.theme-scene.theme-scene--hero{min-height:320px;margin:0 0 34px}',
      '.theme-scene.theme-scene--hero{position:fixed;inset:0;z-index:-1;min-height:100vh;margin:0;border:0;border-radius:0;pointer-events:none}'),
      'background:var(--du-bg);-webkit-font-smoothing:antialiased}',
      'background:transparent;-webkit-font-smoothing:antialiased}'),
      ':root{', 'body{background:transparent}\n:root{'),
    `published_config` = REPLACE(REPLACE(REPLACE(`published_config`,
      '.theme-scene.theme-scene--hero{min-height:320px;margin:0 0 34px}',
      '.theme-scene.theme-scene--hero{position:fixed;inset:0;z-index:-1;min-height:100vh;margin:0;border:0;border-radius:0;pointer-events:none}'),
      'background:var(--du-bg);-webkit-font-smoothing:antialiased}',
      'background:transparent;-webkit-font-smoothing:antialiased}'),
      ':root{', 'body{background:transparent}\n:root{'),
    `date_modified` = NOW()
WHERE `slug` = 'david_universe_3d';

-- Verificacao (esperado: body_transp=1, hero_fixo=1 em config e published_config):
-- SELECT (LENGTH(`config`)-LENGTH(REPLACE(`config`,'body{background:transparent}',''))) DIV 26 AS body_transp,
--        (LENGTH(`config`)-LENGTH(REPLACE(`config`,'position:fixed;inset:0;z-index:-1',''))) DIV 33 AS hero_fixo
-- FROM `rms_design_theme` WHERE `slug`='david_universe_3d';
