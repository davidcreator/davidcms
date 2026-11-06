# Índice — Frente BH (tema de referência ThreeJS)

Pacote: `ReamurCMS-Frente-BH-Tema-Referencia-ThreeJS.zip` · HEAD `dc7d2a96…` · CI 7/7 ✅
**1 pacote importável + docs + verificador** · **nenhum código alterado** · **sem migration**.

| # | Entrada | Conteúdo |
|---|---|---|
| 1 | `RELATORIO-FINAL-BH.md` | Dois mecanismos, 6 presets, validação real, limites |
| 2 | `INDICE-BH.md` | Este índice |
| 3 | **`docs/temas/Reamur-ThreeJS-Reference.reamurtheme.json`** | **O tema 3D**: 42.665 bytes |
| 4 | `docs/temas/GUIA-TEMA-THREEJS-REFERENCIA.md` | Como importar, presets, slots, como estender |
| 5 | `tools/verify-threejs-reference.cjs` | Verificador Node puro (checksum + presets + cena) |
| 6 | `evidencia/threejs-decode-harness.txt` | 52 OK / 0 FALHAS com classes reais |
| 7 | `evidencia/validacao.txt` | Guardas na réplica |

## O tema

| Campo | Valor |
|---|---|
| `name` / `slug` | `Reamur ThreeJS Reference` / `reamur_threejs_reference` |
| `checksum` | `16461680c8a1e5a42bf0c53bb1906bdeca794e99d49e3d9e3a4e1b3d94611742` |
| **`three`** | preset **aurora** (`hero-particles` / `hero` / 400 / `#8a4fff` / 0.05) |
| **`conditions`** | **6 regras = os 6 presets** (aurora, nebula, geometric, workshop, lost, off) |
| **`kaz_scene`** | v1 · 960×540 · **24 camadas** · 5 kinds · 5 roles · 7 `bindId` |
| `templates` | **25/25** allowlistadas, classes `thr-*` |
| `tokens` | 22 tokens AA |
| `parent` | vazio (tema base / child-themes) |

**Dois mecanismos:** `theme_scene` (runtime `data-*`) + `theme_scene_view`
(CSS `--rms-scene-*`). **4 cenas** e **3 slots** demonstrados.

## Como usar

Admin → Temas do Catálog → **Importar tema** → escolher o `.json` → conferir
prévia → **Importar como rascunho** → revisar → **Ativar**.
Dica: `?variant=b` → nebula; mobile ou `hour<7` → cena desligada.

## Validação medida

`validateConfig()` **0 erros** · **`decode()` ok=true** · **harness 52 OK / 0
FALHAS** com `ScenePresetRegistry::matchingPreset()`,
`ConditionEngine::evaluate()` em 7 contextos e `ThemeSceneCss::compile()` ·
checksum idêntico em Python e Node · 25 fontes seguras e balanceadas ·
verificador 0 FALHAS · `verify-checksums` 142/0 · `repo-health`
**6 PASS / 0 FAIL** · `verify-contract-freeze` **COERENTE**.

## O que NÃO faz

Não instala o tema automaticamente, não cria arquivos `.twig`/`.js`, não
escreve JavaScript, não registra novas cenas, não faz upload em disco, não
altera `AssetManifest`, `TEMPLATE_KEYS`, schema, contrato, hash, manifesto ou
workflow, não decide D01–D10, não escreve no GitHub pelo agente e **não
significa release aprovada**.

`release_approved: false` · F9-27 `BLOCKED_NOT_RUN` · F9-18
`release_ready: false` · 10 `PENDING_DECISION`. **Health e aceites visuais
ainda pendentes.**
