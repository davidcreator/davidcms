# Análise pós-limpeza — ReamurCMS / `main`

**Data:** 2026-11-05 · **Escopo:** somente consulta. Não removi, não alterei e não escrevi nada no GitHub.

## HEAD auditado

| Item | Valor |
| --- | --- |
| HEAD | `892fbd6993363cfc0898bf856a6030f60ce9f9ef` |
| Commit | `chore(repo): remove validation artifacts (auditoria 2026-11-05)` |
| `pushed_at` | `2026-11-05T20:25:10Z` |
| Commit anterior | `a5cc09c94fb6` — `chore(repo): add cleanup script and audit notes (pre-limpeza)` |
| Base anterior | `5593de67253a` (AM) → `5fb5eb2f553d` (AL) → `914722751cea` (AJ) |

Consulta fresca em `commits?sha=main&per_page=5`, `/git/trees/<SHA>?recursive=1` e `raw/<SHA>/…` com `Cache-Control: no-cache`.

## Antes / depois

| Métrica | Antes (`5593de…`) | Depois (`892fbd…`) | Δ |
| --- | --- | --- | --- |
| Entradas | 3278 | **3182** | −96 |
| Blobs | 2694 | **2600** | −94 |
| Bytes | 43.992.684 | **40.144.159** | **−3.848.525 (−8,7%)** |
| `evidencia/` | 50 | **0** | −50 |
| `docs/KAZTheme-Editor*.md` | 18 | **0** | −18 |
| `tools/kaz-theme-*` + `verify-kaz-*` | 18 | **0** | −18 |
| `tests/` | 33 | **33** | mantido ✅ |
| Nível 2 (`.map`, `tree/`, `reference/`, `preview/`, `entregas/`) | 46 | **46** | mantido ✅ |

> A diferença de 96 entradas para 94 blobs são as 2 entradas de diretório `evidencia/` e `evidencia/kaz-lifecycle/`, que deixaram de existir.

## O que mudou, exatamente

| Tipo | Quantidade | Conferência |
| --- | --- | --- |
| Removidos | **96** | **96/96** = `lista-remocao-segura.txt` · **0** removidos fora das duas listas · **0** da lista segura ainda presentes |
| Do Nível 2 | **0** | todos os 46 arquivos opcionais intactos |
| Modificados | **3** | `INDICE.md` 1221→954 (−3 linhas), `RELATORIO.md` 6157→6091 (−1), `docs/RELATORIO-ADERENCIA-main.md` 3230→3123 (−2) — as **6 linhas** previstas |
| Adicionados | **2** | `limpar-github.sh` (9827 B) e `docs/Auditoria-Limpeza-GitHub-2026-11-05.md` (8809 B) |

**Só 3 blobs foram modificados.** Todo o restante do repositório está **byte a byte idêntico** ao HEAD anterior — incluindo controller, os 3 CSS + 3 JS do editor, `theme_form.twig`, os 3 `language/*/design/theme.php`, `schema.sql`, workflow, guards, manifestos e `node_modules/index.html`.

## Integridade verificada no HEAD novo

| Verificação | Resultado |
| --- | --- |
| 14 `CHECKSUMS*.txt` → **90 paths** | **0 ausentes · 0 divergentes** (SHA-256 **e** tamanho recalculados via `raw/<SHA>`) |
| 11 `frozen_artifacts_sha256` | **0 ausentes · 0 divergentes** |
| `tools/decision-matrix.json` | intacto · 10 `PENDING_DECISION` · 0 `DECIDED` |
| `docs/F9-27-Status.json` | intacto · `status: BLOCKED_NOT_RUN` · **`release_approved: false`** |
| Runtime / contratos | `theme.php`, `theme_form.twig`, `schema.sql`, `.github/workflows/php.yml`, `docs/README.md`, `node_modules/index.html`, 4 guards → **INTACTOS** |
| `docs-links` | **352 `.md` varridos · 0 links apontando exatamente para os 96 removidos.** Os 3 `.md` editados não têm links Markdown; as menções restantes estão em *backticks* (código), que o `lychee --offline` não valida |
| Links "sem alvo" encontrados (8) | **pré-existentes**, em `CONTRIBUTING.md`, `agents-ia/…`, `dev/.../ckeditor/CHANGES.md` — nenhum desses arquivos foi tocado pelo commit; não foram introduzidos pela limpeza |

Não rodei o `lychee` nem os 4 guards no servidor: fiz a verificação equivalente por hash via `raw`. O CI remoto continua sem verificação direta (403 `Must have admin rights`).

## 3 pontos de atenção

### 1. ✅ Sua correção no script estava certa — e eu a incorporei

O `limpar-github.sh` que publiquei tinha um **falso positivo**: `grep -F -f` com o padrão `INDICE.md` casa como substring de `F11-04-INDICE.md` dentro dos manifestos, e abortaria a limpeza no repositório real. A versão do HEAD `892fbd…` substitui isso por uma regex ancorada ao último campo do manifesto (`MDS_RE`) — correção correta e necessária.

Sincronizei meu arquivo local com a versão do repo: **`git hash-object` = `e39f82140d63…` em ambos**, `bash -n` OK e smoke test em repositório falso com 96/96 + 6 linhas + guards 4/4.

### 2. ⚠️ O relatório dentro do repo ficou desatualizado

`docs/Auditoria-Limpeza-GitHub-2026-11-05.md` é a **versão anterior** às correções do `tests/`, enquanto o script no mesmo commit já é a versão nova. Inconsistência interna em 3 trechos:

| Linha | No repo (antigo) | Correto (versão local) |
| --- | --- | --- |
| 54 | `NÍVEL 2 … (79 arquivos / 5.125.218 bytes)` | `46 arquivos / 5.045.211 bytes` |
| 65 | `tests/` listado como removível | **`tests/` MANTIDO** — `php.yml:198` roda `find tools tests …` com `set -euo pipefail` |
| 113 | `2519 blobs, −9,0 MB` | `2552 blobs, −8,9 MB` · `96 + 46 = 142 arquivos` |

O motivo é concreto: remover `tests/` faria o `find` do step *Sintaxe dos scripts Node* reclamar de diretório inexistente. São só 80 KB. A versão corrigida está em **`/home/user/Auditoria-Limpeza-GitHub-2026-11-05.md`** (9068 bytes) — basta substituir o arquivo no repo quando quiser.

### 3. ℹ️ Texto obsoleto nos 3 `.md` (não quebra o CI)

Sobraram menções em *backticks* a arquivos de `tools/` que saíram: `INDICE.md` linhas 9–10, `RELATORIO.md` 68 e 77, `docs/RELATORIO-ADERENCIA-main.md` 40–41 e 65. Não são links Markdown, então o `docs-links` não reclama, mas a documentação agora cita PROBEs que não existem mais. Limpeza documental opcional, de baixo risco.

## Conclusão

A limpeza foi **precisa e segura**: exatamente o Nível 1, sem remoção de runtime, manifesto, contrato congelado, guard ou arquivo de CI; com as 6 linhas de link tratadas corretamente e `tests/` preservado. O ganho foi de **94 blobs / 3,85 MB**, restando **46 arquivos / 5,0 MB** do Nível 2 se quiser avançar depois (`bash limpar-github.sh --aplicar --nivel2`).

## Limites — isto não é aprovação

Continuam pendentes e **não** verificados por mim: as **2 capturas desktop + 390 px do Admin real**, `copy(__kazHealth())` em produção, **CI remoto**, sessão Admin autenticada, MySQL e deploy. `image-7.png` e `image-8.png` seguem como critério histórico. `release_approved` permanece **`false`**, F9-27 **`BLOCKED_NOT_RUN`**, BDREditor/F9-103 pausado, P2/F9-18 não reaberto. **CI verde, PROBE e Chromium headless não significam release aprovada.** Nenhuma escrita minha foi feita no GitHub.
