# Índice — Frente BM (Atlas Studio 3D)

Pacote: `ReamurCMS-Frente-BM-Atlas-Studio-3D.zip`
HEAD `ad7643de…` · **`referencia=atlas` / `threejs=completo`** · tema completo
com ThreeJS **declarativo** · **nenhuma escrita do agente no GitHub** ·
**sem migration, sem código executável**.

| # | Entrada | Conteúdo |
|---|---|---|
| 1 | **`docs/temas/Atlas-Studio-3D.reamurtheme.json`** | **O tema** — 76.637 bytes, 25 fontes, checksum `59ca4cd1…` |
| 2 | `RELATORIO-FINAL-BM.md` | Construção, validação PHP real, capturas, limites |
| 3 | `GUIA-BM.md` | Instalação (**Ativar!**), cenas por rota, verificação |
| 4 | `INDICE-BM.md` | Este índice |
| 5 | `BM-Preview-Cenas.png` | Prancha visual: home / projetos / 404 / mobile |
| 6–8 | `preview/{home,projetos,not-found}.html` | Prévias estáticas fiéis |
| 9 | `evidencia/export-bm.txt` | **14 OK / 0 FALHAS** (`ThemePackage`, `ThemeSnapshot`, `ThemeSceneCss`) |
| 10 | `evidencia/export-report.json` | Nome, slug, checksum, contagens |
| 11 | `evidencia/layout-bm.json` | 4 capturas Chromium 141 |
| 12–15 | `evidencia/shot-{home,home-mobile,projetos,not-found}.png` | Capturas reais |
| 16 | `tools/verify-bm.cjs` | Verificador do pacote |
| 17–21 | `tools/bm_{theme_gen.py,preview.py,export.php,shot.cjs,montagem.py}` | Geradores reproduzíveis |

## Números

| Métrica | Valor |
|---|---|
| Fontes Twig | **25** (allowlist completa) |
| Idênticos ao Atlas aprovado | **13** |
| Templates com slot `[data-scene]` | **11** (1 por página) |
| `three.enabled` | **true** |
| Cenas por rota (`conditions`) | **4 cenas / 5 regras** |
| `kaz_scene` | **16 camadas**, 960×540, grid 8 |
| `validateConfig()` | **`[]`** |
| `decode()` | **`ok=true`** |
| Checksum (config canonizado) | `59ca4cd1ae312abd4c5a5771ed9981335d90f6fd35cc30e3aa8f233c1fb4adbf` |
| PHP WASM | **14 OK / 0 FALHAS** |
| Chromium 141 | 4 capturas · 0 overflow · 0 pageerrors · **0 scripts** · 1 slot/página |
| `verify-bm.cjs` | 0 FALHAS |

## Decisões de projeto

- **`parent` vazio**: child-theme dependeria de registro pai no banco; sem ele
  o editor cai nos `.twig` do disco (defeito BL). Tema autossuficiente.
- **1 slot por página**: o rodapé aprovado já emite o slot só em `footer_top`;
  duplicar geraria 2 containers WebGL na mesma rota.
- **`kaz_scene` ≠ runtime**: as 16 camadas são dados de edição para a prévia do
  Admin; o WebGL no front vem de `theme-scene.twig` + `scene-runner.js` nativo.
- **Recorte A intacto**: dados validados, sem `.js`/`.twig` soltos, `|raw`,
  handlers, fontes/CDN, upload ou contrato novo.

## Limites

`release_approved: false` · F9-27 `BLOCKED_NOT_RUN` · F9-18
`release_ready: false` · 10 `PENDING_DECISION`. **Aceite visual da BM, health
no Admin real e aplicação dos patches BL ainda pendentes.** CI verde, PHP
WASM, Chromium e guardas **não significam release aprovada**.
