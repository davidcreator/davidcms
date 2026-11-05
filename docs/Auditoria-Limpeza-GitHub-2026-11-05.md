# Auditoria de limpeza do GitHub — ReamurCMS / `main`

**Data:** 2026-11-05 · **HEAD consultado:** `5593de67253a018db488592f70699e51cbc0a952`
(`feat(theme-editor): add on-screen editor adjustments`, `pushed_at 17:04:13Z`)
**Escopo:** **somente consulta e listagem**. Não removi nada, não alterei nada, não escrevi no GitHub.

Consulta feita em `commits?sha=main`, `/git/trees/<SHA>?recursive=1` e`raw/<SHA>/…` com `Cache-Control: no-cache`. A árvore é idêntica à da última auditoria (32/32 AM byte a byte).

## Raio-X

| Métrica | Valor |
| --- | --- |
| Entradas / blobs | **3278 / 2694** (`truncated:false`) |
| Peso total dos blobs | **43.992.684 bytes (~44 MB)** |
| Maiores blocos | `dev/` 1970 arquivos / 36,3 MB · `docs/` 260 / 1,63 MB · `tools/` 198 / 1,45 MB · `agents-ia/` 103 / 0,51 MB · `evidencia/` 50 / **3,20 MB** |
| Raiz | 48 arquivos · nenhum `.zip`, `.sha256`, `.log` ou `.bak` no repo |
| Manifestos | **14** `CHECKSUMS*.txt` → **90 paths** referenciados, todos presentes |
| Congelados | **11** artefatos em `tools/decision-matrix.json`, todos presentes |
| CI | `.github/workflows/php.yml` com `docs-links` = `lychee --offline './**/*.md'` e `fail/failIfEmpty=true` |

A poluição **não** é código: é material de validação acumulado pelas frentes A–AM, mais snapshots e sourcemaps.

---

## NÍVEL 1 — Pode remover com segurança (96 arquivos / 3.866.721 bytes)

Lista completa em **`lista-remocao-segura.txt`** (um path por linha, pronta para `git rm`).

| Bloco | Arquivos | Bytes | Por que é seguro |
| --- | --- | --- | --- |
| `evidencia/` | 50 | 3.203.776 | PNGs e HTMLs de teste das frentes AB→AM. Nenhum em manifesto, nenhum congelado, nenhum em `dev/`. |
| `RELATORIO-FINAL-AH/AI/AJ/AL/AM.md` + `INDICE-AH/AI/AJ/AL/AM.md` | 10 | 27.328 | Relatórios/índices de entrega, já reproduzidos nos ZIPs do workspace. |
| `docs/KAZTheme-Editor*.md` | 18 | 491.356 | Documentação das frentes, com as seções A→AM repetidas em cada arquivo. |
| `tools/kaz-theme-*` e `tools/verify-kaz-*` | 18 | 144.261 | PROBEs e verificadores Chromium das frentes. **Nenhum** é exigido por `repo-health.sh`, `verify-checksums.sh`, `verify-ci-workflow.sh` nem pelo workflow. |

Verificado: **0** desses 96 estão nos 90 paths dos manifestos, **0** nos 11 congelados, **0** dentro de `dev/` (runtime). A remoção não toca em `verify-checksums.sh` nem em `verify-contract-freeze.py`.

> ⚠️ **Atenção aos PROBEs:** o `kaz-theme-ergonomia-probe.php` exige exatamente 17 docs e o da AM exige 18. Se você remover os 18 docs, os PROBEs que também serão removidos não rodam mais — remova o bloco **inteiro**, não pela metade.

### Pré-requisito obrigatório: 3 arquivos com links que quebrariam o CI

O job `docs-links` valida links internos em `./**/*.md`. Três `.md` que **permanecem** citam arquivos do bloco acima:

| Arquivo | Linhas | Trecho |
| --- | --- | --- |
| `INDICE.md` | 11, 12, 13 | `docs/KAZTheme-Editor-Estado-Efetivo-Previa.md`, `docs/KAZTheme-Editor*.md`, `evidencia/*.png` |
| `RELATORIO.md` | 82 | `evidencia/estado_a.png`, `estado_b.png`, `estado_b_mobile.png` |
| `docs/RELATORIO-ADERENCIA-main.md` | 43, 67 | `docs/KAZTheme-Editor*.md`, `docs/KAZTheme-Editor-Diagnostico-em-Producao.md` |

**Faça antes ou no mesmo commit:** apague essas 6 linhas (ou os 3 arquivos, se forem só índices históricos). Os três **não** estão nos manifestos nem entre os congelados, então editá-los não quebra `verify-checksums.sh`. Sem isso, o `docs-links` do CI acusa links quebrados.

---

## NÍVEL 2 — Remoção opcional, decisão à parte (79 arquivos / 5.125.218 bytes)

Lista em **`lista-remocao-opcional.txt`**. Também **0** em manifestos e **0** congelados, mas não vieram das frentes A–AM: avalie com calma.

| Bloco | Arquivos | Bytes | Observação |
| --- | --- | --- | --- |
| `**/*.map` | 16 | **4.383.046** | Sourcemaps de Bootstrap/jQuery vendorizados em `dev/admin/view/js/` e `dev/catalog/view/js/`. É o **maior ganho de peso** do repo. Remover não quebra runtime nem `verify-deps-layout.sh`, mas o DevTools passa a dar 404 ao depurar essas libs. Decisão de build, não de limpeza de frente. |
| `tree/` | 19 | 396.228 | Snapshot de código-fonte; **3 idênticos** aos paths reais em `dev/`. Código duplicado versionado. |
| `reference/` | 7 | 85.477 | Outro snapshot; **3 idênticos** aos paths reais. |
| `preview/` | 3 | 160.490 | Prévia estática (`preview/index.html` + 2 assets). |
| `entregas/` | 1 | 19.970 | `BDREditor-UI-Interpretation-Preview.html`. |
| `tests/` | 33 | 80.007 | Checks estáticos do BDREditor; o CI roda `node --check` em `tools/` e `tests/`, e a remoção só reduz a lista. |

`tree/`, `reference/` e `preview/` são citados por alguns ADRs em `agents-ia/` e por docs F8/F9 — se removê-los, rode o `docs-links` e ajuste essas citações.

### Documentação que eu **não** recomendo apagar em bloco

- `docs/` tem **242** outros `.md` (BDREditor, F8, F9, F11, guias). É documentação de projeto, não lixo das frentes. Entre eles estão os **11 congelados** — imutáveis.
- `agents-ia/` (103 arquivos, ADRs e regras): **4 estão em `CHECKSUMS.txt`** → manter.
- Os **15** `F11-XX-INDICE.md` da raiz estão nos manifestos → ver Nível 3.

---

## NÍVEL 3 — **NÃO REMOVER** (contratos, runtime e CI)

| Item | Motivo |
| --- | --- |
| `dev/admin/controller/design/theme.php` | Registra os assets `-aj`, `-aj-al` e `-aj-am`; sem ele o editor não carrega. |
| `dev/admin/view/css/kaz-theme-editor{,-ergonomia,-ajuste}.css` e os 3 `.js` correspondentes | **Runtime** das frentes AJ/AL/AM. |
| `dev/admin/view/template/design/theme_form.twig` (165 bytes, 2 `|raw`), `model/design/theme.php`, os 3 `language/*/design/theme.php` | Contrato congelado do formulário. |
| `dev/install/sql/schema.sql` | **1 dos 11 congelados**; intacto. |
| **14** `CHECKSUMS*.txt` + seus **90 paths** | `verify-checksums.sh` falha se qualquer um sumir. Inclui `README.md`, `CONTRIBUTING.md`, `.gitignore`, `.github/workflows/php.yml` e os 15 `F11-XX-INDICE.md`. |
| **11** `frozen_artifacts_sha256` (`docs/F9-*.json`, `docs/PROJECT-HANDOFF.md`, `schema.sql`) | `verify-contract-freeze.py` é fail-closed: ausência = exit 1. |
| `tools/decision-matrix.json`, `tools/verify-contract-freeze.py` | Matriz de decisões (10 `PENDING_DECISION`). |
| `tools/verify-deps-layout.sh`, `verify-checksums.sh`, `verify-ci-workflow.sh`, `verify-f11-01.sh`, `release-readiness-report.sh`, `install-deps.sh`, `lib/python-runtime.sh` | Guardas exigidos pelo `repo-health.sh` e pelo workflow. |
| `docs/README.md` | Exigido explicitamente por `verify-ci-workflow.sh` (resolve 2 links relativos). |
| `.github/workflows/php.yml` | CI. |
| `dev/system/storage/node_modules/index.html` | **Intencional**: o `.gitignore` ignora o conteúdo mas preserva esse `index.html` para o diretório existir (decisão 0081). Apagar quebra a lógica de dependências. |
| `docs/F9-27-Status.json` (`BLOCKED_NOT_RUN`), `release_approved:false` | Não apagar **nem alterar**; continuam sendo o registro de que não há release aprovada. |

**Não reemita manifestos nem reescreva hashes** para "limpar" os 15 `F11-XX-INDICE.md`: isso alteraria os contratos congelados e é justamente o que `verify-contract-freeze.py` existe para impedir. São 35 KB — não vale o risco.

---

## Ordem recomendada

1. **Edite** as 6 linhas dos 3 `.md` (Nível 1, pré-requisito).
2. Remova os **96** paths de `lista-remocao-segura.txt`.
3. Rode localmente: `bash tools/verify-checksums.sh`, `bash tools/repo-health.sh`, `bash tools/verify-ci-workflow.sh`, `python3 tools/verify-contract-freeze.py` → **4/4** devem continuar idênticos.
4. Rode o link check offline (`lychee --offline './**/*.md'`) antes do push.
5. **Só depois**, se quiser, avalie o Nível 2 — começando pelos 16 `.map`, que são 4,4 MB por 16 arquivos.

```bash
# conferir o que sera removido (nenhuma escrita)
wc -l lista-remocao-segura.txt lista-remocao-opcional.txt
# aplicar — VOCE executa, eu nao escrevo no GitHub
xargs -a lista-remocao-segura.txt git rm --
```

**Ganho potencial:** Nível 1 → 2694 → 2598 blobs, −3,7 MB. Com Nível 2 → **2519 blobs, −9,0 MB (~20% do peso)**, sem tocar em runtime, contratos ou CI.

---

## Limites

Isto é uma **auditoria de arquivos**, não uma aprovação. Continuam pendentes e **não** verificados por mim: as 2 capturas desktop + 390 px do Admin real, `copy(__kazHealth())` em produção, CI remoto (403 `Must have admin rights` conhecido), sessão Admin autenticada e deploy. `release_approved` segue `false`, F9-27 `BLOCKED_NOT_RUN`, BDREditor/F9-103 pausado, P2/F9-18 não reaberto. **CI verde, PROBE e Chromium headless não significam release aprovada.** Nenhuma escrita minha foi feita no GitHub.
