# Guia Composer — ReamurCMS

> **Objetivo:** documentar como o Composer está configurado no ReamurCMS, os comandos do dia a dia e a integração com o CI — incluindo **duas correções pendentes** identificadas no repositório (workflow e `.gitignore`).

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
| `guzzlehttp/promises`, `psr7`, `uri-template` | `^1.0` | Utilitários do ecossistema Guzzle (PSR-7, async) |
| `aws/aws-sdk-php` | `^3.142.1` | Integrações AWS (S3 para mídia, SES para e-mail etc.) |
| `mustache/mustache` | `^3.0` | Adaptador de template Mustache |
| `latte/latte` | `^3.0` | Adaptador de template Latte |
| `phpdocumentor/phpdocumentor` | `^3.4` | Geração da documentação de API — ver [Guia PHP 8 §8](Guia-PHP8.md#8-documentação-de-api-com-phpdocumentor) |

> 💡 Mover `phpdocumentor/phpdocumentor` para `require-dev` reduz o vendor de produção — avaliar na Fase 1.

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

Workflow atual (`.github/workflows/php.yml`): valida o `composer.json`, cacheia o vendor e instala dependências a cada push/PR para `main`.

### ⚠️ Problema: workflow falhando

O primeiro run do workflow terminou em **falha**, porque `composer validate` e `composer install` rodam na **raiz do repositório**, onde **não existe** `composer.json` — ele está em `dev/system/storage/`.

### ✅ Correção (workflow ajustado)

```yaml
name: PHP Composer

on:
  push:
    branches: [ "main" ]
  pull_request:
    branches: [ "main" ]

permissions:
  contents: read

defaults:
  run:
    working-directory: dev/system/storage   # 👈 onde vive o composer.json

jobs:
  build:
    runs-on: ubuntu-latest

    steps:
    - uses: actions/checkout@v4

    - name: Setup PHP
      uses: shivammathur/setup-php@v2
      with:
        php-version: '8.2'
        extensions: mbstring, curl, gd, zip, intl

    - name: Validate composer.json and composer.lock
      run: composer validate --strict

    - name: Cache Composer packages
      id: composer-cache
      uses: actions/cache@v4
      with:
        path: dev/system/storage/vendor     # 👈 caminho correto do vendor
        key: ${{ runner.os }}-php-${{ hashFiles('**/composer.lock') }}
        restore-keys: |
          ${{ runner.os }}-php-

    - name: Install dependencies
      run: composer install --prefer-dist --no-progress --no-interaction

    # Futuro (quando houver testes):
    # - name: Run test suite
    #   run: composer run-script test
```

> Aplicar este ajuste na branch `feature/system` e abrir PR para `main`.

---

## 6. Pontos de atenção do repositório

### 6.1 `.gitignore` não cobre o vendor real

Situação atual:

```gitignore
# .gitignore (raiz) — ATUAL
/planejamento/
/system/storage/vendor      # ❌ só casaria com <raiz>/system/storage/vendor
```

O vendor real está em **`dev/`** `system/storage/vendor` — por isso o Twig inteiro (e demais pacotes) acabou **commitado** ao repositório (~11 mil arquivos).

Correção recomendada:

```gitignore
# .gitignore — SUGERIDO
/planejamento/
# vendor do Composer onde quer que ele exista
**/system/storage/vendor/
# caches e logs locais
dev/system/storage/cache/*
dev/system/storage/logs/*
dev/system/storage/session/*
dev/system/storage/temp/*
# artefatos de documentação gerada
docs/api/
```

E para limpar o histórico futuro (sem reescrever passado):

```bash
git rm -r --cached dev/system/storage/vendor
git commit -m "build: remover vendor do versionamento (gerenciado pelo Composer)"
```

> Manter `composer.lock` **commitado** — é ele que garante builds reproduzíveis.

### 6.2 Idioma padrão do config

`system/config/default.php` referencia `en-gb`, mas as pastas de idiomas criadas são `en-us`, `es-eu`, `pt-br`. Não é Composer, mas vale registrar aqui: padronizar para `pt-br` na Fase 1 (ver [Análise §8](Analise-DavidAlmeida.md#8-plano-de-migração)).

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
