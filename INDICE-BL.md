# Índice — Frente BL (correção definitiva)

Pacote: `ReamurCMS-Frente-BL-Vazamento-Persistente-Editor.zip`
HEAD `3219ccae…` · CI 7/7 · **2 patches + diagnóstico SQL + prova com model real**
· **nenhuma escrita do agente no GitHub** · **sem migration/SQL de escrita**.

| # | Entrada | Conteúdo |
|---|---|---|
| 1 | **`DIAGNOSTICO-BL.md`** | Causa única: repo ≠ banco; `save()` só rascunho; editor cai no disco |
| 2 | **`GUIA-BL.md`** | Ordem: diagnosticar → patches → cache → **Ativar** |
| 3 | `RELATORIO-FINAL-BL.md` | Investigação, prova, validação, limites |
| 4 | `INDICE-BL.md` | Este índice |
| 5 | **`docs/correcao/A-vazamento-failclosed.patch`** | **Obrigatório** — 6 edições / 5 arquivos |
| 6 | **`docs/correcao/B-editor-dados-reais.patch`** | Recomendado — 3 edições / 2 arquivos |
| 7 | `docs/correcao/patches.json` | Inventário dos patches |
| 8 | **`tools/diagnostico-tema.sql`** | Somente `SELECT` — qual tema está ativo e onde está o include |
| 9 | `tools/verify-bl.cjs` | Verificador |
| 10 | **`evidencia/model-harness.txt`** | 10 OK / 0 FALHAS com `design/theme.php` real |
| 11 | `evidencia/cadeia-bl.json` | Cadeia causal com arquivos e linhas do HEAD |
| 12–15 | `docs/temas/*.reamurtheme.json` | 4 temas corrigidos na BK (inalterados) |

## Causa em 3 linhas

1. `3219ccae…` commitou os **arquivos** do ZIP BK; **`dev/` não foi tocado** —
   as 3 linhas do patch continuam no HEAD (`home.twig:4`, `home.php:369`,
   `startup/theme.php:155`).
2. Temas são registros de `design_theme`. `save()`/`import()` gravam **só
   `draft_config`**; o Catálogo lê **`published_config`**, que só muda com
   **Ativar**. JSON em `docs/` não instala tema nenhum.
3. `edit()` completa `template_sources` com os `.twig` **do disco** quando o
   registro não tem `templates` → o editor mostra o tema nativo; e esses
   textareas têm `name="templates[…]"` → **salvar reintroduz o include**.

## A correção

| Patch | Mecanismo | Depende de Ativar? |
|---|---|---|
| **A** | gate `theme_scene_preview` no partial (só a prévia Admin injeta o flag) + 3 remoções + 2 flags | **Não** — fail-closed |
| **B** | herda `templates` do publicado, `template_saved_keys`, fontes nativos `disabled`/sem `name=` | — |

## Validação medida

Model real em PHP 8.4 WASM **10 OK / 0 FALHAS** · `git apply --check` **2/2**
sem conflito · PHP `TOKEN_PARSE` **4/4** · patches aplicáveis e inventariados ·
gate bloqueia no Catálogo e permite na prévia Admin ·
verificador **0 FALHAS** · nenhum patch toca `CHECKSUMS`/schema/`.github`/
allowlist/renderer · `sha256sum -c` 84/84 · CI 7/7 (lido, não disparado).

## O que NÃO faz

Não aplica os patches, não escreve no GitHub, não roda `UPDATE`/`ALTER`,
não altera `save()`/`activate()`/`buildDraftConfig()`, não decide o Recorte B,
não converte `PENDING`/`NOT_RUN`/`BLOCKED_NOT_RUN` em `PASS`, não decide
D01–D10 e **não significa release aprovada**.

`release_approved: false` · F9-27 `BLOCKED_NOT_RUN` · F9-18
`release_ready: false` · 10 `PENDING_DECISION`. **Aceite visual e health no
Admin real ainda pendentes.**
