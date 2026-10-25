# Workbench visual BDREditor

## Estrutura em tela cheia

- Barra superior com identidade, ações de edição, histórico e tamanhos de viewport.
- Painel lateral com três áreas acessíveis: Elementos (inserter pesquisável em cartões), Estrutura (outline) e Configurações (inspetor contextual).
- Canvas à direita com largura ampla e fundo de área de trabalho distinto do conteúdo.
- Breakpoint móvel empilha painel e canvas; a navegação e a inserção continuam disponíveis.

O painel foi montado a partir do registry e dos métodos existentes do BDREditor. Os blocos não são renderizados como HTML arbitrário; schema, salvamento, renderer, prefixo v2 e fix do round-trip continuam a cargo do código existente.

## Não é Elementor

O estilo usa apenas padrões genéricos de construtores visuais: toolbar, dock lateral, cards de widgets, canvas e painéis contextuais. Não há cópia de UI proprietária, código, ícones, assets ou dependência do Elementor.

## Limites de validação

Checks E2E estáticos das funções anteriores passaram. O ambiente não possui browser automatizado nem PHP; não foi possível validar o uso interativo real nem o round-trip em uma instalação CMS. Use o preview para avaliar o fluxo e aplique no projeto apenas após revisar o diff.
