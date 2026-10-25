# BDREditor — Interface moderna

Incremento visual de BDREditor, baseado nos arquivos pontuais consultados na branch `main` do ReamurCMS em `598d353aabdd0cf72b3978c8257d9cea891da2b1`.

## Entrega

Substitua os arquivos no projeto pelos de `dev/`, mantendo os caminhos:

- `dev/admin/view/css/blog-block-editor.css`
- `dev/admin/view/js/blog-block-editor.js`

O incremento atualiza a linguagem visual e adiciona ícones SVG inline autorais, sem dependência externa, CDN ou incorporação de código/assets do Gutenberg. Mantém o modelo de documento, validação, persistência e comportamento funcional existentes.

## Mudanças visuais

- Cabeçalho de identidade BDREditor com subtítulo localizado pelo pacote de strings existente.
- Barra de comandos agrupada, botões primários/terciários e seletores de viewport com ícones distintos.
- Ícones SVG inline para ações, tipos de bloco, inserter, estrutura, configurações, formato inline e controles de tabela.
- Canvas em estilo de folha, contornos e estados de foco/seleção mais claros.
- Inserter em cartões pesquisáveis, painel de estrutura e inspetor com superfícies e hierarquia visual consistentes.
- Responsividade, redução de movimento, modo de alto contraste/forced-colors e foco visível.
- Labels acessíveis mantidos; SVGs são decorativos e `aria-hidden`.

## Preview

`preview/index.html` é uma demonstração isolada. Sirva o diretório do pacote com um servidor estático (por exemplo, `python3 -m http.server 8000`) e abra `/preview/`. Ela usa apenas arquivos locais deste pacote.

## Testes e limites

- `tests/check_modern_ui.js`: verificações estáticas de presença de ícones, acessibilidade, responsividade e redução de movimento.
- Os checkers estáticos existentes do editor foram executados contra o JavaScript visual atualizado: resultados registrados em `docs/BDREditor-Modern-Interface.md`.
- Não foi executado PHP, banco, staging, produção, GitHub write ou deploy. Não há alteração funcional nem migration.
