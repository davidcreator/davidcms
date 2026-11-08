# Diagnóstico — `repo-health: 5 PASS / 1 FAIL` (D09 / `schema.sql`)

**Data:** 2026-11-01 · **HEAD remoto auditado:** `703b0127f5acaaf1c6a49c7092ce4551ed46e91a`
**Veredito do guard:** `REPROVADO — abra a saída do guarda indicado antes de qualquer release`.
**Isto é o guard funcionando corretamente. Não é um defeito a ser "consertado" na matriz.**

---

## 1. O que o output mostrou

```
tools/verify-contract-freeze.py   FAIL (exit 1)
   10/11 hashes coerentes | 10 pendentes de decisao
   REGRA  dev/install/sql/schema.sql mudou sem decisao registrada (D09)
   RESULTADO: BLOQUEADO
== repo-health: 5 PASS / 1 FAIL / 0 SKIP ==
```

Os outros **5 guardas passaram**: `verify-deps-layout.sh` 18/0, `verify-checksums.sh` 3/0 (142 arquivos),
`verify-ci-workflow.sh` 30/0, `verify-f11-01.sh` 16/0/1 SKIP, `release-readiness-report.sh` PASS.

## 2. O que eu confirmei no GitHub (não é suposição)

| Item | Valor |
|---|---|
| `dev/install/sql/schema.sql` no HEAD remoto | **22.495 bytes** · SHA-256 **`c9dd1ec2…f67a5`** · **0 CRLF / 465 LF** · sem BOM |
| SHA-1 do blob Git | **`bcd0a351be1d49d709028480939b50c17c5c3254`** |
| Esperado em `decision-matrix.json` | **`c9dd1ec27af95d7506b8aff6af06133f75b288c2db31e5537fb7fbac2e1f67a5`** → **idêntico** |
| Minha auditoria anterior (mesmo HEAD) | **11/11 congelados coerentes** |
| D09 | `status: PENDING_DECISION` · evidência: *"`dev/install/sql/schema.sql` existe (22.495 bytes) e o checker o proíbe"* |

> **O contrato está intacto no repositório remoto.** A divergência é **somente do seu working tree local**.

## 3. Causa: `core.autocrlf` converteu LF → CRLF

O arquivo tem **465 linhas**. Com CRLF ele passa a **22.960 bytes** (22.495 + 465).
Simulei aqui: gravei o blob correto com `\r\n` e obtive **exatamente 22.960 bytes** e um SHA-256 diferente.
É o mesmo fenômeno que já tinha corrompido `docs/README.md` (3.213 → 3.884 bytes) e que gerou o aviso
`LF will be replaced by CRLF the next time Git touches it`.

Confirme com um comando:
```bash
wc -c dev/install/sql/schema.sql      # 22960 = CRLF | 22495 = correto
```

**`staging/` também apareceu nos links quebrados, mas tem 0 arquivos no HEAD remoto** — é um diretório local seu, não versionado.

## 4. ❌ O que NÃO fazer (restrição permanente)

- **Não registrar/alterar a decisão D09** (continua `PENDING_DECISION`; D01–D10 intactas).
- **Não atualizar o contrato da fase**, não editar `schema.sql`/`schema-complete.sql`/SQL de upgrade.
- **Não reemitir `frozen_artifacts_sha256`** nem recalcular o hash na matriz — é exatamente o que o guard existe para impedir.
- **Não converter `BLOCKED_NOT_RUN`, `SKIP` ou `PENDING_DECISION` em `PASS`**, e **não declarar release**.
- O `REPROVADO` do `repo-health` **não é autorização para relaxar o guard**; é o guard protegendo o contrato.

## 5. ✅ Correção: restaurar os bytes do blob (sem tocar em contrato algum)

Mesma técnica que resolveu o `docs/README.md`:

```bash
git config core.autocrlf input        # impede reconversao no proximo checkout
bash docs/CORRIGIR-SCHEMA.sh          # Git Bash / WSL
```
```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File docs\CORRIGIR-SCHEMA.ps1   # PowerShell
```

Os dois fazem `git cat-file blob bcd0a351… > dev/install/sql/schema.sql`, gravando **bytes crus**,
imunes a `core.autocrlf`, sem Python, sem rede, sem ZIP extraído, sem `git checkout`.

**Teste executado aqui:** `ANTES 22960 bytes / bb0caa87…` (CRLF simulado) →
`DEPOIS 22495 bytes / c9dd1ec2…` → `OK restaurado EXATAMENTE`, `rc=0`.
E `git hash-object -w` do arquivo correto devolveu **exatamente `bcd0a351…`**, o SHA-1 do blob remoto.

Depois:
```bash
bash docs/verificar-congelados.sh     # 11/11 coerentes, 10 pendentes
bash tools/repo-health.sh             # 6 PASS / 0 FAIL
bash tools/verify-checksums.sh        # continua 142 OK / 0 FAIL
```

---

## 6. Sobre os 49 links quebrados — **erro meu, corrigido**

O `docs/verificar-links.sh` que eu entreguei varre **todos** os `.md`, inclusive dependências vendorizadas.
O workflow oficial **exclui** explicitamente:

```
-not -path '*/storage/vendor/*'  -not -path '*/storage/node_modules/*'
--exclude 'dev/system/storage/node_modules'   --exclude 'node_modules'
```

Quase todos os 49 eram `dev/system/storage/vendor/*`, `node_modules/playwright/*` e `staging/*` —
**falsos positivos**, não links do projeto. Corrigi o script para espelhar as exclusões do CI e
ancorei o `ROOT` em `git rev-parse --show-toplevel` (antes, copiá-lo para `/tmp` fazia o `find` varrer o filesystem inteiro).

**Outra fonte real:** `docs/RESTAURAR-docs-README.md` é um artefato meu com **39 links relativos**.
Dentro de `docs/`, o `lychee` o varre e acusa alvos ausentes. Passei a entregá-lo como
**`RESTAURAR-docs-README.txt`** (o `lychee` só varre `.md`). Se você já extraiu o `.md` no repo:
```bash
rm docs/RESTAURAR-docs-README.md       # era untracked; nao faz falta, os scripts usam o blob Git
```

Com as duas correções, restam apenas os **4** `security@cksource.com` sem esquema `mailto:`
em `dev/admin/view/js/ckeditor/CHANGES.md` — **pré-existentes**, e o `lychee` oficial os trata de forma diferente.

> `lychee: command not found` é esperado: o binário só existe no job `docs-links`
> (`lycheeverse/lychee-action@v2`, pinado `v0.15.0`). Use `bash docs/verificar-links.sh` localmente.

---

## 7. `No such file or directory` — resolvido com um ZIP só

`docs/aplicar-limpeza-escopos.sh` e `docs/CORRIGIR-SCHEMA.sh` estavam no ZIP da **análise de escopos**,
que não foi extraído no seu repo. Entreguei agora **`ReamurCMS-KIT-UNICO-2026-11-01.zip`** com
**todos** os scripts e **todas** as listas (raiz + escopos) já em `docs/` e `listas/`.
Basta extrair na raiz: `unzip -o …`.

---

## 8. Sequência

```bash
cd /e/wamp64/www/davidcms
unzip -o ReamurCMS-KIT-UNICO-2026-11-01.zip
rm -f docs/RESTAURAR-docs-README.md

git config core.autocrlf input
bash docs/CORRIGIR-SCHEMA.sh
bash docs/CORRIGIR-README.sh            # idempotente, garante o README tambem

bash tools/verify-checksums.sh          # 142 OK / 0 FAIL
bash docs/verificar-congelados.sh       # 11/11
bash tools/repo-health.sh               # 6 PASS / 0 FAIL  <-- era REPROVADO
bash tools/verify-ci-workflow.sh        # 30 PASS / 0 FAIL
bash docs/verificar-links.sh            # triagem sem lychee, sem falsos positivos

# SÓ ENTÃO, dry-run da limpeza de escopos
bash docs/aplicar-limpeza-escopos.sh
```

---

## 9. Limites

Consulta somente-leitura ao HEAD `703b0127…`; restauração **testada numa réplica Git local** com o
mesmo blob, não executada no seu checkout. **Não fiz `git rm`, commit ou push, nenhuma escrita minha no GitHub.**
Não rodei CI remoto (403 `Must have admin rights`), health autenticado, MySQL, sessão Admin, migration,
deploy ou runtime. `release_approved` segue **`false`**, F9-27 **`BLOCKED_NOT_RUN`**,
**10 `PENDING_DECISION` / 0 `DECIDED`**, D01–D10 intactas. **Guard verde não significa release aprovada.**
