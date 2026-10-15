# Changelog — ReamurCMS

Todas as mudanças notáveis deste projeto serão documentadas aqui.
Formato baseado em [Keep a Changelog](https://keepachangelog.com/pt-BR/1.0.0/), versionamento semântico.

## [Unreleased]

### Adicionado
- **Documentação completa** em `docs/` — análise do site atual, arquitetura MVCL, guias (CMS, PHP 8, Composer, Twig, Three.js), implementação Three.js, webhooks, estrutura de pastas e auditoria de fundação;
- **`agents-ia/`** — agentes de IA por aplicação (orquestrador, system, install, admin, catalog), regras globais (00–03), modelos de arquivo canônicos e registro de decisões (ADR);
- **Contratos de fundação**: `docs/Contrato-Bootstrap.md` (entry points) e `docs/Dicionario-de-Dados.md` (schema canônico);
- **ADRs 0001–0004**: idioma padrão `en-us`; blog híbrido; correção de CI/`.gitignore`; contratos + infra de qualidade;
- Infra de qualidade: `.editorconfig`, PR template com DoD, `CONTRIBUTING.md`, `SECURITY.md`, `CHANGELOG.md`, `phpstan.neon`, pasta `dev/tests/` (ativa na Fase 1).

### Corrigido
- CI (`.github/workflows/php.yml`): `working-directory` correto (`dev/system/storage`), setup PHP 8.2, cache do vendor corrigido + jobs de `php -l` e link check (ADR 0003);
- `.gitignore`: cobre `**/system/storage/vendor/`, caches/logs/temp do storage e `config.php` gerados;
- `composer.json`: phpDocumentor movido para `require-dev`; adicionados PHPStan/PHPUnit (dev) e scripts `analyse`/`test`/`docs` (ADR 0004).

## [1.0.0] — 2026-10-12

### Adicionado
- Esqueleto estrutural MVCL do ReamurCMS (`dev/{admin,catalog,install,system}`) inspirado no OpenCart;
- Núcleo inicial: `engine`, `library`, `helper`, `config`, `framework/startup/vendor`.php;
- Sistema de webhooks para pedidos e produtos (`engine/webhook.php` + `system/webhook/`);
- `composer.json` (pacote `reamurcms/reamurcms`, GPL-3.0) com Twig 3, Guzzle, AWS SDK etc.;
- Estrutura de idiomas `en-us`, `es-eu`, `pt-br` nas três aplicações.
