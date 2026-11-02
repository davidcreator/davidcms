# ADR 0081 — Dependências Node em `dev/system/storage/` e correção do CI

| | |
| --- | --- |
| **Status** | ✅ aceita (F11-01, 2026-11-02) |
| **Data** | 2026-11-02 |
| **Agente proponente** | orquestrador (tarefa solicitada pelo humano) |
| **Contexto** | O `composer.json`/`composer.lock`/`vendor/` do ReamurCMS vivem em `dev/system/storage/`. Em 26/10/2026 o Playwright foi commitado em `node_modules/` na **raiz** do repositório (222 paths, ~17,8 MB), sem manifesto nem lock, fora do escopo do sistema. Ao mesmo tempo o workflow `.github/workflows/php.yml` usava `defaults.run.working-directory: dev/system/storage` globalmente, o que fazia o job `php-lint` executar `find dev ...` dentro de `storage/` e passar **verde sem lintar nada**; e o job `static-analysis` falhava com um erro real do PHPStan em `dev/system/library/blog/block_document.php:122`. |

## Decisão

1. **Toda dependência gerada de terceiros passa a viver em `dev/system/storage/`**: Composer (`composer.json`, `composer.lock`, `vendor/`) e Node/npm (`package.json`, `package-lock.json`, `node_modules/`). Manifestos e locks são **versionados**; `vendor/` e `node_modules/` são **ignorados**. Nenhuma pasta `node_modules/` na raiz do repositório.
2. **Playwright 1.64.1** é a única dependência Node declarada, com `package-lock.json` gerado (lockfileVersion 3), instalável por `npm ci --prefix dev/system/storage`. A versão reproduz exatamente a que estava commitada.
3. **Resolução explícita**: os runners `tools/e2e/*playwright*.cjs` carregam o pacote via `tools/e2e/lib/load-playwright.cjs` (`createRequire` ancorado em `dev/system/storage/package.json`). Sem `NODE_PATH`, sem symlink, sem duplicação de `node_modules`.
4. **CI sem `defaults.run` global**: cada job declara seu próprio `working-directory`. `composer` e `static-analysis` rodam em `dev/system/storage`; `php-lint` e `docs-links` rodam na raiz. Novo job `node-deps` valida instalação/resolução/sintaxe Node sem baixar browser (`--ignore-scripts`).
5. **Correção do erro do PHPStan**: em `BlockDocument::validateDocument()`, `$version` é sempre `int` após o guard de versão (null → 0, inválido → 0, válido → int); o `$version ?? 0` na chamada de `validateNode()` era redundante e é substituído por `$version`. Comportamento fail-closed preservado.
6. **Build de produção e verificação remota** passam a excluir/checar explicitamente `system/storage/node_modules/` e os manifestos Node, preservando a invariante "runtime-only" da ADR 0039.

## Alternativas consideradas

1. **Manter `node_modules/` na raiz e apenas ignorá-lo** — rejeitado: o usuário pediu expressamente que os recursos Node fossem para `storage`, preservando estrutura e escopo; além disso não haveria manifesto/lock reproduzível.
2. **`NODE_PATH` apontando para o storage** — rejeitado: depende de variável de ambiente em cada invocação, quebra execuções manuais documentadas e é implícito/frágil.
3. **Symlink `node_modules` → `dev/system/storage/node_modules`** — rejeitado: item versionado na raiz, problemas em Windows/PowerShell (ambiente de staging do projeto) e contraria a decisão 1.
4. **Adicionar o erro do PHPStan ao baseline** — rejeitado: o baseline é dívida legada congelada (ADR 0004); o erro era novo, real e trivial de corrigir no código.
5. **Mover o `composer.json` para a raiz** — rejeitado: contraria a decisão do usuário e as ADRs 0003/0039/Contrato-Bootstrap.

## Consequências

- ✅ CI deixa de ter falso verde no `php-lint` e falha real no `static-analysis`; estrutura de dependências fica simétrica e documentada.
- ✅ `prod/` não recebe mais 17,8 MB de Playwright; `check_producao.sh` passa a detectar vazamento de dev-only no novo caminho.
- ✅ `npm ci` reproduzível; `composer install` continua intacto em `dev/system/storage`.
- ⚠️ **Aplicação exige remoção do índice do Git**: `git rm -r --cached node_modules` (a pasta antiga continua no disco até exclusão manual). O ZIP **não** executa comandos Git nem escreve no GitHub.
- ⚠️ Os 4 runners Playwright e a bateria `tools/staging/batteries/e2e.cjs` mudam a forma de resolver o pacote; `tools/staging/staging.ps1` deixa de copiar `node_modules` para o clone descartável.
- ⚠️ `lychee` precisa excluir `dev/system/storage/node_modules`: o pacote Playwright contém 27 arquivos `.md` com links externos.
- ⚠️ Avisos do runner (`actions/*@v4` em Node 20 forçados a Node 24; `ubuntu-latest` migrando para Ubuntu 26) são **informativos e não bloqueantes**; não foram alterados nesta frente para evitar inventar versões de actions.

## Documentos atualizados

- `.github/workflows/php.yml` · `.gitignore` · `dev/system/storage/package.json` + `package-lock.json` · `dev/system/library/blog/block_document.php`
- `tools/build_prod.sh` · `tools/check_producao.sh` · `tools/e2e/lib/load-playwright.cjs` · `tools/e2e/*playwright*.cjs` · `tools/e2e/README.md` · `tools/staging/{lib/config.cjs,batteries/e2e.cjs,staging.ps1}` · `tools/verify-f11-01.sh`
- `README.md` · `CONTRIBUTING.md` · `docs/Guia-Composer.md` · `docs/Estrutura-de-Pastas.md` · `docs/THREEJS_CMS_E2E.md` · `docs/CHANGELOG.md` · `docs/F11-01-CI-e-Dependencias.md`
- `agents-ia/regras/00-contexto-global.md` · `agents-ia/regras/02-padroes-de-codigo.md` · `agents-ia/decisoes/README.md`
