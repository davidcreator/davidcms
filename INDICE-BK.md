# Índice — Frente BK (correção do vazamento do KAZEditor-Theme)

Pacote: `ReamurCMS-Frente-BK-Correcao-Stage-Editor.zip` · HEAD `ab8b765…`
**4 temas corrigidos + patch de runtime (proposta) + diagnóstico + prova**
· **nenhum arquivo do repositório escrito pelo agente** · **sem migration**.

| # | Entrada | Conteúdo |
|---|---|---|
| 1 | **`DIAGNOSTICO-BK.md`** | Cadeia causal com arquivos/linhas, garantia quebrada, 2ª observação |
| 2 | **`GUIA-CORRECAO-BK.md`** | Os 2 passos: reimportar temas + `git apply` |
| 3 | `RELATORIO-FINAL-BK.md` | Investigação, correção, validação, limites |
| 4 | `INDICE-BK.md` | Este índice |
| 5 | **`docs/temas/David-Creator.reamurtheme.json`** | BJ corrigido · `f2e53d07…` |
| 6 | **`docs/temas/Atlas-Studio.reamurtheme.json`** | BI corrigido · `dced34ce…` |
| 7 | **`docs/temas/Reamur-Reference.reamurtheme.json`** | BG corrigido · `0197e140…` |
| 8 | **`docs/temas/Reamur-ThreeJS-Reference.reamurtheme.json`** | BH corrigido · `1c6fc07a…` |
| 9 | **`docs/correcao/correcao-runtime.patch`** | Proposta: 4 linhas em 3 arquivos do Catálogo |
| 10 | `docs/correcao/patch.json` | Inventário: alvos, linhas, Admin intacto |
| 11 | `tools/verify-no-scene-leak.cjs` | Verificador anti-vazamento |
| 12 | **`evidencia/antes-depois.png`** | Sua captura real × prévia corrigida |
| 13 | `evidencia/layout-check.json` | 1 stage / 3 camadas → **0 / 0** |
| 14 | `evidencia/theme-export.txt` | 32 OK / 0 FALHAS com `export()`/`decode()` reais |

## O bug

`kaz_scene` → `ThemeSceneCss::compile()` → `scene_view.enabled=true` →
`startup/theme.php:155` publica `catalog_theme_scene_view` →
`home.php:369` injeta `theme_scene_view` → `home.twig:4` inclui
`theme-scene-block.twig` no topo do `<main>` → `<section class="rms-scene">`
**no site público**. O próprio editor documenta que a cena **não** é
renderização no site público — garantia violada pela Frente AB.

## A correção

| Camada | O que | Estado |
|---|---|---|
| **A — temas** | remove o include dos 4 pacotes (2/2/2/**12** pontos), mantém `kaz_scene`, CSS, ThreeJS | ✅ entregue, reimportar |
| **B — runtime** | remove 4 linhas em 3 arquivos do Catálogo; Admin intacto | ⚠️ **proposta, não aplicada** |

## Validação medida

`validateConfig()` **0 erros** · **`export()`/`decode()` reais 32 OK / 0
FALHAS** · checksums independentes **4/4** · **0** ocorrências de
`theme-scene-block.twig` · `theme-scene.twig` mantido nos 4 · Twig balanceado
· verificador **0 FALHAS** · antes/depois **1 stage + 3 camadas → 0 + 0**
com hero, CTA, footer e 10 seções preservados · CI **7/7**.

## O que NÃO faz

Não aplica o patch, não escreve no GitHub, não cria migration/schema,
não altera contrato, hash, manifesto, workflow, allowlist ou renderer,
não decide o Recorte B, não converte `PENDING`/`NOT_RUN`/`BLOCKED_NOT_RUN`
em `PASS`, não decide D01–D10 e **não significa release aprovada**.

`release_approved: false` · F9-27 `BLOCKED_NOT_RUN` · F9-18
`release_ready: false` · 10 `PENDING_DECISION`. **Aceite visual da BJ/BK e
health no Admin real ainda pendentes.**
