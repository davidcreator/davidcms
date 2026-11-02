# F11-11 — Dossiê de decisão dos 10 `MARKER_OR_FINDING`

**Data:** 2026-11-02 · **HEAD verificado:** `749888e76` (retificado em F11-14) · **Tipo:** governança / matriz de decisão. **Nenhum contrato, checker ou arquivo de produto foi alterado. Nenhuma escrita no GitHub.**

---

## 1. Por que este pacote existe

O F11-09 corrigiu 4 falsos negativos comprovados e **manteve 10 checkers intactos** porque são checks de segurança, contratos de fase ou possíveis achados reais. Registrá-los como `PASS` sem decisão seria mascarar. A série F11 resolveu o CI de build, as dependências, os guards, o inventário de checkers e o próprio `docs-links`: o F11-12 adotou `--offline` e o F11-13 consolidou o fechamento (**run `41483802295` e `41504039830`: 7/7 success**, `docs-links` em 0,13 min). **Retificação F11-14:** a versão original deste dossiê dizia que o job ainda apresentava `exit 2` — isso foi superado. À parte da infraestrutura, o que resta **não é um problema de código a corrigir por inferência**, é um conjunto de **decisões do responsável**.

Este pacote transforma esses 10 itens numa matriz auditável, com duas saídas explícitas por linha:

- **A — Atualizar o contrato da fase**: quando a mudança no código foi intencional e aprovada; o contrato congelado é reemitido com a nova realidade.
- **B — Registrar como achado**: quando a divergência pode ser um problema real; entra no `F9-18-Risk-Register.json` como P1/P2, e o checker **permanece falhando** até correção do produto.

> ⛔ **Nenhuma das 10 decisões foi tomada neste pacote.** Todas estão `PENDING_DECISION`.

## 2. Matriz de decisão

| ID | Checker | Evidência no `main` | Natureza | Recomendação |
| --- | --- | --- | --- | --- |
| D01 | `e2e_bdre_hierarchical_reordering_static.cjs` | `moveBlockAcrossHierarchy(document,nodeId,direction,allowPageMedia)` existe, mas há uma cadeia de outros marcadores divergentes (`!validateDocument(document).valid`, `indent.disabled = …`) | Cadeia de marcadores | **A**, com revisão caso a caso |
| D02 | `e2e_bdre_spacer_static.cjs` | `spacer:{heightLabel,heights}` existe nos defaults, mas não `Object.entries(this.strings.spacer.heights)` no inspector | Equivalente não comprovado | **Investigar** — pode ser regressão real do inspector |
| D03 | `check_audio_catalog.cjs` | Rotas e IDs presentes; `can_modify and can_use_audio_catalog` **ausente** no Twig | Possível mudança de ACL | **B** — segurança, não relaxar |
| D04 | `check_threejs_native_admin.cjs` | `three_scene_config->runtime(` presente nos 2 controllers, assinatura divergente | Equivalente não comprovado | **Investigar** |
| D05 | `e2e_bdre_unsaved_changes_static.cjs` | `fetch(...)` real no init, no fluxo de **histórico de revisões** disparado por clique; não é autosave | Contrato histórico | **A** — atualizar contrato, mantendo a proibição de autosave |
| D06 | `e2e_f914.cjs` | `await fetch(url, options)` no editor (L3243); contrato `F9-14` (MVP) proibia rede | Contrato histórico | **A** — atualizar `F9-14`, sem alterar o checker de segurança |
| D07 | `e2e_f915.cjs` | Twig usa `{{ post.block_html }}` / `{{ post.description }}` **sem `\|raw`** | Segurança **ou** regressão | **Investigar/B** — se não for `Twig\Markup`, é regressão funcional (P1) |
| D08 | `e2e_f925_static.cjs` | `localStorage`, `sessionStorage`, `fetch(`, `XMLHttpRequest` reais (notas, presets, rascunho, upload) enquanto o contrato diz `PROPOSAL_PENDING` | Divergência real de contrato | **B** — a mais sensível; exige confirmação de aprovação das features |
| D09 | `e2e_f905.cjs` | `dev/install/sql/schema.sql` **existe** (22.495 bytes) e o checker o proíbe | Achado real de governança | **B** — decidir se o arquivo é legítimo de outra fase e o checker histórico deve ser aposentado |
| D10 | `e2e_f927_static.cjs` | `hashes.head = 448dd61f…` ≠ `status.base_head = 3d481ecf…` | Drift de metadados do gate | **B** — **não** atualizar o SHA sem autorização: equivaleria a declarar prontidão |

## 3. Guard de congelamento (entregue)

`tools/verify-contract-freeze.py` lê `tools/decision-matrix.json`, recalcula o SHA-256 dos **11 artefatos congelados** (contratos F9-14/15/25/26/27/05/18, `schema.sql`, handoff, etc.) e:

- **`exit 0`** se todos os hashes coincidirem e as 10 decisões estiverem `PENDING_DECISION` — estado atual, coerente;
- **`exit 1`** se qualquer artefato congelado tiver mudado **enquanto a decisão ainda está pendente** — impede que um contrato seja reemitido "por inferência";
- **`exit 1`** (fail-closed) se um artefato estiver ausente ou a matriz for inválida.

Ele é **read-only**: não escreve, não acessa rede, não altera contratos nem checkers. Não substitui os guardas existentes; protege especificamente a fronteira entre "diagnóstico" e "decisão".

```bash
python3 tools/verify-contract-freeze.py
```

## 4. O que este pacote **não** faz

- Não edita nenhum dos 10 checkers, nenhum contrato `F9-*`, nenhum arquivo PHP/JS/Twig, `schema.sql`, `F9-18-Risk-Register.json` ou `F9-27-*`.
- Não marca `NOT_RUN`/`STATIC_ONLY` como `PASS`, não altera `release_approved` e não reabre o BDREditor (pausado em F9-103 pelo handoff).
- Não executa PHP, Composer, PHPStan, browser, banco, staging, F9-12, migrations ou deploy.
- **Nenhuma escrita no GitHub.**

Todos os arquivos são **novos**, portanto os 7 manifestos `CHECKSUMS*.txt` publicados permanecem válidos.

## 5. Validações executadas

| Verificação | Resultado |
| --- | --- |
| `python3 -m json.tool tools/decision-matrix.json` | 10 decisões, 11 artefatos congelados |
| `python3 -m py_compile tools/verify-contract-freeze.py` | OK |
| Guard na réplica do `main` | **`exit 0` — 11/11 hashes coerentes, 10 pendentes** |
| A/B: contrato alterado sem decisão | **`exit 1` — divergência detectada** |
| A/B: artefato removido | **`exit 1` — fail-closed** |
| `bash tools/verify-checksums.sh` | 0 divergentes em 8 manifestos |
| `bash tools/verify-ci-workflow.sh` | 28 PASS / 0 FAIL |
| `unzip -t` / CRC / `sha256sum -c` | OK |

## 6. Como prosseguir

Para cada ID, o responsável escolhe **A** ou **B** e registra em `decision-matrix.json` (`status: DECIDED`, `choice`, `decided_by`, `date`). Só então o contrato correspondente pode ser reemitido — e o guard passará a aceitar o novo hash. Sem esse registro, qualquer alteração de contrato será reprovada.

Frentes ainda fora de escopo por restrição explícita: F9-27 (`BLOCKED_NOT_RUN`), BDREditor/F9-103 (pausado), `ubuntu-26.04` (revisão após 19/11/2026) e F11-06 (radar, ainda não aplicado).
