# F9-91 — Paleta de comandos

## Objetivo

Facilitar o acesso às ferramentas existentes do BDREditor sem ter de percorrer cada aba da ribbon.

## Funcionalidade

- Busca local acionada pelo botão **Comandos** na ribbon ou por `Ctrl/Cmd + Shift + P`.
- Localiza abas, ferramentas do workspace, ações seguras e tipos de bloco permitidos no ponto selecionado.
- Compatível com português, espanhol e inglês, seguindo o idioma da interface.
- Atalhos do teclado: setas para navegar, `Enter` para executar, `Esc` para fechar e devolver foco; `Tab` permanece no diálogo.
- O seletor usa combobox/listbox acessíveis e mantém o foco com `aria-activedescendant`.
- A paleta chama os fluxos e ações existentes; sugestões de inserção são calculadas com `insertTypes` e ficam indisponíveis em documento bloqueado. Nenhum bloco é substituído automaticamente.
- Sem requisições externas, HTML arbitrário ou JavaScript arbitrário. A independência Pages/Blog permanece inalterada.
- Acessível em tela cheia e no editor incorporado; o atalho também abre a paleta quando o workspace está em uma das telas do editor.
- Chaves de cache de Pages e Blog atualizadas para `threejs-37-command-palette`.

## Validação disponível

- `node --check dev/admin/view/js/blog-block-editor.js`
- `node tools/f9-91-command-palette-smoke.js`
- Smokes F9-61, F9-66, F9-76–F9-90.
- `python3 tools/f9-57-static-battery.py`
- Parsing de CSS com `tinycss2`, se disponível.

PHP CLI e navegador real não estão disponíveis neste ambiente; não se afirma validação runtime PHP/browser.
