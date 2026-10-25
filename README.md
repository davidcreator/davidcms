# BDREditor — bloco editorial de aviso/callout

Incremento cumulativo sobre **BDREditor-Unsaved-Changes-Warning**, no editor exclusivo do ReamurCMS.

## Funcionalidade

- Novo bloco v2 `core/callout`, com conteúdo inline seguro e sem filhos.
- Quatro tons fixos: `info`, `success`, `warning` e `danger`. O inspetor tem seleção nativa e localizada; conteúdo inicial também é localizado.
- Prévia administrativa e renderer público com saída semântica `<aside role="note">`, paleta fixa de alto contraste e rich text via renderer allowlisted.
- Validação cliente e server-side falha fechada para tom inválido, atributos desconhecidos, children ou uso em documento v1.

Não altera o formato raiz v2 nem adiciona migration/persistência. Sem HTML/CSS arbitrário, GitHub write, runtime, banco, staging ou deploy.

## Verificações executadas

```sh
node --check dev/admin/view/js/blog-block-editor.js
node tests/check_callout.js
node tools/e2e/e2e_bdre_callout_static.cjs
```

Checker funcional: **14 PASS**. Checker estático E2E novo: **34 PASS**. Todos os checkers cumulativos `tools/e2e/*.cjs` e parsing das fixtures JSON também passaram. `php -l` e runtime PHP não foram executados porque PHP não está instalado; não houve browser real.

- Contrato e limites: `docs/BDREditor-Editorial-Callout.md`
- Fixture: `tests/fixtures/core_callout.json`
- Checker funcional: `tests/check_callout.js`
- Checker estático: `tools/e2e/e2e_bdre_callout_static.cjs`
- Checksums: `SHA256SUMS`
