# Nova análise — após a limpeza de escopos

**Data:** 2026-11-01 · **HEAD consultado agora:** `9c606810a030c9ee5e16bf34d915f25bfa836d36`
(`docs(docs): remove RESTAURAR-docs-README.md file`, 2026-11-08T19:52:43Z)
**Consulta somente-leitura.** Não removi nada, não fiz commit, não fiz push, nenhuma escrita minha no GitHub.

---

## 1. Resumo executivo

| Métrica | Antes (`703b0127…`) | **Agora (`9c606810…`)** | Δ |
|---|---:|---:|---|
| Entradas na árvore | 3.456 | **3.236** | −220 |
| Blobs | 2.840 | **2.701** | **−139** |
| Bytes totais | 57.864.981 | **43.303.922** | **−14.561.059 (−25,2%)** |
| Arquivos na raiz | 43 | 44 | +1 (ver §4) |
| `evidencia/` | 86 / 11.873.366 B | **0** | −100% |
| `previa/`, `tree/`, `preview/`, `patch/`, `reference/`, `diagnostico/` | 62 / 3.252.818 B | **0** | −100% |
| `entregas/` | 1 | **0** | −100% |
| `docs/` | 301 / 2.792.363 B | **310 / 3.106.804 B** | +9 (artefatos do kit) |
| `tools/` | 227 / 1.613.676 B | 227 / 1.613.676 B | intacto |
| `dev/` | 1.975 / 36.419.333 B | 1.975 / 36.419.333 B | **intacto** |

**Nível A aplicado 100%: 153/153 paths, 0 restantes.** Os 3 commits desde a última análise:

| Commit | O que fez |
|---|---|
| `e7f2ce0…` | `feat: add schema fix scripts, cleanup tooling and update docs` (subiu o kit) |
| `6f46514…` | `chore(evidences): clean up temporary files, logs, and unused assets` (**a limpeza**) |
| `9c60681…` | `docs(docs): remove RESTAURAR-docs-README.md file` (o `.md` que gerava 39 links quebrados) |

**Diff exato:** 154 removidos / 15.166.985 B · 15 adicionados / 604.741 B · 6 alterados.
Os 154 = 153 da lista A + `docs/RESTAURAR-docs-README.md`. Os 6 alterados são **apenas** as correções
que eu fiz (`verificar-links.sh`, `verificar-congelados.sh` e as trocas `.md`→`.txt` nos 4 guias).
**Nenhum manifesto, congelado, contrato, `schema.sql`, `README.md`, workflow ou arquivo de `dev/` foi alterado.**

---

## 2. ✅ Integridade — validada por hash via `raw/<SHA>`

| Verificação | Resultado |
|---|---|
| 14 `CHECKSUMS*.txt` → 90 paths | **142 OK / 0 divergentes / 0 ausentes** |
| 11 `frozen_artifacts_sha256` | **11/11 coerentes** |
| **`dev/install/sql/schema.sql`** | **22.495 B / `c9dd1ec2…` — coerente** ✅ |
| `docs/README.md` | **3.213 B / `6f0783e2…`** ✅ |
| `decision-matrix.json` | **10 `PENDING_DECISION` / 0 `DECIDED`** — D01–D10 intactas |
| `docs/F9-27-Status.json` | `BLOCKED_NOT_RUN` · **`release_approved: false`** |
| 384 `.md` (sem vendor/node_modules/staging) | **281 links relativos**, 6 para diretórios, **4 sem alvo** |
| Links apontando para os 154 removidos | **0** |
| Guards no workflow | presentes |

Os **4 links sem alvo** são todos `security@cksource.com` sem esquema `mailto:` em
`dev/admin/view/js/ckeditor/CHANGES.md` — **pré-existentes**, não causados pela limpeza.

> 🔑 **O D09 foi resolvido da forma correta:** `schema.sql` voltou aos 22.495 bytes do blob
> `bcd0a351…` **por restauração de working tree**, sem editar o contrato, sem registrar decisão,
> sem reemitir `frozen_artifacts_sha256`. O guard `verify-contract-freeze.py` deixou de bloquear
> porque o arquivo voltou a bater — **não** porque foi relaxado.

---

## 3. Inventário atualizado do HEAD (2.701 linhas)

| Classe | Arquivos | Bytes |
|---|---:|---:|
| 🔒 INTOCAVEL (runtime, 90 paths de manifesto, 11 congelados, CI, ADRs) | 2.056 | 32.506.104 |
| 📌 MANTER (docs de governança, raiz, `tema/`, `threejs/`, `tests/`) | 230 | 3.236.658 |
| ⏳ PENDENTE-A2 (16 `.map`) | 16 | 4.383.046 |
| ⏳ PENDENTE-B (399 de `docs/` + `tools/`) | 399 | 3.178.114 |

Artefato: **`inventario-HEAD-9c606810.csv`** (2.701 linhas, substitui o `inventario-escopos.csv` de 2.840 linhas, que ficou **desatualizado**).

---

## 4. 🟡 Achados novos — resíduos do meu próprio kit

A limpeza está correta, mas o KIT ÚNICO que eu montei deixou **lixo de auditoria versionado**. Sou eu quem aponta:

| Item | Bytes | Problema |
|---|---:|---|
| `docs/inventario-escopos.csv` + `listas/inventario-escopos.csv` | 267.869 + 267.869 | **Duplicatas idênticas** e **desatualizadas** (2.840 linhas; o HEAD tem 2.701) |
| `docs/lista-nivel-A-remover.txt` / `A2-map` / `B-compilar` | 5.109 + 894 + 12.899 | **Duplicatas idênticas** das 3 listas em `listas/` |
| `LEIA-ME.txt` (raiz) | 1.501 | Artefato do ZIP; fez a raiz voltar de 43 → **44** |
| `docs/RESTAURAR-docs-README.txt` | 3.213 | **Redundante**: `CORRIGIR-README.sh/.ps1` usam `git cat-file blob`, não este arquivo |

**Total: 7 arquivos / 559.354 bytes de redundância.** Nenhum está em manifesto, congelado ou é alvo de link.
As cópias em **`listas/` devem ficar** — o `aplicar-limpeza-escopos.sh` lê `lista-nivel-A-remover.txt`
e `lista-nivel-A2-map.txt` dali para o `--nivel2`.

Também confirmei: **`threejs/index.html` (953.239 B) e `tema/` (2 arquivos / 175.609 B) seguem presentes** —
estavam marcados como MANTER, não eram lixo. **`entregas/` ficou vazia** (o HTML do BDREditor saiu).

---

## 5. ⏳ O que ficou pendente (por decisão, não por erro)

| Nível | Arquivos | Bytes | Status |
|---|---:|---:|---|
| **A2** — 16 `.map` de Bootstrap/jQuery | 16 | 4.383.046 | **Não aplicado.** Decisão de build: remove, mas o DevTools passa a dar 404 ao depurar essas libs. `dev/` ficou byte a byte idêntico |
| **B** — `docs/` 203 + `tools/` 197 | **399** | 3.178.114 | **Não aplicado.** 1 dos 400 saiu (`docs/RESTAURAR-docs-README.md`, que estava na lista B) |
| **C** — resíduos do kit (§4) | 7 | 559.354 | **Novo**, proposto aqui |

`tools/` permanece **227 arquivos / 1.613.676 bytes, intacto** — nenhum PROBE/e2e foi removido, como combinado.

---

## 6. Próximos passos sugeridos (você decide)

**6.1 — Limpeza final de artefatos (Nível C), baixíssimo risco**

```bash
git rm -- LEIA-ME.txt \
  docs/inventario-escopos.csv listas/inventario-escopos.csv \
  docs/RESTAURAR-docs-README.txt \
  docs/lista-nivel-A-remover.txt docs/lista-nivel-A2-map.txt docs/lista-nivel-B-compilar.txt
```
Depois: `bash tools/verify-checksums.sh` (142/0), `bash tools/repo-health.sh`, `bash tools/verify-ci-workflow.sh`.
O novo `inventario-HEAD-9c606810.csv` vai no lugar, **uma cópia só** (sugiro `listas/`).

**6.2 — A2 (`.map`)** — só se você aceitar perder o sourcemap vendorizado:
```bash
bash docs/aplicar-limpeza-escopos.sh --nivel2        # dry-run: 169 paths
bash docs/aplicar-limpeza-escopos.sh --apply --nivel2
```

**6.3 — B (`docs/` + `tools/`)** — ainda precisa de decisão. Para `docs/`, posso gerar os dois
consolidados (índice enxuto + texto integral dos ~203 `.md`), como fiz na raiz, **zero perda**.
Para `tools/`, o caminho é **remoção por bloco** (109 `tools/e2e/` + 80 na raiz), não "compilação" —
juntar scripts não faz sentido. Lembre: `php.yml` roda `find tools tests …`; remover **arquivos** é seguro,
remover o **diretório** `tools/` não é.

**Projeção se tudo for aplicado:** 43.303.922 → ~35,2 MB (**−39%** desde `703b0127…`),
sem tocar em runtime, contrato, manifesto, CI, SQL ou D01–D10.

---

## 7. Limites

Árvore `truncated:false`; todos os hashes recalculados via `raw/<SHA>` do HEAD `9c606810…`.
**Não executei `git rm`, commit ou push; nenhuma escrita minha no GitHub.** Não rodei `lychee`,
os 6 guards locais, CI remoto (403 `Must have admin rights`), health autenticado, MySQL, sessão Admin,
migration, deploy ou runtime. Os 4 links sem alvo são pré-existentes. `release_approved` segue **`false`**,
F9-27 **`BLOCKED_NOT_RUN`**, **10 `PENDING_DECISION` / 0 `DECIDED`**, D01–D10 intactas.
**Guard verde e repo menor não significam release aprovada.**

---

*Artefatos: `inventario-HEAD-9c606810.csv` (2.701 linhas) · `lista-limpeza-final-C.txt` (7 paths) ·
`ANALISE-POS-LIMPEZA-ESCOPOS.md` · `ReamurCMS-Analise-Pos-Limpeza-2026-11-01.zip` (+ `.sha256`).*
