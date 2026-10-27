# ReamurCMS — estado de Blog, CMS e banco de dados

**HEAD consultado:** `2bbac1ad532a010e78e85d3aa61129258994ddc7`  
**Escopo:** inspeção estática do código e dos SQL versionados. Nenhuma conexão ao banco, execução SQL, migration, deploy ou acesso a credenciais foi feito.

## Resultado

A árvore de código contém o schema necessário para o CRUD atual de Páginas CMS e Blog, inclusive o armazenamento estruturado do editor de blocos no Blog. Isso **não comprova** que as tabelas ou grants já estejam aplicados no banco instalado; o estado do banco real permanece desconhecido.

## Estruturas encontradas

| Área | Schema/arquivo | Uso |
|---|---|---|
| Páginas CMS | `rms_page`, `rms_page_description` em `dev/install/sql/schema-complete.sql` | Status, ordenação, datas; conteúdo multilíngue `MEDIUMTEXT`; SEO em `rms_seo_url` global. |
| Posts do Blog | `rms_blog_post`, `rms_blog_post_description` em `dev/install/sql/schema-complete.sql` | Capa, status, data de publicação, títulos/descrições multilíngues e metadados SEO. |
| Blocos do Blog | `rms_blog_post_content` em `schema-complete.sql` e `dev/install/sql/upgrade_f9_12_blog_block_content.sql` | Sidecar JSON por post/idioma; preserva o snapshot HTML legado. |
| URLs SEO | `rms_seo_url` global | CMS usa `page_id=N`; Blog usa `blog_id=N`. Não há uma tabela SEO separada para Blog. |
| Acesso CMS | `dev/install/sql/upgrade_f2_10_cms_page_grant.sql` | Grant `cms/page` ao grupo alvo da migration. |
| Acesso Blog | `dev/install/sql/upgrade_f7_01_blog.sql` | Cria as tabelas base do Blog e acrescenta `blog/blog` em `access`/`modify` do grupo cujo nome é `Administrator`. |

`dev/install/sql/schema-complete.sql` inclui as três tabelas de Blog e os grants de `blog/blog` para `Administrator` ou `user_group_id = 1` em instalações novas. A migration de F7-01 para bases existentes usa o nome `Administrator`, conforme a decisão da ADR 0042. Os SQLs versionados usam o prefixo `rms_`; confirme o prefixo real antes de qualquer execução.

### Migrations relevantes

1. `upgrade_f7_01_blog.sql` — necessário para uma base existente sem as tabelas `blog_post`/`blog_post_description`; também adiciona grant da rota Blog para o grupo `Administrator`.
2. `upgrade_f9_12_blog_block_content.sql` — necessário para persistência do editor de blocos do Blog (`blog_post_content`). É aditivo e não converte posts existentes.
3. `upgrade_f7_03_blog_import.sql` — somente grant da ferramenta de importação `blog/import`; não é requisito para criar/publicar posts manualmente.
4. `upgrade_f2_10_cms_page_grant.sql` — grant das Páginas CMS, caso também falte.

As duas migrations de tabelas usam `CREATE TABLE IF NOT EXISTS`; os grants usam guardas de JSON. Mesmo assim, revisar backup, prefixo e alvo antes de aplicação. O agente não executou nenhuma delas. O release gate de F9-27 mantém F9-12 como **não confirmado no ambiente**.

## Por que o item “Blog” pode não aparecer no Admin

No HEAD consultado, o grupo e o item Blog já estão no menu em `dev/admin/controller/common/header.php`. O menu é ACL-driven: `blog/blog` só aparece se o usuário atual tiver `access` nessa rota. Portanto, ausência visual normalmente significa grant não aplicado ou grupo do usuário sem a permissão — não ausência de controller/template.

Não foi possível determinar qual caso ocorre no banco real sem inspecioná-lo. Verifique no grupo associado ao usuário:

- rota `blog/blog` em **Access** para exibir/acessar o módulo;
- rota `blog/blog` em **Modify** para criar/editar/publicar;
- se o grupo não se chama `Administrator`, ajuste pelo controle de Grupos de Usuários do Admin, sem ampliar grants de outros grupos por suposição.

## Fluxo de publicação e Catalog

- `cms/page` grava páginas em `page`/`page_description`; o Catalog renderiza uma página CMS ativa por `cms/page.info|ID` ou keyword SEO.
- `blog/blog` grava posts em `blog_post`/`blog_post_description` e, com a tabela F9-12 disponível, no sidecar de blocos. O Catalog lista posts com `status = 1` e `date_published <= NOW()`; rascunhos e posts agendados não aparecem antes da data.
- CMS Page não vira post. O índice público Blog é a rota existente `/blog`, separado da ferramenta de páginas institucionais.
- O schema atual **não** contém armazenamento de curtidas, contagem de compartilhamentos, visualizações ou ranking editorial; esses recursos exigiriam definição de produto/schema e uma migration nova.

## Three.js nas thumbs

A thumb declarativa não precisa de tabela própria. A página CMS guarda o envelope do editor em `page_description.description`; os posts do Blog usam o sidecar F9-12. O Catalog tem o controller `/three-runtime/*`, o manifesto allowlist e o runtime local. Na revisão do código, os controllers CMS/Blog chamavam `Document::addScript()`, mas o shell público não transferia `Document::getScripts()` ao Twig — por isso o runtime não era incluído no HTML, mesmo quando a thumb era válida. A correção proposta neste pacote passa e emite a lista de scripts como `defer`, e também adiciona um link para `/blog` na navegação pública.

A execução visual/WebGL no navegador e o estado das tabelas no banco continuam **não verificados**; dependem de ambiente autorizado e da aplicação das migrations necessárias pelo responsável.
