# F9-88 — Pages CMS: edição rápida + editor em tela cheia

## Referência de interface

A listagem do Pages adota o padrão visual de Quick Edit do WordPress: cada linha oferece **Editar** e **Edição rápida**. O primeiro abre a edição existente, cujo BDREditor entra no modo imersivo/tela cheia; **Edição rápida** expande um formulário compacto dentro da própria lista.

## Campos rápidos

Somente propriedades existentes no modelo Page: título do idioma administrativo ativo, slug/keyword SEO (exceto páginas de sistema), status e ordenação. Campos WordPress que não existem no schema do Pages — por exemplo, senha, comentários, pai ou data programada — não foram inventados nem gravados.

O endpoint rápido é POST-only e exige ACL `modify`, CSRF, idioma ativo validado, slug válido e não duplicado, valores de status/ordem permitidos e fingerprint transacional para evitar sobrescrita concorrente. Ele parte das descrições e keywords já persistidas, altera apenas os quatro campos permitidos e mantém descrição BDRE, metadados SEO e valores de campos personalizados. A paginação e os filtros de busca/status são preservados no retorno. Páginas de sistema continuam sem edição rápida de slug e sem exclusão.

## Independência

A interface e endpoint pertencem ao Pages. O conteúdo BDRE permanece no mesmo armazenamento e a tela de criação separada do F9-87 continua disponível. O Blog não recebeu novo fluxo de Quick Edit; sua versão de asset compartilhado permanece compatível com o editor comum.

## Verificação

- `node tools/f9-88-pages-quick-edit-smoke.js`
- `node tools/f9-87-page-create-workflow-smoke.js`
- regressões F9-61, F9-66 e F9-76–F9-87;
- `python3 tools/f9-57-static-battery.py`, `node --check` e CSS parse.

PHP CLI e navegador real não estão disponíveis neste ambiente. Os smoke/static checks não simulam runtime PHP, execução de SQL ou interação visual em browser.
