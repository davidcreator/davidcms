# F11-05 — Hotfix do `repo-guards` (exit 127) e restauração de integridade

**Data:** 2026-11-02 · **HEAD verificado:** `706205389` (`ci: modernize and harden CI pipeline, add repo guards`) · **Tipo:** correção de CI/governança. **Nenhuma escrita no GitHub.**

---

## 1. O que falhou

Run `41462336587` no HEAD `706205389`:

| Job | Resultado |
| --- | --- |
| `composer`, `php-lint`, `static-analysis` | ✅ success |
| `node-deps (20)` e `node-deps (22)` | ✅ **success** — a matrix Node 20+22 do F11-04 funcionou |
| `docs-links` (lychee@v2) | ⏳ em andamento na última consulta, sem erro reportado |
| **`repo-guards`** | ❌ **failure** — passo 4 `Integridade dos manifestos CHECKSUMS*.txt` → **`Process completed with exit code 127`**; passo 5 ficou `skipped` |

`127` = comando/arquivo inexistente. O passo 3 (`verify-deps-layout.sh`) passou; o passo 4 chamava `bash tools/verify-checksums.sh`, que **não está no repositório**.

---

## 2. Causa raiz (três achados encadeados)

| # | Achado | Evidência |
| --- | --- | --- |
| 1 | **O pacote F11-03 não foi aplicado** | Não há commit entre `510640c68` e `706205389`; `tools/verify-checksums.sh` está ausente da árvore (2.485 blobs). O F11-04 assumiu esse arquivo como pré-requisito e passou a chamá-lo no `repo-guards`. |
| 2 | **Dois índices foram removidos na aplicação do F11-04** | O commit `706205389` registra `removed F11-01-INDICE.md` e `removed F11-02-INDICE.md`. Ambos são alvos dos manifestos de integridade. O `CHECKSUMS.txt` publicado, por sua vez, contém apenas **3 entradas** (o do F11-02), não as 30 do F11-01. |
| 3 | **O contrato do CI não detectava a ausência** | `tools/verify-ci-workflow.sh` só fazia `grep -qF` no YAML. Executei o auditor publicado num diretório com o workflow mas **sem** `verify-checksums.sh`: **19 PASS / 0 FAIL**. Ele aprovava uma referência a um arquivo inexistente. |

Verificação de integridade do restante: **11/11 entradas** do `CHECKSUMS-F11-04.txt` conferem com os arquivos reais do GitHub, e os 31 demais arquivos das frentes F11-01/F11-02 estão byte-idênticos aos ZIPs. **0 divergências** — o problema é exclusivamente de arquivos ausentes/removidos, não de conteúdo corrompido.

---

## 3. Correção entregue

| Arquivo | Ação |
| --- | --- |
| `tools/verify-checksums.sh` | **adicionado** — auditor do F11-03, reforçado: antes de validar manifestos, exige a presença dos 3 scripts que o `repo-guards` executa. Assim um `exit 127` futuro vira `FAIL` explícito e legível. |
| `CHECKSUMS.txt` | **substituído** pelo manifesto consolidado: **33 entradas** (30 F11-01 + 3 F11-02), em seções comentadas. Diferente do F11-03, os hashes são **reemitidos a partir do estado final do repositório**: o F11-04 alterou 7 dos arquivos listados (`.github/pull_request_template.md`, `.github/workflows/php.yml`, `CONTRIBUTING.md`, `README.md`, `agents-ia/decisoes/README.md`, `docs/CHANGELOG.md`, `docs/Guia-Composer.md`), então seus hashes originais não batem mais. Os ZIPs continuam imutáveis, cada qual com seu próprio manifesto; este arquivo descreve o repo, não os artefatos. |
| `F11-01-INDICE.md`, `F11-02-INDICE.md` | **restaurados**, byte-idênticos aos ZIPs originais — todos os hashes publicados voltam a ter alvo válido. São snapshots históricos (`31b15e06`), mantidos sem reescrita. |
| `tools/verify-ci-workflow.sh` | **reforçado**: nova seção verifica que `verify-deps-layout.sh`, `verify-checksums.sh` e `verify-ci-workflow.sh` existem no disco, não apenas no YAML. |
| `CHECKSUMS-F11-04.txt` | **recalculado** para refletir o hash final do `verify-ci-workflow.sh` reforçado (os outros 10 arquivos do F11-04 permanecem idênticos). |
| `docs/F11-05-Hotfix-Repo-Guards.md`, `F11-05-INDICE.md`, `CHECKSUMS-F11-05.txt` | relatório, índice e integridade desta frente. |

**Nenhum outro arquivo do F11-04 foi alterado** — em particular o `.github/workflows/php.yml`, que já referenciava os guards corretamente e não precisa mudar. O `docs/CHANGELOG.md` tampouco foi tocado (está listado no `CHECKSUMS-F11-04.txt`); o registro desta frente fica neste relatório para não invalidar hashes já publicados.

---

## 4. Validações executadas

| Verificação | Resultado |
| --- | --- |
| `bash tools/verify-checksums.sh` em réplica completa com os arquivos reais do GitHub + este pacote | **52 arquivos conferidos / 0 divergentes** (33 + 11 + 8) |
| `bash tools/verify-ci-workflow.sh` (reforçado) na réplica | **22 PASS / 0 FAIL** |
| Mesmo auditor no cenário da brecha (sem `verify-checksums.sh`) | **falha** na nova seção — a regressão agora é detectada |
| Simulação dos 3 steps do `repo-guards` | `verify-deps-layout.sh`, `verify-checksums.sh`, `verify-ci-workflow.sh` → **todos `exit 0`** (fim do 127) |
| `bash -n` nos dois scripts · `yaml.safe_load` do workflow publicado | OK |
| `sha256sum` dos arquivos do GitHub vs. manifestos publicados | 11/11 F11-04 + 31/31 F11-01/F11-02 → **0 divergentes** |

### ⚠️ Limitações
- **`docs-links` (lychee@v2) ainda estava em andamento** na última consulta do run `41462336587`. Não foi executado localmente (ação externa). Os demais 6 jobs/pernas já estavam verdes ou com a falha aqui corrigida.
- PHP, Composer, PHPStan, `php -l`, browser, banco, staging e deploy **não** foram executados localmente (ferramentas ausentes no sandbox); os resultados de CI citados vêm dos check-runs reais do GitHub.
- **Nenhuma escrita no GitHub**: sem commit, push, PR ou aplicação remota. A aplicação é do responsável, como nas frentes anteriores.
- O projeto segue pausado em F9-103; F9-27 permanece `BLOCKED_NOT_RUN`; os 36 checkers `tools/e2e` com saída não-zero seguem pré-existentes e fora deste escopo.

---

## 5. Aplicação

Extrair o ZIP na raiz do clone e confirmar antes do push:

```bash
bash tools/verify-checksums.sh      # esperado: 52 conferidos / 0 divergentes
bash tools/verify-ci-workflow.sh    # esperado: 22 PASS / 0 FAIL
bash tools/verify-deps-layout.sh    # esperado: 0 FAIL
```

Após o push, o `repo-guards` deve ficar verde e o passo 5 (`Contrato do próprio workflow`) deixa de ser `skipped`.

> Nota de governança: o pacote **F11-03** permanece disponível em `entregas/` com seu relatório de fechamento e `CHECKSUMS-F11-03.txt`. Esta frente incorpora apenas os dois artefatos funcionais dele que eram pré-requisito do F11-04, evitando reaplicar documentação já entregue.
