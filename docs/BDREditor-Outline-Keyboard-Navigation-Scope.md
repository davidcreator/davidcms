# Escopo — navegação da outline com teclado

## Objetivo

Completar a navegação hierárquica do editor com alternativa por teclado para explorar a outline, sem converter setas em ações de reordenação.

## Dentro

- Anterior/próximo, pai/primeiro filho, início/fim no outline.
- Escopo estrito ao foco em item da outline; manter foco nela, atualizar seleção e anunciar mudança.
- Hint, strings PT-BR/EN-US/ES-EU, fixture/checker estáticos e documentação cumulativa.

## Fora

- Reordenação, indentação/outdentação, drag-and-drop, novos atalhos no canvas ou captura de setas em conteúdo/campos/breadcrumbs.
- Alteração de hierarquia, schema, persistência, servidor, undo, endpoints, banco/migrations, staging ou deploy.
- Redefinir ARIA como `role=tree` ou alterar o formato da árvore outline.

## Guardrails

- Navegação só leitura; as teclas não alteram conteúdo nem histórico.
- Em limites, foco e seleção permanecem estáveis.
- Nenhuma numeração oficial foi inventada.
