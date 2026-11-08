# Análise dos escopos seguintes — `docs/`, `tools/`, `dev/` e demais

**Data:** 2026-11-01 · **HEAD consultado agora:** `703b0127f5acaaf1c6a49c7092ce4551ed46e91a`
(`docs: fix broken links and restructure docs README`, 2026-11-08T15:00:20Z)
**Escopo:** todo o repositório **após** a limpeza da raiz.
**Natureza:** consulta e proposta. **Não removi nada, não alterei nada e não escrevi no GitHub.**

---

## 1. ✅ A limpeza da raiz foi confirmada — e foi exata

| Commit | O que fez |
|---|---|
| `5c18e88…` | `fix(docs): resolve CHECKSUMS failure and restore correct README.md` |
| `9aa630c…` | `feat(cleanup,docs): add complete root repo cleanup tooling and docs` |
| **`0ae835b…`** | **`chore: remove obsolete root-level delivery artifacts` — 68 arquivos removidos, 0 adições** |
| `703b0127…` | `docs: fix broken links and restructure docs README` (HEAD) |

| Métrica | Antes (`b2569ef…`) | **Agora (`703b0127…`)** |
|---|---:|---:|
| Arquivos na raiz | 111 | **43** |
| Bytes na raiz | 3.974.200 + duplicatas | **148.863** |
| Blobs / entradas | 2.890 / 3.506 | **2.840 / 3.456** |
| Bytes totais | 61.615.703 | **57.864.981** |

**O número bate exatamente com a projeção** (43 arquivos / 148.863 bytes).

### Integridade verificada por hash via `raw/<SHA>` (não é suposição)

| Verificação | Resultado |
|---|---|
| `docs/README.md` | **3.213 bytes / `6f0783e2…` = OK** (o FAIL não voltou) |
| 14 `CHECKSUMS*.txt` → **90 paths** | **142 OK / 0 divergentes / 0 ausentes** |
| 11 `frozen_artifacts_sha256` | **11/11 coerentes** |
| `tools/decision-matrix.json` | **10 `PENDING_DECISION` / 0 `DECIDED`** |
| `docs/F9-27-Status.json` | `BLOCKED_NOT_RUN` presente · `release_approved:false` |
| 384 `.md` / **320 links relativos** | **0 links apontando para os 68 removidos** · 4 links sem alvo, todos `security@cksource.com` sem `mailto:` em `ckeditor/CHANGES.md` (**pré-existentes**) |
| Guards citados no workflow / `repo-health.sh` | **8** presentes (`install-deps.sh`, `release-readiness-report.sh`, `repo-health.sh`, `verify-checksums.sh`, `verify-ci-workflow.sh`, `verify-contract-freeze.py`, `verify-deps-layout.sh`, `verify-f11-01.sh`) |

**Conclusão:** a limpeza foi precisa — sem tocar runtime, manifesto, contrato, CI ou link. Pode seguir.

---

## 2. Raio-X por escopo (2.840 blobs / 57.864.981 bytes)

| Diretório | Arquivos | Bytes | Em manifesto/congelado | Linkados | Veredito |
|---|---:|---:|---:|---:|---|
| `dev/` | 1.975 | **36.419.333** | 4 | 3 | 🔒 Runtime — intocável, salvo 16 `.map` |
| **`evidencia/`** | **86** | **11.873.366** | **0** | **0** | 🗑 **Maior alvo: 20,5% do repo, sem nenhuma proteção** |
| `docs/` | 301 | 2.792.363 | 30 | 52 | 📚 Compilável em parte |
| `previa/` | 8 | 2.195.457 | 0 | 0 | 🗑 Prévia estática |
| `tools/` | 227 | 1.613.676 | 29 | 4 | 📚 PROBEs/e2e acumulados |
| `agents-ia/` | 103 | 507.867 | 5 | 33 | 🔒 ADRs — manter |
| `tree/` | 19 | 396.228 | 0 | 0 | 🗑 Snapshot de código |
| `preview/` | 8 | 280.180 | 0 | 0 | 🗑 Prévia estática |
| `patch/` | 15 | 274.820 | 0 | 0 | 🗑 Diffs já aplicados |
| `reference/` | 7 | 85.477 | 0 | 0 | 🗑 Snapshot |
| `tests/` | 33 | 80.007 | 0 | 0 | 🔒 **MANTER** — `php.yml` roda `find tools tests …` |
| `diagnostico/` | 5 | 25.656 | 0 | 0 | 🗑 Artefatos BO |
| `listas/` | 4 | 12.618 | 0 | 0 | 🗑 Listas da limpeza da raiz (já cumpriram o papel) |
| `.github/` | 2 | 10.252 | 2 | 1 | 🔒 CI |

---

## 3. 🗑 NÍVEL A — remoção segura: **153 arquivos / 15.163.772 bytes**

**0 em manifestos, 0 congelados, 0 alvos de link, fora de runtime/CI/testes.**

| Bloco | Arquivos | Bytes | Observação |
|---|---:|---:|---|
| `evidencia/` | 86 | **11.873.366** | 42 PNG + 19 TXT + 13 JSON + 8 LOG + 3 SHA + 1 PHP. A auditoria de 2026-11-05 já tinha removido 50 arquivos daqui; **voltaram a acumular 86**. Maior PNG: `shot-home.png` 1,19 MB |
| `previa/` | 8 | 2.195.457 | Prévia BO-r4 (HTML isolado), já nos ZIPs |
| `tree/` | 19 | 396.228 | Snapshot de `dev/` — 3 idênticos aos paths reais |
| `preview/` | 8 | 280.180 | Prévia estática antiga |
| `patch/` | 15 | 274.820 | Diffs das frentes, já aplicados |
| `reference/` | 7 | 85.477 | Outro snapshot — 3 idênticos aos paths reais |
| `diagnostico/` | 5 | 25.656 | Artefatos de diagnóstico BO |
| `listas/` | 4 | 12.618 | As 3 listas + CSV; a limpeza da raiz já foi executada |
| `entregas/BDREditor-UI-Interpretation-Preview.html` | 1 | 19.970 | Já era Nível 2 em 2026-11-05 |

Lista pronta: **`lista-nivel-A-remover.txt`** (153 linhas).

> ⚠️ `tree/`, `reference/`, `preview/` e `evidencia/` são citados em **backticks** por alguns ADRs e docs F8/F9.
> Backticks **não** são links Markdown e o `lychee --offline` não os valida — conferi: 0 alvos de link nesses diretórios.
> Ainda assim rode o `docs-links` antes e depois, por disciplina.

### NÍVEL A2 — decisão de build à parte: **16 `.map` / 4.383.046 bytes**

Sourcemaps de Bootstrap/jQuery vendorizados em `dev/admin/view/js/` e `dev/catalog/view/js/`.
**0 manifestos, 0 congelados, 0 links.** Não quebram runtime nem `verify-deps-layout.sh`, mas o DevTools
passa a dar 404 ao depurar essas libs. É o mesmo Nível 2 da auditoria de 2026-11-05 — **decisão sua, não de limpeza de frente**.
Lista: **`lista-nivel-A2-map.txt`**.

**Ganho A + A2: 169 arquivos / 19.546.818 bytes = 33,8% de todo o repositório.**

---

## 4. 📚 NÍVEL B — compilação / união: **400 arquivos / 3.180.142 bytes**

| Escopo | Arquivos | O que fazer |
|---|---:|---|
| `docs/` | **203** | **Compilar de verdade.** 186 `.md` históricos + 7 de `docs/temas/` + 3 de `docs/correcao/`, todos sem manifesto/congelado/link. Exemplo de repetição: 132 arquivos `F9-*` na pasta |
| `tools/` | **197** | **Não "compilar" (juntar scripts não faz sentido): remover por bloco**, 109 em `tools/e2e/`, 80 na raiz de `tools/`, 6 `staging`, 2 `preview`. São 84 `.cjs` + 52 `.sh` + 32 `.js` + 15 `.py` + 13 `.php` de PROBEs/e2e acumulados das frentes F8/F9/BO |

Lista: **`lista-nivel-B-compilar.txt`** (400 linhas).

**Ressalvas obrigatórias antes de mexer em `tools/`:**
- `php.yml:198` roda `find tools tests -type f \( -name '*.js' -o -name '*.cjs' \)` com `set -euo pipefail`.
  **Remover arquivos individualmente é seguro; remover o diretório `tools/` NÃO é.**
- **8 guards oficiais + 29 paths em manifestos ficam** (já excluídos da lista).
- `tools/e2e/` pode ser referenciado por `agents-ia/` em backticks; rode `repo-health.sh` e `lychee` antes/depois.

**Para `docs/`**, sugiro o mesmo padrão que funcionou na raiz: um `docs/INDICE-HISTORICO.md` com a
tabela-mestra dos 203 + um `docs/HISTORICO-INTEGRAL.md` com o texto byte a byte (zero perda).
**Não** gerarei esses dois sem você pedir — são ~3 MB e a decisão de quanto preservar é sua.

---

## 5. 🔒 INTOCÁVEIS — 2.154 arquivos / 32.853.549 bytes

| Item | Motivo |
|---|---|
| `dev/` (1.975 arquivos) | Runtime do CMS: 365 `.php`, 498 `.js`, 239 `.css`, 90 `.twig`, 36 `.sql`. Inclui `three.core.js` (1,46 MB), `three.module.js` (663 KB), `ckeditor.js` (749 KB) |
| `tests/` (33) | `php.yml` usa `find tools tests …`; 80 KB não justificam o risco |
| `agents-ia/` (103) | 5 em `CHECKSUMS.txt`, 33 alvos de link (ADRs) |
| 14 manifestos + **90 paths** | `verify-checksums.sh` fail-closed |
| **11** congelados | `verify-contract-freeze.py` fail-closed |
| 8 guards + `.github/workflows/php.yml` + `docs/README.md` | CI |
| `threejs/index.html` (953.239) | **Fonte funcional `g3` do BN** (Vite/React/Three r186) — é referência, não lixo |
| `tema/bo_config.json`, `tema/david_universe_3d_bo.reamurtheme.json` | Config canônica (checksum `e2b935d8…`) e tema BO-r4 entregue |
| `docs/*.json` (40) | JSONs de estado/contrato F8/F9/F11 — **não compilar** |
| `docs/README.md`, 15 `F11-XX-INDICE.md`, `CONTRIBUTING.md`, `README.md` | Manifestos / links |
| `docs/F9-27-Status.json` (`BLOCKED_NOT_RUN`), `release_approved:false` | Não apagar **nem alterar** |

**Não reemita manifestos, não recalcule hashes, não decida D01–D10.**

---

## 6. Ordem recomendada (você aplica)

```bash
cd /e/wamp64/www/davidcms
git config core.autocrlf input          # evita o FAIL do README voltar

# 0) estado atual
bash tools/verify-checksums.sh          # 3 PASS / 0 FAIL | 142 arquivos
bash tools/repo-health.sh
bash tools/verify-ci-workflow.sh
bash docs/verificar-congelados.sh       # sem Python: 11/11, 10 pendentes
bash docs/verificar-links.sh            # sem lychee: triagem

# 1) DRY-RUN do Nivel A (nada escrito)
bash docs/aplicar-limpeza-escopos.sh

# 2) conferir antes de remover
lychee --offline --no-progress './**/*.md'    # se tiver; ou o verificar-links.sh

# 3) aplicar — commit local e seu, SEM push
bash docs/aplicar-limpeza-escopos.sh --apply
bash tools/verify-checksums.sh          # deve continuar 142 OK / 0 FAIL
bash tools/repo-health.sh
bash tools/verify-ci-workflow.sh

# 4) SÓ DEPOIS, se quiser: Nivel A2 (.map) e Nivel B (docs/tools)
```

O aplicador valida antes de remover: **0 paths em manifestos, 0 congelados, 0 guards, 0 alvos de link,
0 arquivos dentro de `dev/`(salvo `.map` só com `--nivel2`), `tests/`, `agents-ia/` e `.github/`**,
e aborta se algum path não existir no índice. **Dry-run por padrão, nunca faz push.**

---

## 7. Ganho projetado

| Cenário | Blobs | Bytes | Δ |
|---|---:|---:|---|
| Hoje (`703b0127…`) | 2.840 | 57.864.981 | — |
| Após Nível A | **2.687** | **42.701.209** | −153 arquivos / **−15,16 MB (−26,2%)** |
| Após A + A2 | **2.671** | **38.318.163** | −169 arquivos / **−19,55 MB (−33,8%)** |
| Após A + A2 + B (se aprovado) | ~2.271 | ~35.138.021 | −569 arquivos / **−39,3%** |

**0** runtime, **0** contrato, **0** manifesto, **0** CI, **0** SQL, **0** decisão D01–D10.

---

## 8. Limites

Consulta **somente-leitura** ao HEAD `703b0127…` (árvore `truncated:false`, hashes recalculados via `raw/<SHA>`).
**Não executei `git rm`, não fiz commit, não fiz push, nenhuma escrita minha no GitHub.**
Não rodei `lychee`, os 4 guards, CI remoto (403 `Must have admin rights`), health autenticado, MySQL,
sessão Admin, migration, deploy nem runtime. Os 4 links sem alvo são **pré-existentes** em `ckeditor/CHANGES.md`.
`release_approved` segue **`false`**, F9-27 **`BLOCKED_NOT_RUN`**, 10 `PENDING_DECISION` / 0 `DECIDED`,
D01–D10 intactas. **CI verde e Chromium headless não significam release aprovada.**

---

*Artefatos: `inventario-escopos.csv` (2.840 linhas) · `lista-nivel-A-remover.txt` (153) · `lista-nivel-A2-map.txt` (16) · `lista-nivel-B-compilar.txt` (400) · `aplicar-limpeza-escopos.sh`.*
