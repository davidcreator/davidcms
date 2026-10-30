# F9-52 — Abrir BDREditor como workspace fullscreen nos formulários

## Correção

A F9-51 aumentou a largura do editor, mas o BDREditor ainda era apresentado como um campo dentro do formulário e dependia de o usuário acionar a tela cheia. Isso não correspondia à referência mais recente, que mostra o workspace como a superfície principal de edição.

## Comportamento implementado

- Em **CMS Pages** e **Blog**, a primeira aba de idioma, quando ativa, abre o BDREditor diretamente no modo fullscreen ao montar o formulário.
- O workspace usa o layout completo já existente: menus Office, barra de ferramentas, navegação lateral, canvas e inspetor. O shell cobre o viewport; o formulário do CMS continua no DOM, preservando os valores e a submissão existentes.
- A faixa de contexto mostra o título/idioma e disponibiliza **Salvar** ligado ao submit do formulário existente. Em Blog, o contexto é montado a partir dos controles já presentes, sem controller ou API novos.
- **Voltar ao formulário**, a tecla Escape e o comando da guia Exibir fecham o overlay e devolvem o usuário aos campos externos. Eles permanecem na ordem vertical reorganizada pela F9-51.
- Abas de idioma não ativas não abrem overlays adicionais. Após sair do workspace, trocar para outra língua mantém o fluxo normal do formulário.

## Arquivos incluídos

- `dev/admin/view/js/blog-block-editor.js`
- `dev/admin/view/js/blog-block-editor-init.js`
- `dev/admin/view/template/blog/blog_form.twig`
- `dev/admin/view/template/cms/page_form.twig`
- `dev/admin/view/css/blog-block-editor.css`
- `docs/F9-52-BDREditor-Workspace-Fullscreen.md`
- `docs/F9-51-BDREditor-Area-de-Edicao-em-Largura-Total.md` (com nota de complemento)
- `docs/F9-50-Workspace-Office-Interface-Completa.md` (atualizado com as referências posteriores)

## Limites e validação

- O modo fullscreen reutiliza `setFullscreen()` e o envio existente do formulário; não altera controllers, banco, migrações, rotas ou deploy.
- CMS Pages e Blog continuam independentes; apenas compartilham o shell do editor e a rotina já existente de bind do formulário.
- `node --check` nos dois arquivos JavaScript; revisão estática dos atributos de inicialização por aba e da montagem do contexto Blog; integridade CRC do ZIP.
- Não foi possível validar a tela num navegador nem executar PHP/Twig/runtime. A semelhança visual é baseada na composição do shell e não é apresentada como teste visual em execução.
