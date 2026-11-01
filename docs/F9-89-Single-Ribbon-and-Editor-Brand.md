# F9-89 — Ribbon única e marca BDREditor

## Objetivo

Eliminar a navegação clássica duplicada (`File / Edit / Insert / Format / Tools / Help`) no fullscreen e manter a ribbon como única faixa de menu. Exibir BDREditor junto ao contexto da página, sem repetir o masthead nessa visualização.

## Implementação

- Removidos o renderer e a ação do menu clássico; as abas da ribbon continuam sendo renderizadas e controlando suas ferramentas.
- No editor fullscreen com contexto de página, o masthead não é duplicado: a marca BDREditor aparece no início da barra de contexto, ao lado das informações da página e dos controles existentes.
- Fora desse contexto fullscreen, o masthead existente permanece, preservando as demais telas e modos.
- Removidos estilos CSS sem uso associados ao menu clássico e adicionados estilos compactos e responsivos para a marca.
- Atualizadas as chaves de cache de CSS/JS nos controllers de Pages e Blog.

## Escopo e preservação

Alteração visual localizada no editor compartilhado. Não muda a independência Pages/Blog, não remove blocos/recursos e não altera conteúdo, persistência, ações de workspace ou publicação. Nenhuma escrita no GitHub ou deploy foi executada.

## Validação disponível

- `node --check dev/admin/view/js/blog-block-editor.js`
- `node tools/f9-89-single-ribbon-brand-smoke.js`
- `python3 tools/f9-57-static-battery.py`
- Smokes anteriores F9-61, F9-66 e F9-76 a F9-88.
- Parsing de CSS com `tinycss2`, se disponível.

PHP CLI e navegador real não estão disponíveis neste ambiente; portanto, não se afirma validação runtime PHP/browser.
