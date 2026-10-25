# BDREditor — código pré-formatado

Incremento cumulativo sobre **BDREditor-Vertical-Spacer**. Acrescenta o bloco v2 `core/code`, limitado a texto simples UTF-8 (256 KiB), sem atributos nem filhos.

A edição usa uma textarea monoespaçada com suporte a tabs e quebras de linha. A renderização pública escapa o texto e usa `<pre><code>`; o conteúdo não é HTML nem executável. O tipo é v2-only, sem migration ou mudança na raiz JSON. Sem syntax highlighting ou dependências externas.

## Verificações

```sh
node --check dev/admin/view/js/blog-block-editor.js
node tests/check_preformatted_code.js
node tools/e2e/e2e_bdre_preformatted_code_static.cjs
```

Checker funcional: **11 PASS**; checker estático novo: **29 PASS**. Checkers cumulativos e fixtures JSON passaram. PHP lint/runtime e browser real não foram executados neste ambiente.

- Contrato: `docs/BDREditor-Preformatted-Code.md`
- Fixture: `tests/fixtures/core_code.json`
- Checker funcional: `tests/check_preformatted_code.js`
- Checker estático: `tools/e2e/e2e_bdre_preformatted_code_static.cjs`
- Manifesto: `SHA256SUMS`
