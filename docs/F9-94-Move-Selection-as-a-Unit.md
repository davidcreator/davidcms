# F9-94 — Mover seleção como unidade

## Objetivo

Permitir reposicionar vários blocos adjacentes sem envolvê-los em um bloco Grupo.

## Fluxo e comportamento

- Selecione blocos vizinhos no mesmo contêiner usando o seletor existente do F9-92.
- Use **Mover para cima** ou **Mover para baixo** para mover o intervalo uma posição na lista de irmãos.
- Os controles são desativados quando a seleção está no limite naquela direção.
- A ordem relativa e os IDs dos blocos não mudam; os blocos mantêm o mesmo pai e suas estruturas internas são preservadas.
- Cada movimento produz uma única alteração de histórico, disponível em **Desfazer**.
- A função pura `moveSiblingSelection` clona o documento, verifica contiguidade/parentesco e limites, valida a saída e não muta a entrada.
- A operação não cria `core/group`, não remove conteúdo e não usa HTML/JavaScript arbitrário.
- Chaves de cache de Pages e Blog atualizadas para `threejs-40-move-selection`.

## Validação disponível

- `node --check dev/admin/view/js/blog-block-editor.js`
- `node tools/f9-94-move-selection-smoke.js`
- Smokes F9-61, F9-66 e F9-76–F9-93.
- `python3 tools/f9-57-static-battery.py`
- Parsing de CSS com `tinycss2`, se disponível.

PHP CLI e navegador real não estão disponíveis neste ambiente; não se afirma validação runtime PHP/browser.
