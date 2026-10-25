# BDREditor — bloco editorial de aviso (callout)

Incremento cumulativo sobre **BDREditor-Unsaved-Changes-Warning**. Acrescenta o bloco nativo `core/callout` ao formato de documento v2.

## Contrato do bloco

- Forma: `{ id, type: "core/callout", attributes: { tone }, children: [], content: InlineRun[] }`.
- `tone` é obrigatório e limitado a `info`, `success`, `warning` ou `danger`. Nenhuma classe, estilo ou HTML vindo do documento é interpolado.
- O conteúdo usa o modelo de rich text v2 já existente, com os mesmos limites e allowlist de marcações/links. O callout é um bloco folha e não aceita filhos.
- O tipo não é aceito em documento v1. O formato raiz permanece v2; não há mudança de versão, migração ou persistência.

## Editor e acessibilidade

- O inserter oferece o tipo em conteúdo/layout compatíveis, com conteúdo inicial localizado.
- O inspetor permite escolher o tom por um `select` nativo, com quatro rótulos localizados.
- Prévia administrativa estilizada acompanha o tom escolhido e mantém controles editáveis.
- A renderização pública emite `<aside role="note">` com uma das quatro classes de tom fixas. O texto passa pelo renderer allowlisted de runs; estilos usam cores legíveis e borda inline-start.

## Segurança e escopo

A validação cliente e servidor allowlista o tipo, exige o enum do tom e recusa atributos desconhecidos, filhos e versão legada. Sem HTML livre, scripts, CSS controlado por documento, alteração da raiz JSON, banco, migration, staging, deploy ou escrita GitHub.

## Arquivos

- `dev/admin/view/js/blog-block-editor.js` — inserter, schema cliente, conteúdo inicial e controle de tom.
- `dev/system/library/blog/block_document.php` — allowlist e validação server-side.
- `dev/system/library/blog/block_renderer.php` — saída semântica segura.
- `dev/catalog/view/css/blog-blocks.css` e `dev/admin/view/css/blog-block-editor.css` — apresentação pública e prévia.
- Controller e idiomas PT-BR/EN-US/ES-EU — labels e tom.
- `tests/fixtures/core_callout.json`, `tests/check_callout.js` e `tools/e2e/e2e_bdre_callout_static.cjs` — fixture e verificações.

## Verificação

```sh
node --check dev/admin/view/js/blog-block-editor.js
node tests/check_callout.js
node tools/e2e/e2e_bdre_callout_static.cjs
```

Resultado local: checker funcional JS **14 PASS**, checker estático **34 PASS** e suites cumulativas executadas. Não foi possível executar `php -l` (PHP não está instalado); os testes estáticos não substituem runtime PHP, navegador real ou leitor de tela.
