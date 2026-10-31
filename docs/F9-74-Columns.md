# F9-74 — Interações do bloco Columns

## Implementação

- Barra contextual e inspetor apresentam ações rotuladas para adicionar/remover colunas; limites continuam sendo 2–3.
- Ao remover a última coluna, seus blocos filhos são transferidos para a coluna imediatamente anterior, em vez de serem descartados. A mudança permanece reversível por Desfazer/Refazer.
- Cada coluna tem ação **Adicionar bloco a esta coluna** na barra contextual e no inspetor. O inspetor informa posição (por exemplo, coluna 2 de 3) e quantidade de blocos.
- Colunas continuam reordenáveis pelas ações existentes de mover para cima/baixo; a aparência pode ser ajustada pelo inspetor. O layout público já se adapta a telas estreitas empilhando colunas.
- A estrutura de dados continua usando `count` validado (2 ou 3) e filhos `core/column`; não houve alteração de schema, banco, renderer público, mídia, nem de independência Pages/Blog.
- Cache-bust: `threejs-21-columns-controls`.

## Verificações

- `node --check` nos scripts JavaScript e smoke tests de URL passaram.
- Bateria estática: **210 verificações aprovadas**.
- ZIP completo e íntegro. PHP CLI e navegador real indisponíveis; não alego teste runtime.
