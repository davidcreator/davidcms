# F9-66 — Hotfix da biblioteca de mídia de CMS Pages

## Diagnóstico no checkout

- O formulário de Pages já aponta para as rotas `cms/page.mediaList` e `cms/page.mediaUpload` e declara o modal `cmsPageMediaModal`.
- O controller existente já implementa essas ações, mas suas dependências não estavam completas neste checkout: faltavam `dev/admin/model/cms/page_media.php` e o script declarado `dev/admin/view/js/cms-page-media.js`.
- A URL do erro traz `&amp;` nos parâmetros. O script novo normaliza esse escape antes de criar a URL e usa `URL.searchParams`, preservando parâmetros de sessão existentes.

## Hotfix

- Inclui o model `Page_media`, separado do Blog e sem banco de dados: lista e grava mídia em diretórios `catalog/view/*/cms/page/`; valida extensão, MIME real, origem de upload, tamanho e caminho; usa nomes aleatórios e recusa symlinks.
- Inclui o cliente da biblioteca Pages: carrega arquivos, pesquisa/pagina, alterna tipo de mídia, envia com credenciais same-origin e token CSRF, e aplica a imagem enviada/selecionada ao bloco ativo.
- O ZIP inclui também o controller e template CMS Pages completos, para que as rotas, configuração e modal correspondam ao cliente/model entregues.
- Não altera banco, migrações, armazenamento do Blog, controller de Blog, `custom_js` ou fluxo independente do Blog.

## Verificações

- `node --check` para `cms-page-media.js` e `blog-block-editor.js` passou.
- Bateria estática: **167 verificações aprovadas**; smoke de URLs do editor: **9 aprovados**; smoke adicional confirmou a normalização de `&amp;` e a preservação de rota, token e parâmetros de mídia.
- Não foi possível executar PHP CLI, nem navegador ou staging. A resposta HTTP 404 não foi reproduzida localmente; o pacote precisa ser aplicado no ambiente autorizado para confirmar o endpoint.

## Arquivos completos

O pacote inclui controller/template CMS Pages, model novo, cliente novo, editor compartilhado e CSS, validador/renderer compartilhados, ferramentas e documentação F9-52 a F9-66.
