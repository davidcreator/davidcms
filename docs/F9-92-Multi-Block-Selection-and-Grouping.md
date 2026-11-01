# F9-92 — Seleção múltipla e agrupamento de blocos

## Objetivo

Permitir organizar blocos vizinhos em um Grupo nativo sem reconstruir ou substituir seu conteúdo.

## Fluxo

1. Use o controle acessível **Selecionar para agrupar** na barra de ferramentas de cada bloco.
2. Selecione pelo menos dois blocos adjacentes no mesmo contêiner. A seleção não atravessa limites entre pais; contêineres que não permitem um bloco Grupo recusam a seleção.
3. Escolha **Agrupar seleção** para revisar os blocos em uma prévia. Opcionalmente, informe um rótulo no navegador da estrutura.
4. Confirme explicitamente para inserir um bloco `core/group` que contém os blocos selecionados.

A ordem, o conteúdo e os IDs existentes dos blocos são preservados. O Grupo recebe um novo ID. A transformação só é aplicada se a saída passar pela validação BDRE e pode ser desfeita com uma única ação. Seleção, prévia ou cancelamento não mudam o documento.

## Implementação e preservação

- Controles de seleção por bloco têm nomes acessíveis e estado `aria-pressed`; os blocos selecionados recebem destaque visual.
- A confirmação é um diálogo modal com foco confinado, Escape para cancelar e prévia do conteúdo selecionado.
- A função pura `groupSiblingBlocks` clona o documento, exige irmãos contíguos no mesmo pai, aplica limite UTF-8 de 160 bytes ao rótulo e valida o resultado; falhas não alteram a entrada.
- O agrupamento é bloqueado em documentos BDRE inválidos ou em contêineres cuja regra não permite inserir Grupo.
- Pages e Blog continuam independentes; não há rede, HTML ou JavaScript arbitrário.
- Chaves de cache de Pages e Blog atualizadas para `threejs-38-group-selection`.

## Validação disponível

- `node --check dev/admin/view/js/blog-block-editor.js`
- `node tools/f9-92-group-selection-smoke.js`
- Smokes F9-61, F9-66 e F9-76–F9-91.
- `python3 tools/f9-57-static-battery.py`
- Parsing de CSS com `tinycss2`, se disponível.

PHP CLI e navegador real não estão disponíveis neste ambiente; não se afirma validação runtime PHP/browser.
