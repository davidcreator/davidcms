# RELATORIO KZ-27 — Reparo do path SVG corrompido nos selos do tema

**Data:** 2026-11-17 · **Pacote:** `ReamurCMS-Frente-KZ27-SVG-Path-Repair.zip`

## Sintoma
Console: `<path> attribute d: Expected path command, "M40 24h80v84#eb767e40z"` (e variante `#a7b3b540z`).

## Causa
O `d` correto é `M40 24h80v84H40z` (confferido em `docs/temas/David-Universe-3D.reamurtheme.json`,
`tema/*.json` e `tests/Unit/Library/KazLooseEndsTest.php`). Na tabela `rms_design_theme` instalada,
o `H` foi substituído pela cor do selo durante uma importação antiga.

## Correção
`tools/upgrade_kz27_repair_svg_badge_path.sql` — REPLACE das 3 variantes corrompidas
(`#eb767e`, `#a7b3b5`, `#5cc6d0`) de volta para `H40z`, em `config` e `published_config`.

## Aplicar
```bash
mysql -u USUARIO -p BANCO < tools/upgrade_kz27_repair_svg_badge_path.sql
```
