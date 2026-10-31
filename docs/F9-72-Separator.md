# F9-72 — Interações do bloco Separator

## Implementação

- O bloco `core/separator` agora expõe controles no inspetor para estilo de linha (contínua, tracejada ou pontilhada), espessura (1–4 px), largura (25/50/75/100%) e alinhamento (esquerda/centro/direita).
- A barra contextual oferece ações rotuladas para reduzir/aumentar a largura, respeitando os limites.
- A prévia administrativa e o renderer público refletem as escolhas. PHP e JavaScript validam somente enums e larguras/espessuras permitidas; o estilo renderizado é derivado desses valores, sem CSS arbitrário.
- Documentos existentes que não têm os novos atributos continuam usando os padrões anteriores. Cache-bust: `threejs-19-separator-controls`.

## Verificações

- `node --check` nos scripts JavaScript e smokes de URL passaram.
- Bateria estática: **203 verificações aprovadas**.
- ZIP íntegro. PHP CLI, navegador real e staging indisponíveis; não alego validação runtime.
