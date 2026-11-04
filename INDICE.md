# Índice — Frente AD

| # | Arquivo | Papel |
| --- | --- | --- |
| 1 | `dev/admin/view/js/kaz-theme-editor.js` | **boot resiliente** — `boot()` após o DOM |
| 2 | `dev/admin/controller/design/theme.php` | 9 × `addScriptDeferred()`, cache `-ad` |
| 3 | `dev/system/library/document.php` | `addScriptDeferred()` / `getScriptsDeferred()` (novos) |
| 4 | `dev/admin/controller/common/header.php` | publica `scripts_deferred` |
| 5 | `dev/admin/view/template/common/header.twig` | segundo loop com `defer`; loop original intacto |
| 6 | `tools/verify-kaz-boot.cjs` | **novo** — reproduz o sintoma e prova a correção |
| 7 | `tools/diagnostico-kaztheme-somente-leitura.sql` | **novo** — SELECTs apenas; **não é migration** |
| 8 | `docs/KAZTheme-Editor-Boot-e-Ferramentas.md` | **novo** — diagnóstico completo |
| 9–17 | `docs/KAZTheme-Editor*.md` | seção AD anexada aos 9 documentos |
| 18–21 | `evidencia/*.png` | antes/depois do boot, mobile e catálogo |
| 22 | `RELATORIO.md` | este relatório |
| 23 | `INDICE.md` | este índice |

`.sha256` fica fora do ZIP, em `/home/user/entregas/`.
Frentes A–AC preservadas, aplicadas e confirmadas — não reentregues.
