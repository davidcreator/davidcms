-- ReamurCMS / Frente AD — DIAGNOSTICO SOMENTE-LEITURA.
-- ATENCAO: este arquivo NAO e uma migration e NAO deve ser aplicado.
--          A Frente AD nao exige nenhuma alteracao no banco de dados.
--          A causa de "nenhuma ferramenta responde" e a ordem de carregamento
--          do <script> no <head>, nao o schema nem os dados.
-- Serve apenas para confirmar, se desejar, que a configuracao do tema ja
-- suporta a cena publicada (kaz_scene dentro do config JSON).
-- Compatibilidade: MySQL 8 / MariaDB 10.5+. Ajuste o prefixo se necessario.

-- 1) Temas cadastrados e presenca da chave kaz_scene no config de rascunho.
SELECT theme_id, name, status,
       CASE WHEN draft_config IS NULL THEN 'sem draft_config'
            WHEN JSON_EXTRACT(draft_config, '$.kaz_scene') IS NULL THEN 'sem kaz_scene (estado derivado)'
            ELSE 'kaz_scene presente' END AS cena_rascunho
FROM oc_theme ORDER BY theme_id;

-- 2) Tema publicado (settings do store) — nao e obrigatorio para o boot do JS.
SELECT store_id, `key`, LEFT(`value`, 120) AS valor_resumido
FROM oc_setting WHERE `key` LIKE '%theme%' AND `key` NOT LIKE '%_config%'
ORDER BY store_id, `key`;

-- 3) Confirmar que NAO existe coluna/coluna nova exigida pela AD.
--    A frente usa apenas colunas preexistentes: draft_config/published_config.
SELECT COLUMN_NAME, DATA_TYPE
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'oc_theme'
ORDER BY ORDINAL_POSITION;

-- Nenhum INSERT, UPDATE, ALTER TABLE, CREATE TABLE ou DROP neste arquivo.
