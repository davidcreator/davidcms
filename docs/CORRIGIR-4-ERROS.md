# Corrigindo os 4 erros da execução no Windows

**Data:** 2026-11-01 · **Repo:** `E:\wamp64\www\davidcms` (seu checkout, **PowerShell**)
**Se você está no PowerShell, use `COMANDOS-POWERSHELL.md` + `VERIFICAR.ps1`** — os comandos `sha256sum`/`wc`/`.sh` abaixo são de Git Bash. A **opção B** (`git checkout … -- docs/README.md`) já rodou com sucesso e **resolveu o bloqueante**.
**HEAD real agora:** `b2569efbaf8e99736258c6bbe62de67761799823` — *posterior* ao `687fba34…` que eu havia auditado.
**Eu não removi nada, não alterei nada e não escrevi no GitHub.** Você aplica.

---

## Diagnóstico rápido

| # | Sintoma | Gravidade | Causa |
|---|---|---|---|
| 1 | `FAIL CHECKSUMS-F11-10.txt: hash/tamanho divergente docs/README.md` | 🔴 **BLOQUEANTE** | O commit `b2569ef…` **substituiu** o `docs/README.md` original pelo meu índice do workspace |
| 2 | `Python was not found … Microsoft Store` | 🟡 | Sem Python no Windows; `verify-contract-freeze.py` não rodou |
| 3 | `lychee: command not found` | 🟡 | `lychee` só existe no CI (`lychee-action@v2`), não localmente |
| 4 | `xargs: Cannot open input file 'lista-raiz-*.txt'` | 🟢 | As listas estão em **`listas/`**, não na raiz (foi onde você as subiu) |

**O `git rm` não chegou a rodar — e ainda bem**: ele teria criado um commit em cima de um guard já vermelho.

---

## 🔴 PASSO 0 — obrigatório, antes de qualquer `git rm`

### O que aconteceu

O `docs/README.md` **original** do repo (3.213 bytes, SHA `6f0783e2…`) é o índice da pasta `docs/`, criado na F11-10 **justamente para fechar 2 links relativos que o `lychee` acusava**. Ele está fixado no manifesto `CHECKSUMS-F11-10.txt`:

```
70310442  6f0783e24765594763237fdc023cb092db47c8be2051df027f802a79ae340bec  3213  docs/README.md
```

O commit `b2569ef…` (`docs: add github root audit…`, +48/−29) gravou no mesmo caminho o **meu** `docs/README.md` do workspace (3.057 bytes, SHA `0909b54e…`). Conferi no próprio HEAD remoto: **a divergência está no GitHub, não na sua máquina**. Foi erro meu ter entregado um `README.md` com esse nome — ele colidiu com um arquivo de contrato.

### ❌ O que NÃO fazer

**Não reemita `CHECKSUMS-F11-10.txt` nem recalcule o hash.** Isso alteraria um dos 89 paths dos manifestos e um contrato congelado; `verify-contract-freeze.py` é fail-closed e o `verify-checksums.sh` passaria a validar um hash diferente do registrado. A correção é **devolver o arquivo ao conteúdo original**, não mudar o manifesto.

### ✅ Como corrigir

Entreguei o conteúdo original exato em **`docs/RESTAURAR-docs-README.txt`**
(3.213 bytes · SHA `6f0783e24765594763237fdc023cb092db47c8be2051df027f802a79ae340bec` — conferido).

```bash
cd /e/wamp64/www/davidcms

# opção A (usando o arquivo que entreguei)
cp docs/RESTAURAR-docs-README.txt docs/README.md

# opção B, se você tem o commit anterior local
git checkout 687fba34f90b0eefcef5e32907d74be8ce42432b -- docs/README.md

# conferência — DEVE bater exatamente
sha256sum docs/README.md
wc -c docs/README.md        # 3213
bash tools/verify-checksums.sh   # 0 divergente(s)
```

**E preserve o meu índice sob outro nome**, fora de qualquer manifesto:

```bash
git show b2569efbaf8e99736258c6bbe62de67761799823:docs/README.md > docs/INDICE-CONSOLIDADO.md
```

Assim você não perde o mapa da documentação consolidada e o guard volta a ficar verde. Confirme também que os links que o README original fazia continuam resolvendo — conferi no HEAD novo: `docs/PROJECT-HANDOFF.md`, `docs/CHANGELOG.md`, `docs/Guia-ThreeJS.md` e `tools/verify-checkers-inventory.py` **estão presentes**.

---

## 🟡 Erro 2 — sem Python

`python3 tools/verify-contract-freeze.py` não rodou. **O guard oficial continua sendo o `.py` no CI** — não o substitua. Para conferir localmente sem instalar nada, use o equivalente read-only que escrevi:

```bash
bash docs/verificar-congelados.sh
```

Testado aqui contra a `decision-matrix.json` real do HEAD `b2569ef…`:
**`11/11 hashes coerentes | 0 ausentes | 0 divergentes | 10 decisões pendentes` → `COERENTE`**, exit 0.
Ou seja: os contratos congelados **estão intactos** — o FAIL é exclusivamente do `docs/README.md`.

---

## 🟡 Erro 3 — sem `lychee`

`lychee` é um binário que só o job `docs-links` instala (`lycheeverse/lychee-action@v2`, pinado em `v0.15.0`). Não existe no Git Bash e **não precisa instalar**. Use:

```bash
bash docs/verificar-links.sh
```

Varre todos os `.md`, resolve links relativos e aponta alvos ausentes, sem Python/Node/lychee.

**Conferência que fiz no HEAD `b2569ef…`:** 242 links relativos, **0 links apontando para qualquer um dos 66 paths** das listas de unificar/remover. A limpeza proposta **não quebra o `docs-links`**.

Os **10 links "quebrados" históricos** são links para **diretórios** (`docs/`, `agents-ia/modelos/`) e `security@cksource.com` sem esquema `mailto:` — pré-existentes em `CONTRIBUTING.md`, `ckeditor/CHANGES.md` e ADRs, e o `lychee` oficial os trata de forma diferente. Não foram introduzidos por esta entrega. O script é **triagem**, não veredito: num checkout incompleto ele pode listar falsos positivos.

---

## 🟢 Erro 4 — caminho das listas

O commit `b2569ef…` as colocou em **`listas/`**, não na raiz. Estas são as três:

```
listas/lista-raiz-unificar.txt      63 paths
listas/lista-raiz-remover.txt        3 paths
listas/lista-raiz-operacional.txt    7 paths
listas/inventario-raiz.csv          os 109 classificados
```

Em vez de `xargs`, use o aplicador que acha as listas automaticamente e é **dry-run por padrão**:

```bash
bash docs/aplicar-limpeza-raiz.sh            # so confere, nada altera
bash docs/aplicar-limpeza-raiz.sh --apply    # git rm (commit local e seu; SEM push)
```

Ele valida antes de remover: 0 intocáveis, 0 paths em manifesto, 0 arquivos linkados e todos presentes no índice do Git. Testado aqui: **66 paths, `segurança: 0/0/0`**, e ao injetar `F11-04-INDICE.md` na lista ele **abortou com exit 1**.

> ⚠️ Corrigi um erro meu nesse script: a 1ª versão usava `grep -F` e casava `INDICE.md` como substring de `F11-04-INDICE.md` dentro dos manifestos — **o mesmo falso positivo que a auditoria de 2026-11-05 já tinha corrigido** no `limpar-github.sh`. Agora compara o **4º campo** do manifesto via `awk`.

---

## 🧹 Extra — 2 duplicatas que o ZIP criou na raiz

O `b2569ef…` extraiu o pacote **inteiro** dentro do repo, então `ANALISE-RAIZ-GITHUB.md` e `LEIA-ME.txt` aparecem **duas vezes** (na raiz e em `docs/`). A raiz foi de **109 → 111 arquivos** — o oposto do objetivo.

```bash
git rm -- ANALISE-RAIZ-GITHUB.md LEIA-ME.txt
```

Mantenha as cópias de `docs/`. O conteúdo é idêntico; não há link apontando para as da raiz.

---

## Sequência final recomendada

```bash
cd /e/wamp64/www/davidcms

# 1) CORRIGIR O BLOQUEANTE
cp docs/RESTAURAR-docs-README.txt docs/README.md
git show b2569efbaf8e99736258c6bbe62de67761799823:docs/README.md > docs/INDICE-CONSOLIDADO.md

# 2) GUARDS — todos devem ficar verdes
bash tools/verify-checksums.sh          # 0 divergente(s)  <-- era o FAIL
bash tools/repo-health.sh
bash tools/verify-ci-workflow.sh        # 30 PASS / 0 FAIL (como antes)
bash docs/verificar-congelados.sh       # 11/11 coerentes, sem Python
bash docs/verificar-links.sh            # triagem offline, sem lychee

# 3) CONFERIR A LIMPEZA (nada escrito)
bash docs/aplicar-limpeza-raiz.sh

# 4) SÓ ENTÃO aplicar — voce decide, commit local, sem push
bash docs/aplicar-limpeza-raiz.sh --apply
git rm -- ANALISE-RAIZ-GITHUB.md LEIA-ME.txt
```

Resultado esperado após o passo 4: raiz **111 → 43 arquivos** (36 se depois mover os 7 operacionais para `tools/`), **−3,83 MB**, com 14 manifestos intactos, 89 paths byte a byte, 11 congelados intactos e `lychee` sem novos links quebrados.

---

## Limites

Consulta somente-leitura aos HEADs `687fba34…` e `b2569ef…`. Não executei `git rm`, não fiz commit, não fiz push, **nenhuma escrita minha no GitHub**. Não rodei o `lychee` oficial, CI remoto (403 `Must have admin rights`), health autenticado, MySQL, sessão Admin, migration, deploy nem runtime. `release_approved` segue **`false`**, F9-27 **`BLOCKED_NOT_RUN`**, `decision-matrix.json` com **10 `PENDING_DECISION` / 0 `DECIDED`**, D01–D10 intactas. **Guard verde e Chromium headless não significam release aprovada.**

---

*Artefatos entregues: `RESTAURAR-docs-README.txt`, `verificar-congelados.sh`, `verificar-links.sh`, `aplicar-limpeza-raiz.sh`, este guia.*
