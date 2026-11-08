# ✅ Guard verde — 3 observações e o próximo passo

**Data:** 2026-11-01 · **Resultado confirmado por você:**
`== resumo: 3 PASS / 0 FAIL | 14 manifesto(s), 142 arquivo(s) conferidos ==`

O `CORRIGIR-README.sh` funcionou: `ANTES 3884 bytes / 93554c5a…` → `DEPOIS 3213 bytes / 6f0783e2…`,
`10 entradas / 0 divergente(s)`. **O bloqueante está resolvido.**

---

## 1. ⚠️ O aviso `LF will be replaced by CRLF` — trate antes de continuar

```
warning: in the working copy of 'docs/README.md', LF will be replaced by CRLF the next time Git touches it
```

Isso é **`core.autocrlf=true`** no seu clone. Significa que, no próximo `git checkout` / `git restore` / `pull`, o Git vai **reconverter** `docs/README.md` para CRLF — e o `verify-checksums.sh` **volta a falhar**. Foi provavelmente isso (ou um re-encoding para 3.884 bytes) que corrompeu o arquivo entre uma execução e outra.

```bash
git config core.autocrlf input
git config --get core.autocrlf        # input
```

`input` **não converte no checkout** (mantém LF no disco) e normaliza no `add`. É config **local** em `.git/config`: não é versionada, não altera manifesto, contrato congelado, workflow nem CI.
Se preferir `false`, rode `git status` antes e depois: ele pode listar muitos arquivos como modificados — **não** faça commit em massa nesse caso.

Depois reconfirme:
```bash
bash docs/CORRIGIR-README.sh      # idempotente, regrava o blob cru
bash tools/verify-checksums.sh    # 0 FAIL
```

---

## 2. ℹ️ `no changes added to commit` **não é erro**

O `git status` que apareceu **não lista `docs/README.md`** entre os modificados nem entre os staged. Leitura: o **índice e o HEAD do seu branch já tinham o blob correto** (`6f0783e2…`); só o **working tree** estava divergente (3.884 bytes). Ao rodar `git add`, o Git normalizou e não sobrou diff — logo não havia nada para commitar.

Confirme com um comando:
```bash
git status --short docs/README.md     # esperado: vazio
```

Se estiver vazio, **não precisa de commit de correção**. Se aparecer `M  docs/README.md` ou `M docs/README.md`, aí sim:
```bash
git add docs/README.md
git commit -m "fix(docs): restore frozen docs/README.md (CHECKSUMS-F11-10)"
```

> Nota: seu output diz `On branch hotfix/login`, `up to date with 'origin/main'`. Confira em qual branch você quer aplicar a limpeza antes do `--apply`.

**O que o `git status` mostrou de fato** (esperado, veio dos ZIPs extraídos):
- modificados: `LEIA-ME.txt`, `docs/ANALISE-RAIZ-GITHUB.md`
- untracked: `docs/COMANDOS-POWERSHELL.md`, `CORRIGIR-4-ERROS.md`, `CORRIGIR-AGORA.md`, `CORRIGIR-README.ps1`, `CORRIGIR-README.sh`, `RESTAURAR-docs-README.txt`, `VERIFICAR.ps1`

Não são problema. São os artefatos que você copiou para o repo.

---

## 3. ❌ `docs/aplicar-limpeza-raiz.sh: No such file or directory`

Você extraiu o ZIP de **correção**, que contém só os scripts de restauração/verificação. O **aplicador e as listas** estavam no ZIP `ReamurCMS-Analise-Raiz-GitHub-2026-11-01.zip` (e no de limpeza), que não foi extraído aí.

**Resolvi com um único ZIP**: `ReamurCMS-KIT-Limpeza-Completo-2026-11-01.zip` — 30 entradas, com **tudo** no lugar: aplicador, as 3 listas (em `listas/` **e** em `docs/`), os 2 consolidados, os verificadores e os scripts de correção. O aplicador procura as listas em `listas/`, `.` e `docs/`, então **basta extrair na raiz**.

---

## 4. ▶️ Próximo passo

```bash
cd /e/wamp64/www/davidcms

# 0) extrair o KIT na raiz (unzip -o sobrescreve os .sh/.ps1 com as versoes atuais)
unzip -o ReamurCMS-KIT-Limpeza-Completo-2026-11-01.zip

# 1) travar o EOL e reconfirmar
git config core.autocrlf input
bash docs/CORRIGIR-README.sh
bash tools/verify-checksums.sh          # 3 PASS / 0 FAIL

# 2) DRY-RUN — nada escrito
bash docs/aplicar-limpeza-raiz.sh
# esperado: 66 paths | docs/README.md OK | seguranca: 0 intocaveis, 0 em manifestos, 0 linkados

# 3) conferencia adicional (opcional, sem Python/lychee)
bash docs/verificar-congelados.sh       # 11/11 coerentes, 10 pendentes
bash docs/verificar-links.sh            # triagem offline

# 4) SÓ ENTÃO aplicar — voce decide, commit local e seu, SEM push
bash docs/aplicar-limpeza-raiz.sh --apply
git rm -- ANALISE-RAIZ-GITHUB.md LEIA-ME.txt
bash tools/verify-checksums.sh          # deve continuar 0 FAIL
```

Também atualizei o aplicador: agora ele **checa o SHA de `docs/README.md` antes do `--apply`** e aborta se o FAIL tiver voltado (com a dica do `core.autocrlf`). Teste limpo no kit: `66 paths / 0/0/0 / rc=0`; ao injetar `F11-04-INDICE.md` na lista, `rc=1` e `ABORTADO`.

---

## 5. Limites

O aplicador e os verificadores `.sh` foram **executados numa réplica Git local** com os manifestos reais; o `.ps1` foi validado estaticamente (não há `pwsh` neste sandbox Linux). Não executei nada no seu checkout, não fiz `git rm`, commit ou push, **nenhuma escrita minha no GitHub**. Não rodei CI remoto (403 `Must have admin rights`), health autenticado, MySQL, sessão Admin, migration, deploy nem runtime.

`release_approved` segue **`false`**, F9-27 **`BLOCKED_NOT_RUN`**, 10 `PENDING_DECISION` / 0 `DECIDED`, D01–D10 intactas. **Guard verde não significa release aprovada.**

---

*Artefatos: `ReamurCMS-KIT-Limpeza-Completo-2026-11-01.zip` (+ `.sha256`) · `CORRIGIR-README.sh/.ps1` · `aplicar-limpeza-raiz.sh` · `VERIFICAR.ps1` · `FRENTES-CONSOLIDADO.md` / `FRENTES-INTEGRAL.md`.*
