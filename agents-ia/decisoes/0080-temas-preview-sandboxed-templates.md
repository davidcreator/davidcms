# ADR 0080 — Fontes Twig do tema com prévia isolada

- **Status:** aprovada para implementação incremental
- **Data:** 2026-10-27

## Contexto
A ADR 0079 criou gerenciamento isolado de temas, mas não conectava a seleção ao renderer, nem separava alterações não publicadas do estado ativo. A edição de Twig precisa limitar destinos a templates do Catalog e oferecer prévia isolada.

## Decisão
- O Catalog aplica somente payload explicitamente publicado; rascunhos continuam separados.
- O adaptador Twig recebe um método aditivo `setThemeTemplates()` que aceita exclusivamente chaves fixas do Catalog e preserva filesystem loader como fallback; Admin/Install não chamam esse método.
- Prévia usa Twig Sandbox, dados simples, CSP `default-src 'none'` e iframe sem permissões. O Admin não permite PHP, JS, caminhos arbitrários nem `raw` na fonte controlada.
- Revisões preservam snapshots e restauração exige POST, CSRF e ACL `modify`.
- Tokens de cor aceitam apenas hexadecimal de seis dígitos; cenas seguem chaves allowlisted, sem JavaScript de cena.

## Impacto
Alteração aditiva em `system/library/template/twig.php`; Catalog é consumidor opt-in via `startup/theme`. Admin e Install continuam com carregamento e cache previamente configurados. Migrations/schema são arquivos locais; nenhuma execução SQL autorizada.
