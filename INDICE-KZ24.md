# INDICE-KZ24 — Pacote da etapa KZ-24

**Pacote:** `ReamurCMS-Frente-KZ24-Texto-do-Tema-Quebra-de-Linha.zip` · 2026-11-17 · base pós-KZ-23 (`235271c`)

| # | Entrada no ZIP | Papel |
| --- | --- | --- |
| 1 | `RELATORIO-FINAL-KZ24.md` | Entrega, validação, limites, como aplicar |
| 2 | `INDICE-KZ24.md` | Este índice |
| 3 | `dev/admin/view/js/kaz-canvas.js` | KZ-24 no editor (quebra de linha, nl2br, pre-line) |
| 4 | `dev/admin/language/pt-br/design/theme.php` | +chave `text_canvas_theme_lines_help` |
| 5 | `dev/admin/language/en-us/design/theme.php` | +chave `text_canvas_theme_lines_help` |
| 6 | `dev/admin/language/es-eu/design/theme.php` | +chave `text_canvas_theme_lines_help` |
| 7 | `tests/fixtures/kaz_theme_lines.json` | Casos do conferidor |
| 8 | `tests/check_kaz_theme_lines.js` | Conferidor Node (40 conferências) |
| 9 | `tests/Unit/Library/KazThemeLinesTest.php` | Contrato estático/servidor PHPUnit |
| 10 | `agents-ia/decisoes/0117-kazeditor-texto-do-tema-com-quebra-de-linha.md` | ADR 0117 |
| 11 | `docs/CHANGELOG.md` | Entrada KZ-24 |

**Aplicação:** descompactar sobre a raiz do projeto; recarregar o editor de tema. Não é release.
