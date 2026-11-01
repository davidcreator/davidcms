# F9-93 — Duplicar seleção como Grupo

## Objetivo

Reutilizar uma sequência de blocos relacionados sem refazer sua composição e sem alterar os originais.

## Fluxo

1. Selecione blocos adjacentes no mesmo contêiner usando os controles introduzidos no F9-92.
2. Escolha **Duplicar como Grupo** e confira a prévia; um rótulo opcional pode ser adicionado ao navegador da estrutura.
3. Confirme para inserir uma cópia dos blocos selecionados, agrupada imediatamente após os originais.

## Garantias

- Blocos e IDs originais permanecem inalterados.
- Cada bloco duplicado e cada descendente recebem um ID novo e único; conteúdo, atributos e ordem são copiados.
- A transformação é aplicada apenas se o documento resultante passar na validação BDRE e o contêiner permitir Grupo.
- Prévia, cancelamento, colisões de ID, seleção não contígua e falhas de validação não alteram o documento.
- A confirmação gera uma única ação de histórico, desfeita com **Desfazer**.
- Reutiliza a seleção do F9-92 e mantém Pages/Blog independentes; sem HTML ou JavaScript arbitrário.
- Chaves de cache de Pages e Blog atualizadas para `threejs-39-duplicate-selection`.

## Validação disponível

- `node --check dev/admin/view/js/blog-block-editor.js`
- `node tools/f9-93-duplicate-selection-smoke.js`
- `node tools/f9-92-group-selection-smoke.js`
- Smokes F9-61, F9-66 e F9-76–F9-91.
- `python3 tools/f9-57-static-battery.py`
- Parsing de CSS com `tinycss2`, se disponível.

PHP CLI e navegador real não estão disponíveis neste ambiente; não se afirma validação runtime PHP/browser.
