# ADR 0031 — SEO público: sitemap dinâmico, robots estático e Open Graph no head

- **Status:** Aceita — 2026-10-20 (F4 Tarefa 07)
- **Contexto:** A publicação (F2-17/ADR 0017, F3) entregou URLs limpas +
  tabela `rms_seo_url` (keyword ↔ query), meta title/description por página
  (projeto, page) e `config_meta_title/description` globais (F4-01). Mas a
  camada **descoberta** não existia: sem `sitemap.xml`, sem `robots.txt`,
  sem canonical e sem Open Graph/Twitter Cards — compartilhamento social
  renderizava link cru e buscadores dependiam só de links internos. A meta
  técnica de SEO do documento de fases (§Fase 3/meta técnica) cobre
  sitemap/OG/canonical como itens de lançamento.
- **Leitura real (antes de escrever — prova do estado do HEAD `1cd556b`):**
  - `catalog/controller/startup/router.php`: aliases públicas concretas
    (`contato|contact`, `orcamento|quote`, `portfolio`, `tools`) +
    resolução keyword→rota via `design/seo_url` — inventário de rotas
    públicas reais está todo aí; ninguém listava isso em XML.
  - `catalog/view/template/common/header.twig` emitia apenas `title` +
    `description` + styles — zero canonical/OG/sitemap-link.
  - `Document` tem `addLink/getLinks` (API guardada), mas o twig não itera.
  - `catalog/.htaccess` já manda tudo o que não é arquivo físico ao front
    controller — `/sitemap.xml` passa a ser rota sem arquivo estático.
  - Crawlers só consultam `/robots.txt` na raiz do domínio (RFC 9309):
    um endpoint dinâmico sob `/davidcms/dev/catalog/` ficaria invisível.
  - Modelos catalog de conteúdo (`catalog/project.getProject`,
    `catalog/page.getPage`, `tool/tool.getTool`) já filtram `status=1` —
    são o filtro pronto de "publicado" para o índice.

- **Decisões:**

  1. **`sitemap.xml` DINÂMICO sob o front controller** (`common/sitemap`,
     alias no router — nenhum arquivo estático é criado). Conteúdo: home +
     4 aliases estáticas + cada keyword de `rms_seo_url` que resolve para
     rota válida **cuja entidade está ativa** (despublicar no admin tira
     do índice na próxima requisição, sem cache próprio). `lastmod` vem de
     `date_modified`/`date_added` quando a coluna existe; ausência é
     legal no spec (omitida). Deduplicação por alvo com preferência à
     `language_id=1` (en-us, ADR 0001).
     **Renúncia declarada:** matriz hreflang ×3 (um `<url>` por idioma com
     `xhtml:link`) fica para tarefa própria — acrescenta o switcher de
     idioma ao XML e multiplica a bateria; o canonical de cada idioma hoje
     aponta para a própria URL com `?language=` não-canônica, estado
     honesto e documentado.

  2. **`robots.txt` ESTÁTICO em `dev/robots.txt`** (o deploy publica
     `dev/` como raiz; file-served pelo Apache, sem boot PHP — custo zero
     por hit). Regras: `Disallow: /admin/`, `/install/`, `/system/`.
     A diretiva `Sitemap:` exige URL absoluta e o domínio varia por deploy
     (localhost/XAMPP vs produção) — **recusado hardcodar**; a descoberta
     oficial passa a ser `<link rel="sitemap">` em todas as páginas.
     Endpoint dinâmico de robots sob o catalog foi considerado e recusado
     (invisível fora da raiz — §Contexto).

  3. **Canonical e `og:url` SÓ quando a requisição veio por caminho
     limpo.** Com `?route=` o canonical sintetizado apontaria para
     `index.php` ou para a rota interna gramatical — um sinal ERRADO aos
     buscadores (canonical incorreto é ativamente pior que ausente). A
     checagem `!str_ends_with(path, 'index.php')` garante: URL limpa tem
     canonical = origem+path atual (origem = `site_url` do wizard, nunca
     `Host` cru — proxy/preview pode mascarar); `?route=` omite.

  4. **OG/Twitter mínimos e honestos:** `og:type=website`,
     `og:site_name=config_name` (F4-01), `og:title/description` =
     título/descrição EFETIVOS da página (projeto e page já trazem
     `meta_*` próprios via `document`, com os fallbacks F4-01 intactos),
     `og:locale` mapeado de `language_directory` (`en-us`→`en_US`);
     `twitter:card=summary`. **Sem `og:image`** — não há asset de marca
     dedicado no tema; inventar um caminho quebraria o preview social.
     Renúncia registrada (futuro: banner 1200×630 gerenciável pelo painel).

  5. **XML/robots não passam por camada Language.** Não são texto de
     interface; OG/Twitter reutilizam os títulos já traduzidos por página.
     Zero langs novos nesta tarefa — o i18n testado na bateria é o das
     páginas que agora EMITEM os meta (conteúdo continua ×3 normal).

  6. **Model de seo recebe método de listagem** (`getEntriesForSitemap`)
     em vez de join no controller — SQL vive exclusivamente em Model
     (regra 01 §1), controller só orquestra/dedup/desambigua.

- **Alternativas consideradas:**
  1. **Sitemap estático gerado por cron/command** — recusado: não há camada
     de cron na casa (`catalog/controller/cron/` está vazia); dinâmico
     por requisição é o padrão do CMS e o conteúdo é barato (6 SELECTs
     no máximo);
  2. **Endpoint dinâmico de robots no catalog** — recusado, ver §2;
  3. **Canonical sempre presente (inclusive ?route=)** — recusado, ver §3;
  4. **og:image padrão sintético** (gerar PNG de marca via GD) — recusado:
     escopo + pipeline de imagem novo; entra como tarefa futura.

- **DoD verificado:** bateria `e2e_f407.sh` com 10 checks: wizard + upgrades;
  `sitemap.xml` 200 + `Content-Type: application/xml` + XML **parseável**
  (python `xml.dom.minidom`, não grep); 4 aliases estáticas presentes;
  keyword seedada `jwa` presente; **status=0 no banco some do sitemap e
  status=1 volta**; `robots.txt` 200 `text/plain` com as 3 Disallow;
  home emite og:type/site_name/title/description/url/locale + twitter:card
  + canonical + link sitemap; `/jwa` tem og:title = `meta_title` do
  projeto e canonical terminando em `/jwa`; `?route=common/contact`
  **omite** canonical mantendo og:title; `lastmod` bate regex ISO `YYYY-MM-DD`.
- **Registro de suposições (anti-alucinação):** assumi que produção publica
  `dev/` como raiz do domínio (documento de fases §9 / mapping das URLs);
  o `robots.txt` é inócuo em `/davidcms/dev/` no XAMPP. Constantes
  duplicadas (`TARGET_ROUTES` no sitemap ≡ `SEO_ROUTES` do router) ficaram
  PRIVATE e localeis — uma tabela-compartilhada foi considerada overkill
  para 3 linhas; comentário cruza as duas.
