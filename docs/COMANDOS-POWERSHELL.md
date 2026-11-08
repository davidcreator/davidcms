# Comandos no PowerShell — equivalentes do Git Bash

**Para:** `PS E:\wamp64\www\davidcms>` · **Data:** 2026-11-01
Você está no **PowerShell**, não no Git Bash. `sha256sum`, `wc`, `cp` com semântica Unix e os `.sh` não funcionam aqui. O `git` funciona normalmente.

---

## ✅ O bloqueante JÁ foi resolvido

```
git checkout 687fba34f90b0eefcef5e32907d74be8ce42432b -- docs/README.md
```

Esse comando rodou **sem erro** na sua execução. Ele restaurou `docs/README.md` ao blob original (3.213 bytes / `6f0783e2…`) e o **estagiou no índice**. O `FAIL` do `CHECKSUMS-F11-10.txt` deve ter desaparecido.

O `cp docs/RESTAURAR-docs-README.txt docs/README.md` falhou apenas porque o arquivo está dentro do ZIP **`ReamurCMS-Correcao-4-Erros-2026-11-01.zip`**, no meu workspace — você não o extraiu no repo. **Não é mais necessário.**

> ⚠️ `git checkout <sha> -- <path>` deixa o arquivo **no stage**. Faça o commit dessa correção antes de qualquer `git rm`.
> E **não reemita `CHECKSUMS-F11-10.txt`**.

---

## Tabela de equivalentes

| Git Bash / Linux | PowerShell |
|---|---|
| `sha256sum arquivo` | `(Get-FileHash -Algorithm SHA256 arquivo).Hash.ToLowerInvariant()` |
| `wc -c arquivo` | `(Get-Item arquivo).Length` |
| `cp a b` | `Copy-Item -LiteralPath a -Destination b -Force` |
| `cat arquivo` | `Get-Content -Raw arquivo` |
| `grep -E 'x' arquivo` | `Select-String -Pattern 'x' arquivo` |
| `bash script.sh` | **não roda** — use o Git Bash, ou o `.ps1` abaixo |
| `python3 x.py` | indisponível (alias da Microsoft Store) |
| `lychee --offline` | indisponível localmente (só no CI) |

**Conferência imediata do README:**

```powershell
$h = (Get-FileHash docs/README.md -Algorithm SHA256).Hash.ToLowerInvariant()
$n = (Get-Item docs/README.md).Length
$h                 # 6f0783e24765594763237fdc023cb092db47c8be2051df027f802a79ae340bec
$n                 # 3213
```

---

## Um comando para tudo: `VERIFICAR.ps1`

Entreguei `docs/VERIFICAR.ps1` — **PowerShell 5.1+ puro**, sem Bash, sem Python, sem `sha256sum`, sem `wc`, sem `lychee`. Ele faz, localmente:

1. `docs/README.md` → tamanho e SHA esperados
2. **14** `CHECKSUMS*.txt` → recalcula todos os hashes e tamanhos (equivalente ao `verify-checksums.sh`)
3. **11** contratos congelados via `ConvertFrom-Json` + `Get-FileHash` (equivalente ao `verify-contract-freeze.py`)
4. Links Markdown relativos de todos os `.md` (triagem do `lychee --offline`)
5. Limpeza da raiz: acha `listas/`, valida intocáveis/manifestos/ausentes — **dry-run por padrão**

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File docs\VERIFICAR.ps1
```

Esperado depois da correção: **0 divergentes**, **11/11 congelados coerentes**, **10 decisões pendentes**, **66 paths candidatos**, `0 bloqueios`.

Para aplicar a limpeza (commit local é seu, **sem push**):

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File docs\VERIFICAR.ps1 -Aplicar
```

Ele **aborta sozinho** se houver qualquer `FAIL` ou se um intocável aparecer na lista. Testei a lógica numa réplica: ao injetar `F11-04-INDICE.md`, o bloqueio dispara.

---

## E os guards oficiais `.sh`?

`tools/verify-checksums.sh`, `repo-health.sh` e `verify-ci-workflow.sh` são **Bash** e são os guards que o CI executa. No PowerShell puro eles **não rodam**. Você tem duas opções:

- **Recomendado:** abra o **Git Bash** (vem com o Git for Windows — foi onde você rodou o primeiro bloco, com `bash tools/...`) e execute lá:
  ```bash
  bash tools/verify-checksums.sh
  bash tools/repo-health.sh
  bash tools/verify-ci-workflow.sh
  ```
- Ou use apenas o `VERIFICAR.ps1` para conferência local, **sem substituir** os guards no CI.

---

## Onde estão as listas

O commit `b2569ef…` as colocou em **`listas/`**, não na raiz:

```
listas/lista-raiz-unificar.txt      63 paths
listas/lista-raiz-remover.txt        3 paths
listas/lista-raiz-operacional.txt    7 paths
```

O `VERIFICAR.ps1` procura em `listas/`, `.` e `docs/` automaticamente; se estiverem em outro lugar, use `-Listas "E:\caminho"`.

---

## Sequência mínima

```powershell
# 1. confirmar a correcao
powershell -NoProfile -ExecutionPolicy Bypass -File docs\VERIFICAR.ps1

# 2. se 0 FALHAS, commitar a restauracao do README
git add docs/README.md
git commit -m "fix(docs): restore frozen docs/README.md (CHECKSUMS-F11-10)"

# 3. conferir a limpeza (dry-run)
powershell -NoProfile -ExecutionPolicy Bypass -File docs\VERIFICAR.ps1

# 4. aplicar — voce decide; SEM push
powershell -NoProfile -ExecutionPolicy Bypass -File docs\VERIFICAR.ps1 -Aplicar
git rm -- ANALISE-RAIZ-GITHUB.md LEIA-ME.txt
```

---

## Limites

`VERIFICAR.ps1` é **conferência local**, não o CI. Não executei PowerShell neste sandbox (Linux, sem `pwsh`); validei estaticamente (ASCII puro, 56/56 chaves, 123/123 parênteses, 0 CJK) e simulei a lógica em Python sobre a réplica: após restaurar o README, **0 divergentes / 11/11 congelados / 10 pendentes / 66 paths**. Os 27 "ausentes" dessa réplica são artefato do checkout incompleto que montei — no seu repo real o `verify-checksums.sh` já reportou **0 ausentes**.

Nenhuma escrita minha foi feita no GitHub. `release_approved` segue `false`, F9-27 `BLOCKED_NOT_RUN`, 10 `PENDING_DECISION`, D01–D10 intactas.
