-- KZ-26 — David Universe 3D: cena david-universe vira FUNDO FIXO fullscreen atras do conteudo
-- (como a referencia https://davidalmeida.xyz/threejs/). O tema injeta o CSS via template
-- partial/site_header; o runtime le a tabela rms_design_theme. Este patch aplica no banco
-- os mesmos dois trechos alterados em docs/temas/David-Universe-3D.reamurtheme.json:
--   1) .du deixa de ter background solido (transparent) para o canvas aparecer atras;
--   2) .theme-scene--hero vira position:fixed;inset:0;z-index:-1 (cena atras do conteudo).
-- Atencao: as strings abaixo casam com o JSON serializado (newlines sao \n literais),
-- por isso os trechos escolhidos nao contem quebras de linha.

UPDATE `rms_design_theme`
SET `config` = REPLACE(REPLACE(`config`,
      'background:var(--du-bg);-webkit-font-smoothing:antialiased}',
      'background:transparent;-webkit-font-smoothing:antialiased}'),
      '.theme-scene.theme-scene--hero{min-height:320px;margin:0 0 34px}',
      '.theme-scene.theme-scene--hero{position:fixed;inset:0;z-index:-1;min-height:100vh;margin:0;border:0;border-radius:0;pointer-events:none}'),
    `published_config` = REPLACE(REPLACE(`published_config`,
      'background:var(--du-bg);-webkit-font-smoothing:antialiased}',
      'background:transparent;-webkit-font-smoothing:antialiased}'),
      '.theme-scene.theme-scene--hero{min-height:320px;margin:0 0 34px}',
      '.theme-scene.theme-scene--hero{position:fixed;inset:0;z-index:-1;min-height:100vh;margin:0;border:0;border-radius:0;pointer-events:none}'),
    `date_modified` = NOW()
WHERE `slug` = 'david_universe_3d';

-- Verificacao (esperado: 2 em cada coluna = os dois REPLACE aplicaram):
-- SELECT (LENGTH(`config`) - LENGTH(REPLACE(`config`, 'position:fixed;inset:0;z-index:-1', ''))) DIV LENGTH('position:fixed;inset:0;z-index:-1') AS fixos_config,
--        (LENGTH(`config`) - LENGTH(REPLACE(`config`, 'background:transparent;-webkit-font-smoothing', ''))) DIV LENGTH('background:transparent;-webkit-font-smoothing') AS transp_config
-- FROM `rms_design_theme` WHERE `slug` = 'david_universe_3d';
