-- KZ-27 — Reparo do atributo d corrompido dos selos SVG do tema David Universe 3D
-- Sintoma (console): <path> attribute d: Expected path command, "M40 24h80v84#eb767e40z"
-- Causa: em alguma importacao antiga, o "H" de "M40 24h80v84H40z" foi substituido pela
-- cor do selo (#eb767e / #a7b3b5), gerando "v84#<cor>40z". O valor correto no repo e
-- docs/temas/David-Universe-3D.reamurtheme.json e tema/*.json: d="M40 24h80v84H40z".

UPDATE `rms_design_theme`
SET `config` = REPLACE(REPLACE(REPLACE(`config`,
      'M40 24h80v84#eb767e40z', 'M40 24h80v84H40z'),
      'M40 24h80v84#a7b3b540z', 'M40 24h80v84H40z'),
      'M40 24h80v84#5cc6d040z', 'M40 24h80v84H40z'),
    `published_config` = REPLACE(REPLACE(REPLACE(`published_config`,
      'M40 24h80v84#eb767e40z', 'M40 24h80v84H40z'),
      'M40 24h80v84#a7b3b540z', 'M40 24h80v84H40z'),
      'M40 24h80v84#5cc6d040z', 'M40 24h80v84H40z'),
    `date_modified` = NOW()
WHERE `slug` = 'david_universe_3d';

-- Verificacao: deve retornar 0 (nenhum path corrompido restante) e N>0 paths sadios:
-- SELECT (LENGTH(`config`) - LENGTH(REPLACE(`config`, 'v84#', ''))) DIV 4 AS corrompidos,
--        (LENGTH(`config`) - LENGTH(REPLACE(`config`, 'M40 24h80v84H40z', ''))) DIV 17 AS sadios
-- FROM `rms_design_theme` WHERE `slug` = 'david_universe_3d';
