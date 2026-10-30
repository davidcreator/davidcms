# F9-51 — BDREditor em largura total nos formulários CMS Pages e Blog

> **Complementado pela F9-52:** esta etapa alargou o editor no fluxo do formulário, mas não reproduzia o workspace em tela cheia do protótipo. A F9-52 passa a abrir o workspace fullscreen ao carregar a aba de idioma ativa e preserva o retorno ao formulário.

## Correção solicitada

O BDREditor estava montado dentro da coluna principal de um formulário Bootstrap com uma segunda coluna lateral de dados. Isso limitava a largura do editor e fazia a tela parecer um formulário com um editor embutido, em vez de uma área de trabalho ampla.

## Alterações

- Reorganizei os formulários de **CMS Pages** e **Blog** para usar a largura principal inteira do painel de administração: o conteúdo fica em uma coluna de largura total e os dados auxiliares passam para a sequência abaixo, em vez de compartilhar a linha com o editor.
- O título, o slug/keyword e as abas de idioma permanecem acima do conteúdo; os metadados SEO permanecem depois do editor no painel de conteúdo; status, publicação, imagem de capa, ordem e ações auxiliares ficam na seção seguinte.
- Apliquei uma altura mínima responsiva ao workspace embutido e ao canvas, para que o editor use também a área vertical disponível sem cobrir ou desativar os campos do formulário.
- Não alterei o comportamento do formulário, a persistência, a estrutura de dados nem os controllers. CMS Pages e Blog continuam com seus próprios formulários, configurações e seletores de mídia.

## Arquivos completos no pacote

- `dev/admin/view/template/blog/blog_form.twig`
- `dev/admin/view/template/cms/page_form.twig`
- `dev/admin/view/css/blog-block-editor.css`
- `docs/F9-51-BDREditor-Area-de-Edicao-em-Largura-Total.md`
- `docs/F9-50-Workspace-Office-Interface-Completa.md` (atualizado com referência à correção de layout)

## Validação e limites

- Revisão estática confirma que as colunas principais e auxiliares passam a ocupar linhas separadas com largura total nos dois formulários.
- Balanceamento de chaves CSS e integridade CRC do ZIP.
- O template engine PHP/Twig e navegador real não estão disponíveis nesta validação; não alego teste runtime ou visual. Não foram alterados controllers, banco, migrações, GitHub ou deploy.
