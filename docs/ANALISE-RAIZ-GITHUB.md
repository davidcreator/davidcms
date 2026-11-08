# Análise da Raiz do GitHub — compilação, união e remoção

**Data:** 2026-11-01 · **HEAD consultado agora:** `687fba34f90b0eefcef5e32907d74be8ce42432b`
(`docs: add general ReamurCMS audit and consolidation report`, `2026-11-08T12:23:33Z`)
**Escopo:** **somente os arquivos da raiz do repositório** (`path` sem `/`).
**Natureza:** consulta e proposta. **Não removi nada, não alterei nada e não escrevi no GitHub.**

> A auditoria anterior era de 2026-11-05 (`892fbd…`). Esta é uma leitura **fresca**:
> a árvore mudou (3.495 entradas / 2.880 blobs / 61.255.662 bytes) e o commit atual
> já contém `docs/RELATORIO-GERAL.md` (o relatório que você subiu, 140 linhas).

---

## 1. Metodologia

| Passo | Resultado |
|---|---|
| `GET commits/main` | HEAD `687fba34…`, 1 arquivo adicionado (`docs/RELATORIO-GERAL.md`, +140) |
| `GET git/trees/<SHA>?recursive=1` | `truncated:false` · **3.495 entradas / 2.880 blobs / 61.255.662 bytes** |
| Filtro `type=blob` e `path` sem `/` | **109 arquivos / 3.974.200 bytes** |
| Download via `raw/<SHA>/…` | 115 arquivos (textos da raiz + guards + workflow) e **435/436 `.md` do repo** |
| Parse dos **14** `CHECKSUMS*.txt` | **89 paths** referenciados; formato `CRC32 SHA256 tamanho caminho` |
| Varredura de links Markdown | **281 links relativos** · 10 quebrados **pré-existentes** · 9 alvos na raiz |
| Leitura dos guards | `verify-checksums.sh` varre `CHECKSUMS*.txt` **por glob**; `verify-ci-workflow.sh` exige `README.md`; `php.yml` roda `lychee --offline './**/*.md'` |

---

## 2. Raio-X da raiz

| Métrica | Valor |
|---|---|
| Arquivos | **109** |
| Bytes | **3.974.200** |
| 2 PNGs de evidência | `BN-Amostra-vs-Tema.png` 2.191.551 + `BM-Preview-Cenas.png` 1.344.397 = **3.535.948 (89,0%)** |
| Os outros 107 arquivos | **438.252 bytes** — quase tudo documentação e ferramentas de texto |

| Categoria | Qtd |
|---|---|
| `INDICE-*.md` | 23 |
| `RELATORIO-FINAL-*.md` | 22 |
| `F11-XX-INDICE.md` | 15 |
| `CHECKSUMS*.txt` | 14 |
| `GUIA-*.md` | 5 |
| `DIAGNOSTICO-*.md` | 3 |
| Outros `.md` | 15 |
| PNG / HTML / SH / PY / PS1 / BAT / JS / TXT / CFG | 12 |

**Sobreposição medida** entre os 53 documentos de frente: seção `Limites` aparece **13×**, `Validação medida` **11×**, `O que não faz` **11×**, `Como aplicar` **9×**. Pares como `INDICE-BI ↔ INDICE-BJ` compartilham **35%** das linhas e `RELATORIO-FINAL-BI ↔ BJ` **22%**. Há repetição estrutural real, não só quantidade.

---

## 3. Classificação dos 109 arquivos

| Grupo | Arquivos | Bytes | O que fazer |
|---|---:|---:|---|
| 🔒 **INTOCAVEL** | **33** | 71.242 | Não remover, não unificar, não editar |
| ✅ **MANTER** | **3** | 40.998 | Permanecem na raiz |
| 🔧 **OPERACIONAL** | **7** | 36.623 | Manter até o aceite do 3D / Nível 2; depois mover para `tools/` |
| 📚 **UNIFICAR** | **63** | 284.932 | **Compilar** em 1–2 arquivos e retirar da raiz |
| 🗑 **REMOVER** | **3** | 3.540.405 | Remoção direta, sem compilação |

Inventário completo (arquivo, bytes, grupo, motivo): **`inventario-raiz.csv`**.
Listas prontas: **`lista-raiz-unificar.txt`** (63), **`lista-raiz-remover.txt`** (3), **`lista-raiz-operacional.txt`** (7).

---

## 4. 📚 O que pode ser COMPILADO / unido por completo — 63 arquivos

São a documentação acumulada das frentes, sem nenhuma proteção de manifesto nem de link:

| Bloco | Qtd | Bytes |
|---|---:|---:|
| `RELATORIO-FINAL-*.md` | 22 | 114.984 |
| `INDICE-*.md` | 23 | 58.197 |
| `GUIA-*.md` | 5 | 17.458 |
| `DIAGNOSTICO-*.md` | 3 | 24.613 |
| Avulsos (`RELATORIO.md`, `INDICE.md`, `RELATORIO-BO.md`, `LEIA-ME.md`, `Roteiro-Proximos-Passos-AP.md`, `Protocolo-Aceitacao-Visual-AP.md`, `Registro-Veredito-Visual-AP.md`, `Plano-Frente-AX.md`, `Inventario-Rename-Global.md`, `Analise-Viabilidade-Ferramentas-Internas.md`) | 10 | ~69.680 |
| **Total** | **63** | **284.932** |

**Já gerei os dois consolidados**, para você escolher o nível de preservação:

| Arquivo | Bytes | O que é |
|---|---:|---|
| **`docs/FRENTES-CONSOLIDADO.md`** ⭐ | **29.717** | Tabela-mestra das 63 frentes (arquivo, frente, bytes, veredito) + resumo de cada documento. **União enxuta e legível** |
| `docs/FRENTES-INTEGRAL.md` | 290.670 | **Texto byte a byte** dos 63 documentos concatenados, com divisórias `<!-- INICIO/FIM -->`. **Zero perda de conteúdo** |

As duas opções reduzem a raiz de **63 arquivos → 0** (o conteúdo vai para `docs/`):

- **Opção A (recomendada):** manter só `FRENTES-CONSOLIDADO.md` → 63 → **1 arquivo**, **−255.215 bytes**. Os originais seguem integralmente nos ZIPs em `entregas/`.
- **Opção B (arquivo morto):** manter os dois → 63 → **2 arquivos**, conteúdo integral preservado no repo, custo de **+35.455 bytes**.

> `docs/RELATORIO-GERAL.md` (já no HEAD) continua sendo a **visão executiva**; o consolidado é o **índice histórico por frente**. Não se sobrepõem.

**Ressalva de governança:** `Protocolo-Aceitacao-Visual-AP.md` e `Registro-Veredito-Visual-AP.md` registram o aceite da AP. Se compilá-los, **mantenha uma cópia em `docs/governanca/`** antes de remover da raiz — o conteúdo é pequeno e é prova de veredito. `Pauta-Decisoes-D01-D10.md` **não** entra nesta união (ver §5).

---

## 5. 🔒 O que NÃO pode sair — e por que

### 33 INTOCAVEIS

| Bloco | Qtd | Motivo técnico |
|---|---:|---|
| **14** `CHECKSUMS*.txt` (incluindo `CHECKSUMS-F11-16.txt`) | 14 | `tools/verify-checksums.sh` faz `for m in CHECKSUMS*.txt` — **varre por glob**. Remover qualquer um muda o conjunto auditado; `MANIFESTS=0` é `exit 1` |
| **15** `F11-XX-INDICE.md` | 15 | Estão entre os **89 paths** dos manifestos → `verify-checksums.sh` é fail-closed. 7 deles também são alvo de link em `docs/README.md` → `lychee --offline` quebraria |
| `README.md`, `CONTRIBUTING.md`, `.gitignore` | 3 | Paths dos manifestos; `README.md` é exigido explicitamente por `verify-ci-workflow.sh` e linkado por `CONTRIBUTING.md` |
| `SECURITY.md` | 1 | Alvo de link em `CONTRIBUTING.md` → `docs-links` |

> **Não "limpe" os 15 `F11-XX-INDICE.md` reemitindo manifestos ou recalculando hashes.**
> Isso alteraria contratos congelados e é exatamente o que `verify-contract-freeze.py` impede. São ~35 KB.

### 3 MANTER

`LICENSE` (35.149) · `.editorconfig` (706) · **`Pauta-Decisoes-D01-D10.md`** (5.143 — contém as **10 `PENDING_DECISION`**; `tools/decision-matrix.json` segue 10 pendentes / 0 decididas. Não compilar nem apagar sem decidir D01–D10).

### 7 OPERACIONAIS

`INSTALAR.bat` · `instalar-bo.ps1` · `instalar-bo.py` · `verificar-bo.html` · `diagnostico-console.js` · `COPIAR-MANUALMENTE.md` · `limpar-github.sh`.

São as ferramentas **ainda vivas**: o BO-r4 não tem aceite visual no seu servidor e o Nível 2 da limpeza de 2026-11-05 não foi aplicado. **Sugestão:** após `verificar-bo.html` verde e o Nível 2 concluído, mova os 7 para `tools/` (a raiz cai de 43 → 36 arquivos). Não apague agora.

---

## 6. 🗑 O que pode ser REMOVIDO por completo — 3 arquivos

| Arquivo | Bytes | Conferência |
|---|---:|---|
| `BN-Amostra-vs-Tema.png` | 2.191.551 | 0 manifestos · 0 links Markdown · duplicata no workspace e no ZIP BN |
| `BM-Preview-Cenas.png` | 1.344.397 | 0 manifestos · 0 links Markdown · duplicata no workspace e no ZIP BM |
| `verify-bn.log` | 4.457 | 0 manifestos · 0 links · log de validação já consolidado |
| **Total** | **3.540.405** | **89,1% de toda a raiz** |

São evidências de prévia, **não runtime, não contrato, não CI**. Estas são as remoções de maior ganho e menor risco de toda a análise.

---

## 7. Ordem de execução (você aplica)

```bash
# 0) conferir o estado atual — nada escrito
git status --porcelain
bash tools/verify-checksums.sh
bash tools/repo-health.sh
bash tools/verify-ci-workflow.sh
python3 tools/verify-contract-freeze.py

# 1) colocar os consolidados em docs/ (já gerados por mim, no workspace)
#    docs/FRENTES-CONSOLIDADO.md  +  opcional docs/FRENTES-INTEGRAL.md
#    e, se compilar os 2 de governanca, copia-los para docs/governanca/

# 2) checar links antes de remover (o CI usa lychee --offline)
lychee --offline --no-progress './**/*.md'

# 3) remover os 63 compilados + os 3 de evidencia
xargs -a lista-raiz-unificar.txt git rm --
xargs -a lista-raiz-remover.txt  git rm --

# 4) repetir os 4 guards + lychee. Os 33 intocaveis devem continuar 0 ausentes / 0 divergentes.
# 5) commit LOCAL. O agente NAO faz push.
```

**Pré-requisito de CI:** `docs/README.md` linka 7 `F11-XX-INDICE.md`. Eles ficam, então não há nada a editar desta vez — ao contrário da limpeza de 2026-11-05, que exigiu apagar 6 linhas em 3 `.md`. Ainda assim rode o `lychee` antes e depois: há **10 links quebrados pré-existentes** no repo (em `CONTRIBUTING.md`, `agents-ia/…`, `dev/.../ckeditor/CHANGES.md`), que não foram introduzidos por esta proposta.

---

## 8. Ganho projetado

| Cenário | Arquivos na raiz | Bytes na raiz |
|---|---:|---:|
| **Hoje** | **109** | **3.974.200** |
| Remover os 3 + compilar os 63 (Opção A: só o enxuto) | **43** | **148.863** |
| …e depois mover os 7 operacionais para `tools/` | **36** | **112.240** |

- **−66 arquivos na raiz** (−60,6%) e **−3.825.337 bytes** (−96,3%) já no primeiro passo.
- Saldo líquido no repositório: **−3.795.620 bytes** com a Opção A; **−3.504.950 bytes** com a Opção B (enxuto + integral).
- **0** manifesto alterado · **0** contrato congelado tocado · **0** runtime · **0** CI · **0** SQL · **0** decisão D01–D10 · `release_approved:false` intacto.

---

## 9. Limites

Consulta **somente-leitura** feita agora sobre `687fba34…`; não é health autenticado, não é CI remoto
(403 `Must have admin rights` conhecido), não é sessão Admin, MySQL, migration, deploy nem runtime.
Não executei `lychee`, os 4 guards nem qualquer `git rm` — **esses passos são seus**.
Os 10 links quebrados listados são pré-existentes. `release_approved` segue **`false`**,
F9-27 **`BLOCKED_NOT_RUN`**, `tools/decision-matrix.json` com **10 `PENDING_DECISION` / 0 `DECIDED`**,
D01–D10 intactas. **CI verde, PHP WASM, jsdom e Chromium headless não significam release aprovada.**
Nenhuma escrita do agente foi feita no GitHub.

---

*Artefatos: `inventario-raiz.csv` · `lista-raiz-unificar.txt` · `lista-raiz-remover.txt` · `lista-raiz-operacional.txt` · `FRENTES-CONSOLIDADO.md` · `FRENTES-INTEGRAL.md`.*
