# F9-90 — Ícones de expandir e voltar da tela cheia

## Alteração

- O controle para **expandir a tela** usa quatro setas orientadas para fora, como na segunda referência enviada.
- O controle **Back to form / voltar ao formulário** usa quatro setas orientadas para dentro, como na última referência.
- Os dois controles recebem traço SVG um pouco mais forte para manter as pontas e hastes nítidas no tamanho reduzido; os demais ícones não são afetados.
- A ribbon troca o glifo conforme o estado fullscreen. O botão inferior de retorno também usa o ícone de recolher.
- Chaves de cache de Pages e Blog atualizadas para `threejs-36-fullscreen-icons`.

## Validação

- `node --check dev/admin/view/js/blog-block-editor.js`
- `node tools/f9-90-fullscreen-icons-smoke.js`
- `python3 tools/f9-57-static-battery.py`
- Smokes anteriores F9-61, F9-66 e F9-76–F9-89.
- Parsing de CSS com `tinycss2`, se disponível.

PHP CLI e navegador real não estão disponíveis neste ambiente; não se afirma validação runtime PHP/browser. Nenhuma escrita no GitHub ou deploy foi feita.
