# ADR 0042 — Blog interno antecipado: base de dados + admin CRUD (revoga parcial da ADR 0002)

| Campo | Valor |
|---|---|
| Status | Aceito |
| Data | 2026-10-21 |
| Entrega | F7-01 (Fase 7 · blog interno) |
| Prova | `tools/e2e/e2e_f701.sh` **10/10** + suíte completa **29 baterias / 331 asserts / 0 falhas** |

## Contexto

A [ADR 0002](0002-blog-hibrido.md) ("Blog híbrido") decidiu que o site
lançaria com a seção "Latest News" lendo o **WordPress antigo** (origem
externa via HTTP), e que o blog interno só migraria **pós-lançamento**.

Em 2026-10-21, com a Fase 6 concluída e o go-live adiado (decisão do
usuário: **concluir o projeto por completo em dev** antes de qualquer build
prod "em nível de uso real"), o usuário escolheu explicitamente iniciar o
**blog interno AGORA** — permitindo lançar com SEO em domínio próprio desde
o dia 1, sem dívida de migração 301 de URLs do WP no meio do caminho.

Esta ADR **revoga parcialmente a ADR 0002**: mantém-se o objetivo do blog
como produto interno do ReamurCMS, mas o cronograma "somente pós-lançamento"
e a estratégia híbrida (homepage lendo feed WP externo) deixam de valer.
O bloco F7 fica:

- **F7-01 (esta)** — schema + admin CRUD completo + plumbing SEO de rotas
  + bateria e2e + esta ADR;
- **F7-02** — frente pública: lista `/blog` e post por keyword (controller
  catálogo `blog/blog`, sitemap, seção "Latest News" da home lendo os posts
  INTERNOS — o feed WP externo some);
- **F7-03** — importador WordPress (WP REST `/wp-json/wp/v2/posts` público
  de davidcreator.com) + redirects 301 das URLs antigas.

## Decisão

Espelho do batelado de módulos F2 (`cms/page` é o blueprint), com quatro
mudanças deliberadas:

1. **Cronologia, não hierarquia**: posts não têm `parent_id`/`sort_order`;
   a ordenação é `date_published DESC` (campo novo `DATETIME`, opcional no
   form → fallback `NOW()`).
2. **Imagem destaque como texto** (`MEDIUMTEXT`→`VARCHAR(255)`): sem
   componente de upload de propósito — o importador F7-03 baixará e será
   dono das imagens; operação humana aceita path relativo ou URL externa.
3. **Coluna extra `date_published`** com índice composto
   `KEY status_published (status, date_published)` (cobre a query pública
   exata: status=1 ORDER BY date_published DESC).
4. **Idempotência total do upgrade**: `CREATE TABLE IF NOT EXISTS` + grants
   via `JSON_ARRAY_APPEND` protegido por `JSON_SEARCH IS NULL` (padrão já
   consolidado nos upgrades F2—F6).

### Schema

- `rms_blog_post` (`post_id` PK AI, `image` VARCHAR(255) DEFAULT '',
  `status` TINYINT, `date_published`, `date_added`, `date_modified`)
- `rms_blog_post_description` (PK composta `post_id + language_id`, `title`
  VARCHAR(255), `description` MEDIUMTEXT, `meta_title`, `meta_description`)
  — mesmo molde multilíngue de `rms_page_description`.
- Keywords SEO moram na **`rms_seo_url` global** (`key = 'blog_id=N'`,
  por idioma): uma keyword é ÚNICA no ecossistema inteiro — o blog não
  pode "invadir" keyword de page/projeto/tool (prova cruzada na bateria).

### Admin (rota `blog/blog`)

- Controller espelha `cms/page`: `index/add/delete|N/edit|N`, delete
  exclusivo por POST, formulário por idioma em fieldsets, `postPayload` normalizado (chaves sempre presentes), validação: título EN
  obrigatório ≤255; keyword regex `^[a-z0-9]+(?:-[a-z0-9]+)*$` + unicidade
  cross-entidade via `keywordTaken`; `date_published` aceita datetime-local
  (`2026-10-21T09:30`) e é normalizada para MySQL (`T`→espaço, `:00` se
  faltar segundos) — lição aplicada após o red inicial do mini script.
- `text_manage_blog` + card no dashboard com gate
  `hasPermission('access', 'blog/blog')`, nos 3 idiomas.
- Grants no grupo **Administrator** pelo upgrade SQL.

### Plumbing SEO já entregue (invertendo o custo da F7-02)

Para a F7-02 ser só "controller de catálogo + twigs + sitemap", o router
já sai pronto nesta F7-01: `ALIASES 'blog' => 'blog/blog'`,
`SEO_ROUTES 'blog_id' => 'blog/blog.info|'`, e o `rewrite()` reverso
aceita `blog/blog.info|N` (link `{{ link }}` → `/keyword`). Provado por
greps na bateria — não há render por keywords ainda (sem controller).

## Consequências

- **Build prod NÃO roda**: o usuário segurou o go-live até o projeto
  completo em dev; o zip build-prod-2026-10-21 ficou com f6-06 e será
  regenerado ao fim do bloco F7. O admin do site ao vivo NÃO TEM blog
  até esse momento. Cada tabela do blog nasce apenas por
  `install/sql/upgrade_f7_01_blog.sql` no phpMyAdmin (cPanel, sem SSH).
- A homepage continua lendo o feed do **WordPress antigo** até a F7-02
  (seção "Latest News" ainda não migra — isso é o próximo sub-bloco).
- Credenciais de **Mercado Pago produção** continuam pendentes (§5.7) —
  mantida a decisão do usuário de tratar só pós-go-live.
- O seed não entrega nenhum post inicial de fábrica (diferente de
  page/tools com conteúdo demo); a régua "estado inicial vazio orienta a
  primeira ação" (mensagem `text_no_results`) é mantida.

## Alternativas consideradas

1. **Adiar para pós-lançamento** (status quo da ADR 0002) — rejeitada pelo
   usuário: lançaria sem indexação do próprio blog e criaria migração 301.
   Poupa tempo agora, custa mais depois.
2. **Modelo `blog_id` reservado mas front público já na F7-01** — rejeitada:
   controladores públicos exigem render/twigs/sitemap; melhor fatiar em 3
   entregas auditáveis (F7-01/02/03), cada qual revertível.
3. **VARCHAR para `description`** em vez de MEDIUMTEXT — rejeitada: posts
   reais do WP excedem 64 KB com HTML; tabela `_description` separada justamente segue a precedência page (consistência > economia de coluna).
4. **Grants por user_group_id=1 em vez de `WHERE name='Administrator'`** —
   rejeitada: name é a âncora portátil (instalações existentes podem ter
   recriado grupos); padrão já validado nos upgrades anteriores.

## Verificação

`tools/e2e/e2e_f701.sh` (10 checks, estado virgem por execução):
tabelas nascem no upgrade pós-wizard; 2ª passagem do upgrade não duplica
grants (guard JSON_SEARCH); card no dashboard com gate de ACL; lista vazia
200 com mensagem; add multilíngue persiste post + 3 descrições + 3 keywords
`blog_id=1` + `date_published` 09:30 respeitada; keyword `Meu Post!`
rejeitada (formato) sem INSERT; keyword de page (`cookies` criada no
próprio teste) rejeitada no blog (unicidade global); edit persiste swap de
título/keywords, status vai a rascunho quando o checkbox sai do POST e
image zera; lista mostra tudo; delete (POST) remove main + descrições + 3
keywords; greps de fonte cobrem router (`blog_id`, alias, rewrite),
text_manage_blog ×3, card no dashboard e guards `index.html` nos 6
diretórios novos. Suíte completa pós-entrega: **29 baterias, 0 falhas**.

---

ADR revoga parcialmente: [0002 — Blog híbrido](0002-blog-hibrido.md)
Relacionadas: F2-10/F2-11 (padrão CRUD+grant que esta feature espelha), [0038](0038-mapa-301-legado-htaccess-cirurgico-cmsbase-dinamico.md) (mapeamento de rotas/URLs).
