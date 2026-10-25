# BDREditor — detalhes/accordion acessível

Incremento cumulativo sobre **BDREditor-Editorial-Callout**, para o editor exclusivo do ReamurCMS.

## Funcionalidade

- Novo bloco v2 `core/details`: título resumido e conteúdo inline em um `<details>` nativo.
- Summary obrigatório, simples, escapado, limite 512 bytes; conteúdo por runs com a allowlist existente. Sem filhos, `open` persistido ou atributos adicionais.
- Render público começa recolhido e pode ser aberto/fechado pelo teclado usando o comportamento nativo. Prévia do editor começa aberta para edição; o inspetor edita o título.
- Texto de interface e conteúdo inicial localizados em PT-BR/EN-US/ES-EU.

Sem alteração de versão raiz, migration, persistência, API, HTML livre, scripts ou CSS controlado por documento.

## Verificações

```sh
node --check dev/admin/view/js/blog-block-editor.js
node tests/check_details.js
node tools/e2e/e2e_bdre_details_static.cjs
```

Checker funcional: **12 PASS**; checker estático novo: **27 PASS**. Todos os checkers cumulativos e fixtures JSON também foram verificados. PHP lint/runtime e navegador real não foram executados no ambiente.

- Especificação: `docs/BDREditor-Accessible-Details.md`
- Fixture: `tests/fixtures/core_details.json`
- Checker funcional: `tests/check_details.js`
- Checker estático: `tools/e2e/e2e_bdre_details_static.cjs`
- Manifesto: `SHA256SUMS`
