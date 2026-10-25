# Outline por teclado — contrato e comportamento

## Helper puro

`getOutlineNavigationTarget(nodes, currentId, key)` cria uma sequência depth-first alinhada com os itens expostos pela outline (ignora `core/table-row` e `core/table-cell`) e retorna o ID de destino ou `null`:

- `ArrowUp`/`ArrowDown`: item anterior/próximo na sequência;
- `ArrowLeft`: `parentId` do item;
- `ArrowRight`: primeiro filho visível;
- `Home`/`End`: primeiro/último item;
- outras teclas, ID ausente e limites: `null`.

O helper não altera a árvore, IDs ou state do documento.

## Handler DOM

O listener só ativa em `.rb-outline-button`. Se houver destino e o botão correspondente existir, cancela a tecla, foca o novo botão, seleciona o nó e anuncia `statusOutlineChanged`. A outline permanece montada para preservar o foco. Em limites, cancela a tecla sem mudar seleção ou foco, evitando scroll da página.

## Semântica

A outline continua como navegação com lista ordenada aninhada e botões; não altera a semântica ARIA do documento nem implementa um widget `role=tree`. O hint local ajuda descoberta. Breadcrumbs e ação de clique existentes continuam navegando para ancestrais e focando o bloco; o novo uso de setas conserva o foco dentro da outline.

## Escopo e segurança

Sem `preventDefault` fora da outline, sem interferência em conteúdo editável, formulário ou campos de mídia. Setas não movem blocos. Seleção apenas muda highlight/inspector, não conteúdo nem histórico. Nenhum novo endpoint, schema, storage ou JS externo.

## Teste

Fixture v2 inclui grupos e parágrafos aninhados e verifica direcionais, pai/filho, Home/End e limites. O checker usa VM Node sem DOM e inspeção estática; não prova comportamento real do leitor de tela, foco no browser ou runtime CMS.
