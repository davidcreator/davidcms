# BDREditor — incremento local: bloco de tabela

Este pacote implementa o primeiro bloco editorial novo do BDREditor, editor próprio do ReamurCMS. O Gutenberg é somente referência de interação; não há pacote, código ou asset do Gutenberg neste incremento.

## Implementado

- Novo `core/table` em documento v2, com modelo JSON hierárquico table → row → cell.
- Limites: 20 linhas, 12 colunas, linhas retangulares e 262144 bytes de texto total por tabela.
- Cabeçalho opcional (primeira linha semântica `thead/th scope=col`), sem spans, fórmulas ou estilos livres.
- Edição inline das células com rich-text v2; controles acessíveis para cabeçalho, adicionar/remover linha e coluna.
- Regras de dimensão mínima 1×1; inserção inicial 3×3.
- Validação server/client e renderer server-side determinístico com escaping do rich text.
- Preview responsivo com overflow horizontal controlado; strings localizadas PT-BR, EN-US e ES-EU.
- Nenhuma migration, endpoint, persistência nova ou autosave.

## Base e natureza da entrega

- `main` consultada: `ffa278226a6ffa6d11f71d95b003d3b4dac39c01`, commit que adicionou o backlog BDREditor.
- A revisão F9-26 confirmou que os 11 fontes cumulativos coincidiam com `main` no pai `d9e09d8`; esse commit posterior adicionou apenas a documentação de backlog.
- Implementação local para revisão; não enviada ao GitHub. O pacote inclui fontes pontuais, não o projeto inteiro.
- Isto é um incremento do BDREditor, sem número oficial de tarefa atribuído.

## Verificações executadas

- `node --check` em JS do editor e checker.
- Parser JSON em contrato e fixtures.
- Checker estático: `node tools/e2e/e2e_bdre_table_static.cjs`.
- O validador JavaScript foi invocado isoladamente em Node/VM, sem DOM/browser, sobre fixtures sintéticas e limites dimensionais.

**Não executados:** PHP, SQL, browser, app, E2E runtime, staging, deploy, migrations ou banco. O checker estático não certifica o PHP runtime, interação real, acessibilidade em leitor de tela ou rendering em catálogo.

## Conteúdo

- Contrato: `docs/BDREditor-Table-Contract.json`
- Detalhes e aceite: `docs/BDREditor-Table.md`
- Base/source hashes: `docs/BDREditor-Table-Base-Hashes.json`
- Fixture: `tests/fixtures/bdre_table.json`
- Checker: `tools/e2e/e2e_bdre_table_static.cjs`
