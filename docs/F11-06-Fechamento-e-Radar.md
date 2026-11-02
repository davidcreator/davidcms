# F11-06 — Fechamento da frente de infraestrutura e radar de prontidão

**Data:** 2026-11-02 · **HEAD verificado originalmente:** `fb7b56055` · **Retificado em F11-14 contra `749888e76`** · **Tipo:** consolidação + diagnóstico. **Nenhuma escrita no GitHub.**

---

## 1. Estado final do CI (verificado por API)

Run `41464495804` no HEAD `fb7b56055`:

| Job | Resultado |
| --- | --- |
| `composer` | ✅ success |
| `php-lint` | ✅ success |
| `static-analysis` (PHPStan nível 2) | ✅ success |
| `node-deps (20)` | ✅ success |
| `node-deps (22)` | ✅ success |
| **`repo-guards`** | ✅ **success** — os 3 steps verdes (`verify-deps-layout`, `verify-checksums`, `verify-ci-workflow`) |
| `docs-links` (lychee@v2) | ⏳ em andamento na última consulta, **sem erro** |

**0 annotations em todos os 7 check-runs** — os avisos de "Node.js 20 is deprecated" e o notice de migração do `ubuntu-latest` desapareceram. O run anterior `41462336587` consta como **`cancelled`**: o `concurrency` do F11-04 cancelou o run antigo quando o novo push chegou, exatamente como projetado.

> 🔴 **RETIFICAÇÃO F11-14 (2026-11-02): a conclusão abaixo foi SUPERADA. Não confiar nela.**
> O parágrafo original dizia: *"O `docs-links` não está travado… comportamento normal do lychee, não regressão."* Medição dos 4 runs `@v1`: 358 / 363 / 369 / 360 s.
> O que aconteceu depois: com `lychee-action@v2` o job passou a **14,0 / 15,5 / 12,8 min** (cancelados), e após o pin do F11-08 tornou-se **`failure` com `exit 2` em 3 runs** (`41468925481` 6,5 min, `41471699316` 6,5 min, `41478689925` 5,7 min). O `fail: true` da `@v2` expôs 16 links quebrados reais; o F11-10 corrigiu os reproduzíveis localmente, mas a varredura externa continuou não determinística no runner.
> **Resolução:** F11-12 adotou `--offline` mantendo `fail`/`failIfEmpty=true` e o pin `v0.15.0`. Em `6ac7561f9` (run `41483802295`) e `749888e76` (run `41504039830`) o workflow fechou **7/7 success**, com `docs-links` em **0,13 min (~8 s)** e 0 annotations de falha. Trade-off: links externos saíram do escopo do CI.
> O registro histórico desta seção (run `41464495804`, que de fato estava em andamento quando este documento foi escrito) foi preservado; esta nota é a única alteração de conteúdo.

| Invariante | Estado |
| --- | --- |
| `node_modules/` na raiz | **0 blobs** |
| `dev/system/storage/node_modules/` | **0 blobs** |
| `dev/system/storage/vendor/` | **0 blobs** |
| Manifestos | `CHECKSUMS.txt` 33 · `CHECKSUMS-F11-04.txt` 11 · `CHECKSUMS-F11-05.txt` 8 entradas |
| Índices F11-01/F11-02/F11-05 | presentes |

---

## 2. Linha do tempo da frente (o que foi entregue)

| Frente | Correção |
| --- | --- |
| F11-01 / ADR 0081 | CI corrigido (`php-lint` falso verde, PHPStan em `block_document.php:122`); dependências Node realocadas para `dev/system/storage/` com manifesto + lock |
| F11-02 | Verificação de instalação no padrão Composer e prova de `.gitignore`; auditor `verify-deps-layout.sh` |
| F11-03 | Fechamento com CI verde e manifesto de integridade consolidado |
| F11-04 / ADR 0082 | Actions Node 24, runner pinado `ubuntu-24.04`, matrix Node 20+22, `concurrency`, `timeout-minutes`, job `repo-guards` |
| F11-05 | Hotfix do `exit 127`: `verify-checksums.sh` adicionado, 2 índices restaurados, contrato do CI passa a exigir os guards no disco |

Sete commits aplicados desde `61f39a93`. **Nenhuma frente alterou código PHP/JS de produto, banco, schema, staging ou produção.**

---

## 3. Radar de prontidão (levantamento real dos artefatos do repo)

| Frente | Estado declarado no repo | Pode executar agora? |
| --- | --- | --- |
| **A. Higienizar os 36 checkers `tools/e2e/*.cjs` com saída não-zero** | Pré-existentes desde antes da F11-01; reproduzidos identicamente em árvore-base. Incluem runners que exigem browser/env, wrappers `.sh`, argumentos de CLI e marcadores literais desatualizados (`docs/F9-47`) | ✅ **Sim** — manutenção de ferramentas de QA, não é feature do BDREditor e não exige runtime |
| **B. F9-27 — release gate** | `BLOCKED_NOT_RUN`, `release_approved: false`, `f9_12_status: UNKNOWN_NOT_CONFIRMED`, matriz com 12 itens `NOT_RUN`/`BLOCKED`, 7 pré-requisitos pendentes, `explicit_runtime_authorization: NOT_PROVIDED`, `staging_hostname: NOT_PROVIDED` | ❌ **Não** — exige autorização por escrito, staging descartável isolado, confirmação de F9-12 e rollback |
| **C. BDREditor / F9-103** | `docs/PROJECT-HANDOFF.md`: *"The BDREditor project is paused/closed at F9-103 by user request. Do not continue BDREditor or add features unless I explicitly reopen it."* | ❌ **Não** — proibido sem reabertura explícita do responsável |
| **D. F9-18 — achados de risco** | `release_ready: False`; **2 P1 + 2 P2 `open`**; gate exige resolver os P1 antes de release | ⚠️ Parcial — exige decisão do responsável sobre semântica de idioma/exclusão e, no limite, runtime autorizado |
| **E. Migrar para `ubuntu-26.04`** | Label em migração gradual entre **19/10 e 19/11/2026**; `ubuntu-24.04` pinado pelo F11-04 | 📅 **Após 19/11/2026**, com um run verde que comprove a imagem nova |

**Recomendação:** a frente **A** é a única executável sem nova autorização e sem violar o handoff. Ela deve começar por **classificar** os 36 (esperado por ausência de runtime vs. dívida real de marcador), e só então corrigir os falsos negativos — sem transformar `NOT_RUN` em `PASS` e sem mexer nos checkers de segurança (F9-14/F9-15/F9-25).

---

## 4. Novo painel read-only

`tools/release-readiness-report.sh` agrega os três artefatos de estado já existentes (`F9-27-Status.json`, `F9-27-Preflight-Status.json`, `F9-18-Risk-Register.json`) mais o handoff e imprime o estado declarado. **Não é um gate**, não aprova release, não reimplementa `tools/e2e/e2e_f927_static.cjs` e não marca nada como `ready`. Serve para evitar que um relatório futuro infira prontidão a partir do CI verde.

---

## 5. Validações executadas

| Verificação | Resultado |
| --- | --- |
| `bash -n tools/release-readiness-report.sh` | OK |
| Painel executado sobre os 4 artefatos reais baixados do `main` | `BLOCKED_NOT_RUN`, `release_approved: False`, `2 P1 / 2 P2`, `PAUSADO em F9-103` — saída coerente, `exit 0` |
| `bash tools/verify-checksums.sh` em réplica com o estado publicado | 52 arquivos conferidos / 0 divergentes |
| `bash tools/verify-ci-workflow.sh` | 22 PASS / 0 FAIL |
| `bash tools/verify-deps-layout.sh` | 0 FAIL (índice limpo) |
| Integridade dos 5 ZIPs anteriores | `sha256sum -c` em todos → OK |

### ⚠️ Limitações
- **Retificado em F11-14:** o `docs-links` não estava apenas lento — fechou `failure` em 3 runs e só passou a `success` com `--offline` no F11-12. Estado atual: 7/7 verde. Não foi executado localmente (ação externa).
- PHP, Composer, PHPStan, `php -l`, browser, banco, staging, F9-12, migrations e deploy **não** foram executados localmente em nenhuma frente; os resultados de CI citados vêm dos check-runs reais do GitHub.
- **Nenhuma escrita no GitHub**: sem commit, push, PR, aplicação remota ou alteração de produção.
- O CI verde **não** significa release aprovada: F9-27 segue bloqueado e F9-18 com `release_ready: False`.

---

## 6. Arquivos deste pacote

Todos **novos** — nenhum arquivo existente foi alterado, portanto os três manifestos `CHECKSUMS*.txt` publicados permanecem válidos.

`docs/F11-06-Fechamento-e-Radar.md` · `tools/release-readiness-report.sh` · `F11-06-INDICE.md` · `CHECKSUMS-F11-06.txt`.

```bash
bash tools/release-readiness-report.sh
bash tools/verify-checksums.sh
```
