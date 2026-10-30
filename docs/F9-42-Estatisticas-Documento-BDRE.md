# F9-42 — Estatísticas do documento BDRE

## Entregue

Adicionada uma faixa de estatísticas no editor que mostra a contagem de palavras e caracteres do texto estruturado BDREditor. Os números atualizam durante a digitação e depois de alterações que re-renderizam o editor. A contagem agrega o texto de runs formatadas, blocos de código pré-formatado, blocos aninhados e células de tabela; exclui atributos/metadados e HTML legado fora do documento BDRE.

- **Palavras:** segmentação linguística com `Intl.Segmenter` quando disponível; fallback por sequências de letras/números/marcas Unicode.
- **Caracteres:** clusters de grafemas (caracteres percebidos pelo usuário), incluindo espaços, calculados por bloco textual para evitar separadores artificiais entre blocos. Em ambientes sem `Intl.Segmenter`, o fallback conta pontos de código.
- Rótulos PT-BR, EN-US e ES-EU carregados independentemente pelos controladores de Pages e Blog.
- Os totais são informativos; não alteram o documento, o fluxo de salvar, nem o histórico.

## Verificações

`node --check` nos JavaScripts do editor e testes unitários isolados de estatísticas com acentos, conteúdo em runs e células aninhadas, e documento vazio. Verificações estáticas de locale/controller e integridade do ZIP. Não foram executados testes de navegador, leitor de tela, PHP runtime, banco, staging ou E2E; PHP CLI não está disponível.
