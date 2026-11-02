# F11-01 — Correção do CI e realocação das dependências Node para `dev/system/storage/`

| | |
| --- | --- |
| **Data** | 2026-11-02 (America/Sao_Paulo) |
| **ADR** | [`agents-ia/decisoes/0081-dependencias-node-em-system-storage-e-ci.md`](../agents-ia/decisoes/0081-dependencias-node-em-system-storage-e-ci.md) |
| **Origem** | Branch `main`, HEAD `61f39a9` (310 commits) + site em produção |
| **Tipo** | Correção de CI/infraestrutura + documentação. **Sem** migration, banco, staging, deploy ou escrita no GitHub |
| **Estado do BDREditor** | Permanece **pausado em F9-103** ( closure/handoff ). Esta frente **não** retoma o backlog do editor |

---

## 1. Diagnóstico (fatos verificados no `main`)

| # | Achado | Evidência | Criticidade |
| --- | --- | --- | --- |
| 1 | `static-analysis` falha | Annotation do check-run: `dev/system/library/blog/block_document.php:122` — *"Variable `$version` on left side of `??` always exists and is not nullable"*. O resumo "2 errors" conta também a annotation `Process completed with exit code 1` | 🔴 CI vermelha |
| 2 | `php-lint` é **falso verde** | `defaults.run.working-directory: dev/system/storage` aplicava-se a **todos** os jobs; `find dev ...` rodava dentro de `storage/`, onde não existe `dev/`. Sem `pipefall`/`-r`, o `xargs` terminava 0 sem lintar nenhum arquivo | 🔴 CI enganosa |
| 3 | `node_modules/` na raiz | 222 paths / ~17,8 MB (`playwright` + `playwright-core` **1.64.1**, `.bin`, `.package-lock.json`), commitado em 26/10/2026 **sem `package.json` nem lock versionados** → instalação não reproduzível e fora do escopo do sistema | 🔴 Estrutura |
| 4 | Build de produção vazaria QA | `tools/build_prod.sh` faz `rsync dev/ → prod/` sem excluir `system/storage/node_modules/`; com a realocação, 17,8 MB de Playwright subiriam para produção, violando a ADR 0039 (runtime-only) | 🔴 Impacto crítico |
| 5 | Runners E2E quebrariam | 4 arquivos `tools/e2e/*playwright*.cjs` usavam `require('playwright')`; com o pacote em `dev/system/storage/`, a resolução padrão do Node a partir de `tools/e2e/` não o encontra | 🔴 Impacto crítico |
| 6 | Bateria de staging quebraria | `tools/staging/batteries/e2e.cjs` checava `ROOT/node_modules/playwright`; `staging.ps1` copiaria 17,8 MB por `sync`; `config.cjs` não tinha caminho canônico | 🟠 Impacto |
| 7 | Link check geraria ruído | O pacote Playwright contém **27 arquivos `.md`**; `lychee './**/*.md'` passaria a varrê-los após a realocação | 🟠 Impacto |
| 8 | Verificação remota cega | `check_producao.sh` (check 14) não cobria `system/storage/node_modules/` nem os manifestos npm | 🟠 Impacto |
| 9 | Documentação divergente | README/"regra 00" diziam "esqueleto" e "vendor commitado"; `Guia-Composer.md` §5/§6 descreviam o workflow **falhando** e o vendor commitado; `docs/README.md` (linkado) é 404; CHANGELOG parado em 27/10; índice de ADRs com `0080` duplicado | 🟡 Dívida de doc |

Avisos do runner **não bloqueantes**, registrados e intencionalmente não alterados (não inventar versões de actions): `actions/checkout@v4`/`actions/cache@v4` em Node 20 forçados a Node 24; `ubuntu-latest` migrando para Ubuntu 26.

---

## 2. O que foi corrigido

### 2.1 CI — `.github/workflows/php.yml`
- Removido o `defaults.run` global. **`composer`** e **`static-analysis`** declaram `working-directory: dev/system/storage` no próprio job; **`php-lint`**, **`docs-links`** e o novo **`node-deps`** rodam na raiz.
- `php-lint`: `set -euo pipefail`, `find dev -type f -name '*.php'` com exclusão de `storage/vendor/` e `storage/node_modules/`, `xargs -0 -r`, e **falha explicitamente se zero arquivos** forem encontrados.
- `docs-links`: lychee exclui `dev/system/storage/node_modules` e `node_modules`.
- `node-deps` (novo): `setup-node@v4` (Node 20, cache pelo `package-lock.json`), `npm ci --ignore-scripts --prefix dev/system/storage`, validação da resolução do Playwright e `node --check` em `tools/` + `tests/`.

### 2.2 Erro real do PHPStan — `dev/system/library/blog/block_document.php`
Em `validateDocument()`, após o guard de versão, `$version` é **sempre** `int`: `null` → `0`, valor inválido → `0`, valor allowlisted → `int`. O `$version ?? 0` na chamada de `validateNode()` era redundante (e sinalizado no nível 2). Substituído por `$version`. **Comportamento fail-closed preservado**; nenhum contrato, schema ou bloco foi alterado.

### 2.3 Dependências Node — ADR 0081
- Novos e **versionados**: `dev/system/storage/package.json` + `package-lock.json` (lockfileVersion 3, `playwright 1.64.1` — mesma versão que estava commitada).
- **Ignorado**: `dev/system/storage/node_modules/` (`.gitignore`: `**/system/storage/node_modules/` e `/node_modules/` para o legado da raiz).
- Novo `tools/e2e/lib/load-playwright.cjs`: `createRequire()` ancorado em `dev/system/storage/package.json`. Os 4 runners passam a usar `loadPlaywright()` — sem `NODE_PATH`, sem symlink, sem `node_modules` na raiz. Mensagens de erro e guardas (`E2E_CONFIRM_DISPOSABLE`, `READ-ONLY`, restauração em `finally`) preservadas.
- `tools/staging/lib/config.cjs` ganha `NODE_DEPS_DIR`/`PATHS.nodePlaywright`; `batteries/e2e.cjs` checa o novo caminho e propaga `NODE_PATH` apenas como compatibilidade; `staging.ps1` deixa de copiar `node_modules` para o clone descartável.

### 2.4 Impactos críticos de produção/verificação
- `tools/build_prod.sh`: exclui `system/storage/node_modules/`, `package.json`, `package-lock.json` e qualquer `node_modules/`; o veredito final **falha** se algum deles aparecer em `prod/`.
- `tools/check_producao.sh`: check 14 passa a testar `system/storage/node_modules/`, `system/storage/package.json`, `system/storage/package-lock.json` e `node_modules/`.
- `tools/verify-f11-01.sh` (novo): verificação local read-only de manifestos, JSON/YAML, sintaxe, resolução npm e invariantes da frente.

### 2.5 Documentação e governança (regra 00 §6: docs no mesmo commit)
`README.md`, `CONTRIBUTING.md`, `.github/pull_request_template.md`, `docs/Guia-Composer.md` (§5/§5.1/§6 reescritos), `docs/Estrutura-de-Pastas.md` (§6), `docs/THREEJS_CMS_E2E.md`, `docs/CHANGELOG.md`, `tools/e2e/README.md`, `agents-ia/regras/00-contexto-global.md`, `agents-ia/regras/02-padroes-de-codigo.md`, `agents-ia/decisoes/README.md` (0081 + duplicata 0080 removida) e a nova ADR 0081.

---

## 3. Verificações executadas (estado real)

| Verificação | Resultado |
| --- | --- |
| `bash tools/verify-f11-01.sh` | **17 PASS / 0 FAIL / 0 SKIP** |
| `npm ci --ignore-scripts --prefix dev/system/storage` | OK — 2 pacotes, `playwright 1.64.1` resolvido por `createRequire` |
| `tools/*smoke.js` (30 scripts) | **30 OK / 0 falhas** |
| `python3 tools/f9-57-static-battery.py` | **335 checks**, 21 blocos top-level + 4 aninhados — todos aprovados |
| `node --check` em `tools/` + `tests/` | **110 arquivos, 0 falhas** |
| `bash -n` em `tools/*.sh` | **51 arquivos, 0 falhas** |
| JSON (`package.json`, `package-lock.json`) e YAML (`php.yml`) | válidos; workflow sem `defaults` global e com os 5 jobs |
| **Comparação A/B** com árvore idêntica do `main` original | `tools/e2e/*.cjs`: 61 checkers → **25 OK / 36 não-zero em ambas, zero diferenças**; `tests/check_*.js`: 9 → zero diferenças; 30 smokes e bateria → idênticos. **Nenhuma regressão introduzida** |

Sobre os **36 checkers `tools/e2e` com saída não-zero**: são **pré-existentes no `main`** e foram reproduzidos identicamente na árvore-base. Incluem (a) 4 runners Playwright que exigem browser/env/`E2E_CONFIRM_DISPOSABLE`, (b) runners HTTP que exigem seus wrappers `.sh`, (c) checkers que exigem argumentos de CLI, e (d) checkers com marcadores literais desatualizados já registrados em `docs/F9-47-Auditoria-Funcional-BDREditor.md` e `docs/F9-18-Risk-Register.json`. **Não foram mascarados como sucesso e não foram alterados nesta frente** (escopo cirúrgico).

### ⚠️ Limitações declaradas (não executado)
- **PHP, Composer, `php -l`, PHPStan, rsync e browser real não estavam disponíveis** no ambiente de validação. A correção do PHPStan foi identificada pela annotation oficial do GitHub e aplicada por análise semântica do trecho; **o job `static-analysis` não foi reexecutado aqui** — confirme o CI verde após aplicar.
- **Risco residual importante**: ao corrigir o falso verde, `php-lint` passará a verificar **todos os ~374 arquivos PHP** de `dev/`. Erros de sintaxe antes mascarados podem aparecer. Rode localmente antes do push:
  `find dev -name '*.php' -not -path '*/storage/vendor/*' -print0 | xargs -0 -n1 php -l`
- `lychee` (ação externa), `composer validate/install`, E2E em navegador, banco, migrations, staging e produção **não** foram executados.
- `rsync` ausente no sandbox: a exclusão do `build_prod.sh` foi verificada por leitura/asserts, não por execução da build.

---

## 4. Como aplicar (o pacote NÃO executa nada no GitHub)

1. Extraia o ZIP na **raiz** de um clone atualizado de `main` (ele espelha a árvore do repositório; sobrescreva os arquivos).
2. Remova o legado do índice, sem reescrever histórico:
   ```bash
   git rm -r --cached node_modules
   rm -rf node_modules                 # opcional, depois de conferir
   ```
3. Instale as dependências:
   ```bash
   composer install --working-dir=dev/system/storage
   npm ci --prefix dev/system/storage
   ```
4. Valide localmente:
   ```bash
   bash tools/verify-f11-01.sh
   find dev -name '*.php' -not -path '*/storage/vendor/*' -print0 | xargs -0 -n1 php -l
   for f in tools/*smoke.js; do node "$f" || exit 1; done
   python3 tools/f9-57-static-battery.py
   ```
5. Somente **com autorização explícita**: commit/PR seguindo o fluxo `feature/* → staging → main`, com a ADR 0081 referenciada no PR template.

> Nenhum comando Git, push, migration, escrita em banco, staging, deploy ou alteração de produção foi realizado por esta entrega. O projeto segue pausado em F9-103; F9-12 permanece adiada e não foi investigada.
