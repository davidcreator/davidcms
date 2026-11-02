# F11-02 — Verificação: dependências Node em `dev/system/storage/` e ignore no Git

**Data:** 2026-11-02 · **Estado verificado:** `main` @ `31b15e06` (aplicação do F11-01/ADR 0081) · **Tipo:** auditoria remota + prova local. **Nenhuma escrita no GitHub.**

---

## Resposta direta

**Sim — é possível, e já está configurado exatamente no mesmo padrão do Composer.** A pasta `node_modules/` que ainda aparece na raiz **não é resultado de nenhuma instalação**: são 182 arquivos que foram commitados por engano em 26/10/2026 e continuam no índice do Git. O `.gitignore` não apaga do índice o que já está versionado; por isso ela continua visível no GitHub.

| Gerenciador | Manifesto (versionado) | Comando | Destino da instalação (ignorado) |
| --- | --- | --- | --- |
| Composer | `dev/system/storage/composer.json` + `.lock` | `composer install --working-dir=dev/system/storage` | `dev/system/storage/vendor/` |
| npm | `dev/system/storage/package.json` + `package-lock.json` | `npm ci --prefix dev/system/storage` | `dev/system/storage/node_modules/` |
| npm (equivalente) | idem | `cd dev/system/storage && npm ci` ou `npm run ci:deps` | idem |

---

## 1. Estado real do GitHub (verificado por API)

| Item | Resultado |
| --- | --- |
| HEAD | `31b15e06` — *"feat(ci,deps): implement ADR 0081: fix CI and restructure deps"*, 2026-11-02T12:52Z |
| Arquivos do commit | **31** — exatamente os 31 arquivos do ZIP F11-01, todos presentes |
| Árvore | 3.281 itens / 2.661 blobs, `truncated: false` |
| `node_modules/` na raiz | **182 blobs ainda versionados** ⚠️ |
| `dev/system/storage/node_modules/` | **0 blobs** ✅ |
| `dev/system/storage/vendor/` | **0 blobs** ✅ |
| Manifestos | `composer.json`, `composer.lock`, `package.json`, `package-lock.json` presentes em `dev/system/storage/` ✅ |
| Outros `.gitignore` | apenas 2 aninhados em `dev/*/view/js/jquery/datetimepicker/`, sem regras sobre dependências |

Ou seja: **o objetivo "vendor e node_modules fora do GitHub" já está 100% cumprido para o destino novo**. O único resíduo é o legado da raiz, que depende de um comando Git.

---

## 2. Por que a pasta da raiz ainda aparece

`.gitignore` (linhas 11, 13, 17 do arquivo publicado) contém:

```gitignore
**/system/storage/vendor/
**/system/storage/node_modules/
/node_modules/
```

Regra do Git: arquivos **já rastreados** não são afetados pelo `.gitignore`. A pasta foi adicionada ao índice quando essa regra não existia; o commit `31b15e06` alterou o `.gitignore`, mas não removeu os 182 paths do índice. A correção é única e está documentada em `docs/F11-01-CI-e-Dependencias.md` §4:

```bash
git rm -r --cached node_modules          # remove SÓ do índice; nao apaga do disco
git commit -m "chore(deps): remover node_modules da raiz (ADR 0081)"
git push                                # somente com autorizacao explicita
rm -rf node_modules                     # opcional: apaga o residuo fisico local
```

> `git rm --cached` não reescreve histórico. Depois dele, mesmo que a pasta continue no disco local, ela fica ignorada e não volta ao GitHub.

---

## 3. Provas executadas localmente (repo Git temporário, fora do workspace)

Montei um repositório Git com o **`.gitignore` real do GitHub** e os **manifestos reais baixados de `main`**, e reproduzi os dois cenários.

### 3.1 Instalação no padrão do Composer

```
npm ci --ignore-scripts --no-audit --no-fund --prefix dev/system/storage   → added 2 packages
npm run ci:deps   (a partir de dev/system/storage)                        → added 2 packages
find . -type d -name node_modules                                          → ./dev/system/storage/node_modules   (único)
node → playwright 1.64.1 | chromium: object   (via tools/e2e/lib/load-playwright.cjs)
npm run verify → 17 PASS / 0 FAIL / 0 SKIP
```

**Nenhum `node_modules/` foi criado na raiz pela instalação.** O `npm run verify` foi executado sobre os 31 arquivos **baixados do próprio GitHub**, confirmando que o pacote aplicado está coerente.

### 3.2 Ignore — `git check-ignore -v --no-index`

| Caminho | Resultado | Regra |
| --- | --- | --- |
| `dev/system/storage/vendor/autoload.php` | ignorado | `:11 **/system/storage/vendor/` |
| `dev/system/storage/node_modules/playwright/index.js` | ignorado | `:13 **/system/storage/node_modules/` |
| `dev/system/storage/node_modules/playwright/index.html` | ignorado | `:13` |
| `dev/system/storage/vendor/twig/index.html` | ignorado | `:11` |
| `prod/system/storage/vendor/autoload.php` | ignorado | `:43 /prod/` |
| `node_modules/playwright/index.js` | ignorado | `:17 /node_modules/` |
| `dev/system/storage/package.json` | **não** ignorado | manifesto versionado |
| `dev/system/storage/composer.lock` | **não** ignorado | manifesto versionado |

⚠️ **Brecha verificada e descartada:** a linha 26 `!dev/system/storage/**/index.html` (negação para manter os `index.html` das pastas voláteis) **não re-inclui** arquivos dentro de `vendor/` ou `node_modules/`, porque o Git não permite re-incluir um arquivo cujo diretório-pai está excluído. Testado explicitamente com `.../node_modules/playwright/index.html` e `.../vendor/twig/index.html`: ambos continuam ignorados.

### 3.3 Índice após aplicar os 31 arquivos do GitHub

```
git add -A → nenhum arquivo com "vendor" ou "node_modules" entrou no índice ✅
git ls-files node_modules                     → 0
git ls-files dev/system/storage/node_modules  → 0
git ls-files | grep system/storage/vendor/   → 0
manifestos versionados                       → composer.json, composer.lock, package.json, package-lock.json
```

### 3.4 Simulação do legado

Criei os **182 paths reais** da árvore do GitHub, forcei `git add -f` (cenário "commitado por engano") e apliquei `git rm -r --cached node_modules`:

| | Antes | Depois |
| --- | --- | --- |
| `git ls-files node_modules \| wc -l` | **182** | **0** |
| Arquivos físicos em `node_modules/` | 182 | 182 (permanece no disco, agora ignorado) |
| `git status --short` | — | vazio |

---

## 4. Auditor entregue

`tools/verify-deps-layout.sh` — read-only, sem instalar/remover/commitar. Verifica manifestos, as 3 regras de `.gitignore`, `git check-ignore`, e — o mais importante — **o índice real do Git** (`git ls-files`). Se ainda houver dependências versionadas, imprime a correção e sai com `exit 1`.

| Cenário testado | Resultado |
| --- | --- |
| Índice limpo | **18 PASS / 0 FAIL / 1 WARN**, `exit 0` (WARN apenas pelo resíduo físico da raiz) |
| Índice com 182 arquivos na raiz (GitHub atual) | **17 PASS / 1 FAIL / 1 WARN**, `exit 1`, com `git rm -r --cached node_modules` sugerido |

Pode ser rodado antes de commits ou acoplado ao CI futuramente, se desejado.

---

## 5. Limitações e escopo

- **Não executado:** PHP, `composer install`, `php -l`, PHPStan, browser/Playwright real, banco, migrations, staging, produção, deploy, `build_prod.sh` (`rsync` ausente no sandbox) e `lychee`. O destino do Composer foi verificado por simulação do `vendor/` + `git check-ignore`, não por execução do Composer.
- **Nenhuma escrita no GitHub**: sem `git rm`, commit, push ou alteração remota. O `git rm -r --cached` foi executado **apenas** em repositórios temporários em `/tmp`.
- A pasta na raiz chama-se **`node_modules`** (com "s" e underscore), não `node_modulos`.
- `vendor/` e `node_modules/` **já não são enviados ao GitHub** no destino canônico; somente os 182 arquivos legados da raiz ainda estão publicados.
- O projeto segue pausado em F9-103; nenhuma frente do BDREditor foi retomada.

---

## 6. Próximo passo (depende de autorização)

No clone local do `main`, executar os 3 comandos da §2 e confirmar com:

```bash
bash tools/verify-deps-layout.sh    # esperado: 0 FAIL
git ls-files node_modules | wc -l   # esperado: 0
```
