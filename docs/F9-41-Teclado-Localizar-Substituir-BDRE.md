# F9-41 — Fluxo de teclado para Localizar/Substituir

## Entregue

- Em **Localizar**, Enter navega para a próxima ocorrência; Shift+Enter navega para a anterior. O campo de busca recupera o foco após localizar.
- Em **Substituir por**, Enter executa uma substituição única e retorna o foco a Localizar.
- Escape fecha o painel, volta a faixa para a aba Início e devolve o foco ao botão Localizar e substituir. Consulta, substituição e opções permanecem no estado do editor para que o painel possa ser reaberto sem reiniciar a busca.
- A dica de teclas é visível junto dos controles e traduzida em PT-BR, EN-US e ES-EU para Pages e Blog.

Navegação pelos botões, Ctrl/⌘+F, substituição undoable e salvamento explícito não foram alterados.

## Verificações e limites

Executado `node --check` nos JavaScripts afetados e verificações estáticas de tratamento de teclado, foco, acessibilidade, CSS, strings e mapeamento nos controladores. Testes reais de teclado/foco no navegador e com leitor de tela não foram executados; PHP CLI não está disponível. Também não houve runtime PHP, banco, staging ou E2E.
