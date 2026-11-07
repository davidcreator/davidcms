# Índice — Frente BN (David Universe 3D)

Pacote: `ReamurCMS-Frente-BN-David-Universe-3D.zip`
HEAD `ad7643de…` · amostra **medida** em `davidalmeida.xyz/threejs/?lang=pt` ·
tema completo ThreeJS **declarativo** · **nenhuma escrita do agente no GitHub**
· sem migration, sem código executável.

| # | Entrada | Conteúdo |
|---|---|---|
| 1 | **`docs/temas/David-Universe-3D.reamurtheme.json`** | **O tema** — 88.864 bytes, 25 fontes, checksum `e081f0d2…` |
| 2 | `RELATORIO-FINAL-BN.md` | Amostra medida, construção, validação, limites |
| 3 | `GUIA-BN.md` | Instalação (**Ativar!**), cenas, 3 diferenças conscientes |
| 4 | `INDICE-BN.md` | Este índice |
| 5 | **`BN-Amostra-vs-Tema.png`** | Hero da amostra online × tema entregue + 4 cenas + mobile |
| 6 | `evidencia/ref.json` | Medições Chromium da amostra (cores, fontes, canvas, seções) |
| 7–9 | `evidencia/ref-{hero,desktop,mobile}.png` | Capturas reais da amostra online |
| 10–14 | `evidencia/sec-0X.png` | 6 fases scroll-driven da amostra |
| 15 | `evidencia/export-bn.txt` | **14 OK / 0 FALHAS** com as classes PHP reais |
| 16 | `evidencia/export-report.json` | Nome, slug, checksum, contagens |
| 17 | `evidencia/layout-bn.json` | 5 capturas Chromium 141 do tema |
| 18–22 | `evidencia/shot-{home,home-mob,catalogo,ferramentas,not-found}.png` | Capturas do tema |
| 23–26 | `preview/{home,catalogo,ferramentas,not-found}.html` | Prévias estáticas fiéis |
| 27 | `tools/verify-bn.cjs` | Verificador do pacote |
| 28–33 | `tools/bn_{css.py,theme_gen.py,export.php,preview.py,shot.cjs,montagem.py}`, `bn_capture.cjs`, `bn_sections.cjs` | Geradores reproduzíveis |

## Números

| Métrica | Valor |
|---|---|
| Fontes Twig | **25** (allowlist completa) |
| Fases `.du-phase` | **10** · 37 cards · 9 níveis de catálogo |
| `three.enabled` | **true** |
| Cenas por rota | **4 cenas / 5 regras `conditions`** |
| `kaz_scene` | **20 camadas**, 960×540, grid 8 |
| `validateConfig()` | **`[]`** |
| `decode()` | **`ok=true`** |
| Checksum (config canonizado) | `e081f0d26759034b15f1e200e2248199bbee43dea30765fb8977704c3f29a1d4` |
| PHP WASM | **14 OK / 0 FALHAS** |
| Chromium 141 | 5 capturas · 0 overflow (390/360/320) · 0 pageerrors · **0 scripts** · 1 slot/página |
| `bodyBg` medido (amostra = tema) | `rgb(18,21,22)` |
| Botão primário (amostra = tema) | `rgb(92,198,208)` |

## Decisões de projeto

- **Sem Google Fonts / `.webp` remotas / JS**: substituídos por stack local,
  SVG inline e `data-scene` — Recorte A intacto.
- **`parent` vazio**: autossuficiente, não depende de registro pai no banco
  (evita o defeito BL).
- **1 slot por página**: o rodapé só emite `footer_top` no 404.
- **`kaz_scene` ≠ runtime**: 20 camadas são dados de edição para a prévia do
  Admin; o WebGL no front vem de `theme-scene.twig` + `scene-runner.js`.

## Limites

`release_approved: false` · F9-27 `BLOCKED_NOT_RUN` · F9-18
`release_ready: false` · 10 `PENDING_DECISION`. **Aceite visual da BN, health
no Admin real e aplicação dos patches BL ainda pendentes.** CI verde, PHP
WASM, Chromium e guardas **não significam release aprovada**.
