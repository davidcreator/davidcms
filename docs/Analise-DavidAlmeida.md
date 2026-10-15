# Análise — davidalmeida.xyz → ReamurCMS

> **Objetivo:** mapear o estado atual do site [davidalmeida.xyz](https://davidalmeida.xyz), diagnosticar pontos fortes e limitações, e definir o plano de migração para o **ReamurCMS** preservando a estrutura base e o máximo de código existente.

| | |
| --- | --- |
| **Autor** | David L. Almeida |
| **Projeto** | ReamurCMS · repositório [`davidcreator/davidcms`](https://github.com/davidcreator/davidcms) |
| **Versão do documento** | 1.0 |
| **Data** | Outubro/2026 |
| **Status** | ✅ Aprovado para iniciar a implementação |

---

## Sumário

1. [Contexto](#1-contexto)
2. [Estado atual do site](#2-estado-atual-do-site)
3. [Inventário de conteúdo](#3-inventário-de-conteúdo)
4. [Evidências técnicas](#4-evidências-técnicas)
5. [Diagnóstico](#5-diagnóstico)
6. [Por que manter a base ReamurCMS](#6-por-que-manter-a-base-reamurcms)
7. [Mapeamento: site atual → ReamurCMS](#7-mapeamento-site-atual--reamurcms)
8. [Plano de migração](#8-plano-de-migração)
9. [SEO e redirecionamentos](#9-seo-e-redirecionamentos)
10. [Riscos e mitigações](#10-riscos-e-mitigações)
11. [Métricas de sucesso](#11-métricas-de-sucesso)
12. [Próximos passos imediatos](#12-próximos-passos-imediatos)
13. [Referências](#13-referências)

---

## 1. Contexto

O site **davidalmeida.xyz** é o portfólio e a vitrine de serviços de **David L. Almeida**, *Creative Developer* (design gráfico, web design e SEO), com escritório em São Paulo, Brasil. Hoje ele é sustentado por um **CMS próprio e simples**, usado para organizar **conteúdos** (páginas, portfólio, depoimentos, clientes) e **pedidos de clientes** (orçamentos/solicitações).

A reformulação do site será feita sobre o **ReamurCMS**: um CMS novo, porém construído a partir de uma base que o autor já domina (inspirada no OpenCart), o que evita reescrever/refatorar a maior parte da programação atual. O repositório encontra-se em estágio de **esqueleto estrutural**: a árvore MVCL inteira está criada e preenchida com `index.html` vazios, o `composer.json` já declara o pacote `reamurcms/reamurcms` e o sistema de webhooks (pedidos e produtos) já foi iniciado.

---

## 2. Estado atual do site

### 2.1 Identidade e posicionamento

- **Marca pessoal:** David L. Almeida — *Creative Developer*.
- **Proposta de valor:** "transformar ideias em realidade com criatividade e responsabilidade", unindo design (identidade visual, logos, ilustrações) e desenvolvimento (sites, CMS, e-commerce, web apps) com foco em **SEO**.
- **Diferencial metodológico:** processo criativo documentado em 6 etapas — *Knowing the Client → Search → Concept → Execution → Flexibility → Delivery*.
- **Especialidade declarada:** projetos de identidade visual que posicionam marcas no mercado (logo, cores, tipografia, gráficos, manual de marca).

### 2.2 Estrutura pública atual (one-page + páginas de portfólio)

| Seção | ID/Âncora | Conteúdo |
| --- | --- | --- |
| Hero | topo | Nome, título (*Creative Developer*), links sociais (Facebook, Instagram, Pinterest, X, Google Business, RedBubble) |
| Sobre | `#profile` | *Who I Am?* — bio, metodologia em 6 etapas, "Por que investir em identidade visual" (Unity, Credibility, Attraction) |
| Depoimentos | — | *Client Testimonials* (Silvio Robson B. Lima, Pedrinho Seus Brinquedos, Bruno Reis) |
| Skills | — | Habilidades (Developer, Brand Design, Visual Identity, Product Design, Illustrations, Brand Action) + Tech (HTML, CSS, PHP, Javascript, jQuery) + Plataformas (Wordpress, Prestashop, Joomla, Blogger, Winter) + Programas (VS Code, Illustrator, Photoshop, CorelDRAW, Figma) + GitHub/GitLab |
| Serviços | — | *What do I do?* — **Graphic Design** (Brand, UI, Illustrations, Social Media, Digital Installations), **Web Design** (Landing Page, CMS, E-commerce, Web App, Institutional), **SEO** (Keyword Analysis, On-Page, Content, Link Building, Audit) |
| Ferramentas conectadas | — | *Connected tools* — Fluxa, Quotia, Vertex, Solis (abrem via `/catalog/tools/open?id=N&src=home`) |
| Clientes | — | *Beloved clients* — SEVEN A, Alex Cristopher, BDRProPlayer, AJL Sorvete Express, Horror na Veia |
| Portfólio | — | *What it looks like?* — categorias **Visual Identity, Brand, Illustration**; itens: JWA, Steel of Death, Farmer Life Simulation, Chameleon Digital Art, Hiperion Gamer (link externo), RC – Rogério Consultoria, Pitbull Colors, Cat Fe, Classic Car Race, Selva Dev |
| Últimas notícias | — | Posts do blog externo [davidcreator.com](https://davidcreator.com) (tutoriais PowerPoint, Scratch) |
| Contato | — | *Let's have a chat?* — formulário (Nome, E-mail, Telefone/WhatsApp, Assunto, Mensagem), endereço ("Home and Office: São Paulo, Brazil"), e-mail `contato@davidalmeida.xyz`, botão WhatsApp `+55 11 98393-8347` |
| Rodapé | — | © 2025, links Cookies e Políticas Internas, redes sociais |

### 2.3 Ferramentas conectadas (ecossistema DavidCreator)

| Ferramenta | Descrição (site atual) |
| --- | --- |
| **Fluxa** | Gestão financeira e acompanhamento de indicadores |
| **Quotia** | Plataforma para solicitar e organizar orçamentos em um único fluxo |
| **Vertex** | Gerador gratuito de currículos com foco em clareza e apresentação profissional |
| **Solis** | Gerador estratégico para planejamento de conteúdo e postagens em redes sociais |

---

## 3. Inventário de conteúdo

Relação de todo o conteúdo que precisa existir no ReamurCMS no dia do lançamento:

| Tipo de dado | Volume atual | Origem |
| --- | --- | --- |
| Página institucional (home one-page com ~10 seções) | 1 | CMS atual |
| Itens de portfólio (capa, título, categoria, página/slug) | 10+ | CMS atual (`/catalog/portfolio/{slug}`) |
| Categorias de portfólio | 3 (Visual Identity, Brand, Illustration) | CMS atual |
| Depoimentos de clientes | 3+ | CMS atual |
| Logos de clientes | 5+ | CMS atual |
| Listas de skills / tech / plataformas / programas | 4 grupos | CMS atual |
| Serviços e sub-serviços | 3 grupos / 15 itens | CMS atual |
| Ferramentas conectadas (ícone, nome, descrição, link) | 4 | CMS atual |
| Páginas legais (Cookies, Políticas Internas) | 2 | CMS atual |
| Posts de blog | — (hoje consumidos de davidcreator.com) | Blog externo (WordPress) |
| Pedidos/orçamentos de clientes | histórico a preservar | CMS atual |
| Contatos recebidos pelo formulário | histórico a preservar | CMS atual |

---

## 4. Evidências técnicas

Observações levantadas a partir do site em produção e do repositório:

| Evidência | Interpretação |
| --- | --- |
| Todo o site público vive sob o path **`/catalog/`** | O CMS atual já adota o conceito *catalog* (front) do OpenCart — **mesma divisão que o ReamurCMS** (`dev/catalog/`). Migração natural de URLs. |
| Assets em `/catalog/assets/...` e rotas como `/catalog/portfolio/{slug}` e `/catalog/tools/open?id=N` | Já existe um padrão de rotas amigáveis para portfólio e um *controller* de ferramentas. No ReamurCMS isso vira `catalog/controller/...` com URLs via helper `seo/slug`. |
| Formulário de contato com validação e mensagens de timeout/sucesso | Há backend de comunicação/mail — mapeado para `admin/controller/communication` + `system/library/mail`. |
| Stack declarada: HTML, CSS, **PHP**, Javascript, **jQuery** | PHP é a linguagem do servidor hoje — o ReamurCMS permanece em PHP 8+, sem trocar stack de hospedagem. |
| Experiência em plataformas CMS (Wordpress, Prestashop, Joomla, Winter) | Domínio de conceitos de CMS pelo autor; decisão por sistema próprio é deliberada (controle total, sem bloat). |
| Blog em domínio separado (davidcreator.com) | No novo site, o blog pode ser uma seção do ReamurCMS (`catalog/controller/cms`) ou permanecer externo — **decisão aberta** (ver §8 Fase 3). |
| Repositório: 3 branches (`main`, `staging`, `feature/system`) e tag `v1.0.0` | Workflow Git já iniciado; o site legado deve coexistir com o ambiente de staging até o cutover. |
| Workflow de CI (PHP Composer) **falhando** no commit inicial | O workflow roda `composer validate` na raiz, mas o `composer.json` vive em `dev/system/storage/` — correção documentada no [Guia Composer](Guia-Composer.md#5-ci-github-actions). |
| `.gitignore` ignora `/system/storage/vendor`, mas o vendor commitado está em `dev/system/storage/vendor` | Caminho do ignore não corresponde à pasta `dev/` — ver "Pontos de atenção" no [Guia Composer](Guia-Composer.md#6-pontos-de-atenção-do-repositório). |

---

## 5. Diagnóstico

### ✅ Forças (preservar)

- **Marca e conteúdo maduros:** portfólio consistente, depoimentos reais, metodologia clara.
- **Arquitetura de URLs já compatível** com o conceito catalog do ReamurCMS.
- **CMS próprio com lógica de negócio validada** (conteúdos + pedidos de clientes) — é esse funcionamento que se quer "portar", não reescrever do zero.
- **Autor domina a stack** (PHP, JS, design) — manutenção sem dependência de terceiros.

### ⚠️ Limitações (resolver)

- **CMS simples demais:** sem separação formal de camadas; difícil evoluir (multi-idioma, temas, integrações).
- **Sem multi-idioma estruturado** — o site mistura inglês/PT; o ReamurCMS já nasce com `pt-br`, `en-us`, `es-eu`.
- **jQuery como base do front** — oportunidade de modernizar (JS vanilla + Three.js apenas onde agrega valor).
- **Blog fora do domínio** — dilui autoridade SEO do domínio principal *(decisão híbrida registrada: externo no lançamento, migração interna pós-lançamento — [ADR 0002](../agents-ia/decisoes/0002-blog-hibrido.md))*.
- **Sem sistema de eventos/webhooks** — integrações (ex.: novo pedido → notificação) hoje exigem código pontual.
- **Hospedagem/manutenção manual** — sem pipeline de deploy/testes.

### 🎯 Oportunidades (novo site)

- Hero e seções com **experiências 3D (Three.js)** condizentes com o posicionamento "Creative Developer".
- **Portfólio como módulo CMS de primeira classe** (categorias, tags, galeria, SEO por projeto).
- **Webhooks nativos** para pedidos/orçamentos (ex.: Quotia, formulário de contato → CRM/WhatsApp).
- **Painel admin único** para conteúdo + clientes + pedidos + ferramentas (Fluxa, Quotia, Vertex, Solis).

---

## 6. Por que manter a base ReamurCMS

Decisão registrada: **manter a estrutura base com o nome ReamurCMS** em vez de reescrever ou adotar um CMS de mercado.

| Critério | Justificativa |
| --- | --- |
| **Reaproveitamento** | A base inspirada em OpenCart já possui engine completa (`registry`, `loader`, `router`, `event`, `config`, `proxy`) e bibliotecas (db, cache, session, mail, template, cart) — portar a lógica atual é mover código, não reinventá-lo. |
| **Identidade de produto** | `reamurcms/reamurcms` já está declarado no `composer.json`, namespaces `Reamur\*`, constante `DIR_REAMUR`, sessão `RMSSESSION`, homepage e licenciamento GPL-3.0 — mudar de nome agora geraria retrabalho em todos os arquivos. |
| **Fit com o negócio** | O site precisa de portfólio + conteúdo + pedidos/orçamentos + ferramentas — exatamente os módulos já previstos na árvore (`cms`, `sale`, `customer`, `communication`, `tool`, `marketing`, `report`). |
| **Evolução futura** | Estrutura de extensões, marketplace e multi-template/lib permite vender/white-label o CMS no futuro (licença GPL-3.0 compatível). |

---

## 7. Mapeamento: site atual → ReamurCMS

| Funcionalidade atual | Destino no ReamurCMS | Observações |
| --- | --- | --- |
| Home one-page (hero, sobre, metodologia, depoimentos, skills, serviços, contato) | `dev/catalog/controller/common/home.php` + tema Twig | Cada seção vira um *partial* Twig; Three.js no hero |
| Portfólio (categorias + projetos) | `admin/controller/catalog/` (gestão) + `catalog/controller/product/` ou `cms/` (exibição) | Slugs preservados: `/portfolio/{slug}` → ver §9 |
| Depoimentos / clientes | `admin/controller/marketing/` (testimonials) | Galeria de logos com `webp` + lazy load |
| Formulário de contato | `catalog/controller/information/contact.php` + `admin/controller/communication/` + `library/mail` | SMTP configurável; notificação via webhook |
| Pedidos/orçamentos de clientes | `admin/controller/sale/order/` + `system/webhook/controller/sale/order.php` | Webhooks `order_add` / `order_edit` já esboçados |
| Ferramentas (Fluxa, Quotia, Vertex, Solis) | `catalog/controller/tool/` (+ cards administráveis em `admin/controller/tool/`) | Rota atual `/catalog/tools/open?id=N` → URL amigável `/tools/{slug}` |
| Clientes (área de conta) | `catalog/controller/account/` + `admin/controller/customer/` | Login, acompanhamento de pedidos |
| Blog / notícias | Lançamento: externo (davidcreator.com) via feed; migração interna pós-lançamento | **Decidido: híbrido** ([ADR 0002](../agents-ia/decisoes/0002-blog-hibrido.md)) — não criar módulo de blog agora |
| Páginas legais (Cookies, Políticas) | `catalog/controller/information/` | Reaproveita conceito *information pages* do OpenCart |
| Multi-idioma (EN/PT/ES) | `dev/{admin,catalog,install}/language/{en-us,es-eu,pt-br}` | **Padrão: `en-us`** ([ADR 0001](../agents-ia/decisoes/0001-idioma-padrao-en-us.md)); pt-BR/es-EU no go-live |
| SEO / URLs amigáveis | `system/helper/seo.php`, `system/helper/slug.php` | Tabela `seo_url` no padrão OpenCart |

---

## 8. Plano de migração

### Visão geral das fases

| Fase | Entregável | Status |
| --- | --- | --- |
| **0 — Fundação** | Estrutura MVCL, composer, CI, tag v1.0.0, webhooks iniciais | ✅ Concluída |
| **0.2 — Documentação** | `README.md` + pasta `docs/` (este documento) | ✅ Concluída |
| **1 — Núcleo** | `system/engine`, `system/library`, `framework.php` rodando em PHP 8 estrito | ⬜ |
| **2 — Instalador** | Wizard `install/` + schema SQL + migração dos dados do CMS atual | ⬜ |
| **3 — Catalog** | Tema Twig + Three.js; todas as seções da home e portfólio migrados | ⬜ |
| **4 — Admin** | Painel com conteúdo, clientes, pedidos, ferramentas, relatórios | ⬜ |
| **5 — Integrações** | Mercado Pago, webhooks de pedidos/produtos, Google Shopping (se aplicável) | ⬜ |
| **6 — Lançamento** | Testes, hardening, redirects 301, cutover de DNS | ⬜ |

### Checklist por fase

**Fase 1 — Núcleo**
- [ ] Revisar `engine/*` (autoloader, registry, config, loader, router, action, event, proxy) com tipagem PHP 8
- [ ] Revisar `library/*` (db, cache, session, template, mail, image, language)
- [ ] Definir `index.php` de entrada por aplicação conforme [Contrato-Bootstrap](Contrato-Bootstrap.md) (modelo em `agents-ia/modelos/`) — tarefa única do `agente-system`
- [ ] Alinhar `config/default.php` para `en-us` conforme ADR 0001
- [ ] Schema do banco conforme [Dicionário de Dados](Dicionario-de-Dados.md) (alimenta a Fase 2)
- [ ] Limpeza do vendor versionado (`git rm -r --cached`) — ADR 0003
- [ ] Ativar PHPStan (`composer analyse`) e PHPUnit em `dev/tests/` — ADR 0004

**Fase 2 — Instalador**
- [ ] Passos do wizard: licença → requisitos → banco → admin → concluir
- [ ] Schema SQL das tabelas núcleo (conteúdo, portfólio, clientes, pedidos, settings, seo_url)
- [ ] Script de importação do banco do CMS atual (conteúdos + pedidos de clientes)

**Fase 3 — Catalog / Front**
- [ ] Base do tema Twig: layout base + partials das 10 seções
- [ ] Hero com Three.js (ver [Guia Three.js](Guia-ThreeJS.md))
- [ ] Páginas de portfólio com galeria e SEO por projeto
- [ ] Páginas das ferramentas (Fluxa, Quotia, Vertex, Solis)
- [ ] Formulário de contato + SMTP + webhook de notificação
- [ ] Seção "Latest News" lendo o feed do davidcreator.com (**blog externo no lançamento** — ADR 0002; módulo interno fica para fase posterior)
- [ ] Textos en-US completos (padrão — ADR 0001) + versões pt-BR / es-EU das páginas-chave

**Fase 4 — Admin**
- [ ] Login/ACL (usuários e grupos)
- [ ] CRUDs: portfólio, depoimentos, clientes, serviços, skills, ferramentas, páginas information
- [ ] Gestão de pedidos/orçamentos + histórico migrado
- [ ] Configurações (SMTP, SEO, idiomas, cache)

**Fase 5 — Integrações**
- [ ] Webhooks de pedido (`order_add`, `order_edit`) com assinatura HMAC + retries
- [ ] Mercado Pago Marketplace (pagamentos/serviços)
- [ ] Google Shopping / OpenBay apenas se fizerem sentido ao novo escopo

**Fase 6 — Lançamento**
- [ ] Testes manuais das jornadas críticas (contato, orçamento, portfólio)
- [ ] `composer validate --strict` verde no CI (corrigir working-directory)
- [ ] Hardening: HTTPS, headers, `error_display=false`, backups
- [ ] Redirects 301 de todas as URLs antigas (lista em §9)
- [ ] Cutover + monitoramento dos logs (`dev/system/storage/logs`)

---

## 9. SEO e redirecionamentos

| URL atual | URL nova (ReamurCMS) | Ação |
| --- | --- | --- |
| `/catalog/` | `/` | 301 (home na raiz) |
| `/catalog/#profile` | `/#sobre` | âncora |
| `/catalog/portfolio/{slug}` | `/portfolio/{slug}` | 301 + slug preservado |
| `/catalog/tools/open?id=N` | `/tools/{slug}` | 301 por ferramenta |
| `/catalog/cookies`, `/catalog/politicas-internas` | `/cookies`, `/politicas-internas` | 301 |
| Blog (davidcreator.com) | a definir (Fase 3) | se interno: 301 + importar posts |

**Meta técnica de SEO:** títulos/meta descriptions por página via admin, Open Graph com imagens do portfólio, sitemap XML, schema.org `Person` + `CreativeWork`, Core Web Vitals ≥ 90 no Lighthouse (hero 3D com lazy-init para não penalizar LCP/INP).

---

## 10. Riscos e mitigações

| Risco | Impacto | Mitigação |
| --- | --- | --- |
| Perda de dados do CMS atual na migração | Alto | Backup completo + script de importação testado em staging + freeze de conteúdo na semana do cutover |
| Quebra de SEO pelos redirects | Alto | Mapa 301 completo (§9), sitemap novo no Search Console, monitorar 404s por 30 dias |
| Three.js comprometer performance | Médio | Lazy-init da cena, `prefers-reduced-motion`, fallback estático, orçamento de < 150 KB gz no hero |
| Escopo crescer (blog, e-commerce completo) | Médio | Fases travadas neste documento; extensões fora do escopo entram no backlog pós-lançamento |
| Vendor commitado no Git (conflitos/peso) | Baixo | Corrigir `.gitignore` (ver Guia Composer §6) e remover vendor do histórico futuro |
| CI falhando dar falsa sensação de erro | Baixo | Corrigir `working-directory` do workflow (ver Guia Composer §5) |

---

## 11. Métricas de sucesso

1. **Conteúdo:** 100% dos itens do inventário (§3) migrados e editáveis pelo admin.
2. **Funcional:** formulário de contato e fluxo de orçamento/pedido funcionando com notificação por webhook.
3. **SEO:** zero 404 em URLs antigas; posições preservadas no Google em até 60 dias.
4. **Performance:** Lighthouse ≥ 90 (Performance, Acessibilidade, Boas práticas, SEO) na home.
5. **Operação:** deploy via Git (staging → main) sem acesso manual ao servidor.

---

## 12. Próximos passos imediatos

1. ✅ **Documentação** (`README.md` + `docs/`) — *este entregável*.
2. 🔧 Corrigir o workflow do CI (`working-directory: dev/system/storage`) — ver [Guia Composer §5](Guia-Composer.md#5-ci-github-actions).
3. 🧹 Ajustar `.gitignore` para ignorar `dev/system/storage/vendor/` — ver [Guia Composer §6](Guia-Composer.md#6-pontos-de-atenção-do-repositório).
4. 🧱 Iniciar **Fase 1 (Núcleo)** na branch `feature/system`: revisar engine e criar os pontos de entrada (`index.php`).
5. 🎨 Definir a direção visual do hero 3D — ver [Guia Three.js](Guia-ThreeJS.md#8-ideias-para-o-davidalmeidaxyz).

---

## 13. Referências

- Site atual: [davidalmeida.xyz](https://davidalmeida.xyz)
- Repositório: [github.com/davidcreator/davidcms](https://github.com/davidcreator/davidcms)
- Blog atual: [davidcreator.com](https://davidcreator.com)
- Arquitetura de referência: [Documentação OpenCart](https://docs.opencart.com/)
- Documentos relacionados: [Arquitetura MVCL](Arquitetura-MVCL.md) · [Guia CMS](Guia-CMS.md) · [Estrutura de Pastas](Estrutura-de-Pastas.md) · [Webhooks](Webhooks.md)

---

<p align="center">Análise concluída em Outubro/2026 · ReamurCMS — "Projeto 2027"</p>
