# F9-44 — Funções de blocos e faixa de ferramentas (incremento 1)

## Escopo desta entrega

Este é o primeiro incremento de completude solicitado para os blocos e para a faixa de ferramentas; não declara concluída toda a auditoria funcional do BDREditor.

### Controles estruturais de Colunas

- O inspetor de `core/columns` agora permite adicionar a terceira coluna ou remover a última, mantendo o intervalo suportado de 2–3 colunas.
- Coluna nova recebe um parágrafo inicial editável; a remoção é registrada no histórico e pode ser desfeita.
- O estado `count` acompanha a quantidade real de filhos, e o documento continua sujeito à validação BDRE v2 existente. Não houve mudança no formato do documento nem no renderer.
- Controles e mensagens estão traduzidos para PT-BR, EN-US e ES-EU.

### Estado dos comandos da faixa

- Os comandos Desfazer e Refazer na guia Início agora refletem o estado dos respectivos históricos e ficam desativados quando não há ação disponível, assim como os demais controles de histórico.

## Inventário funcional observado

A interface existente oferece três guias de faixa:

- **Início:** exportar, inserir, colar, desfazer/refazer, histórico, localizar/substituir, marcas inline, link e alinhamento de parágrafo.
- **Inserir:** inserir bloco, importar documento e abrir os painéis Elementos/Estrutura.
- **Exibir:** painéis, prévias desktop/tablet/celular, modo de prévia e tela cheia.

As barras dos blocos incluem seleção, movimentação, duplicação, cópia, transformação quando compatível, salvar como padrão conforme permissão, remoção e inserção após o bloco. O inspetor contém campos contextuais de conteúdo e personalização.

Tipos de bloco tratados no editor incluem parágrafo, título, lista/item, imagem, citação, vídeo incorporado/local, thumbnail Three.js, botão, aviso, detalhes, espaçador, código, separador, grupo, colunas, galeria, capa, tabela/células, arquivo e áudio. Os fluxos de salvamento continuam integrados aos formulários existentes de Pages e Blog; esta entrega não altera salvamento automático nem executa `custom_js`.

## Verificações e limites

- `node --check` do editor passou.
- Testes isolados do validador aceitaram documentos com 2 e 3 colunas e rejeitaram quantidade não suportada e divergência entre `count` e filhos.
- Assertions estáticas confirmaram o vínculo dos quatro novos textos nos dois controladores e nas seis traduções.
- CLI PHP indisponível: controladores não foram lintados. Sem staging disponível ainda, não houve teste visual, navegador, execução PHP ou E2E.

## Continuação necessária

O pedido de “trazer por completo” envolve uma auditoria funcional por tipo de bloco e por comando da faixa. Este marco fecha dois pontos objetivos encontrados na inspeção inicial; a validação final de todas as combinações e a eventual ampliação da faixa devem continuar em incrementos com critérios verificáveis, antes da declaração de completude.
