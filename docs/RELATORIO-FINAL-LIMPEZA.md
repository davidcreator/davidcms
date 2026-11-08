# RELATÓRIO FINAL — Fase de Limpeza do repositório

**Data:** 2026-11-01 · **Repositório:** https://github.com/davidcreator/davidcms
**HEAD auditado:** `8246b9015dbb399e2679921d48aeda1383f2d04b` (2026-11-08T21:13:57Z)
**Natureza:** consulta somente-leitura + proposta. **Nenhuma escrita minha no GitHub**, nenhum `git rm`, commit ou push executado por mim.

---

## ⛔ VEREDITO: **FASE EM FECHAMENTO — 1 BLOQUEIO**

> **Não declaro a fase fechada:** o CI está **vermelho**. A causa é **`docs/README.md`**, que eu
> inadvertidamente deixei ser substituído pela versão do *workspace* ao empacotar o último ZIP.
> Enquanto esse único arquivo não for restaurado, **não se deve aplicar mais nenhuma limpeza**.

Assim que os 2 comandos da §2 forem executados por você e o `repo-guards` voltar a verde,
**a fase está fechada** nos números da §5. O fechamento é seu (commit local + push, se quiser).

---

## 1. 🔴 Diagnóstico do CI — confirmado pela API do GitHub

| Run | HEAD | Conclusão |
|---|---|---|
| **`42524162889`** | **`8246b901…`** | **`failure`** |
| `42517236510` | `9c606810…` | `success` |
| `42502585246` | `e7f2ce0…` | `success` |
| `42492572210` | `703b0127…` | `success` |

**7 jobs no run atual — 6 passaram, 1 falhou:**

| Job | Resultado |
|---|---|
| `node-deps (20)`, `node-deps (22)` | ✅ success |
| `php-lint` | ✅ success |
| `static-analysis` (PHPStan nível 2) | ✅ success |
| **`docs-links`** (lychee v0.15.0) | ✅ **success** — **não é link quebrado** |
| `composer` | ✅ success |
| **`repo-guards`** | ❌ **failure no step 4 — "Integridade dos manifestos CHECKSUMS*.txt"** |

> O log detalhado do job retorna `403 Must have admin rights`, mas a causa é totalmente
> determinável por hash — e eu a reproduzi abaixo.

### Causa exata (141 OK / **1 divergente** / 0 ausentes)

| | Atual no HEAD | Esperado em `CHECKSUMS-F11-10.txt` |
|---|---|---|
| `docs/README.md` | **4.950 bytes** · `22520a16…` · começa `# docs/ — Documentação consolidada do ReamurCMS` | **3.213 bytes** · `6f0783e2…` · começa `# Documentação — ReamurCMS` |

O commit `8246b901…` (`feat: add final level C cleanup tooling and docs`) **modificou `docs/README.md`**.
O conteúdo atual é o **README da raiz do meu workspace**, que eu vinha editando a cada entrega e que
coloquei dentro de `ReamurCMS-Analise-Pos-Limpeza-2026-11-01.zip`. Ao extrair na raiz, ele
**sobrescreveu o `docs/README.md` congelado**. É a **mesma colisão de nomes** que já tinha causado o
primeiro `FAIL` no início desta conversa — **erro meu, não seu, não do CMS, não do CI**.

**Prova material:** o arquivo `entregas/ReamurCMS-Analise-Pos-Limpeza-2026-11-01.zip` continha
`docs/README.md` com **exatamente 4.950 bytes / `22520a16…`** — o mesmo SHA do HEAD que falhou.
Extrair esse ZIP na raiz substituiu o congelado. **Já reembalei esse ZIP e o KIT-UNICO sem nenhum
`README.md`** (novos `.sha256` emitidos só para os ZIPs, **nunca para os manifestos do repo**), e restaurei
`docs/README.md` no meu workspace para o blob correto (3.213 B / `6f0783e2…`), movendo meu índice local
para `docs/INDICE-WORKSPACE.md`. A armadilha está desmontada.

O guard está **funcionando corretamente**. Não é para relaxá-lo.

**O que mais está íntegro no mesmo HEAD:**
- **11/11 congelados coerentes** (inclusive `dev/install/sql/schema.sql` 22.495 B — D09 **não** foi decidido)
- 14 manifestos / 90 paths → **141 OK**, só o README divergente
- `docs-links` verde → 0 link novo quebrado
- `release_approved:false` · F9-27 `BLOCKED_NOT_RUN` · **10 `PENDING_DECISION` / 0 `DECIDED`**

---

## 2. ✅ Correção (prioridade ZERO — antes de qualquer limpeza)

```bash
cd /e/wamp64/www/davidcms
git config core.autocrlf input          # evita reconversao CRLF
bash docs/CORRIGIR-README.sh            # git cat-file blob 24b7957d... -> 3.213 bytes
bash tools/verify-checksums.sh          # 142 OK / 0 FAIL
```

Alternativa PowerShell: `powershell -NoProfile -ExecutionPolicy Bypass -File docs\CORRIGIR-README.ps1`
Rede de segurança: `docs/RESTAURAR-docs-README.txt` (3.213 bytes, o blob cru).

> ⚠️ **Diferente da primeira vez**, agora **há sim um diff para commitar**: a versão errada está no
> **HEAD**, não só no working tree.
> ```bash
> git status --short docs/README.md        # esperado: " M docs/README.md"
> git add docs/README.md
> git commit -m "fix(docs): restore frozen docs/README.md (CHECKSUMS-F11-10)"
> ```
> Commit/push são seus; **eu não faço push**.

❌ **Não reemitir `CHECKSUMS-F11-10.txt`**, não recalcular hash, não editar `decision-matrix.json`,
não registrar D09, não desligar `fail`/`failIfEmpty`, não adicionar `--accept` ao lychee.

Depois, para confirmar o fechamento:
```bash
bash tools/repo-health.sh                # 6 PASS / 0 FAIL
bash tools/verify-ci-workflow.sh         # 30 PASS / 0 FAIL
bash docs/verificar-congelados.sh        # 11/11, 10 pendentes
bash docs/verificar-links.sh
```

---

## 3. 🧹 Rodada final de conteúdo desnecessário (Nível D)

**13 arquivos / 773.334 bytes** — todos **0 manifesto, 0 congelado, 0 guard, 0 link, fora de runtime**:

| Grupo | Arquivos | Bytes | Motivo |
|---|---|---:|---|
| 2 CSVs desatualizados | `docs/inventario-escopos.csv`, `listas/inventario-escopos.csv` | 535.738 | 2.840 linhas; o HEAD tem 2.701. Substituídos por `listas/inventario-HEAD-9c606810.csv` |
| CSV duplicado na raiz | `inventario-HEAD-9c606810.csv` | 199.794 | Cópia idêntica à de `listas/` |
| 3 listas duplicadas em `docs/` | `lista-nivel-A-remover.txt`, `A2-map`, `B-compilar` | 18.902 | Cópias idênticas às de `listas/` |
| 2 LEIA-MEs transitórios | `LEIA-ME.txt`, `LEIA-ME-NOVO.txt` | 2.889 | São instruções de extração de ZIPs, já cumpridas |
| Listas/aplicador da raiz | `lista-raiz-unificar.txt`, `remover.txt`, `operacional.txt`, `inventario-raiz.csv`, `aplicar-limpeza-raiz.sh` | 16.011 | A limpeza da raiz foi executada em `0ae835b…` |

**Ficam** (não são lixo): `listas/lista-nivel-*.txt` — o `aplicar-limpeza-escopos.sh --nivel2` lê dali;
`listas/lista-limpeza-final-C.txt`; `listas/inventario-HEAD-9c606810.csv` (única cópia do inventário atual);
todos os corretores/verificadores `.sh`/`.ps1`; `docs/RESTAURAR-docs-README.txt` (rede de segurança
enquanto o CI não fecha); `threejs/index.html`; `tema/`; `dev/`; `tools/`; `tests/`; `agents-ia/`.

```bash
bash docs/aplicar-rodada-final.sh            # dry-run
# SÓ DEPOIS do CI verde:
bash docs/aplicar-rodada-final.sh --apply    # você decide, SEM push
```

O aplicador **aborta se `docs/README.md` ou `schema.sql` estiverem divergentes** — ou seja,
**recusa aplicar limpeza sobre repo vermelho**. Testado em réplica: `rc=0` com os hashes corretos,
`rc=1` com README divergente, `git rm` de 13/13 no `--apply`.

### Opcional — decisão sua, não automatizado

| Item | Bytes | Observação |
|---|---:|---|
| `docs/FRENTES-INTEGRAL.md` | 290.670 | Texto integral dos 63 `.md` da raiz **já removidos**. Os originais estão no histórico Git (`0ae835b…`). É o maior arquivo de `docs/` |
| `docs/FRENTES-CONSOLIDADO.md` | 29.717 | Índice consolidado — recomendo **manter** |
| ~10 relatórios intermediários (F11, D09, escopos, pós-limpeza) | ~76.000 | Podem virar 1 só. Não os removi automaticamente: são registro histórico |

---

## 4. ⏳ Pendências que **não** fazem parte do fechamento

| Nível | Arquivos | Bytes | Natureza |
|---|---:|---:|---|
| **A2** — 16 `.map` de Bootstrap/jQuery | 16 | 4.383.046 | Decisão de build; remove, mas DevTools passa a dar 404 ao depurar essas libs |
| **B** — `docs/` + `tools/` | 399 | 3.178.114 | Compilação dos ~203 `.md` históricos + remoção por bloco dos 196 PROBEs/e2e |

**Não aplicados por decisão, não por erro.** `php.yml` roda `find tools tests …` — remover **arquivos**
é seguro, remover o diretório `tools/` não é. O fechamento desta fase **não exige** A2 nem B.

---

## 5. 📊 Números acumulados da fase

| Marco | HEAD | Blobs | Bytes | Raiz | `evidencia/` |
|---|---|---:|---:|---:|---:|
| Início da auditoria | `687fba34…` | 2.880 | 61.255.662 | 109 / 3.974.200 | 11.873.366 |
| Limpeza da raiz | `703b0127…` | 2.840 | 57.864.981 | **43 / 148.863** | 11.873.366 |
| Nível A (escopos) | `9c606810…` | 2.701 | 43.303.922 | 44 / 150.364 | **0** |
| **Agora** | **`8246b901…`** | **2.707** | **43.717.393** | 46 / 351.546 | **0** |

**Saldo desde o início:** **−173 blobs · −17.538.269 bytes (−28,6%)** · raiz **109 → 46** arquivos
(3.974.200 → 351.546 bytes, **−91,2%**) · **215 paths removidos**, 42 adicionados (artefatos de auditoria).
Os +413.471 bytes do último commit são exatamente os **6 arquivos do meu ZIP** — por isso a rodada D existe.

**Após a rodada D:** ~2.694 blobs / ~42,94 MB / raiz **43 arquivos / ~148 KB** — voltando ao número
projetado no início.

### Cadeia de commits (todos do usuário)

`687fba34…` → `b2569ef…` → `040e3bc…` → `5c18e88…` → `9aa630c…` → **`0ae835b…`** (raiz, −68)
→ `703b0127…` → `e7f2ce0…` → **`6f46514…`** (evidências, −153) → `9c60681…` → **`8246b901…`** (kit + 1 bloqueio).

---

## 6. ✅ Checklist de fechamento

| # | Item | Status |
|---|---|---|
| 1 | 14 manifestos / 90 paths → **142 OK / 0 divergentes / 0 ausentes** | ⛔ **1 divergente** (`docs/README.md`) |
| 2 | 11 congelados coerentes · D09 não decidido | ✅ 11/11 |
| 3 | `docs/README.md` = 3.213 B / `6f0783e2…` | ⛔ **4.950 B / `22520a16…`** |
| 4 | `schema.sql` = 22.495 B / `c9dd1ec2…` | ✅ |
| 5 | `repo-health` 6 PASS / 0 FAIL | ⛔ depende de #3 |
| 6 | `verify-ci-workflow` 30 PASS / 0 FAIL | ✅ local anterior |
| 7 | `docs-links` (lychee v0.15.0) verde | ✅ no run atual |
| 8 | 0 links para os 215 paths removidos | ✅ |
| 9 | 10 `PENDING_DECISION` / 0 `DECIDED`, D01–D10 intactas | ✅ |
| 10 | Rodada D aplicada (13 / 773.334 B) | ⏳ após #1 |
| 11 | CI `repo-guards` verde no GitHub | ⏳ após commit seu |

**Fase fecha quando #1, #3, #5 e #11 estiverem ✅.** Falta **um arquivo**.

---

## 7. Limites e governança

Consulta somente-leitura às APIs `commits`, `git/trees`, `actions/runs`, `actions/runs/{id}/jobs`,
`check-runs` e `raw/<SHA>`; árvore `truncated:false`, hashes recalculados localmente.
Logs do job → `403 Must have admin rights` (não li o log; a causa foi determinada por hash).
**Não executei `git rm`, commit ou push; nenhuma escrita minha no GitHub.**
Não rodei CI remoto autenticado, `lychee`, os 6 guards no seu checkout, health autenticado, MySQL,
sessão Admin, migration, deploy ou runtime. Os 4 links `security@cksource.com` sem `mailto:` em
`ckeditor/CHANGES.md` são pré-existentes.

`release_approved` segue **`false`** · F9-27 **`BLOCKED_NOT_RUN`** · **10 `PENDING_DECISION` / 0 `DECIDED`**
· D01–D10 intactas · sem aceite visual do 3D / BO-r4 · sem prova de renderização no servidor.
**CI verde, repo menor e limpeza concluída não significam release aprovada.**

---

*Artefatos: `RELATORIO-FINAL-LIMPEZA.md` · `aplicar-rodada-final.sh` · `lista-rodada-final-D.txt` (13) ·
`CORRIGIR-README.sh/.ps1` · `ReamurCMS-Relatorio-Final-Limpeza-2026-11-01.zip` (+ `.sha256`).*
