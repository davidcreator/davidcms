# ReamurCMS — F11-01 · CI e dependências em `dev/system/storage/`

**Pacote de entrega — 2026-11-02 · ADR 0081 · arquivos COMPLETOS (não são patches).**

Este ZIP contém **todos os scripts, configurações, documentos e regras atualizados** pela frente F11-01, organizados **exatamente na estrutura do repositório**. Extraia na raiz de um clone atualizado de `main`.

- 📄 Relatório completo, diagnóstico, validações e **limitações**: `docs/F11-01-CI-e-Dependencias.md`
- 📄 Decisão arquitetural: `agents-ia/decisoes/0081-dependencias-node-em-system-storage-e-ci.md`
- 🔐 Integridade: `CHECKSUMS.txt` (CRC-32 + SHA-256 por arquivo). O SHA-256 do ZIP fica em `ReamurCMS-F11-01-CI-Deps-Node.zip.sha256`, fora deste pacote.

## Resumo da frente

| Antes | Depois |
| --- | --- |
| `static-analysis` vermelho (PHPStan em `block_document.php:122`) | `$version ?? 0` → `$version` (fail-closed preservado) |
| `php-lint` verde sem lintar nada (`defaults.run` global) | Cada job declara seu cwd; `php-lint` falha se zero arquivos e linta `dev/` na raiz |
| `node_modules/` (17,8 MB) commitado na raiz, sem manifesto | `package.json` + `package-lock.json` em `dev/system/storage/`; `node_modules/` ignorado |
| Playwright resolvido por `require('playwright')` | `tools/e2e/lib/load-playwright.cjs` (`createRequire` no storage) |
| `build_prod.sh` levaria Playwright para produção | Exclusões + veredito que reprova a presença em `prod/` |
| 4 jobs de CI | 5 jobs (novo `node-deps`) |

## Como aplicar (resumo)

```bash
# 1. extrair este ZIP na raiz do clone de main
# 2. remover o legado do índice (sem reescrever histórico)
git rm -r --cached node_modules
# 3. instalar dependências nos dois gerenciadores, no mesmo storage
composer install --working-dir=dev/system/storage
npm ci --prefix dev/system/storage
# 4. validar
bash tools/verify-f11-01.sh
```

⛔ **Este pacote não executa comandos Git, não faz push, não aplica migrations, não escreve em banco/staging/produção e não retoma o BDREditor** (projeto pausado em F9-103 por solicitação do usuário). Aplicação e publicação dependem de autorização explícita. PHP/PHPStan/browser não estavam disponíveis no ambiente de validação — veja as limitações no relatório.

## Arquivos do pacote

```text
.
├── .github/
│   ├── workflows/
│   │   └── php.yml
│   └── pull_request_template.md
├── agents-ia/
│   ├── decisoes/
│   │   ├── 0081-dependencias-node-em-system-storage-e-ci.md
│   │   └── README.md
│   └── regras/
│       ├── 00-contexto-global.md
│       └── 02-padroes-de-codigo.md
├── dev/
│   └── system/
│       ├── library/
│       │   └── blog/
│       │       └── block_document.php
│       └── storage/
│           ├── package-lock.json
│           └── package.json
├── docs/
│   ├── CHANGELOG.md
│   ├── Estrutura-de-Pastas.md
│   ├── F11-01-CI-e-Dependencias.md
│   ├── Guia-Composer.md
│   └── THREEJS_CMS_E2E.md
├── tools/
│   ├── e2e/
│   │   ├── lib/
│   │   │   └── load-playwright.cjs
│   │   ├── README.md
│   │   ├── threejs_admin_preview_sandbox_isolated.cjs
│   │   ├── threejs_cms_playwright.cjs
│   │   ├── threejs_native_admin_playwright.cjs
│   │   └── threejs_native_readonly_playwright.cjs
│   ├── staging/
│   │   ├── batteries/
│   │   │   └── e2e.cjs
│   │   ├── lib/
│   │   │   └── config.cjs
│   │   └── staging.ps1
│   ├── build_prod.sh
│   ├── check_producao.sh
│   └── verify-f11-01.sh
├── .gitignore
├── CHECKSUMS.txt
├── CONTRIBUTING.md
├── F11-01-INDICE.md
└── README.md
```

**Total: 31 arquivos** (incluindo `CHECKSUMS.txt`).

## Verificação de integridade

```bash
unzip -t ReamurCMS-F11-01-CI-Deps-Node.zip       # teste de CRC de todas as entradas
sha256sum -c ReamurCMS-F11-01-CI-Deps-Node.zip.sha256
```
