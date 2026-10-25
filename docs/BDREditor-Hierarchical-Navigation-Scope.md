# Escopo — navegação hierárquica do BDREditor

## Objetivo

Prosseguir do backlog funcional de organização/navigation após copiar/colar local: tornar visível o caminho do bloco selecionado e facilitar voltar a ancestrais sem perder referência/foco.

## Dentro

- Helper de leitura para caminho hierárquico raiz→nó.
- Breadcrumbs no inspetor; ancestrais navegáveis por botões e nó atual sem ação.
- Ao selecionar outline/breadcrumb, mover foco e anunciar destino.
- Rótulos localizados e custom labels de grupo; tabela com strings PT-BR/EN-US/ES-EU.
- Fixture, checker estático, documentação e pacote cumulativo.

## Fora

- Reordenação entre containers/pais, seleção múltipla, novos atalhos, alteração do modelo de navegação do editor ou do JSON.
- Clipboard/paste entre documentos, persistência, endpoints, schema, migrations, banco, drafts, colaboração ou Site Editor.
- PHP/runtime, browser real, staging, deploy ou produção.

## Guardrails

- Só leitura da árvore; a navegação não cria histórico de conteúdo nem persiste mudança.
- Os rótulos do conteúdo são renderizados como texto, sem HTML.
- Nenhuma numeração oficial de tarefa foi inventada.
