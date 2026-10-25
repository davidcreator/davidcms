# BDREditor — reordenação hierárquica acessível

Incremento cumulativo sobre **BDREditor-Outline-Keyboard-Navigation**, para o editor exclusivo de conteúdo do ReamurCMS. Gutenberg permanece somente referência; não há dependência, código ou assets incorporados.

## Uso

No inspetor do bloco selecionado, os botões **Indentar** e **Desindentar** movem a seleção entre níveis hierárquicos sem drag-and-drop:

- Indentar move para dentro do contêiner compatível imediatamente anterior.
- Desindentar move para depois do pai.
- Ações impossíveis ficam desativadas. Em tentativa inválida, o documento não é alterado; mensagens de estado acessíveis são anunciadas.
- IDs, atributos e conteúdo são preservados. A validação do documento v2 completo ocorre antes e depois da movimentação; commit e undo são atômicos.
- Blocos de tabela não podem ser movidos. Não se permite esvaziar contêineres que exigem filhos.

## Verificações

Com Node.js:

```sh
node --check dev/admin/view/js/blog-block-editor.js
node tests/check_hierarchical_reordering.js
node tools/e2e/e2e_bdre_hierarchical_reordering_static.cjs
```

Nesta entrega: checker funcional **23 PASS**, checker estático novo **27 PASS**; checkers cumulativos anteriores executados com sucesso: áudio 49, copy/paste 29, arquivo/download 48, atalhos 25, breadcrumbs 24 e outline por teclado 24. `php -l` não foi executado: interpretador PHP não está instalado no ambiente. Os checkers estáticos não substituem teste de browser/leitor de tela ou runtime PHP.

## Escopo e segurança

Sem alteração de schema ou persistência. Nenhuma escrita GitHub, execução de runtime, staging, banco, migration, teste destrutivo ou deploy. Pacote preparado com cópias pontuais de fontes; projeto completo não foi clonado.

- Contrato: `docs/BDREditor-Hierarchical-Reordering.md`
- Fixture: `tests/fixtures/hierarchy_move.json`
- Checker comportamental: `tests/check_hierarchical_reordering.js`
- Checker estático E2E: `tools/e2e/e2e_bdre_hierarchical_reordering_static.cjs`
- SHA-256: `SHA256SUMS`
