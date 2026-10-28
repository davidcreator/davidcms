# Guia CMS — Conceitos e Módulos do ReamurCMS

> **Objetivo:** consolidar os conceitos essenciais de um CMS e mostrar como cada um se traduz nos módulos do ReamurCMS — visão de produto para orientar o desenvolvimento e o go-live.

---

## Sumário

1. [O que é um CMS](#1-o-que-é-um-cms)
2. [Tipos de CMS e o posicionamento do ReamurCMS](#2-tipos-de-cms-e-o-posicionamento-do-reamurcms)
3. [Módulos essenciais de um CMS × ReamurCMS](#3-módulos-essenciais-de-um-cms--reamurcms)
4. [Gestão de conteúdo](#4-gestão-de-conteúdo)
5. [Usuários e permissões](#5-usuários-e-permissões)
6. [Multi-idioma](#6-multi-idioma)
7. [SEO no CMS](#7-seo-no-cms)
8. [Segurança](#8-segurança)
9. [Extensibilidade](#9-extensibilidade)
10. [Governança de conteúdo](#10-governança-de-conteúdo)
11. [Checklist de go-live](#11-checklist-de-go-live)

---

## 1. O que é um CMS

Um **CMS (Content Management System)** é um software que permite criar, editar, organizar e publicar conteúdo digital sem manipular código diretamente. Todo CMS saudável entrega cinco pilares:

| Pilar | Significado |
| --- | --- |
| **Separação conteúdo × apresentação** | O texto/imagem vive no banco; o *visual* vive em templates |
| **Gestão por não-programadores** | Quem edita não precisa conhecer PHP |
| **Controle de acesso** | Cada usuário vê/faz só o que sua função permite |
| **Versionamento & fluxo** | Rascunho → revisão → publicação |
| **Extensibilidade** | Novos recursos sem alterar o núcleo |

O ReamurCMS implementa esses pilares através da arquitetura [MVCL](Arquitetura-MVCL.md): conteúdo no **Model**, apresentação na **View** (Twig), orquestração no **Controller** e textos na camada **Language**.

---

## 2. Tipos de CMS e o posicionamento do ReamurCMS

| Tipo | Característica | Exemplos | ReamurCMS? |
| --- | --- | --- | --- |
| Tradicional (acoplado) | Backend e frontend no mesmo sistema | WordPress, Joomla | ✅ Base principal |
| Headless | Só API; front separado (SPA) | Strapi, Directus | ➖ Via endpoints `api/` (opcional) |
| Híbrido | Tradicional + API + eventos/webhooks | Winter, OctoberCMS | ✅ **Posicionamento alvo** |

O ReamurCMS é um **CMS híbrido**: renderiza o site com Twig (tradicional), expõe controllers em `catalog/controller/api/` e `admin/controller/api/` para integrações, e notifica sistemas externos por [webhooks](Webhooks.md).

---

## 3. Módulos essenciais de um CMS × ReamurCMS

Mapeamento entre os módulos clássicos de CMS e as pastas reais do projeto:

| Módulo CMS | Admin (`dev/admin/controller/`) | Catalog (`dev/catalog/controller/`) |
| --- | --- | --- |
| Dashboard / visão geral | `common/` *(a criar)* | — |
| Conteúdo / páginas | `cms/` | `cms/`, `information/` |
| Catálogo / portfólio | `catalog/` | `product/` |
| Clientes / contas | `customer/` | `account/` |
| Pedidos / orçamentos | `sale/` | `checkout/` |
| Comunicação / contato | `communication/`, `mail/` | `mail/`, `information/` (contato), `chat/` |
| Marketing (banners, depoimentos) | `marketing/` | — |
| Design / temas / layouts | `design/` | `view/theme/` |
| Relatórios | `report/` | — |
| Configurações | `setting/` | — |
| Ferramentas internas | `tool/` | `tool/` (Fluxa, Quotia, Vertex, Solis) |
| Usuários e grupos (ACL) | `user/` | — |
| Extensões | `extension/` | — |
| Localização / idiomas / moedas | `localization/` | `localisation/` |
| Integrações / API | `api/` | `api/` |
| Rotinas agendadas | — | `cron/` |
| Tratamento de erros | `error/` | `error/` |
| Inicialização de app | `startup/` | `startup/` |
| Eventos | `event/` | `event/` |

---

## 4. Gestão de conteúdo

### 4.1 Tipos de conteúdo do novo site

Derivados diretamente do inventário do site atual (ver [Análise](Analise-DavidAlmeida.md#3-inventário-de-conteúdo)):

| Tipo | Exemplos | Módulo gestor |
| --- | --- | --- |
| Página institucional/informativa | Cookies, Políticas Internas, Sobre | `admin/controller/cms/page.php` → `catalog/controller/cms/page.php` |
| Postagem de blog | Artigos e novidades publicados | `admin/controller/blog/blog.php` → `catalog/controller/blog/blog.php` |
| Projeto de portfólio | JWA, Selva Dev, Steel of Death | `admin/controller/catalog/` → exibição em `catalog/` |
| Categoria de portfólio | Visual Identity, Brand, Illustration | `admin/controller/catalog/` |
| Depoimento | Silvio Robson, Bruno Reis | `admin/controller/marketing/` |
| Cliente (logo) | SEVEN A, AJL Sorvete Express | `admin/controller/marketing/` |
| Serviço | Graphic Design, Web Design, SEO | `admin/controller/cms/` |
| Ferramenta conectada | Fluxa, Quotia, Vertex, Solis | `admin/controller/tool/` |
| Pedido / orçamento | solicitações de clientes | `admin/controller/sale/` |

**Páginas (CMS) e Blog são módulos independentes:** `cms/page` administra páginas institucionais/informativas; `blog/blog` administra postagens, publicação agendada e respectivos detalhes públicos. Uma página CMS não cria nem substitui uma postagem. No menu do Admin, cada módulo tem seu próprio grupo e permanece sujeito à ACL da respectiva rota. No Catalog, a página pública de listagem é a rota `/blog`, ligada também na navegação principal; as postagens são alimentadas apenas pelo módulo Blog.

### 4.1.1 Conteúdo editorial das rotas públicas (F10-01)

A migration `dev/install/sql/upgrade_f10_01_cms_catalog_routes.sql` liga páginas especiais de `rms_page` a chaves estáveis em `system_key`. O Admin continua usando `cms/page` e a ACL existente; páginas especiais são identificadas e protegidas contra exclusão. O Catalog lê o conteúdo publicado no idioma ativo (fallback en-US) e o renderiza na área editorial compartilhada do site.

Chaves atuais: `home`, `portfolio`, `portfolio_detail`, `blog`, `blog_post`, `tools`, `tool_detail`, `contact`, `quote` e `not_found`. A Home inclui seções editoriais como About; não existe rota pública separada `/about` no mapa atual. XML do sitemap, callbacks de pagamento, listagens de dados e formulários não viram conteúdo CMS: seus fluxos permanecem técnicos/dinâmicos. O CMS pode editar o conteúdo editorial de uma página, mas não duplica projetos, posts, ferramentas ou registros de orçamento.

### 4.2 Ciclo de vida do conteúdo

```
Rascunho ──► Revisão ──► Publicado ──► Arquivado
   ▲                       │
   └──────── edição ◄──────┘
```

Implementar no mínimo os campos: `status` (rascunho/publicado), `sort_order`, `date_added`, `date_modified`, `seo_url` (slug via `helper/slug.php`).

### 4.3 Mídia

- Uploads validados por `system/library/security/upload_security.php` (whitelist de extensões/MIME, reescrita de nome, bloqueio de executáveis).
- Redimensionamento/thumbs por `system/library/image.php`.
- Imagens do site em **WebP** (padrão já adotado no site atual) + `alt` obrigatório (SEO + acessibilidade).

---

## 5. Usuários e permissões

Dois universos distintos (padrão OpenCart, mantido):

| Universo | Tabela/grupo | Acesso |
| --- | --- | --- |
| **Usuários do admin** | `admin/controller/user/` (usuários e grupos) | ACL por rota: cada grupo tem lista de rotas `access`/`modify` (ex.: `sale/order`, `catalog/product`) |
| **Clientes do site** | `admin/controller/customer/` + `catalog/controller/account/` | Cadastro, login, histórico de pedidos/orçamentos |

Boas práticas obrigatórias:
- Senhas com `password_hash()` / `password_verify()` (Argon2id quando disponível);
- Sessões regeneradas no login (`session_regenerate_id()`), cookie `SameSite=Lax` (já no config) e `Secure` sob HTTPS;
- Bloqueio de IP para a API via `system/config/api/settings/ip_blocked.php`;
- Log de ações administrativas sensíveis (criar/editar/excluir pedidos e usuários).

---

## 6. Multi-idioma

- Idiomas previstos: **`en-us`** (padrão — [ADR 0001](../agents-ia/decisoes/0001-idioma-padrao-en-us.md)), **pt-br**, **es-eu** — estrutura já criada em `catalog/language/`, `admin/language/` e `install/language/`. Conteúdo novo nasce primeiro em `en-us`; versões pt-BR/es-EU das páginas-chave entram no checklist de go-live (§11).
- Detecção inicial via `system/library/language/detect.php` (Accept-Language) com persistência em sessão/cookie.
- Conteúdo de banco traduzível por tabela de tradução (padrão `*_description`: um registro por idioma, como `product_description`).
- URLs localizadas futuras: `/pt/portfolio/jwa`, `/en/portfolio/jwa` (Fase 6 — SEO internacional).

---

## 7. SEO no CMS

| Recurso | Onde | Uso |
| --- | --- | --- |
| URLs amigáveis / slugs | `system/helper/slug.php`, `helper/seo.php` | geração e sanitização de slugs únicos |
| Meta tags por página | camada View/`document.php` | título, description, canonical, OG/Twitter Cards |
| Imagens otimizadas | `library/image.php` | WebP, dimensões explícitas, lazy load |
| Sitemap + robots | `catalog/controller/` (a criar) | submissão ao Search Console |
| Dados estruturados | templates Twig | schema.org `Person` (autor), `CreativeWork` (portfólio), `LocalBusiness` (contato) |
| Redirects 301 | mapa da migração | ver [Análise §9](Analise-DavidAlmeida.md#9-seo-e-redirecionamentos) |

---

## 8. Segurança

Checklist de segurança do CMS (hardening):

**Aplicação**
- [ ] SQL sempre escapado (`$this->db->escape()`) ou via prepared statements (driver PDO)
- [ ] Escape automático do Twig ativo em todos os templates (padrão — não usar `|raw` sem necessidade)
- [ ] CSRF token em todos os formulários do admin e do catalog
- [ ] Uploads passando por `upload_security.php`
- [ ] Rate limit no formulário de contato e no login

**Servidor / deploy**
- [ ] HTTPS forçado (`site_ssl = true`) + headers: `X-Frame-Options`, `X-Content-Type-Options`, `Referrer-Policy`, `Content-Security-Policy`
- [ ] `error_display = false` e `error_log = true` em produção (logs em `storage/logs/`)
- [ ] Pasta `dev/admin/` renomeável/protegida + `storage/` fora do webroot quando possível
- [ ] Backups automáticos do banco + de `storage/` e uploads

**Credenciais**
- [ ] Chaves (Mercado Pago, SMTP, webhooks) em config fora do Git / variáveis de ambiente
- [ ] Segredo HMAC por endpoint de webhook ([Webhooks §5](Webhooks.md#5-segurança))

---

## 9. Extensibilidade

| Mecanismo | Para quê |
| --- | --- |
| **Extensions** (`Reamur\Extension`, pasta `extension/`) | Módulos plugáveis (pagamento, frete, mídia) sem tocar o núcleo |
| **Events** | Modificar comportamento existente (antes/depois de controllers e models) |
| **Webhooks** | Reagir a eventos de negócio fora do sistema (pedido, produto) |
| **`modification.xml`** | Alterações declarativas em arquivos do núcleo (legado OCMOD — usar com parcimônia; preferir events) |
| **Template engines** | Trocar/convivência de engines de view por módulo |
| **Marketplace** (`storage/marketplace/`) | Distribuição/instalação de pacotes de terceiros |

---

## 10. Governança de conteúdo

Mesmo sendo um projeto de um desenvolvedor principal, vale o ritual mínimo:

| Papel | Responsável |
| --- | --- |
| Product owner / editor-chefe | David L. Almeida |
| Desenvolvedor do núcleo | David L. Almeida |
| Revisor de conteúdo do cliente | O próprio cliente (via validação em staging) |

Rotinas:
1. **Rascunho primeiro:** nenhum conteúdo entra direto como "publicado" em produção.
2. **Staging obrigatório:** cliente aprova em `staging` antes do merge em `main`.
3. **Changelog por release:** usar as tags do Git (`v1.0.0`, `v1.1.0`...) com resumo das alterações.
4. **Backup antes de publish estrutural:** exportar banco antes de importações em lote.

---

## 11. Checklist de go-live

**Conteúdo**
- [ ] Inventário 100% migrado ([Análise §3](Analise-DavidAlmeida.md#3-inventário-de-conteúdo))
- [ ] Textos pt-BR revisados; versões en-US/es-EU ao menos nas páginas-chave
- [ ] Imagens em WebP com `alt`

**Técnico**
- [ ] CI verde (`composer validate --strict` no diretório correto)
- [ ] `template_cache = true`, cache engine configurado, OPcache ativo
- [ ] HTTPS + headers de segurança + `error_display = false`
- [ ] Redirects 301 do mapa de migração publicados
- [ ] Sitemap no Search Console; Analytics/TagManager instalados

**Negócio**
- [ ] Formulário de contato entregando no e-mail certo + notificação por webhook
- [ ] Fluxo de orçamento (Quotia/pedido) testado ponta a ponta
- [ ] Política de cookies e políticas internas acessíveis no rodapé

---

## Veja também

- [Análise do site atual](Analise-DavidAlmeida.md) — de onde viemos
- [Arquitetura MVCL](Arquitetura-MVCL.md) — como o CMS está montado por dentro
- [Guia PHP 8+](Guia-PHP8.md) · [Guia Twig](Guia-Twig.md) · [Webhooks](Webhooks.md)

### 4.1.2 Temas do Catalog (F10-02)

A rota Admin `design/theme` é separada de Pages e Blog e controla tokens visuais e um identificador de cena selecionado de uma allowlist. Não aceita JS nem caminhos de arquivo enviados pelo usuário. As tabelas `design_theme` e `design_theme_revision` mantêm configuração e snapshots. A publicação versionada, o loader Catalog, a edição Twig allowlisted, a prévia sandboxada e a restauração pela interface são descritos nas seções seguintes.


### 4.1.3 F10-03 — Publicação versionada de Temas

O tema agora mantém `draft_config` separado de `published_config`. A prévia não publica; o ato de publicar é explícito e registra revisão. Rollback exige ACL/CSRF e restaura snapshot. O loader Catalog só substitui templates de uma lista fixa, mantendo os templates originais como fallback; Admin e Install não ativam o loader de tema.


### 4.1.4 Fase 10 — renderização dos slots editoriais das rotas

F10-01 já armazenava páginas especiais sob `system_key`, mas o conteúdo público era consumido diretamente apenas pela Home; o header consultava os demais slots para SEO, sem exibi-los no corpo da página. F10-04 liga slots editoriais aos templates públicos de Portfolio (grade e página de detalhe), Blog (somente landing/listagem), Ferramentas (grade e detalhe), Contato, Orçamento e 404. A página Blog/post individual continua fora do CMS; posts permanecem no módulo Blog.

Cada controller fornece o slot como variável de apresentação, enquanto listas e entidades continuam carregadas pelos modelos próprios e formulários mantêm seus POST/CSRF/validação. O controller `cms/slot` memoiza a página e HTML na requisição para compartilhar consulta, renderização e metadados entre o corpo e o header.

### 4.1.5 Layouts públicos e código aprovado

O registro `cms/page` de cada rota do sistema mantém o atalho **Editar template** para o template correspondente no Admin `design/theme`; o editor abre o item correspondente focado. Páginas CMS comuns usam `cms/page_info`. Os templates de rota ficam no rascunho do tema, com ACL própria, prévia sandboxada, publicação explícita e histórico/reversão — o conteúdo de página continua separado.

| `system_key` | Template de apresentação |
| --- | --- |
| `home` | `common/home` + partials públicos |
| `portfolio` · `portfolio_detail` | `catalog/project_list` · `catalog/project_info` |
| `blog` · `blog_post` | `blog/blog_list` · `blog/blog_info` (somente apresentação; posts seguem no Blog) |
| `tools` · `tool_detail` | `tool/tool_list` · `tool/tool_info` |
| `contact` · `quote` | `common/contact` · `checkout/quote` (POST, CSRF e validação permanecem nos controllers) |
| `not_found` | `error/not_found` |

`common/header.twig` mantém o bootstrap/importmap de scripts sob controle do sistema; sua barra visual é customizável por `partial/site_header`. A allowlist do tema também inclui o footer e os partials públicos usados pela Home. A edição de fonte permite Twig/HTML de apresentação, não PHP nem JavaScript inline: interações devem usar somente componentes/cenas aprovados e configuráveis. JavaScript personalizado do componente Three.js continua isolado no sandbox existente. API, sitemap XML, callbacks e fluxos internos não entram no editor de páginas.

## Estrutura visual das páginas públicas

As Pages de sistema compatíveis expõem no BDREditor uma lista ordenável de seções públicas. O contrato allowlisted `system/library/cms/page_layout.php` reconhece somente os identificadores fixos por rota; o editor não recebe campos para Twig, PHP, JavaScript, paths ou HTML de template. A descrição continua armazenando o documento BDRE v2, envelopado com a chave da rota e a ordem de seções no marcador `REAMURCMS_PAGE_LAYOUT_V1:`. O modelo de dados não exige migration.

A integração abrange Home, Portfolio (lista e detalhe), Tools (lista e detalhe), Contact, Quote, 404, Blog (lista) e Blog Post (composição da página). Home inclui Hero/About/Methodology/Skills/Services e as áreas dinâmicas existentes. A seção editorial não substitui os dados de Portfolio, Tools ou Posts: esses dados continuam a ser carregados e editados pelos módulos donos. Blog e Pages seguem rotas, conteúdo e fluxos separados; apenas o contrato visual allowlisted e o BDREditor são compartilhados.

Na apresentação pública, o Catalog valida o envelope e renderiza apenas o conteúdo BDRE aprovado; cada seção de domínio permanece vinculada a um include/código fixo. Um envelope inválido não executa conteúdo estrutural, e documentos legados continuam compatíveis com os defaults da rota. A autorização de edição permanece a ACL `modify cms/page`, com POST e CSRF já usados pelo formulário.
