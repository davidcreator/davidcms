# BDREditor — bloco de código pré-formatado

Incremento cumulativo sobre **BDREditor-Vertical-Spacer**. Acrescenta `core/code` como bloco de texto simples, v2-only.

## Contrato

- Forma: `{ id, type: "core/code", attributes: {}, children: [], content: string }`.
- `content` é texto UTF-8 bruto, limitado a 262.144 bytes (256 KiB). Ao contrário dos outros blocos de texto rich-text, não é uma lista de runs: marcações inline, links, atributos e filhos não são permitidos.
- Documento v1 não aceita `core/code`; a raiz JSON permanece v2 e não há migration.

## Edição e renderização segura

- A edição usa uma `textarea` monoespaçada com label localizado, preservando tabs, espaços e quebras de linha. O estado é validado e integrado ao snapshot de undo existente sem re-render a cada tecla.
- Atalhos de formatação não são aplicados nesse campo; a edição mantém comportamento nativo de controles de formulário.
- Na página pública, o conteúdo passa por escaping HTML e é emitido dentro de `<pre><code>`. CSS preserva whitespace; o conteúdo não é executado nem interpretado como HTML.
- O limite é aplicado no cliente e servidor. Não há highlight dinâmico, scripts, linguagens/atributos livres nem dependência externa.

## Arquivos e verificações

- Editor: `dev/admin/view/js/blog-block-editor.js`
- Contrato/renderer: `dev/system/library/blog/block_document.php`, `dev/system/library/blog/block_renderer.php`
- CSS: `dev/admin/view/css/blog-block-editor.css`, `dev/catalog/view/css/blog-blocks.css`
- Fixture: `tests/fixtures/core_code.json`
- Checkers: `tests/check_preformatted_code.js`, `tools/e2e/e2e_bdre_preformatted_code_static.cjs`

```sh
node --check dev/admin/view/js/blog-block-editor.js
node tests/check_preformatted_code.js
node tools/e2e/e2e_bdre_preformatted_code_static.cjs
```

Checker funcional JS: **11 PASS**; checker estático: **29 PASS**. Suites cumulativas também executadas. PHP lint/runtime e browser real não foram executados no ambiente.
