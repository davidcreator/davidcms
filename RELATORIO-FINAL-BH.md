# Relatório Final — Frente BH (tema completo em ThreeJS de referência)

**Data:** 2026-11-06 · **HEAD auditado:** `dc7d2a969297bfd523f4a5c7b158385d3c2b3d8c`
**CI remoto:** ✅ 7/7 success · **Pedido:** *"fazer um tema completo em ThreeJS que servirá como referência para criar outros temas"*.

## 1. O que foi entregue

**`docs/temas/Reamur-ThreeJS-Reference.reamurtheme.json`** — pacote
`reamurcms-theme` v1 importável pelo botão **Importar tema**. **42.665 bytes**,
checksum `16461680c8a1e5a42bf0c53bb1906bdeca794e99d49e3d9e3a4e1b3d94611742`.

Acompanham: `docs/temas/GUIA-TEMA-THREEJS-REFERENCIA.md`,
`tools/verify-threejs-reference.cjs` e `evidencia/threejs-decode-harness.txt`.

| Bloco | Conteúdo |
|---|---|
| `name` / `slug` / `parent` | `Reamur ThreeJS Reference` / `reamur_threejs_reference` / vazio |
| `primary` / `surface` / `scene` | `#8a4fff` / `#101827` / `hero-particles` |
| **`three`** | preset **aurora** completo (`enabled`, `scene`, `slot`, `count=400`, `color`, `speed`) |
| **`conditions`** | **6 regras**, uma para **cada um dos 6 presets reais** |
| **`kaz_scene`** | `v1`, canvas 960×540, **24 camadas** com os **5 kinds** e **5 roles**, 7 `bindId` |
| `templates` | **25/25 fontes** adaptadas ao 3D (~19,7 KB), classes `thr-*` |
| `tokens` | 22 tokens com contraste AA validado |

## 2. Os dois mecanismos 3D cobertos

| Mecanismo | Origem | Consumo |
|---|---|---|
| **Runtime declarativo** `theme_scene` | `three` + regras | `partial/theme-scene.twig` emite `data-scene/count/color/speed`; `scene-runner.js` faz `boot()` |
| **Composição** `theme_scene_view` | `kaz_scene` | `ThemeSceneCss::compile()` → CSS `--rms-scene-*` + `partial/theme-scene-block.twig` |

As 4 cenas allowlistadas (`hero-particles`, `portfolio-3d`, `tools-3d`,
`not-found`) e os **3 slots** (`hero`, `home_top`, `footer_top`) aparecem.
O tema **não escreve JavaScript** — modelo de dados validados confirmado nas
frentes BC/BF.

### Os 6 presets em regras condicionais

| Regra | Gatilho | Preset |
|---|---|---|
| `home-aurora` | `route = common/home` | aurora |
| `portfolio-geometric` | `path` contém `project` | geometric |
| `tools-workshop` | `path` contém `tool` | workshop |
| `notfound-lost` | `route = error/not_found` | lost |
| `variant-nebula` | `query.variant = b` | nebula |
| `mobile-off` | `device=mobile` **ou** `hour<7` | off (desliga a cena) |

## 3. Validação com as classes REAIS (PHP 8.5.12 WASM)

Carreguei `ThemePackage`, `ConditionEngine`, `TokenRegistry`,
`SectionRegistry`, `ComponentRegistry`, `ThemeSchedule`, `ThemeMedia`,
`ThemeSites`, `PageLayout`, **`ScenePresetRegistry`** e **`ThemeSceneCss`**.

**52 OK / 0 FALHAS**, incluindo:

- `validateConfig()` = `[]` e **`decode()` ok=true** com checksum conferido;
- `ScenePresetRegistry::matchingPreset()` retornando **o preset correto para o
  `three` de base e para cada uma das 6 regras**; `validate()` = `[]` em todos;
- `ConditionEngine::validate()` = `[]` e **`evaluate()` em 7 contextos reais**
  (home, portfolio, tools, 404, `?variant=b`, mobile, hora<7) casando as regras
  e produzindo o preset esperado;
- `ThemePackage::validateScene()` = `[]`, `ThemeSceneCss::compile()` com
  `enabled=true`, **24 camadas** e CSS iniciando em `--rms-scene-w:960`;
- **5 kinds e 5 roles** cobertos, 7 `bindId`, `export()` reproduzindo o
  checksum e **1 byte alterado sendo recusado**.

O checksum também foi recalculado de forma independente em Python/Node e bate
exatamente.

## 4. Segurança e escopo

Fontes Twig sem `<script|iframe|object|embed`, `javascript:`, `on…=`, `|raw`,
`{% autoescape`, `srcdoc=`, `formaction=`, CDN, `@import`, `!important`,
`--bs-*` ou `--rms-*`. Sem arquivos `.twig`/`.js` no servidor, upload em disco,
`eval` ou código executável.

`docs/temas/` já existe desde a BG; testei na réplica fiel: o novo arquivo
**não** entra em `CHECKSUMS*.txt` e **não** altera os guardas —
`verify-checksums` 142 arquivos / 0 divergentes, `repo-health`
**6 PASS / 0 FAIL**, `verify-contract-freeze` **COERENTE**.

**Nenhum código foi alterado**: nenhuma migration, `schema.sql`, contrato
congelado, hash, manifesto, workflow, `AssetManifest`, `ScenePresetRegistry`,
`ThemePackage`, `TEMPLATE_KEYS`, controller, model, idioma ou arquivo de
runtime. **Nada é instalado automaticamente** — import continua **sempre como
rascunho**, nunca publicando.

## 5. Validação consolidada

| Verificação | Resultado |
|---|---|
| `validateConfig()` / `decode()` reais | ✅ 0 erros / ok=true |
| **Harness PHP com presets, condições e CSS** | ✅ **52 OK / 0 FALHAS** |
| Checksum independente (Python + Node) | ✅ idêntico |
| 25 fontes: allowlist, segurança, Twig balanceado | ✅ 25/25, 0 violações |
| `tools/verify-threejs-reference.cjs` | ✅ 0 FALHAS |
| `verify-checksums` / `repo-health` / `verify-contract-freeze` | ✅ 142/0 · 6 PASS/0 FAIL · COERENTE |
| CI remoto do HEAD | ✅ 7/7 success (lido, não disparado) |

## 6. Limites

Nenhuma escrita do agente no GitHub, nenhum runtime/MySQL/staging/deploy,
nenhuma conversão de `PENDING`/`NOT_RUN`/`BLOCKED_NOT_RUN` em `PASS`, nenhuma
decisão das **10 `PENDING_DECISION`**, nenhuma mudança em
`PROPOSAL_PENDING` / `DESIGN_GATE_PENDING`, nenhuma declaração de release.

`release_approved: false` · F9-27 `BLOCKED_NOT_RUN` · F9-18
`release_ready: false`. CI verde, PHP WASM e guardas estáticos **não
significam release aprovada** e não substituem o aceite no Admin real.

**Ainda pendentes:** JSON do **health** do Admin real e os aceites visuais
(Ferramentas internas, nomes, lista, gerenciador, tema Twig e agora o tema
ThreeJS).

## 7. Próximos passos

1. **Importar** o pacote, conferir a prévia e dar o aceite visual; testar
   `?variant=b` e mobile.
2. **Recorte B** — ampliar `TEMPLATE_KEYS` (diga quais templates).
3. Passos do roteiro: health (2), D01–D10 com **AR** (3), F918 com **AS** (4).
