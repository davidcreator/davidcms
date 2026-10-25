# BDREditor — espaçador vertical

Incremento cumulativo sobre **BDREditor-Accessible-Details**. Acrescenta `core/spacer` como bloco decorativo simples, sem estilo livre.

## Contrato

- Forma: `{ id, type: "core/spacer", attributes: { height }, children: [], content: "" }`.
- `height` é inteiro obrigatório em uma allowlist fechada: `16`, `32`, `48`, `64` ou `96` pixels.
- Não aceita conteúdo, filhos, atributos extras nem documento v1.
- O formato raiz continua v2; nenhum inline style é persistido. O renderer deriva classes CSS fixas a partir do enum validado.

## Editor e saída

- O inserter inclui Espaçador na categoria Layout. Inspetor oferece somente as cinco alturas localizadas.
- Prévia administrativa representa a altura com CSS fixo e borda tracejada.
- A saída pública é um elemento vazio `aria-hidden="true"`, pois o espaçamento é decorativo. A altura é aplicada por classes CSS predefinidas; nenhum atributo do documento vira CSS arbitrário.

## Testes

Fixture e checker funcional exercitam todas as alturas, tipos inválidos, tipo/string, atributo ausente/excedente, filhos, conteúdo não vazio e documento legado. O checker estático verifica caminho cliente/servidor, renderer, CSS, controller e idiomas.

```sh
node --check dev/admin/view/js/blog-block-editor.js
node tests/check_spacer.js
node tools/e2e/e2e_bdre_spacer_static.cjs
```

Sem migration/persistência, PHP runtime, browser, banco, staging, deploy ou escrita GitHub. O PHP lint não está disponível no ambiente.
