# F9-95 — Desagrupar bloco Grupo

## Objetivo

Adicionar a operação inversa ao agrupamento: remover um contêiner `core/group` e devolver seus filhos ao nível do pai, sem reconstruí-los.

## Fluxo

- O comando **Desagrupar Grupo** está disponível na barra de ferramentas e no inspetor de um bloco Grupo.
- A prévia lista os filhos e informa que a estrutura do Grupo será removida. Se houver rótulo opcional no navegador da estrutura, a prévia avisa que ele também será removido.
- O usuário precisa confirmar explicitamente; cancelar ou pressionar `Esc` não modifica o documento.

## Garantias

- Os filhos são inseridos exatamente na posição anterior do Grupo e mantêm ordem, conteúdo, atributos, subárvore e IDs.
- Só a estrutura wrapper e seu rótulo opcional desaparecem.
- A função pura `ungroupBlock` clona o documento e valida o resultado. Se o grupo/entrada for inválido ou a estrutura resultante não passar na validação BDRE, nada é alterado.
- O diálogo confina o foco; `Tab` fica no diálogo e `Esc` cancela sem sair do fullscreen.
- A confirmação gera uma única ação de histórico, disponível em **Desfazer**.
- Pages e Blog permanecem independentes; nenhuma chamada externa ou execução de HTML/JavaScript arbitrário.
- Chaves de cache de Pages e Blog atualizadas para `threejs-41-ungroup`.

## Validação disponível

- `node --check dev/admin/view/js/blog-block-editor.js`
- `node tools/f9-95-ungroup-smoke.js`
- Smokes F9-61, F9-66 e F9-76–F9-94.
- `python3 tools/f9-57-static-battery.py`
- Parsing de CSS com `tinycss2`, se disponível.

PHP CLI e navegador real não estão disponíveis neste ambiente; não se afirma validação runtime PHP/browser.
