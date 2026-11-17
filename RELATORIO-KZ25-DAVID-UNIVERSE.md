# RELATORIO KZ-25 — David Universe 3D rodando a cena david-universe (ThreeJS nativo)

**Data:** 2026-11-17 · **Base:** HEAD `5cbcbb2` (CI 7/7 verde) · **Pacote:** `ReamurCMS-Frente-KZ25-David-Univese-3D.zip`

## Diagnóstico (por que o tema "não está nem perto" da referência)
A cena `david-universe` — porta da build da amostra https://davidalmeida.xyz/threejs/?lang=pt
(nave, orbes colecionáveis, voo por scroll, fases 00–08) — **já existe** no runtime:
- `dev/system/library/three/runtime/three/scenes/david-universe.js` (753 linhas), importada em `main.js`
- presente no allowlist `ScenePresetRegistry::SCENES` e no preset `universe` (slot `hero`, count 520, `#5cc6d0`, speed 0.06)

**Mas o tema nunca a selecionava:** `docs/temas/David-Universe-3D.reamurtheme.json` tinha
`scene: hero-particles` na base e na regra da rota `common/home`. E o runtime não lê esse JSON:
o Admin/Catálogo leem a tabela `rms_design_theme` (ver docs/historico/GUIA-BL.md).

## Correção
1. `docs/temas/David-Universe-3D.reamurtheme.json` — `scene`/`three.scene` = `david-universe` na base
   e na regra `du_home_particles`; `du_mobile_lite` continua `hero-particles` (cena leve no mobile).
   Checksum do pacote recalculado (regra `ThemePackage::checksum`).
2. `tools/upgrade_kz25_david_universe_home.sql` — espelha a mudança na tabela `rms_design_theme`
   (`config` e `published_config`) via JSON_SET; rode no MySQL com o prefixo correto.
3. `docs/CHANGELOG.md` + `CHECKSUMS.txt` recalculado na mesma frente (regra de manifesto).

## Pipeline após aplicar (já existente, sem código novo)
`rms_design_theme` → `startup/theme.php` → `catalog_theme_three` → `header.twig` (`has_3d`, importmap,
`<script type="module" src=".../three/main.js">`) → `partial/theme-scene.twig` emite
`data-scene="david-universe"` → `scene-runner.js` monta a cena registrada. O tema segue declarativo:
nenhum `<script>` escrito pelo tema.

## Verificação
- Após o SQL: `SELECT JSON_UNQUOTE(JSON_EXTRACT(config,'$.three.scene')) FROM rms_design_theme WHERE slug='david_universe_3d';` → `david-universe`
- Na home pública: DevTools deve mostrar o slot `.theme-scene--hero` com `data-scene="david-universe"` e o canvas WebGL da cena (nave/orbes/voo por scroll).
