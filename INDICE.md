# Índice — Frente AB

| # | Arquivo | Papel |
| --- | --- | --- |
| 1 | `dev/system/library/theme/theme_scene_css.php` | **novo** — compila a cena em CSS allowlistado + camadas |
| 2 | `dev/system/library/theme/theme_snapshot.php` | publica `scene_view` / `scene_css` (9º parâmetro opcional) |
| 3 | `dev/catalog/controller/startup/theme.php` | `catalog_theme_scene_css` / `_scene_view` |
| 4 | `dev/catalog/controller/common/header.php` | concatena o CSS da cena ao `theme_token_css` |
| 5 | `dev/catalog/controller/common/home.php` | entrega `theme_scene_view` à home |
| 6 | `dev/catalog/view/template/common/home.twig` | inclui o partial da cena |
| 7 | `dev/catalog/view/template/partial/theme-scene-block.twig` | **novo** — HTML estático, autoescape, zero `|raw` |
| 8 | `dev/catalog/view/css/theme.css` | `.rms-scene*` responsivo |
| 9 | `dev/admin/controller/design/theme.php` | `activate_url`, cache `-ab` |
| 10 | `dev/admin/view/template/design/kaz_theme_editor.twig` | abas, `hidden` no servidor, stage, Publicar |
| 11 | `dev/admin/view/js/kaz-theme-editor.js` | `data-kaz-ready`, defesa em profundidade |
| 12 | `dev/admin/view/css/kaz-theme-editor.css` | layout responsivo, abas, detalhes |
| 13–18 | `docs/KAZTheme-Editor*.md` | seção AB anexada aos 6 documentos |
| 19 | `docs/KAZTheme-Editor-Tema-Publicado.md` | **novo** — referência completa da frente AB |
| 20 | `RELATORIO.md` | este relatório |
| 21 | `INDICE.md` | este índice |

`.sha256` fica **fora** do ZIP, em `/home/user/entregas/`.
Frentes A–AA preservadas, aplicadas e confirmadas — não reentregues.
