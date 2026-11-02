# F11-13 — Fechamento da Fase 11: CI 7/7 verde

**Data:** 2026-11-02 · **HEAD verificado:** `6ac7561f9` (`pushed_at 16:40:33Z`) · **Run:** `41483802295` · **Nenhuma escrita no GitHub.**

---

## 1. O marco

Aplicado o F11-12, o workflow completo fechou **verde pela primeira vez na série**, sem novo push e sem cancelamentos:

| Job | Resultado | Duração |
| --- | --- | --- |
| `docs-links` | ✅ success | **0,13 min (~8 s)** |
| `repo-guards` | ✅ success | 0,22 min |
| `php-lint` | ✅ success | 0,32 min |
| `static-analysis` | ✅ success | 0,83 min |
| `node-deps (20)` | ✅ success | 0,25 min |
| `node-deps (22)` | ✅ success | 0,48 min |
| `composer` | ✅ success | 0,32 min |

**7/7 success**, `total_count: 7`, **0 annotations de falha** (a única anotação é o `notice` de "Summary report available", normal da action).

### Evolução do `docs-links`

| Fase | Run | Duração | Resultado |
| --- | --- | --- | --- |
| `@v1` (baseline, 4 runs) | — | 358 / 363 / 369 / 360 s | verde |
| `@v2` sem pin | `41462336587` / `41464495804` / `41466922434` | 14,0 / 15,5 / 12,8 min | `cancelled` |
| F11-08 (pin `v0.15.0`) | `41468925481` / `41471699316` | 6,5 min | **`failure` (`exit 2`)** |
| F11-10 (índice + exclusões + `--accept`) | `41478689925` | 5,7 min | **`failure` (`exit 2`)** |
| **F11-12 (`--offline`)** | **`41483802295`** | **0,13 min** | ✅ **success** |

A correção não desligou o gate: `fail: true`, `failIfEmpty: true`, `lycheeVersion: v0.15.0`, `timeout-minutes: 20` e as 5 exclusões originais permanecem. O que mudou foi o **escopo** — apenas links internos/relativos, eliminando a não-determinismo da rede do runner. O trade-off (links externos fora do CI) está registrado no F11-12.

## 2. O que a Fase 11 resolveu

| Frente | Entrega | Resultado verificado |
| --- | --- | --- |
| F11-01 | Composer/`node_modules` em `dev/system/storage`, correção do `php-lint` falso-verde | 335 checks locais; ADR 0081 |
| F11-02 | `npm ci --prefix`, `.gitignore`, 182 blobs desrastreados | aplicado em `510640c68` |
| F11-03 | Auditor de checksums + manifesto consolidado | artefatos funcionais incorporados pelo F11-05 |
| F11-04 | CI modernizado: Node 24, `ubuntu-24.04`, matrix 20/22, `concurrency`, 3 auditores, ADR 0082 | 6 jobs |
| F11-05 | Hotfix `repo-guards` `exit 127`, 2 índices restaurados, 33 hashes | `fb7b56055` verde |
| F11-06 | Fechamento + radar read-only | **entregue, não aplicado** |
| F11-07 | Inventário auditado de 36 checkers não-zero entre 61, 0 alvos ausentes | `a65d06800` |
| F11-08 | Pin do lychee + 4 checks no auditor | 6,5 min, mas `failure` |
| F11-09 | 4 falsos negativos corrigidos, 10 mantidos, inventário 32 entradas | `709d7fa08` |
| F11-10 | `docs/README.md` real, 16 erros reais diagnosticados com `lychee 0.15.0` | `9ce71a662`; CI ainda vermelho |
| F11-11 | Matriz de decisão dos 10 marcadores + congelamento de contratos | **entregue, não aplicado** |
| F11-12 | `--offline` determinístico | **`6ac7561f9`, CI 7/7** |

## 3. Estado remoto verificado (`6ac7561f9`)

- Árvore: **2.509 blobs**; `--offline` presente (2×), **`--accept` ausente**, 5 exclusões.
- 8 manifestos HTTP 200: `CHECKSUMS.txt` 33 · `F11-04` 11 · `F11-05` 8 · `F11-07` 4 · `F11-08` 8 · `F11-09` 8 · `F11-10` 10 · `F11-12` 10.
- `verify-checksums.sh`: **92 arquivos conferidos / 0 divergentes**.
- `verify-ci-workflow.sh`: **28 PASS / 0 FAIL**.
- `verify-deps-layout.sh`: **0 FAIL** · `verify-f11-01.sh`: 17 PASS / 0 FAIL.

## 4. O que este pacote entrega

**`tools/repo-health.sh`** — um único comando que chama, **sem reimplementar nenhuma lógica**, os guardas estáticos já existentes e agrega o veredito:

```bash
bash tools/repo-health.sh
# == repo-health: 4 PASS / 0 FAIL / 2 SKIP ==
```

Inclui `verify-deps-layout.sh`, `verify-checksums.sh`, `verify-ci-workflow.sh` e `verify-f11-01.sh`. Os guardas dos pacotes **ainda não aplicados** (`verify-contract-freeze.py` do F11-11 e `release-readiness-report.sh` do F11-06) aparecem como **`SKIP`, nunca como `PASS`** — o health check passa a incluí-los automaticamente caso você os aplique.

> **`verify-checkers-inventory.py` ficou de fora de propósito:** ele executa os 61 `tools/e2e/*.cjs` (4 `REQUIRES_RUNTIME` por design) e é sensível à completude do checkout. Continua disponível isoladamente e não foi alterado. Não duplicamos ferramentas.

## 5. Validações executadas

| Verificação | Resultado |
| --- | --- |
| `bash -n tools/repo-health.sh` | OK |
| `bash tools/repo-health.sh` na réplica do `main` | **`exit 0` — 4 PASS / 0 FAIL / 2 SKIP** |
| A/B: `node_modules/` recriado na raiz | **`exit 1`**, `verify-deps-layout.sh` reprovado |
| `bash tools/verify-checksums.sh` | **0 divergentes em 9 manifestos** |
| `bash tools/verify-ci-workflow.sh` | 28 PASS / 0 FAIL |
| `unzip -t` / CRC / `sha256sum -c` (13 ZIPs) | OK |

Todos os arquivos são **novos**; os 8 manifestos publicados permanecem válidos.

## 6. O que ainda está aberto (não é `PASS`)

| Pendência | Estado |
| --- | --- |
| **10 `MARKER_OR_FINDING`** (F11-09/F11-11) | `PENDING_DECISION` — exige A/B do responsável; contratos F9 congelados |
| F11-06 (radar + painel) | entregue, **não aplicado** |
| F11-11 (matriz + guard) | entregue, **não aplicado** |
| F9-27 / release gate | `BLOCKED_NOT_RUN`, `release_approved: false`, 12 itens `NOT_RUN`/`BLOCKED` |
| F9-18 | `release_ready: False`, **2 P1 + 2 P2 `open`** |
| BDREditor | **pausado em F9-103**; só com reabertura explícita |
| `ubuntu-26.04` | revisar após 19/11/2026, com run verde |

> ⚠️ **CI 7/7 verde ≠ release aprovada.** Significa que build, lint, análise estática, dependências, guards e documentação estão coerentes. Não houve certificação de runtime: PHPStan/Composer rodam no CI, mas **browser real, banco, migrations, F9-12, staging e deploy não foram executados por nós**.

## 7. Limitações e próximo passo

Verificação remota foi **somente leitura**: nenhum commit, push, PR, aplicação remota ou mudança de contrato. Sem PHP/Composer/PHPStan locais, browser, banco, staging ou deploy.

Recomendação: aplicar o **F11-11** (matriz + congelamento) para que o `repo-health.sh` passe a 5 PASS / 1 SKIP, e então decidir, uma a uma, as 10 pendências **A/B**. O F11-06 pode entrar em seguida para completar o painel. Nenhuma dessas frentes autoriza release, BDREditor ou F9-27.
