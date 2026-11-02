# Guia Composer — ReamurCMS

> **Objetivo:** documentar como o Composer **e o npm** estão configurados no ReamurCMS, os comandos do dia a dia e a integração com o CI — estado atualizado pela frente **F11-01 / ADR 0081** (correções aplicadas).

---

## Sumário

1. [Onde o Composer vive no projeto](#1-onde-o-composer-vive-no-projeto)
2. [Anatomia do composer.json](#2-anatomia-do-composerjson)
3. [Dependências](#3-dependências)
4. [Comandos do dia a dia](#4-comandos-do-dia-a-dia)
5. [CI — GitHub Actions](#5-ci--github-actions)
6. [Pontos de atenção do repositório](#6-pontos-de-atenção-do-repositório)
7. [Autoload: Composer × Autoloader do ReamurCMS](#7-autoload-composer--autoloader-do-reamurcms)
8. [Boas práticas](#8-boas-práticas)
9. [Cheat sheet](#9-cheat-sheet)

---

## 1. Onde o Composer vive no projeto

Diferente de projetos PHP convencionais, o Composer do ReamurCMS **não fica na raiz** do repositório:

```text
davidcms/
└── dev/
    └── system/
        ├── vendor.php                  # ponte: require do autoload do Composer
        └── storage/
            ├── composer.json           # 📄 manifesto (pacote reamurcms/reamurcms)
            ├── composer.lock           # 🔒 versões exatas (sempre commitado)
            └── vendor/                 # dependências instaladas (ignorado no Git)
```

Isso é intencional: o `storage/` concentra tudo que é gravável/gerado (cache, logs, sessões, vendor), mantendo `engine/` e `library/` 100% código próprio.

A ponte entre o Composer e o CMS é o `vendor.php`, carregado logo após o autoloader nativo no `framework.php`:

```php
// dev/system/framework.php
$autoloader = new \Reamur\System\Engine\Autoloader();
// ... registros de namespace ...
require_once(DIR_SYSTEM . 'vendor.php');   // sobe Twig, Guzzle, etc.
```

---

## 2. Anatomia do composer.json

Manifesto real do projeto (`dev/system/storage/composer.json`), comentado bloco a bloco:

| Bloco | Conteúdo | Observação |
| --- | --- | --- |
| `name` | `reamurcms/reamurcms` | Identidade do pacote — **manter** |
| `description` | "ReamurCMS is a PHP-based open source content management system." | |
| `type` | `project` | É uma aplicação, não uma biblioteca |
| `keywords` | cms, blog, ecommerce, framework, opensource | |
| `homepage` | `https://www.reamurcms.com/` | |
| `license` | `GPL-3.0-or-later` | Compatível com a licença do README |
| `support` | e-mail, issues, fórum, wiki, docs | Apontam para a org `reamurcms` (futura) |
| `config.vendor-dir` | `vendor/` (relativo ao storage) | Por isso o vendor cai em `storage/vendor/` |
| `require.php` | `>=8.0.0` | Ver [Guia PHP 8+](Guia-PHP8.md) |

---

## 3. Dependências

| Pacote | Versão | Para que serve no ReamurCMS |
| --- | --- | --- |
| `twig/twig` | `^3.3.7` | **Template engine padrão** — ver [Guia Twig](Guia-Twig.md) |
| `scssphp/scssphp` | `^1.10.0` | Compilação de SCSS no servidor (temas admin/catalog) |
| `guzzlehttp/guzzle` | `^7.0` | Cliente HTTP — webhooks, Mercado Pago, APIs externas |
| `guzzlehttp/promises` | `^2.0` | Utilitários do ecossistema Guzzle (async) |
| `guzzlehttp/psr7` | `^2.4` | PSR-7 — **pin ≥ 2.4**: série 1.x tem advisories de segurança e conflita com o AWS SDK atual |
| `guzzlehttp/uri-template` | `^1.0` | Exigência do AWS SDK |
| `aws/aws-sdk-php` | `^3.142.1` | Integrações AWS (S3 para mídia, SES para e-mail etc.) |
| `mustache/mustache` | `^3.0` | Adaptador de template Mustache |
| `latte/latte` | `^3.0` | Adaptador de template Latte |

**require-dev (ADR 0004):** `phpstan/phpstan ^1.10` · `phpunit/phpunit ^10.5`.

> ⚠️ **phpDocumentor via PHAR, não via Composer:** suas dependências (flysystem 1.x) são bloqueadas por advisories. Baixar `phpDocumentor.phar` quando precisar gerar a doc de API e rodar `composer docs` (ver [Guia PHP 8 §8](Guia-PHP8.md#8-documentação-de-api-com-phpdocumentor)).

---

## 4. Comandos do dia a dia

Todos rodados **a partir de** `dev/system/storage/` (ou com `--working-dir`):

```bash
cd dev/system/storage

# Instalar conforme o composer.lock (produção/CI)
composer install --prefer-dist --no-progress --no-interaction

# Instalar/atualizar resolvendo dependências (dev)
composer update

# Adicionar nova dependência
composer require fabricante/pacote

# Remover
composer remove fabricante/pacote

# Validar o manifesto (o que o CI faz)
composer validate --strict

# Ver dependências desatualizadas
composer outdated

# Regenerar autoload (após mover classes)
composer dump-autoload

# Autoload otimizado para deploy de produção
composer dump-autoload --optimize --classmap-authoritative

# Auditar vulnerabilidades conhecidas
composer audit
```

Sem `cd`:

```bash
composer install --working-dir=dev/system/storage
```

---

## 5. CI — GitHub Actions

Workflow: `.github/workflows/php.yml` (5 jobs). **F11-01 / ADR 0081** removeu o
`defaults.run.working-directory` global: ele era correto para o Composer, mas
fazia o `find dev ...` do `php-lint` rodar **dentro** de `dev/system/storage/`,
onde não existe `dev/` — o job passava verde sem lintar nenhum arquivo.

| Job | `working-directory` | O que valida |
| --- | --- | --- |
| `composer` | `dev/system/storage` | `composer validate --strict` + `composer install` (cache do `vendor/`) |
| `php-lint` | **raiz do repo** | `find dev -name '*.php'` → `php -l`; falha se zero arquivos; exclui `storage/vendor/` e `storage/node_modules/` |
| `docs-links` | **raiz do repo** | lychee em `./**/*.md`, excluindo `node_modules/` (o pacote Playwright traz 27 `.md`) |
| `static-analysis` | `dev/system/storage` | `composer install` + `composer analyse` (PHPStan nível 2, baseline congelado) |
| `node-deps` | **raiz do repo** | `npm ci --ignore-scripts --prefix dev/system/storage`, resolução do Playwright e `node --check` em `tools/` + `tests/` |

Regra: **cada job declara o seu cwd**. `composer.json`/`.lock`/`vendor/` e
`package.json`/`package-lock.json`/`node_modules/` vivem todos em
`dev/system/storage/`. O job `node-deps` não baixa browser (`--ignore-scripts`);
Chromium é instalado manualmente apenas por quem for executar E2E real:
`npx --prefix dev/system/storage playwright install chromium`.

> ⚠️ Ao aplicar esta frente, o `php-lint` passa a verificar **todos** os ~374
> arquivos PHP de `dev/`. Erros de sintaxe antes mascarados podem aparecer.
> Rode `find dev -name '*.php' -not -path '*/storage/vendor/*' -print0 | xargs -0 -n1 php -l`
> localmente antes do push.

## 5.1 Dependências Node (ADR 0081)

| Item | Caminho | Versionado? |
| --- | --- | --- |
| Manifesto | `dev/system/storage/package.json` | ✅ |
| Lock | `dev/system/storage/package-lock.json` | ✅ |
| Instalação | `dev/system/storage/node_modules/` | ❌ (`.gitignore`) |
| Legado | `node_modules/` na raiz (commitado em 26/10/2026) | ❌ remover do índice |

```sh
npm ci --prefix dev/system/storage            # reproduz Playwright 1.64.1
npx --prefix dev/system/storage playwright install chromium   # opcional, so para E2E real
```

Os runners `tools/e2e/*playwright*.cjs` usam
`tools/e2e/lib/load-playwright.cjs` (`createRequire` ancorado no
`package.json` do storage). Não existe mais `require('playwright')` direto nem
dependência de `NODE_PATH`. Verificação local: `bash tools/verify-f11-01.sh`.

---

## 6. Pontos de atenção do repositório

### 6.1 `.gitignore` cobre as duas árvores de dependências

```gitignore
**/system/storage/vendor/            # Composer
**/system/storage/node_modules/      # npm (ADR 0081)
/node_modules/                      # legado da raiz — nao volta a entrar
```

Os manifestos e locks **não** estão ignorados e devem permanecer commitados —
são eles que garantem builds reproduzíveis. O `vendor/` que já foi commitado no
passado foi removido; o `node_modules/` da raiz (222 paths, ~17,8 MB) ainda
precisa sair do índice, **sem reescrever o histórico**:

```bash
git rm -r --cached node_modules
git commit -m "chore(deps): remover node_modules da raiz (ADR 0081)"
```

> O pacote F11-01 **não executa** comandos Git: aplicação e commit ficam a cargo
> do responsável, mediante autorização explícita.

### 6.2 Idioma padrão do config

`system/config/default.php` já usa `en-us` (ADR 0001 aplicada). O manifesto
declara `config.platform.php = 8.2` (ADR 0004), alinhado ao PHP do CI.

---

## 7. Autoload: Composer × Autoloader do ReamurCMS

Dois autoloaders coexistem de forma complementar:

| Autoloader | Responsável por |
| --- | --- |
| **Composer** (`storage/vendor/autoload.php`, via `vendor.php`) | Pacotes de terceiros: `Twig\*`, `GuzzleHttp\*`, `ScssPhp\*`, `Aws\*`... |
| **ReamurCMS** (`system/engine/autoloader.php`) | Classes do próprio CMS: `Reamur\System\*`, `Reamur\Catalog\*`, `Reamur\Admin\*`, `Reamur\Extension\*` |

O autoloader nativo resolve o namespace pela pasta (`Reamur\Catalog\Controller\Cms\Portfolio` → `catalog/controller/cms/portfolio.php`), o que casa com a convenção OpenCart.

**Opcional/futuro:** delegar também as classes `Reamur\` ao PSR-4 do Composer, adicionando ao manifesto:

```json
"autoload": {
    "psr-4": {
        "Reamur\\System\\": "../",
        "Reamur\\Catalog\\": "../../catalog/",
        "Reamur\\Admin\\": "../../admin/"
    }
}
```

> Só vale a pena quando os arquivos seguirem 100% a regra PSR-4 (uma classe por arquivo, nome = arquivo). Hoje controllers legados com estilo `ControllerWebhookSaleOrder` no mesmo arquivo exigem o autoloader nativo — migrar **depois** da Fase 1, sem pressa.

---

## 8. Boas práticas

1. **Commitar `composer.lock`**; nunca commitar `vendor/`.
2. **`composer install` (não `update`)** em CI e produção — build determinístico.
3. **Restrições `^`** para pacotes estáveis (semver), pin exato só para mitigar bug conhecido.
4. **`composer audit`** a cada release; dependências AWS/Guzzle grandes — revisar `outdated` mensalmente.
5. **`require-dev`** para ferramentas (phpDocumentor, futuro PHPUnit/PHPStan) com `--no-dev` no deploy.
6. **`scripts`** úteis a adicionar:

```json
"scripts": {
    "docs": "phpDocumentor run -d ../engine -d ../library -t ../../../docs/api",
    "test": "phpunit"
}
```

7. **PHP da plataforma travado** para evitar resolução errada em máquinas diferentes:

```json
"config": {
    "vendor-dir": "vendor/",
    "platform": {
        "php": "8.2"
    }
}
```

---

## 9. Cheat sheet

| Quero... | Comando |
| --- | --- |
| Instalar dependências (clone novo) | `composer install --working-dir=dev/system/storage` |
| Atualizar tudo (dev) | `composer update --working-dir=dev/system/storage` |
| Adicionar pacote | `composer require vendor/pacote` |
| Validar manifesto | `composer validate --strict` |
| Otimizar p/ produção | `composer dump-autoload -o --classmap-authoritative` |
| Checar vulnerabilidades | `composer audit` |
| Ver árvore de dependências | `composer depends twig/twig` / `composer show -t` |

---

## Veja também

- [Documentação oficial do Composer](https://getcomposer.org/doc/)
- [Guia PHP 8+](Guia-PHP8.md) — extensões e OPcache
- [Guia Twig](Guia-Twig.md) — principal dependência do projeto
- [Análise §10](Analise-DavidAlmeida.md#10-riscos-e-mitigações) — risco do vendor versionado
