# F9-40 — Normalização Unicode na busca

## Escopo entregue

A busca Localizar/Substituir agora normaliza o texto e o termo de pesquisa para NFC antes de comparar. Assim, sequências canonicamente equivalentes (por exemplo, `é` pré-composto e `e` seguido de acento agudo combinante) são localizadas com sensibilidade a caixa e opção de palavras inteiras, tanto dentro de uma run quanto entre runs/blocos de formatação inline.

A normalização mantém um mapa de offsets entre o texto NFC pesquisado e os limites originais. A seleção/substituição continua operando sobre os offsets originais, preservando a run de início/fim, os links, os estilos e a pilha de undo/redo. A busca não casa apenas parte de um cluster de grafema, evitando que a substituição remova acidentalmente um caractere composto completo.

## Verificações

`node --check` e quatro casos unitários de normalização/offset: origem decomposta com termo composto; origem composta com termo decomposto; cluster acentuado dividido entre runs com estilos; e rejeição de match parcial do cluster. Não foram executados navegador, PHP runtime, DB, staging ou E2E. PHP CLI continua indisponível.
