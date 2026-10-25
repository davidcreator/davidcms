# BDREditor — espaçador vertical

Incremento cumulativo sobre **BDREditor-Accessible-Details**, no editor exclusivo do ReamurCMS.

O novo bloco `core/spacer` oferece presets fixos de **16, 32, 48, 64 ou 96 px**. O editor usa select localizado; cliente e servidor validam o enum. A saída é decorativa (`aria-hidden="true"`) e usa apenas classes CSS fixas — sem estilo arbitrário, conteúdo ou filhos. Compatível somente com documento v2; a raiz e a persistência não mudam.

## Verificações

```sh
node --check dev/admin/view/js/blog-block-editor.js
node tests/check_spacer.js
node tools/e2e/e2e_bdre_spacer_static.cjs
```

Checker funcional: **15 PASS**; checker estático: **36 PASS**. Suites cumulativas e fixtures JSON também são executadas antes da entrega. PHP lint/runtime não foram executados porque PHP não está instalado; não houve teste em browser real.

- Contrato: `docs/BDREditor-Vertical-Spacer.md`
- Fixture: `tests/fixtures/core_spacer.json`
- Checker: `tests/check_spacer.js`
- Checker estático: `tools/e2e/e2e_bdre_spacer_static.cjs`
- SHA-256: `SHA256SUMS`
