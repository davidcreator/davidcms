# O FAIL ainda está lá — correção definitiva (1 comando)

**Data:** 2026-11-01 · **Situação:** `FAIL CHECKSUMS-F11-10.txt: hash/tamanho divergente docs/README.md` persiste.

---

## 1. O que o output colado realmente mostra

O cabeçalho `== F11-05 — integridade e presença dos guardas ==` **não é do `VERIFICAR.ps1`**
(que imprime `== VERIFICAR.ps1 | repo=…`). É do **`tools/verify-checksums.sh`**, um script **Bash**.
Além disso o caminho mudou para **`/mnt/e/wamp64/www/davidcms`** — isso é **WSL**, enquanto a
mensagem anterior era `PS E:\wamp64\www\davidcms>` (PowerShell).

Ou seja: o output é do guard Bash, possivelmente de uma execução anterior, em outro terminal.
**Não dá para concluir que o `VERIFICAR.ps1` rodou**, e o arquivo no disco **ainda não é o blob original**.

Conclusão prática: o `git checkout 687fba34… -- docs/README.md` que rodou sem erro **não está efetivo
neste checkout** — foi sobrescrito depois, executado em outro ambiente, ou o output é antigo.
Não vou assumir nada; vou gravar os bytes diretamente.

---

## 2. A correção que não depende de nada

Achei o **objeto Git exato** na árvore do commit `687fba34…`:

| Campo | Valor |
|---|---|
| SHA-1 do blob | **`24b7957d1d668591d9792659a41d2a6e26bcc470`** |
| Tamanho | **3.213 bytes** |
| SHA-256 do conteúdo | **`6f0783e24765594763237fdc023cb092db47c8be2051df027f802a79ae340bec`** |
| EOL | LF puro (0 CRLF), sem BOM |
| `.gitattributes` | **não existe** no repo (404) |

**Prova de identidade:** gravei o `RESTAURAR-docs-README.md` num repositório Git de teste com
`git hash-object -w` e o SHA-1 resultante foi **exatamente `24b7957d…`** — o mesmo do GitHub.
O conteúdo é idêntico byte a byte ao blob do commit.

### Escolha UM, conforme o terminal que estiver aberto

**PowerShell (`PS E:\wamp64\www\davidcms>`):**
```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File docs\CORRIGIR-README.ps1
```

**Git Bash ou WSL (`/mnt/e/wamp64/www/davidcms`):**
```bash
bash docs/CORRIGIR-README.sh
```

Os dois fazem o mesmo: `git cat-file blob 24b7957d… > docs/README.md`.
O redirecionamento grava **bytes crus**, sem conversão de encoding nem de EOL —
**imune a `core.autocrlf`**, sem Python, sem ZIP extraído, sem rede, sem `git checkout`.
Se o blob não existir no clone, o script avisa para rodar `git fetch --all`.

**Teste executado aqui:** antes `3 bytes / cd2eb083…` → depois
`3213 bytes / 6f0783e2…` → **`10 entradas / 0 divergente(s)`** no `CHECKSUMS-F11-10.txt`, `rc=0`.

---

## 3. Confira (escolha conforme o terminal)

| PowerShell | Git Bash / WSL |
|---|---|
| `(Get-FileHash docs/README.md -Algorithm SHA256).Hash.ToLowerInvariant()` | `sha256sum docs/README.md` |
| `(Get-Item docs/README.md).Length` | `wc -c docs/README.md` |

Os dois devem dar `6f0783e2…` e `3213`.

Depois rode **o guard no ambiente certo**:

```bash
# Git Bash / WSL
bash tools/verify-checksums.sh        # esperado: 14 manifestos, 141 arquivos, 0 divergente(s)
```

```powershell
# PowerShell
powershell -NoProfile -ExecutionPolicy Bypass -File docs\VERIFICAR.ps1
# esperado: 0 FALHAS | 11/11 congelados | 10 pendentes | 66 paths
```

---

## 4. ❌ O que NÃO fazer

- **Não reemita `CHECKSUMS-F11-10.txt`** nem recalcule o hash do manifesto: isso altera um dos 89 paths e um contrato congelado (`verify-contract-freeze.py` é fail-closed).
- **Não use `Copy-Item` a partir de um `.md` do workspace** se ele tiver passado por algum editor/ conversão de EOL — por isso a correção usa o blob do Git.
- **Não misture os terminais** sem conferir o caminho: `E:\wamp64\www\davidcms` (PowerShell) e `/mnt/e/wamp64/www/davidcms` (WSL) apontam para o mesmo disco, mas são ambientes diferentes, com `PATH` e ferramentas diferentes.
- **Não faça push por minha conta.** Nenhuma escrita minha foi feita no GitHub.

---

## 5. Sequência final

```bash
# 1) corrigir (Git Bash/WSL) — ou o .ps1 no PowerShell
bash docs/CORRIGIR-README.sh

# 2) confirmar
bash tools/verify-checksums.sh          # 0 divergente(s)

# 3) commitar a correcao (o git checkout anterior pode ter deixado o arquivo no stage)
git add docs/README.md
git commit -m "fix(docs): restore frozen docs/README.md (CHECKSUMS-F11-10)"

# 4) conferir a limpeza, nada escrito
bash docs/aplicar-limpeza-raiz.sh       # ou VERIFICAR.ps1 no PowerShell

# 5) so entao aplicar — voce decide
bash docs/aplicar-limpeza-raiz.sh --apply
git rm -- ANALISE-RAIZ-GITHUB.md LEIA-ME.txt
```

---

## 6. Limites

Consulta somente-leitura aos HEADs `687fba34…` e `b2569ef…`. O `.sh` foi **executado numa réplica
Git local** com o mesmo blob; o `.ps1` foi validado **estaticamente** (ASCII puro, 15/15 chaves,
33/33 parênteses, 0 CJK) — **não há `pwsh` neste sandbox Linux**. Não rodei os guards no seu
checkout, nem CI remoto (403 `Must have admin rights`), health autenticado, MySQL, sessão Admin,
migration, deploy ou runtime. Não fiz `git rm`, commit ou push.

`release_approved` segue **`false`**, F9-27 **`BLOCKED_NOT_RUN`**, 10 `PENDING_DECISION` / 0 `DECIDED`,
D01–D10 intactas. **Guard verde não significa release aprovada.**

---

*Artefatos: `CORRIGIR-README.ps1` · `CORRIGIR-README.sh` · `VERIFICAR.ps1` · `RESTAURAR-docs-README.md` · `COMANDOS-POWERSHELL.md`.*
