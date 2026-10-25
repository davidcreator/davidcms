# Navegação hierárquica — contrato e uso

## Helper de caminho

`findNodeChain(nodes, targetId)` retorna nós ancestrais ordenados da raiz até o nó com o ID pedido. Se a entrada não é lista ou alvo não existe, retorna `[]`. O helper não modifica a árvore.

## Breadcrumbs no inspetor

- Rótulo do `nav`: `text_block_navigation_path`, localizado.
- Conteúdo: lista ordenada com raiz/ancestrais em botões BDREditor (`data-action="outline-select"`) e o nó atual em texto com `aria-current="location"`.
- Grupos usam `outline_label` quando preenchido; `core/table-row` e `core/table-cell` usam rótulos localizados; demais tipos usam rótulos do editor.
- Botões de ancestral acionam seleção, movem foco ao editor do bloco quando disponível (ou ao card de bloco) e anunciam a navegação.
- A árvore outline usa o mesmo fluxo de foco para sua ação de seleção.

## Segurança e acessibilidade

A UI é construída por `createElement`/`textContent`, sem `innerHTML`, URL, script ou mutação de conteúdo. O documento permanece idêntico durante navegação; não cria entrada de undo. Foco visível e `aria-live` reutilizam componentes existentes.

## Escopo/limites

Não introduz keyboard traversal novo, não altera Alt+↑/↓ existente, não move blocos entre pais e não transforma a árvore. Breadcrumbs fornecem um caminho de retorno aos ancestrais; seleção de descendentes segue a árvore outline/conteúdo do editor. Alterações de conteúdo/persistência permanecem separadas.

## Teste

Fixture sintética group → group → paragraph valida a ordem de caminho e o caso de nó inexistente no validador JS isolado em VM. Checker estático verifica marcação/labels/foco/idiomas e CSS. Não foi testado em browser, PHP, aplicação ou leitor de tela real.
