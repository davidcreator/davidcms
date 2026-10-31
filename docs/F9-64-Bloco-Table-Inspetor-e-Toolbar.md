# F9-64 — Bloco Table: operações estruturais e formato de célula

## Entrega

- O inspetor de `core/table` exibe uma grade editável célula a célula. O conteúdo usa os mesmos IDs e o mesmo fluxo de sincronização do canvas, preservando as formatações inline existentes.
- Permite adicionar e remover linhas e colunas, respeitando os limites existentes de 20 linhas e 12 colunas. A remoção de linha/coluna final ajusta spans que atravessam a borda para manter a grade válida.
- Permite selecionar células e mesclar apenas seleções retangulares completas. O conteúdo das células é reunido na célula âncora; a operação inversa desmescla e recria células vazias editáveis.
- Permite definir as células selecionadas individualmente como cabeçalho ou conteúdo normal. Em células sem a nova configuração explícita, o comportamento anterior baseado em `table.header` e na primeira linha continua valendo.
- O schema JS e a validação PHP aceitam `header`, `rowspan` e `colspan` opcionais em `core/table-cell`, verificam os limites, sobreposição e cobertura completa da grade; o limite agregado existente de 262144 bytes de texto da tabela permanece.
- O renderer de prévia e o renderer PHP emitem `th`/`td` por célula e `rowspan`/`colspan`. Tabelas legadas sem atributos individuais mantêm o mesmo fallback visual.
- Toolbar e ações mantêm rótulos visíveis abaixo dos ícones, e ferramentas icon-only continuam com descrição no hover. Rótulos e instruções de seleção/mesclagem estão em português, inglês e espanhol.

## Verificações executadas

- `node --check dev/admin/view/js/blog-block-editor.js` passou.
- Probe Node da validação de schema aceitou uma grade válida com `rowspan` e rejeitou uma grade incompleta.
- `python3 tools/f9-57-static-battery.py`: **157 verificações estáticas aprovadas**.
- `node tools/f9-61-preview-url-smoke.js`: **9 verificações de URL aprovadas**.
- Parser CSS `tinycss2` e validação estrutural do ZIP executados na preparação da entrega.
- Não houve teste de navegador nem teste de runtime PHP (indisponíveis nesta execução). A bateria é estática e não substitui a validação interativa/visual na aplicação.
- Nenhum controller, banco, migração, rota, GitHub ou deploy foi alterado.

## Arquivos completos incluídos no pacote

- `dev/admin/view/js/blog-block-editor.js`
- `dev/admin/view/css/blog-block-editor.css`
- `dev/system/library/blog/block_document.php`
- `dev/system/library/blog/block_renderer.php`
- `tools/f9-57-static-battery.py`
- `tools/f9-61-preview-url-smoke.js`
- Esta documentação e referências F9-52 a F9-63.
