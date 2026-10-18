# ADR 0017: Publicação do site — catálogo one-page, SEO URLs e formulários públicos (contato + orçamento)

- **Status:** Aceita
- **Data:** 2026-10-18
- **Autor:** Cascade (Coevolve AI)
- **Tarefa:** Fase 2 — Tarefa 17 (F2-17)
- **Regras relacionadas:** agents-ia/regras/00-AGENT-PROTOCOL.md

## Contexto

Todo o front foi construído (F2-8…F2-16 admin + engine), mas o site público (`catalog/`)
ainda mostrava apenas um hero temporário "ReamurCMS". A publicação do site precisava:

1. Expor o conteúdo cadastrado no admin (projetos, categorias, páginas CMS, depoimentos,
   logos de clientes, ferramentas) mantendo aUI multilíngue coalesce ativo→en-US.
2. Oferecer formulários públicos de **contato** e **orçamento** (pedido de cotação),
   conforme escopo travado com o usuário (orçamento público SIM; páginas SEO de projeto SIM;
   blog/feed NÃO; tema minimalista SIM por agora — tema Three.js fica para a Fase 3 do roadmap).
3. Integrar `seo_url` (key/value por idioma) para URLs amigáveis (`/jwa`, `/internal-policies`).

Restrições permanentes: não desfazer o trabalho existente; escopo cirúrgico;
validar com PHPStan + bateria E2E + suíte regressiva antes da entrega.

## Decisão

### 1 — Models do catálogo (9 novos)
- `design/seo_url`: resolve keyword↔query com cache por `language_id`; coalesce
  idioma ativo→`language_id` de fallback (1 = en-US do seed).
- `catalog/project`: `getProjects()` (featured DESC, sort_order), `getProject()`
  (apenas `status=1`, coalesce + metas SEO), `getProjectImages()`,
  `getProjectCategories()`.
- `catalog/category`: `getCategoriesWithProjects()` — só categorias ativas com ao
  menos um projeto ativo (EXISTS).
- `catalog/page`, `marketing/testimonial`, `marketing/client_logo`
  (sem coalesce de nome — o nome é a identidade), `tool/tool`.
- `communication/contact_message`: `addContactMessage()` com `status=0`,
  IP do visitante e `NOW()`.
- `checkout/quote`: `getInitialStatusId()` (menor `order_status_id` semeado,
  rótulo `Received`), `addQuote()` criando `rms_order` com `customer_id=0`,
  `total=0.0000`, `rms_order_product` com `price/total=0.0000` (snapshot textual
  do serviço no idioma ativo) e `rms_order_history` inicial consistente.

### 2 — Controllers
- `catalog/controller/common/home.php` reescrito: one-page com seções estáticas
  por idioma (about/services/skills/methodology/contato) + dados reais do schema
  (portfólio, depoimentos, logos, ferramentas). Carrega 5 models.
- `catalog/controller/catalog/project.php`: grade pública com filtro
  `?category_id=N` validado (intval) e página individual `info|{id}` com metas SEO
  e 404 honesto via dispatch `error/not_found` (o qual auto-marca o status HTTP).
- `catalog/controller/cms/page.php` — wrapper público de páginas CMS (content bruto
  do banco por idioma coalesce).
- `catalog/controller/common/contact.php` — GET/POST PRG com flash de sessão
  (`['type','text']` one-shot), validações reguladas pelo schema e honeypot
  `company` (bot recebe flash de sucesso e redirect sem gravação).
- `catalog/controller/checkout/quote.php` — taxonomia estática de 15 serviços
  (`SERVICES` const com chaves `gd_*`, `wd_*`, `seo_*`); POST `services[]`
  filtrado contra whitelist antes de gravitacionar ao `addQuote()`.
- `catalog/controller/startup/router.php`: aliases curtas
  (`contato|contact`, `orcamento|quote`, `portfolio`) + resolução de segmento
  único por `seo_url` (`keyword → project_id=N|page_id=N`); callback `rewrite`
  reescreve links internos `catalog/project.info|N` / `cms/page.info|N` como
  keyword limpa quando existir (`/jwa` em vez de `?route=...`).

### 3 — Idiomas (en-us, pt-br, es-eu)
- Grupos: `common/home` (reescrito), `common/contact`, `catalog/project`,
  `checkout/quote`, `cms/page`.
- `common/header.php` ganhou `text_brand` + `text_nav_*` (home/sobre/portfólio/
  contato/orçamento) nos 3 idiomas.

### 4 — Tema
Minimalista com estilos inline por view (coerente com a decisão de adiar o tema
Three.js para a Fase 3 do roadmap oficial).

## Consequências

### Positivas
- Site publicado end-to-end: home one-page, grade/portfólio, SEO URLs, contato e
  orçamento gravam nas respectivas caixas do admin (inbox + pedidos 0.0000).
- Honeypot descarta bots sem qualquer efeito colateral; validações rejeitam dados
  fora da régua do schema (retorno 200 com erros repovoados).
- 404 honesto para keywords/projetos/páginas inexistentes ou inativos.
- Resolução SEO tolera idioma sem keyword coalescendo ao en-US (a URL `/jwa`
  funciona também com `?language=pt-br` ativo).
- Suíte: 17 checks novos (F2-17) + 85 da regressão = 102/102 verde; PHPStan
  oficial `[OK] No errors` (baseline intacta).

### Negativas / limitações assumidas
- URLs amigáveis só são geradas para chaves `project_id`/`page_id` com keyword;
  demais rotas seguem `?route=` (estável e aceito até a Fase 3).
- O seletor de idioma em páginas com keyword cai na home ao trocar de idioma
  (o `route` canônico não atravessa a keyword); comportamento documentado, a
  revisitar com o tema da Fase 3.
- Taxonomia de serviços do orçamento é estática no controller (whitelist); um CRUD
  de serviços é candidato à Fase 4/5 se necessário.
- `client_logo` não tem `date_added` no schema do projeto (a bateria E2E assumia);
  fixtures ajustadas — atenção em futuros CRUDs.

## Validação executada
- `php -l` 30/30 arquivos novos/alterados; auditoria idioma×controller: nenhuma chave faltando.
- PHPStan oficial: `[OK] No errors` (baseline F2-0 intacta).
- Bateria `tools/e2e/e2e_f217.sh`: 17/17 — wizard, fixtures, home com seções
  reais, rewrite SEO out, keyword in, 404s, filtro de categoria, contato válido/
  honeypot/validações, orçamento válido/honeypot/validações (order + itens +
  histórico), página CMS por keyword, aliases, idiomas pt/es + coalesce, integração
  admin (inbox + pedidos).
- Regressão: `e2e_regressao.sh` 10/10 + F2-12 14/14 + F2-13 14/14 + F2-14 15/15 +
  F2-15 15/15 + F2-16 17/17.
