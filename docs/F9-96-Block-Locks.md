# F9-96 — Bloquear e desbloquear blocos

## Objetivo

Permitir que o autor proteja blocos nativos BDRE contra alterações acidentais e desbloqueie-os explicitamente quando necessário.

## Uso e garantias

- Cada bloco tem uma ação acessível **Bloquear bloco / Desbloquear bloco** e um indicador visível de estado. Um bloco bloqueado em um Grupo também sinaliza a herança nos descendentes.
- O estado é persistido como o atributo booleano `locked` do próprio nó. A validação JavaScript e a validação de documento PHP permitem esse atributo globalmente e rejeitam valores que não sejam booleanos; o renderer público continua a renderizar o conteúdo normal, sem exibir metadados de edição.
- O bloqueio aplica-se ao bloco e, se for Grupo, a toda a subárvore: impede edição, remoção, movimentação, duplicação/cópia e mudanças em seus filhos. Também impede que um ancestral seja movido ou removido enquanto isso alteraria a posição/parentalidade de um descendente bloqueado.
- Alterações não relacionadas, como inserir um bloco irmão sem cruzar a posição relativa do bloco bloqueado, continuam permitidas.
- Desbloquear modifica somente o atributo `locked`, em uma única etapa de histórico. O guarda transacional rejeita alterações simultâneas no conteúdo durante o desbloqueio.
- Importação/restauração/substituição que alteraria blocos bloqueados é recusada; desfazer/refazer continua disponível para reverter ações anteriores.
- O clipboard não permite copiar conteúdo bloqueado e é invalidado quando o bloco de origem é bloqueado.
- A validação aplica-se igualmente a Pages e Blog; os fluxos de conteúdo continuam independentes. Cache de assets atualizado para `threejs-42-block-lock`.

## Validação disponível

- `node --check dev/admin/view/js/blog-block-editor.js`
- `node tools/f9-96-block-lock-smoke.js`
- Smokes F9-61, F9-66, F9-76–F9-95 e bateria `python3 tools/f9-57-static-battery.py`.
- Parsing de CSS com `tinycss2`, se disponível.
- PHP CLI e navegador real não estão disponíveis neste ambiente; não se afirma validação runtime PHP/browser.
