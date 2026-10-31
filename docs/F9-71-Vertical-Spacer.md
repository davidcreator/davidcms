# F9-71 — Interações do bloco Vertical spacer

## Implementação

- O bloco `core/spacer` tem um controle range rotulado no inspetor para escolher as alturas válidas: 16, 32, 48, 64 e 96 px. O valor atual é exibido no painel e o canvas mostra a altura em pixels.
- A barra contextual oferece ações **Diminuir altura** e **Aumentar altura**, com rótulos visíveis abaixo dos ícones. Os botões ficam desativados nos limites mínimo e máximo; a lista de valores permanece igual ao schema e ao renderer já existentes.
- O controle direto da barra, o slider e Desfazer alteram o mesmo atributo `height`. Não foram introduzidos novos valores, CSS arbitrário nem HTML/custom JS.
- Cache-bust: `threejs-18-spacer-controls`.

## Verificações

- `node --check` no editor, inicializador e cliente CMS Pages passou. Smokes de URL passaram, inclusive os 9 casos do helper.
- Bateria estática: **199 verificações aprovadas**.
- ZIP completo íntegro. PHP CLI e navegador/staging indisponíveis; não alego teste runtime.
