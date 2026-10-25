# BDREditor — detalhes expansíveis acessíveis

Incremento cumulativo sobre **BDREditor-Editorial-Callout**. Acrescenta `core/details`, um bloco-folha v2 baseado no elemento HTML nativo `<details>`.

## Contrato

- Forma: `{ id, type: "core/details", attributes: { summary }, children: [], content: InlineRun[] }`.
- `summary` é obrigatório, texto simples UTF-8 e limitado a 512 bytes. O conteúdo usa rich text v2 existente e as respectivas allowlists/limites.
- Não aceita filhos nem atributos adicionais; documentos v1 não podem conter esse bloco.
- Nenhum atributo `open` é persistido: a versão pública inicia recolhida, e a pessoa leitora abre/fecha pelo controle nativo. A prévia administrativa inicia aberta para facilitar edição.
- O formato raiz continua v2; sem migration ou alteração de persistência.

## Acessibilidade e segurança

- A saída usa `<details><summary>…</summary><div>…</div></details>`, preservando interação nativa por teclado e leitor de tela; o foco do summary tem estilo visível.
- A string `summary` é escapada como texto; o corpo passa pelo renderer allowlisted de rich-text runs. Sem scripts, HTML livre, eventos ou CSS vindo do documento.
- Campo e conteúdo inicial são localizados em PT-BR, EN-US e ES-EU.

## Arquivos

- `dev/admin/view/js/blog-block-editor.js` — inserter, validação, conteúdo editável e inspetor do título.
- `dev/system/library/blog/block_document.php` — allowlist e validação server-side.
- `dev/system/library/blog/block_renderer.php` — saída semântica com escaping.
- CSS administrativo/público — prévia e foco visível.
- `tests/fixtures/core_details.json`, `tests/check_details.js`, `tools/e2e/e2e_bdre_details_static.cjs` — fixture e checkers.

## Verificação

```sh
node --check dev/admin/view/js/blog-block-editor.js
node tests/check_details.js
node tools/e2e/e2e_bdre_details_static.cjs
```

O checker funcional JS reporta **12 PASS**, o checker estático **27 PASS**. Suites cumulativas anteriores foram executadas. PHP lint/runtime e navegador real não foram executados neste ambiente.
