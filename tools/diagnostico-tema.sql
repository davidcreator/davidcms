-- Frente BL — diagnóstico SOMENTE LEITURA (nenhum INSERT/UPDATE/DELETE/ALTER).
-- Substitua <PREFIX> pelo DB_PREFIX real (ex.: rms_ ou oc_).
-- Rode no MySQL da instalação e guarde a saída antes de aplicar os patches.

SELECT theme_id,
       name,
       slug,
       status                                            AS ativo,
       CHAR_LENGTH(COALESCE(config,'{}'))                  AS bytes_config,
       CHAR_LENGTH(COALESCE(draft_config,'{}'))            AS bytes_draft,
       CHAR_LENGTH(COALESCE(published_config,'{}'))        AS bytes_publicado,
       (COALESCE(config,'')            LIKE '%theme-scene-block%') AS include_no_config,
       (COALESCE(draft_config,'')      LIKE '%theme-scene-block%') AS include_no_draft,
       (COALESCE(published_config,'')  LIKE '%theme-scene-block%') AS include_no_publicado,
       (COALESCE(config,'')            LIKE '%"templates"%')       AS templates_no_config,
       (COALESCE(draft_config,'')      LIKE '%"templates"%')       AS templates_no_draft,
       (COALESCE(published_config,'')  LIKE '%"templates"%')       AS templates_no_publicado,
       (COALESCE(published_config,'')  LIKE '%"kaz_scene"%')       AS kaz_scene_no_publicado,
       date_modified
FROM <PREFIX>design_theme
ORDER BY status DESC, theme_id ASC;

-- Interpretação:
--  * include_no_publicado = 1  -> é isto que o Catálogo está renderizando.
--  * include_no_draft = 0 e include_no_publicado = 1 -> você importou/corrigiu
--    o RASCUNHO mas não clicou em ATIVAR. O site ainda usa o payload antigo.
--  * templates_no_draft = 0    -> o editor cai nos .twig nativos do disco e
--    "não carrega as informações reais do tema".
--  * mais de um registro com status = 1 -> tema ativo ambíguo (o Catalogo usa
--    ORDER BY theme_id DESC LIMIT 1).
--
-- NÃO rode nada além de SELECT sem autorização explícita.
