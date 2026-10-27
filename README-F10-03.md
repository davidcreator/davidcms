# Fase 10 — Tarefa 03: integração e fechamento de Temas (incremento local)

**Base:** HEAD remoto `b6fcd26b1b2f713238d0c6fbeddc47eedab71503`, commit F10-02 confirmado pelo usuário.

## Escopo implementado localmente
- `Twig::setThemeTemplates()` como API aditiva, apenas opt-in no Catalog; loader ArrayLoader para keys fixas e fallback FilesystemLoader, cache/autescape preservados.
- Edição de fontes em modo avançado apenas para templates Catalog previamente enumerados; limite por fonte e bloqueios de `<script>`, `javascript:` e handlers inline. Sem escrita de arquivos ou JavaScript Three.js livre.
- Prévia Twig com SandboxExtension, contexto sintético, iframe sandbox sem permissões e CSP `default-src 'none'`.
- Draft separado de `published_config`, publicação explícita, snapshots e restauração via POST + CSRF + ACL.
- Tokens de cor e seleção de cena allowlisted aplicados ao Catalog; seleção habilita uma cena existente (Hero/Portfolio/Tools/404) sem aceitar código.
- Nova migration local `upgrade_f10_03_theme_templates.sql`, schemas iniciais atualizados, ADR 0080 e docs.

## Limites / segurança
A lista de templates é intencionalmente curta e não inclui `common/header` (contém scripts essenciais e não é override editável). Nenhuma alteração escreve no filesystem. Three.js permanece no runtime/manifesto já aprovado. Sem testes em localhost ou banco.

**Não executar migration, SQL, push, staging ou deploy** sem autorização separada. PHP CLI não está disponível no workspace, logo não houve `php -l`.
