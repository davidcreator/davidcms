# Prévia privada BDRE para rascunhos do Blog

**Base consultada:** `5838685e4a96d6089d27c0d7e024b7c11b13c434`

**Estado:** implementação local para revisão; não commitada nem publicada.

## Comportamento

- O botão aparece somente nas abas de idioma que estão usando o editor BDRE em blocos e para quem já pode modificar `blog/blog`. O editor legado continua no fluxo atual.
- O browser envia o documento serializado do editor, o idioma selecionado e os campos ainda não salvos do post (título, metadados, imagem e data) por POST para uma rota própria de Blog.
- O endpoint exige método POST, ACL `modify` de `blog/blog` e CSRF. Confere post/idioma, disponibilidade da tabela sidecar existente, prontidão do editor, limite de 4 MiB, validade BDRE e referências de mídia. Um post novo usa ID zero; um ID existente precisa corresponder a um post do Blog.
- Renderiza com `BlockRenderer` no template público `blog/blog_info`, usando o idioma escolhido, o tema publicado e a estrutura/slot ativo `blog_post` do Pages somente para reproduzir o Catalog. O acesso a Pages é leitura de conteúdo já publicado; não muda permissões, CRUD, dados de post ou estrutura Pages.
- O HTML da miniatura Three.js mantém a cena declarativa configurada, mas a prévia elimina `custom_js` antes de renderizar e não executa JavaScript arbitrário de cena.
- A resposta é `private, no-store`, `noindex`, protegida por CSP e sem URL pública/bearer. Nenhuma linha é gravada, nenhum post é publicado, nenhuma migration é adicionada.

## Limites e verificação

- Prévia disponível apenas quando o editor BDRE por blocos está habilitado. Ela usa o documento ainda não salvo da aba ativa, não o fallback multilíngue do conteúdo publicado.
- A ordem editorial e o slot publicado do sistema `blog_post` são lidos para aproximar a rota real; não há escrita nem edição do CMS Pages nesse fluxo.
- `node --check` foi executado no novo script JavaScript. A imagem de ambiente não tem `php`, portanto não foi possível executar lint/runtime PHP. Não foram rodados testes de aplicação, banco, browser ou mutáveis.
- Nenhuma migration, commit, push, deploy, staging ou acesso a dados/credenciais foi feito.
