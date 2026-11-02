# F11-14 — Aplicação consolidada dos pacotes pendentes

**Data:** 2026-11-02 · **HEAD verificado:** `749888e76` (`feat(F11-13): add closure package and tool`, `pushed_at 18:48:26Z`) · **Runs:** `41504039830` e `41483802295`, ambos `success` · **Nenhuma escrita no GitHub.**

---

## 1. Motivo

O F11-13 fechou a infraestrutura com **CI 7/7 verde**, mas **dois pacotes entregues nunca foram aplicados**: F11-06 (radar de prontidão) e F11-11 (matriz de decisão + congelamento de contratos). Verifiquei a árvore de `749888e76`: os 9 arquivos desses pacotes seguem **AUSENTES** e o `repo-health.sh` publicado, por isso, reporta **2 `SKIP`**.

Ao revalidá-los contra o estado atual, encontrei um problema mais importante que a simples ausência: **ambos continham conclusões obsoletas sobre o CI**. Aplicá-los sem correção deixaria documentação contraditória no repositório.

## 2. Retificações aplicadas

| Arquivo | Afirmava (na entrega original) | Estado real em `749888e76` |
| --- | --- | --- |
| `docs/F11-06-Fechamento-e-Radar.md` | "`docs-links` **não está travado** … ~6 min … comportamento normal, não regressão" | Foi **`failure` (`exit 2`) em 3 runs** (6,5 / 6,5 / 5,7 min); só fechou com `--offline` no F11-12: **0,13 min `success`** |
| `docs/F11-06-Fechamento-e-Radar.md` | "`docs-links` ainda estava em andamento … sem erro" | `41483802295` e `41504039830` → **7/7 success, 0 annotations de falha** |
| `F11-06-INDICE.md` | "6/7 jobs verdes, `docs-links` em andamento dentro do tempo normal" | **7/7 verdes** |
| `docs/F11-11-Decisoes-Contratos.md` | "o `docs-links` ainda apresenta `exit 2` … frente F11-12 em aberto" | F11-12 aplicado em `6ac7561f9`; F11-13 aplicado em `749888e76`; **CI verde** |
| `F11-11-INDICE.md` / `tools/decision-matrix.json` | `head: 9ce71a662` | **`head: 749888e76`** |

O **registro histórico do run `41464495804`** foi preservado (ele de fato estava em andamento quando o F11-06 foi escrito); as correções entram como **nota de retificação F11-14**, sem reescrever o passado.

## 3. Contratos congelados revalidados

Recalculei o SHA-256 dos **11 artefatos** de `tools/decision-matrix.json` diretamente do `main` em `749888e76`: **todos idênticos** aos registrados no F11-11. Nenhum contrato `F9-*`, `schema.sql` ou `PROJECT-HANDOFF.md` mudou desde então — o guard `verify-contract-freeze.py` segue válido sem alteração.

## 4. O que muda ao aplicar este pacote

| Antes | Depois |
| --- | --- |
| `repo-health.sh`: **4 PASS / 0 FAIL / 2 SKIP** | **6 PASS / 0 FAIL / 0 SKIP** |
| `verify-contract-freeze.py` ausente | ativo: protege os 11 contratos até decisão registrada |
| `release-readiness-report.sh` ausente | ativo: painel read-only F9-27/F9-18/handoff |

**Nenhuma alteração em `repo-health.sh`** — o script publicado no F11-13 já reconhece os dois guardas e os promove de `SKIP` para `PASS` automaticamente. Nenhum dos 9 manifestos publicados foi alterado (só adicionamos arquivos novos e reemitimos os 2 manifestos dos pacotes nunca aplicados).

## 5. Arquivos do pacote

9 arquivos revalidados (4 do F11-06 + 5 do F11-11), 2 novos (`docs/F11-14-Aplicacao-Pendentes.md`, `F11-14-INDICE.md`) e `CHECKSUMS-F11-14.txt` (11 entradas). `CHECKSUMS-F11-06.txt` e `CHECKSUMS-F11-11.txt` foram **reemitidos** com os hashes das versões retificadas.

> ⚠️ **Aplique este pacote em vez de** `ReamurCMS-F11-06-Fechamento-e-Radar.zip` e `ReamurCMS-F11-11-Decisoes-Contratos.zip` separadamente: as versões antigas têm documentação desatualizada e manifestos divergentes. Os ZIPs originais permanecem em `entregas/` apenas como histórico.

## 6. Validações executadas

| Verificação | Resultado |
| --- | --- |
| Árvore `749888e76` / 9 manifestos HTTP 200 | conferidos |
| 11 contratos congelados recalculados do `main` | **11/11 idênticos** |
| `python3 -m json.tool tools/decision-matrix.json` | 10 decisões `PENDING_DECISION` |
| `python3 -m py_compile tools/verify-contract-freeze.py` | OK |
| `python3 tools/verify-contract-freeze.py` (réplica) | **`exit 0` — COERENTE** |
| `bash -n tools/release-readiness-report.sh` | OK |
| `bash tools/release-readiness-report.sh` (réplica) | `BLOCKED_NOT_RUN`, `release_ready: False`, `2 P1 / 2 P2`, BDREditor `PAUSADO` |
| `bash tools/repo-health.sh` (réplica com os 9 arquivos) | **6 PASS / 0 FAIL / 0 SKIP** |
| `bash tools/verify-checksums.sh` | **0 divergentes em 12 manifestos** |
| `unzip -t` / CRC / `sha256sum -c` (14 ZIPs) | OK |

## 7. O que continua pendente (não é `PASS`)

| Pendência | Estado |
| --- | --- |
| **10 `MARKER_OR_FINDING`** | **`PENDING_DECISION`** — nenhuma decidida; exige A/B do responsável |
| F9-27 / release gate | `BLOCKED_NOT_RUN`, `release_approved: false` |
| F9-18 | `release_ready: False`, 2 P1 + 2 P2 `open` |
| BDREditor | pausado em F9-103; só com reabertura explícita |
| `ubuntu-26.04` | revisar após 19/11/2026 com run verde |

> ⚠️ **CI 7/7 e health check 6/6 ≠ release aprovada.** São coerência de build, lint, análise estática, dependências, guards e documentação. Não houve browser real, banco, migrations, F9-12, staging ou deploy por nós.

## 8. Limitações e próximo passo

Verificação remota **somente leitura**: nenhum commit, push, PR, aplicação remota ou alteração de contrato. Sem PHP/Composer/PHPStan locais, browser, banco, staging ou deploy. O painel F11-06 é **informativo** e continua não sendo um gate.

Depois de aplicar o F11-14, **a única frente executável é a decisão do responsável sobre as 10 pendências A/B**. Recomenda-se começar pelas de maior impacto: **D08** (`PROPOSAL_PENDING` vs. features reais de storage/rede), **D07** (Twig sem `|raw`: segurança ou regressão) e **D03** (ACL do catálogo de áudio). Para cada uma, registrar `status: DECIDED`, `choice`, `decided_by` e `date` na matriz antes de qualquer reemissão de contrato — o guard bloqueará o contrário.
