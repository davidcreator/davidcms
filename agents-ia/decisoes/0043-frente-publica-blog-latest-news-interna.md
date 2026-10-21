# ADR 0043 — Frente pública do blog interno e Latest News migrada do feed WP externo

| Campo | Valor |
|---|---|
| Status | Aceito |
| Data | 2026-10-21 |
| Entrega | F7-02 (Fase 7 · blog interno) |
| Prova | `tools/e2e/e2e_f702.sh` **10/10** + `e2e_f305.sh` reescrita **10/10** + suíte completa **30 baterias / 0 falhas** |

## Contexto

Com a base + admin CRUD entregues em [ADR 0042](0042-blog-interno-antecipado-base-e-admin-crud.md),
a F7-02 dá cara pública ao blog: lista `/blog`, post completo por keyword
seo_url, inclusão no sitemap dinâmico e a migração da seção **"Latest News"
da home**, que até aqui lia o **WordPress externo** via HTTP (contrato da
[ADR 0023](0023-latest-news-do-blog-externo-com-cache-e-degradacao-silenciosa.md)
— crawl + cache + degradação silenciosa + CTA externo).

Esta ADR **revoga a ADR 0023** e o restante da dependência externa da
[ADR 0002](0002-blog-hibrido.md): a partir de F7-02 o site **não faz mais
nenhuma chamada HTTP de leitura ao blog antigo** — o conteúdo de notícias é
100% interno ao ReamurCMS.

## Decisão

### Catálogo público (`blog/blog`)

Espelho do padrão F2-17/F3-04 (`cms/page`, `tool/tool`):

- `index` → lista cronológica (`date_published DESC`), cards com excerpt de
  160 caracteres, data `Y-m-d`, imagem hidratada como URL absoluta
  (relativa → `site_url`; externa `http(s)` intocada), links via
  `blog/blog.info|N` que o `rewrite()` do router converte em keyword limpa
  (plumbing entregue em F7-01 — aqui só "acende");
- `info|N` → post completo: meta_title/meta_description com fallback ao
  título título e descrição HTML raw (editor do admin) — padrão `cms/page`;
  inexistente/rascunho/agendado → Action `error/not_found` (404 honesto);
- model com **coalesce idioma ativo → en-US** idêntico ao de `catalog/page`
  e leituras gating em `status=1 AND date_published <= NOW()`
  (post agendado = invisível até a data, comportamento WP de graça);
  índice `status_published (status, date_published)` cobre a query exata
  sem table-scan;
- idiomas en/pt/es + guards `index.html` nos diretórios novos.

### Sitemap dinâmico (F4-07)

`TARGET_ROUTES` aceita `blog_id`; alias estático `/blog` entra com
`weekly/0.7` (acima de páginas avulsas `0.6`, abaixo do portfolio `0.8`);
`lastmod` usa `date_modified` (`lastmodOf` já existente) — sem código novo
de último-modificado. Dedup por keyword idioma EN preferido vale também
para `blog_id` sem especialização.

### Home "Latest News" — migração do feed WP

- controller passa de `model('feed/news')->getLatestNews(3)` para
  `model('blog/blog')->getPosts(3)` hidratado no MESMA shape
  `{title, url, date, excerpt, source}` consumida pelo partial — a cirurgia
  visual é mínima;
- **degradação silenciosa preservada** (contrato da ADR 0023, só muda a
  origem): 0 posts publicados → seção `#news` some da home; home nunca
  quebra por ausência de notícia;
- **CTA interno**: sai `target="_blank" rel="noopener"` + link
  davidcreator.com externo; entra link interno direto para o post page
  (SEO interno — link juice retido) e rodapé da seção vira "ver todos os
  posts" → `/blog`;
- **crawl HTTP e arquivo `cache.news_feed` aposentados** da home — nenhuma
  request externa sai mais do servidor por leitura de conteúdo (o model
  `feed/news` fica no código como biblioteca legada, fora do caminho home;
  remoção física é tarefa de entulho do F7-03, com o importador);

### Defesa da home contra banco sem o upgrade do blog

Descoberto na dogfood: 7 baterias legadas (F2-17..F4-06) recriam o banco
dos seus setup **sem** o `upgrade_f7_01_blog.sql` no glob cirúrgico delas —
e a home inteira ia a erro com a query do Latest News sobre tabela
inexistente. Solução tomada (model `blog/blog` do catálogo):

```php
if (!$this->tablesExist()) {
    return []; // degradação silenciosa: módulo não instalado neste banco
}
```

`SHOW TABLES LIKE` cacheado por request nos dois getters. Decisão
deliberada contra **remendar as 7 baterias** ou exigir o glob geral em
cada uma: a home jamais pode depender de uma tabela "talvez existente" —
a regra também vale para o modelo real de produção (código novo pode
chegar ao servidor antes do upgrade SQL via phpMyAdmin).

### Bateria `e2e_f305.sh` — morte com honra

A bateria original validava 10 contratos do FEED EXTERNO (cache de crawl,
CTA externo, charset do XML). Reescrita para os 10 contratos do domínio
novo, mesma numeração e régua: conteúdo do banco, metadados/CTA interno,
layout, determinismo sem crawl, degradação silenciosa, i18n ×3, side-
effects, payload, ponte news→post. Nada do histórico de cobertura some
— troca a origem, mantém a assinatura "suite f305 verde" comparável.

## Consequências

- **Site não depende mais do WordPress antigo para renderizar** (nenhuma
  leitura externa em nenhuma página); a homepage fica mais rápida e
  previsível (sem timeout/cURL), e o WP passa a ser apenas ORIGEM do
  importador F7-03;
- Homepage com news encolhe ~1 request externo por visita e ganha SEO
  interno: 3+ links internos para conteúdo próprio, trocando suco externo
  por interno;
- Contrato público novo: posts publicados entram na `sitemap.xml`
  automaticamente (dedup idioma EN), agendados não;
- `feed/news (model)` vira **código morto de propósito** até F7-03:
  removê-lo antes despensa testes; mantê-lo agora evita tocar componentes
  em paralelo. Entulho documentado;
- Cache Twig do lab passou a ser limpo no setup das baterias de leitura
  de tema (era o quinto tipo de flake registrado nos 26 dias de projeto);
  twig compilação stale também causou a "falha fantasma" de C7 de f702
  na primeira corrida.

## Alternativas consideradas

1. **Manter feed WP na home + blog interno na seção própria** (coexistir)
   — rejeitada: 2 fontes de notícia divergentes, manutenção dupla,
   exatamente a dívida que F7 veio matar.
2. **Remendar as 7 baterias legadas** (glob upgrades geral) em vez do
   model defensivo — rejeitada: resolve sintoma, não o problema real do
   deploy em produção (código na frente do SQL). Falha de produção seria a
   mesma: home inteira offline.
3. **Paginação na lista `/blog`** desde já — adiada: seed destina < 50
   posts (importer batelado; reavalia à F7-03, com `LIMIT 50` hoje e
   `page=N` depois se necessário);
4. **Extrair posts recentes no sitemap com changefreq diário** — rejeitada:
   monthly/0.6 suficiente para seed < 50 posts; changefreq alto sem lastmod
   granular é ruído SEO.

## Verificação

`tools/e2e/e2e_f702.sh` (10 checks): `/blog` vazio+degradação, seed multi-
idioma, ordem DESC, keyword pública e rewrite reverso, rota gramatical
estável, meta description, rascunho/agendado 404, home Latest News
interna, sitemap com alias+keyword+lastmod, fontes sem feed, guards/idiomas.
`e2e_f305.sh` reescrita (10 checks, contrato da seção news novo).
Suíte pós-F7-02: **30 baterias, 0 falhas**.

---

ADR revoga: [0023 — Latest News do blog externo](0023-latest-news-do-blog-externo-com-cache-e-degradacao-silenciosa.md)
e (restante da) [0002 — Blog híbrido](0002-blog-hibrido.md).
Relacionadas: [0042 — Blog interno base+admin](0042-blog-interno-antecipado-base-e-admin-crud.md);
seção pública `blog/`, alias F7-01.
