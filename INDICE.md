# Índice — Frente AG

| # | Arquivo | Papel |
| --- | --- | --- |
| 1 | `dev/system/library/theme/theme_preview_state.php` | **novo** — única tradutora do estado: `css()`, `globals()`, `catalogCss()` |
| 2 | `dev/system/library/template/twig.php` | 4º argumento `$themeState`, globais allowlistados no sandbox |
| 3 | `dev/admin/controller/design/theme.php` | `preview()` usa `ThemeSnapshot`; `compare()` repassa os dois estados; cache `-ag` |
| 4 | `dev/system/library/theme/theme_compare_view.php` | 6 de 6 partes de CSS + classes do estado por painel |
| 5 | `tools/kaz-theme-preview-state.php` | **novo** — PROBE 51/0, somente leitura |
| 6 | `tools/verify-kaz-preview-state.cjs` | **novo** — Chromium 24/0, mudança real medida |
| 23 | `RELATORIO.md` | este relatório |
| 24 | `INDICE.md` | este índice |

`.sha256` fica fora do ZIP, em `/home/user/entregas/`.
Frentes A–AF preservadas, aplicadas e confirmadas — não reentregues.
