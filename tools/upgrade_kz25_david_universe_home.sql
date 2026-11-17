-- KZ-25 — David Universe 3D: home passa a rodar a cena david-universe (ThreeJS nativo)
-- O runtime (startup/theme.php -> catalog_theme_three -> header.twig -> theme-scene.twig)
-- le a tabela rms_design_theme, nao o JSON em docs/temas/. Este patch espelha no banco
-- a mudanca feita em docs/temas/David-Universe-3D.reamurtheme.json.
-- Cena 'david-universe' ja esta no allowlist (ScenePresetRegistry::SCENES) e registrada
-- em dev/system/library/three/runtime/three/scenes/david-universe.js (main.js importa).
-- A regra de mobile (du_mobile_lite) continua hero-particles (cena leve).

UPDATE `rms_design_theme`
SET `config` = JSON_SET(`config`,
      '$.scene', 'david-universe',
      '$.three.scene', 'david-universe',
      '$.conditions.rules[0].then.scene', 'david-universe',
      '$.conditions.rules[0].then.three.scene', 'david-universe'),
    `published_config` = JSON_SET(`published_config`,
      '$.scene', 'david-universe',
      '$.three.scene', 'david-universe',
      '$.conditions.rules[0].then.scene', 'david-universe',
      '$.conditions.rules[0].then.three.scene', 'david-universe'),
    `date_modified` = NOW()
WHERE `slug` = 'david_universe_3d';

-- Verificacao (esperado: cena = david-universe nas duas colunas):
-- SELECT JSON_UNQUOTE(JSON_EXTRACT(`config`, '$.three.scene')) AS cena_config,
--        JSON_UNQUOTE(JSON_EXTRACT(`published_config`, '$.three.scene')) AS cena_publicada
-- FROM `rms_design_theme` WHERE `slug` = 'david_universe_3d';
