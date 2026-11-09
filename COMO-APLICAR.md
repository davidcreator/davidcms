# Como aplicar — Windows + WAMP

Guia específico para o seu caso: projeto rodando direto numa pasta do WAMP, Git Bash/PowerShell no Windows, MySQL local com `root` sem senha.

---

## 1. O que aconteceu (diagnóstico)

O commit **`0f09384`** ("fix: add reliable install, schema versioning and CI smoke tests") **não aplicou as correções**: ele **adicionou os arquivos do kit dentro do repositório**.

| Entrou no commit | Status |
|---|---|
| `patches/` (10 `.patch` + README) | ❌ é o kit, não a correção |
| `capturas/` (11 imagens) | ❌ anexo de análise |
| `ANALISE-COMPLETA.md`, `LEIA-ME.txt` | ❌ documento |
| `davidcms` (arquivo vazio, 1 linha) | ❌ provável acidente |
| **Correções no código** | ❌ **nenhuma** |

Prova objetiva no seu `main` atual:

```
dev/install/sql/schema.sql          → ainda sem COLLATE (32 ocorrências originais)
dev/system/library/migration.php    → não existe
tools/e2e/db-smoke.sh               → não existe
dev/system/storage/install.lock     → ainda versionado
```

E por isso o `git add -A` respondeu *"nothing to commit"*: o loop falhou antes (`../kit/patches/*.patch` não existia naquele caminho) e **nada foi alterado**.

---

## 2. ⚠️ AVISO IMPORTANTE — banco de dados

Você tentou rodar:

```bash
DB_NAME=davidcms bash tools/e2e/db-smoke.sh
```

**Não rode assim.** O `db-smoke.sh` **APAGA o banco** no início e no fim (`DROP DATABASE`). Se apontar para `davidcms`, você perde o banco de desenvolvimento.

O script agora **recusa** nomes que não sejam descartáveis (`*_smoke`, `*_test`, `*_ci`, `*_tmp`) e aborta com mensagem clara. Use sempre um banco separado:

```bash
DB_NAME=davidcms_smoke
```

*(No seu caso não houve dano: o comando falhou antes de executar, porque o arquivo ainda não existia.)*

---

## 3. Passo a passo

### Passo 1 — Conferir onde você está

Abra o **Git Bash na pasta do projeto** (a pasta do WAMP, onde fica `dev/`, `tools/`, `README.md`):

```bash
git status                 # deve dizer: nothing to commit, working tree clean
git log --oneline -1       # deve mostrar: 0f09384 fix: add reliable install...
git branch --show-current  # provavelmente fix/f11-17-instalacao-confiavel
```

### Passo 2 — Atualizar e escolher a branch

A branch `fix/f11-17-instalacao-confiavel` já existe (por isso o `checkout -b` reclamou). Basta **entrar** nela:

```bash
git fetch origin
git switch fix/f11-17-instalacao-confiavel
git merge origin/main      # traz o 0f09384, se ainda não estiver aqui
```

> Se preferir começar limpo: `git switch main && git pull && git switch -c fix/f11-17-instalacao-confiavel`

### Passo 3 — Aplicar as correções

Como o `patches/` **já está dentro do repositório**, o caminho correto é `patches/…` (sem `../kit`).

**Opção A — um patch único (recomendado, 1 comando):**

```bash
git apply --check patches/tudo-em-um.patch   # valida sem alterar nada
git apply patches/tudo-em-um.patch           # aplica
```

**Opção B — os 10 patches, um por um (mostra cada etapa):**

```bash
for p in patches/*.patch; do
  case "$p" in *tudo-em-um*) continue;; esac
  git apply "$p" && echo "ok   $(basename "$p")" || echo "FALHOU $(basename "$p")"
done
```

### Passo 4 — Verificar que as 22 alterações entraram

```bash
git status --short
```

Você deve ver **13 arquivos modificados** e **9 novos** (`??`), incluindo:

```
 D dev/system/storage/install.lock
 M dev/install/sql/schema.sql
 M dev/install/model/install/install.php
 M dev/catalog/index.php
 M dev/admin/view/css/admin.css
?? dev/system/library/migration.php
?? dev/system/library/sql/
?? dev/install/sql/migrations.json
?? tools/migrate.php
?? tools/e2e/db-smoke.sh
?? .gitattributes
?? agents-ia/decisoes/0084-…md
?? agents-ia/decisoes/0085-…md
```

Se aparecer **pouca coisa** ou um `FALHOU`, veja a seção 6 (problemas comuns antes de continuar).

### Passo 5 — Commit

```bash
git add -A
git commit -m "fix(install): instalacao confiavel, schema versionado e smoke com banco real (ADRs 0084/0085)"
```

### Passo 6 — Limpar o que entrou por engano na raiz

Agora que as correções estão aplicadas, tire o kit do código-fonte (ele não é produto):

```bash
git rm -r --quiet patches capturas LEIA-ME.txt davidcms
git mv ANALISE-COMPLETA.md docs/Analise-DavidAlmeida-2026-11-09.md
git commit -m "chore: remove artefatos do kit da raiz e arquiva a analise em docs/"
```

> `patches/` some do repositório, mas você continua com o `.zip` guardado. Se preferir manter o histórico dos patches, troque o `git rm -r patches` por `git mv patches docs/F11-17-patches`.

### Passo 7 — Rodar o smoke com banco real (o teste que importa)

Descubra o caminho do `mysql.exe` do WAMP (versão muda conforme a instalação):

```bash
ls /c/wamp64/bin/mysql/
```

Depois rode (exemplo com MySQL 8.0.32 — ajuste a versão):

```bash
MYSQL_BIN="/c/wamp64/bin/mysql/mysql8.0.32/bin/mysql.exe" \
DB_HOST=127.0.0.1 DB_PORT=3306 DB_USER=root DB_PASS= \
DB_NAME=davidcms_smoke \
bash tools/e2e/db-smoke.sh
```

**Esperado:** `== RESUMO: 41 PASS / 0 FAIL ==`

O script cria o banco `davidcms_smoke`, instala o CMS por HTTP, navega no site e no painel e **apaga o banco de teste** no fim.

### Passo 8 — Push

```bash
git push -u origin fix/f11-17-instalacao-confiavel
```

Depois abra o PR no GitHub. O job `db-smoke` (novo) roda no CI com MariaDB 11.8 e deve ficar verde.

---

## 4. Se você usa PowerShell (sem Git Bash)

Aplicar:

```powershell
git checkout fix/f11-17-instalacao-confiavel
git apply --check patches\tudo-em-um.patch
git apply patches\tudo-em-um.patch
git add -A
git status --short      # confirme os 22 itens
git commit -m "fix(install): instalacao confiavel, schema versionado e smoke com banco real (ADRs 0084/0085)"
```

O `db-smoke.sh` é **bash** — rode pelo Git Bash (menu Iniciar → *Git Bash*), apontando para a pasta do projeto.

---

## 5. Requisitos do ambiente (WAMP)

| Item | Como conferir | Se faltar |
|---|---|---|
| PHP no PATH | `php -v` | adicione `C:\wamp64\bin\php\php8.2.x` ao PATH, ou use o `php.exe` completo |
| extensões `mysqli` e `pdo_mysql` | `php -m \| grep -i mysql` | habilite no `php.ini` do WAMP |
| cliente MySQL | `mysql --version` | use `MYSQL_BIN=` com o caminho completo (Passo 7) |
| dependências do Composer | `ls dev/system/storage/vendor` | `composer install --working-dir=dev/system/storage` |
| `curl` | `curl --version` | já vem no Windows 10+ e no Git Bash |

---

## 6. Problemas comuns

| Mensagem | Causa | Solução |
|---|---|---|
| `can't open patch '.../*.patch': No such file or directory` | caminho errado | use `patches/*.patch` (dentro do projeto), não `../kit/patches` |
| `fatal: a branch named '...' already exists` | a branch já foi criada | `git switch fix/f11-17-instalacao-confiavel` |
| `nothing to commit, working tree clean` | nenhum patch aplicado | repita o Passo 3 e confira o Passo 4 |
| `error: patch failed` / `patch does not apply` | fim de linha (CRLF) do Windows | `sed -i 's/\r$//' patches/*.patch` e tente de novo |
| `corrupt patch at line N` | arquivo `.patch` foi convertido para CRLF | idem acima |
| ainda falhando por CRLF | `core.autocrlf` do Windows | `git config core.autocrlf false` → `git apply --cached patches/tudo-em-um.patch` → `git checkout -- .` |
| `bash: tools/e2e/db-smoke.sh: No such file or directory` | o patch 09 não foi aplicado | volte ao Passo 4 — o arquivo tem de existir |
| `cliente MySQL não encontrado` | `mysql` não está no PATH | use `MYSQL_BIN="/c/wamp64/bin/mysql/.../mysql.exe"` |
| `recusando rodar com DB_NAME="davidcms"` | proteção contra apagar seu banco | use `DB_NAME=davidcms_smoke` |
| `vendor ausente e composer não encontrado` | dependências não instaladas | `composer install --working-dir=dev/system/storage` |

---

## 7. Checklist final

- [ ] `git log --oneline -1` mostra o commit das correções
- [ ] `git status --short` limpo (nada pendente)
- [ ] `grep -c "COLLATE=utf8mb4_unicode_ci" dev/install/sql/schema.sql` → **33**
- [ ] `ls dev/system/library/migration.php` → existe
- [ ] `ls tools/e2e/db-smoke.sh` → existe
- [ ] `git ls-files dev/system/storage/install.lock` → **vazio** (não versionado)
- [ ] `php tools/migrate.php status` → lista as 29 migrações como `[BASE]`
- [ ] `bash tools/e2e/db-smoke.sh` → **41 PASS / 0 FAIL**
- [ ] raiz do repo sem `patches/`, `capturas/`, `LEIA-ME.txt`, `davidcms`
