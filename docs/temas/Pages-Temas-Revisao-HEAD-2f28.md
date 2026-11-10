# Revisão: Páginas CMS e Temas do Catalog

**Fonte revisada:** `davidcreator/davidcms`, HEAD `2f28cf4ab7c390bb44bf88b598c0c9084770cc43` (27 out. 2026).  
**Tipo de revisão:** leitura estática seletiva; nenhum acesso ao `localhost`, banco ou estado de sessão.

## Decisões confirmadas

- Blog está funcionando; fora do escopo desta revisão.
- **Pages/CMS:** conteúdo editorial de todas as páginas públicas do Catalog deve ser administrável, mas os dados e fluxos especializados continuam nos próprios módulos. Ex.: editar a introdução do Portfolio não substitui o CRUD/listagem de projetos; a página de Blog não administra posts.
- **Temas:** ferramenta própria, separada de Pages e Blog, dedicada à aparência do Catalog.
- **Twig:** tanto um modo visual quanto um modo avançado de edição de fonte, restrito a templates do tema, com rascunhos, prévia isolada, versionamento e rollback.
- **Three.js:** cenas/componentes aprovados e configuração declarativa; sem editor de JavaScript arbitrário. Preservar o manifesto allowlist, o runtime compartilhado, fallback e sandbox existentes.
- **Banco:** autorizado preparar arquivos de migration/schema localmente, mas não executá-los. Sem push ou deploy.

## Constatações no HEAD

### CMS Pages

`dev/admin/controller/cms/page.php` e `dev/admin/model/cms/page.php` mantêm o CRUD de páginas CMS próprias, com idiomas, SEO, status, campos personalizados e conteúdo em blocos. O formulário permite blocos/Three.js em conteúdo CMS; no Catalog, `dev/catalog/controller/cms/page.php` busca a entidade por `page_id`.

Esse fluxo **não associa** uma entidade CMS às rotas próprias do Catalog. As palavras-chave SEO do CMS resolvem páginas CMS por `page_id`; não tornam a Home, os detalhes de projeto ou outras rotas editáveis no CMS.

### Páginas públicas

Os controllers atuais incluem:

| Área pública | Rota/camada existente | Responsabilidade que deve permanecer no módulo |
|---|---|---|
| Home e seções | `common/home` e partials | Projetos, depoimentos, logos e ferramentas são carregados dos modelos especializados; textos e ordem visual podem ser editáveis. |
| Portfolio | `catalog/project` | Dados e listagem de projetos permanecem no módulo Portfolio. CMS pode editar introduções e conteúdo editorial da rota. |
| Blog | `blog/blog` | Listagem e posts permanecem exclusivamente na ferramenta Blog; o CMS pode editar conteúdo da landing, sem duplicar posts. |
| Ferramentas | `tool/tool` | Registros e detalhes continuam no módulo Ferramentas; CMS pode editar textos de introdução/layout da área. |
| Contato | `common/contact` | Campos, validação e envio continuam no fluxo de contato; conteúdo de apresentação é editorial. |
| Orçamento | `checkout/quote` | Formulário e processamento permanecem no módulo de orçamento; textos e seções de apoio são editoriais. |
| Páginas CMS | `cms/page` | Páginas institucionais e legais. |
| Sitemap/404 | `common/sitemap`, `error/not_found` | Estrutura e resolução de rota permanecem técnicas; textos e apresentação podem ser configuráveis. |

A Home (`dev/catalog/controller/common/home.php`) é composta por strings de idioma/configuração e dados buscados em modelos de projetos, categorias, depoimentos, logos e ferramentas. Portanto, adicionar um registro CMS isolado não alteraria sua renderização atual.

### Temas e Twig

- A árvore atual não possui controller de Design no Admin: `dev/admin/controller/design/index.html` é placeholder. `dev/catalog/view/theme/index.html` também é placeholder.
- A aparência existente em `admin/setting/appearance` trata o **painel Admin**, não o tema do Catalog.
- O adaptador em `dev/system/library/template/twig.php` usa `FilesystemLoader` sobre `DIR_TEMPLATE` e aceita diretório de tema opcional. Não existe, hoje, ferramenta de administração de temas ou mecanismo versionado para editar fontes Twig.
- O modo de edição de fonte requer desenho próprio de armazenamento/carregamento dos templates, sandbox/allowlist e revisão de versão; não deve escrever controllers, `system/` ou caminhos arbitrários.

### Three.js

As ADRs 0072–0074 estabelecem configuração declarativa, manifesto allowlist, runtime compartilhado no System, lifecycle comum e isolamento de `custom_js` em iframe opaco. O projeto não tem um construtor geral de cenas. O escopo confirmado aqui usa configurações de cenas/components aprovados — não scripts arbitrários — para continuar compatível com essas decisões.

## Arquitetura recomendada para a atualização

1. **Registro explícito de áreas públicas editáveis:** identificar cada rota/slot público por chave estável, em vez de tentar inferir páginas de qualquer controller automaticamente.
2. **Conteúdo editorial por idioma e slot:** títulos, subtítulos, texto, mídia, SEO, visibilidade e ordem das seções, com o editor de blocos existente ou sua evolução controlada.
3. **Componentes dinâmicos allowlisted:** slots como grade de projetos, lista de posts, ferramentas, depoimentos e formulários chamam os módulos/modelos existentes. O CMS escolhe a composição; não copia os registros de negócio.
4. **Páginas de detalhe:** conteúdo específico de cada projeto/post/ferramenta continua no seu CRUD; a ferramenta Temas controla o template geral de detalhes e Pages pode controlar a introdução/slots editoriais permitidos da área.
5. **Temas separados:** gerenciar tema ativo do Catalog, tokens/estilos, layouts e cenas aprovadas. O modo visual compõe componentes; o modo avançado edita somente fontes Twig do tema, com draft → preview isolado → publish/rollback.
6. **Permissões e publicação:** ACL própria para `design/theme`; manutenção da ACL existente de `cms/page`; CSRF nas gravações; publicação explícita de conteúdo e templates.
7. **Persistência e instalação:** novas tabelas/migration idempotente para vínculo rota/slot e versões de tema/template, além da atualização de `schema-complete.sql`. Arquivos serão preparados no patch; nenhuma migration será executada no banco.

## Critérios de aceite propostos

- O Admin Pages apresenta as áreas públicas suportadas e permite editar conteúdo por idioma sem tornar posts/projetos/ferramentas/formulários duplicados.
- Alterar um texto ou ordem de seção no CMS reflete na rota pública correspondente; dados dinâmicos continuam sendo consultados em seus módulos.
- Pages e Blog permanecem ferramentas distintas no menu.
- Temas possui pré-visualização, rascunho, publicação, histórico e rollback; a seleção não altera temas do Admin.
- Twig avançado só opera sobre templates do tema escolhido; templates do núcleo, controllers, PHP e arquivos do System não são editáveis pela ferramenta.
- Three.js utiliza somente componentes/cenas aprovados, mantendo o carregamento allowlisted, fallback e acessibilidade.
- Migrações/schema ficam preparados para revisão; nada é executado no banco, publicado ou enviado ao GitHub nesta etapa.

## Situação

Esta entrega é uma **revisão de arquitetura**, não uma implementação. Nenhum código de produto, migration ou dado do banco foi alterado. Próxima etapa: implementar o registro de páginas/slots e a ferramenta Temas em overlay local baseado no HEAD acima, incluindo ADR e documentação afetada; depois revisar o patch antes de qualquer execução de SQL ou deploy.
