# ADR 0019: Tema base do catalog, home composta em 10 partials Twig e seed de conteúdo real (davidalmeida.xyz)

- **Status:** Aceita
- **Data:** 2026-10-18
- **Autor:** Cascade (Coevolve AI)
- **Tarefa:** Fase 3 — Tarefa 01 (F3-01)
- **Regras relacionadas:** agents-ia/regras/00-AGENT-PROTOCOL.md; adendos do ADR 0001, 0016 (ErrorException), 0017 (publicação), 0018 (hardening)

## Contexto

A F2-17 publicou o catalog com home mínima (grade de projetos + chrome
simples). A F3 inicia a fidelidade visual ao site de referência
`davidalmeida.xyz` (raspado em 2026-10-18 como fonte canônica de copy).
F3-01 entrega o **tema base e a home composta**: hero, about, metodologia,
depoimentos, skills, serviços, portfólio filtrável, ferramentas, clientes e
contato, com conteúdo real semeado por SQL idempotente.

Restrições vindas do mantenedor e das decisões anteriores:

- Hero **estático** nesta tarefa (Three.js/3D é F3-02, cf. Guia-ThreeJS §9).
- Sem feed RSS no blog (blog é módulo futuro).
- Imagens **placeholder CSS** (monograma da inicial + gradiente) — nenhum binário
  de imagem entra no pacote.
- Engine lean preservada: **não** entra nada exclusivo do upstream
  ReamurCMS público (módulos blog/chat/mooc/landpage, system de 111 MB).
- Lang é a fonte de verdade do texto (ADR/regra F2-17); controller monta dados.

## Decisão

### 1 — Tema base (`catalog/view/css/theme.css`)
- Variáveis de design: `--bg`, `--ink`, `--accent: #6d28d9`, container 950 px.
- Classes utilitárias de seção: `.hero`, `.section`, `.step-card`, `.chip`,
  `.pgrid`/`.pcard`, `.badge`, `.quote-card`, `.client-pill`, `.tool-card`,
  `.site-header`, `.site-footer`; responsivo em 640 px.
- Injetada via `$this->document->addStyle()` + `getStyles()` no
  `common/header` (ordem dos estilos preservada — estilos internos primeiro).

### 2 — Home composta em 10 partials
- `catalog/view/template/partial/`: `hero`, `about`, `methodology`,
  `testimonials`, `skills`, `services`, `portfolio`, `tools`, `clients`,
  `contact` (o `.twig` cada).
- `common/home.twig` vira orquestradora slim: `{{ header|raw }}` + `<main>`
  com 10 `{% include 'partial/...' %}` + `{{ footer|raw }}`.
- **Decisão consciente:** o chrome continua dupla pipeline `{% verbatim %}header|raw/footer|raw`{% endverbatim %}
  (sub-controllers renderizados) e o `layout/base.twig` proposto no
  Guia-Twig §8 **não** é adotado agora — refatoração adiada para a F3-02,
  quando o hero ganhar `hero-3d.twig` e o slot de assets WebGL.

### 3 — Dados estáticos de copy em lang (sem tabela nova)
- Sobre (3 parágrafos), metodologia (6 etapas reais), identidade visual
  (Unity/Credibility/Attraction), skills (papéis + tech + platforms +
  programs + scm), serviços (3 grupos × 5 itens) e redes sociais (6 URLs
  reais) vivem em `catalog/language/{en-us,pt-br,es-eu}/common/home.php`.
- **Convenção lang = string apenas:** listas usam separador `|` com
  `explode()` no controller. Motivo: `Language::get()` faz cast `(string)`
  — array na chave dispara "Array to string conversion" (ErrorException →
  404 do ADR 0016). Bug real encontrado e corrigido nesta tarefa.

### 4 — Seed SQL idempotente com conteúdo real
- `install/sql/upgrade_f3_01_seed_conteudo_davidalmeida.sql`
  (INSERT ... ON DUPLICATE KEY UPDATE / checagem de existência):
  10 projetos (keywords reais do site: `jwa`, `steel-of-death`, `farmer`,
  `chameleon`, `hiperion-gamer`, `rc`, `pitbul-color` [1 L legado],
  `cat-fe`, `classic-car`, `selva-dev`), 3 categorias, 3 depoimentos
  literais, 5 logos de clientes, 4 ferramentas e 2 páginas legais
  (`cookies`, `politicas-internas`) com copy verbatim da raspagem.
- **Mapa projeto→categoria inferido** (assunção): VI = jwa, steel-of-death,
  hiperion-gamer, selva-dev; Brand = farmer, chameleon; Illustration =
  rc, pitbul-color (1 L), cat-fe, classic-car.
- Seed **somente en-us** (language_id=1); pt-br/es-eu resolvem por
  coalesce no model (decisão F2-17 mantida).
- `hiperion-gamer` é card **externo** (`url <> ''` → `_blank`); regra
  real do projeto é `url != ''` — coluna inventada `url_is_external`
  foi removida antes da entrega (leitura-antes-de-escrever no model).
- Ferramentas raspadas expõem apenas `/catalog/tools/open?id=N&src=home`
  (URL final não consta no HTML) → seed com `url = ''` até o admin
  preencher pelo painel.
- Featured: ids 1–4.

### 5 — Filtro da grade por `?category_id=`
- Home aceita `category_id` e destaca o chip ativo; grade usa as 3
  categorias semeadas (open/idioma coalesce herdados da F2-17).

### 6 — Footer dinâmico sem 404
- `common/footer` consulta páginas por keyword **novo método**
  `getPageByKeyword(string $keyword): array|null` em
  `catalog/model/catalog/page.php` (coalesce idioma ativo → en-US).
- Links Cookies/Políticas Internas só são emitidos se a página existir —
  sem seed, não há link quebrado; **nunca** 404 por link do próprio chrome.
- Copyright com `sprintf('%s', date('Y'))`; redes sociais do lang home.

### 7 — Imagens: monograma + gradiente
- Sem binários: card de projeto/cliente renderiza monograma da inicial
  com gradiente CSS determinístico quando `image = ''` (estado do seed).

## Assunções declaradas

1. Raspagem de 2026-10-18 é a fonte canônica da copy (EN); traduções
   pt/es geradas a partir dela e podem receber revisão editorial fina.
2. Mapa projeto→categoria foi **inferido** (item 4); se o mantenedor
   tiver o mapa real, basta UPDATE no seed (idempotente).
3. `pitbul-color` com 1 L mantido — keyword legada do site de produção.
4. SEO (meta/OG/sitemap) permanece como na F2-17; refinamentos não entraram.
5. Tradução dos 3 parágrafos de `about` e etapas do método não altera
   semântica; revisão do mantenedor bem-vinda via lang.

## Consequências

### Positivas
- Home fiel ao site de referência em estrutura e copy, com i18n pt/es
  por coalesce e zero tabela nova (engine lean).
- Seed re-aplicável (idempotente) — pacote pode ser pulado/reaplicado.
- Footer nunca emite link morto; ausência de seed é estado válido.

### Negativas / débitos
- Hero estático até a F3-02; `layout/base.twig` adiado (item 2).
- `tools.url = ''` até preenchimento manual no admin (produção usa
  endpoint `open?id=` que não revela a URL final).
- Monogramas sem logo real — dependerá de upload de imagens em fase futura.

## Validação

- **E2E novo:** `tools/e2e/e2e_f301.sh` — **13/13** (wizard, seed idempotente
  2×, home composta, hero+social, metodologia 6+3, skills, serviços 3×5,
  grade com filtro, keywords reais 200, detalhe de projeto, footer dinâmico
  com TRUNCATE/re-seed, coalesce pt, smoke dos formulários públicos).
- **Regressão:** suíte consolidada **118 asserts, 0 FAIL** (check 16 do
  f217 atualizado para a nova copy pt/es de contato — marcadores de idioma,
  mesmo objetivo do assert original).
- **PHPStan:** `[OK] No errors`.
- **Correções reais desta validação:** lang-arrays → pipe+explode (item 3);
  linha órfã `$$` removida dos langs de footer (variable-variable →
  ErrorException); `load->model('catalog/page')` corrigido (era `cms/page`,
  model inexistente); DELETE do seed passou a cobrir `page_id=1..2` em
  `seo_url` (re-apply sobre banco com fixtures f2xx falhava com duplicate
  key — idempotência agora provada com 3 applies consecutivos sobre banco
  sujo); check e2e do método ajustado para título canônico
  "Knowing the Client and the Company".
