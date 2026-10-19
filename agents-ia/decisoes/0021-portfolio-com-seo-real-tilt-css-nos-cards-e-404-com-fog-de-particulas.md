# ADR 0021: Portfólio com SEO real, tilt CSS nos cards (Cena 3.1) e 404 com fog de partículas (Cena 4)

- **Status:** Aceita
- **Data:** 2026-10-19
- **Autor:** Cascade (Coevolve AI)
- **Tarefa:** Fase 3 — Tarefa 03 (F3-03)
- **Regras relacionadas:** agents-ia/regras/00-contexto-global.md, 03-protocolo-anti-alucinacao.md; docs/Implementacao-ThreeJS.md §7, §8; ADR 0016, 0019, 0020
- **Decisão de escopo do mantenedor (ask_user, 2026-10-19):** a Cena 2 (logo GLB, cronograma S3) depende do asset real `logo-da.glb` que ainda não existe — o mantenedor escolheu avançar com **Portfólio + tilt CSS + 404** (itens sem bloqueio de asset) e adiar a Cena 2 até o GLB existir. Oferecidas e recusadas: anexar o GLB agora, GLB placeholder gerado (violaria "zero binários artificiais" das ADRs 0019/0020), páginas das ferramentas.

## Contexto

Após F3-01 (tema + home composta + seed real) e F3-02 (hero Three.js, Cena 1),
a Fase 3 continua com itens do guia que não dependem de binários:
a **Cena 3 etapa 1** (tilt 3D CSS nos cards de portfólio, Guia §7) e a
**Cena 4** (página 404 com "fog" de partículas, Guia §8). Durante a preparação,
a leitura-antes-de-escrever revelou três dívidas/pendências reais tratadas no
mesmo pacote (escopo cirúrgico — todas estavam no caminho dos objetivos):

1. **SEO nominal:** `Document::setDescription()` era chamado pelos controllers
   da home e do projeto, mas o `common/header.twig` **nunca emitia**
   `<meta name="description">` — a meta existia só no PHP, invisível ao HTML.
2. **Dívida F2-17:** `catalog/project_list.twig` e `catalog/project_info.twig`
   estilizavam-se com `style="..."` inline (porque o `theme.css` só existia
   desde a F3-01 e eles são de antes dela).
3. **404 estática:** o template de erro não usava a infra WebGL entregue na
   F3-02 — o Guia §8 pede fog de partículas reaproveitando
   `components/particle-field.js`.

Restrições herdadas: engine lean; zero binários de imagem gerados
(fallbacks CSS — ADRs 0019/0020); textos 100% na Language; escopo cirúrgico.

## Decisão

### 1 — SEO real: `<meta name="description">` emitida de verdade

- `catalog/controller/common/header.php` passa a expor
  `$data['description'] = $this->document->getDescription()` ao partial
  (mesma pipeline de `getTitle()`/`getStyles()`).
- `common/header.twig` emite
  `{% if description %}<meta name="description" content="{{ description }}">{% endif %}`
  logo após o `<title>` — rotas que não definem descrição simplesmente não
  emitem a tag (sem `<meta content="">` vazia).
- **Efeito imediato, sem tocar controllers de rota:** home (`text_tagline`),
  `/jwa` e demais projetos (`meta_description` do seed/CRUD), e qualquer
  rota futura que chame `setDescription()` passam a ter SEO efetivo.
  Principiante de coalesce: a meta segue o idioma ativo como o título.

### 2 — Portfólio migrado ao tema do site (zera estilos inline)

- `catalog/project_list.twig` — reescrito sobre as classes do `theme.css`:
  `.container`/`.page-head`/`.lead`/`.filter-chips .chip` (filtro com estado
  ativo igual ao da home, chip "All" ativo sem `category_id`)/`.pgrid`/`.pcard`
  (cards com `.thumb` monograme-fallback via `p.name[:1]`, `.body`, `.badge`)
  → os cards da grade **ganham o tilt automaticamente** (item 3) e a página
  fica visualmente idêntica à grade da home (mesma família de classes).
- `catalog/project_info.twig` — reescrito: `.page-head`/`.back-link`/
  `.chip-row`(linha 111 do tema — `.chip` compartilhada)/`.project-cover`/
  `.prose`/`.fact-table`/`.gallery-grid` (galeria F2-17 preservada —
  `getProjectImages` intacto, somente a marcação migra).
- `theme.css` recebe **somente** as classes novas desses templates
  (`.site-main`, `.page-head`, `.back-link`, `.project-cover`, `.prose`,
  `.fact-table`, `.gallery-grid`) — sem duplicar `.chip`/`.chip-row` que já
  existiam (duplicata achada e removida na revisão).
- **Nenhuma chave nova de lang:** os templates usam exatamente as chaves F2-17
  (`heading_title`, `text_lead`, `text_filter`, `entry_*`, `button_back*`).
  pt/es já eram 1:1 com en — paridade conferida (13 chaves ×3 languages).
- Comportamento não-migrado (deliberado): monograma fallback na grade usa
  `p.name[:1]` — padrão idêntico ao `partial/portfolio.twig` da home.

### 3 — Cena 3 etapa 1: tilt 3D CSS nos `.pcard` (Guia §7, exato)

```css
.pcard { transition: transform .25s ease, box-shadow .25s ease; transform-style: preserve-3d; }
.pcard:hover { transform: perspective(800px) rotateX(4deg) rotateY(-4deg) translateY(-4px);
               box-shadow: 0 10px 24px rgba(17,24,39,.08); }
@media (prefers-reduced-motion: reduce) { .pcard:hover { transform: none; } }
```

- Aplicado à classe `.pcard` existente (nossa `card-project` do guia) — afeta
  a grade da home **e** a página `/portfolio` (mesma classe), sem JS.
- `transition` do `.pcard` subiu de .15s → .25s (valor exato do guia).
- **Etapa 2 (WebGL `portfolio-3d.js`) fica no backlog pós-lançamento** —
  explícito no Guia §7.

### 4 — Cena 4: 404 com fog de partículas (Guia §8)

- `js/three/scenes/not-found.js` — SceneModule completo
  (`registerScene('not-found', ...)` + `play/pause/resize/dispose`):
  - **Reusa** `components/particle-field.js` (nada duplicado);
  - **Drift por partícula**: array de velocidades verticais independentes
    (`velocities[i] = (0.5+rand)*drift`), integrado no `frame()` com
    **anti-salto de clock** (`dt` limitado a 50 ms após pausa — evita
    "queda em bloco" ao voltar à aba, pois usamos `performance.now()`
    explícito em vez de `THREE.Clock` para o drift);
  - **Wrap**: partícula que passa de `-spread/2` volta a `+spread/2`
    (chuva contínua/rotação suave do campo `speed * dt` como o hero);
  - `data-count=120` (valor exato do guia), `data-color=#8a4fff`,
    `data-speed=0.05`, `data-drift=0.4`;
  - `DPR ≤ 2` (budget §10), `try/catch` no WebGLRenderer → `null`
    (fallback permanece — mesmo contrato das outras cenas).
- `error/not_found.php` — passa a declarar `has_3d`/`asset_base`/`nf3d`
  (números e cor não são texto de UI — mesmo argumento dos `hero3d` da
  ADR 0020; **nenhuma lang nova**).
- `error/not_found.twig` — marcação espelha o `hero-3d.twig`:
  `.card-404--3d` com `.nf-3d__canvas` (`aria-hidden`, `data-scene`,
  `data-fallback` presente para `clearFallback`)+ `.nf-3d__content` com os
  textos traduzidos intactos ×3 (pt-br/es-eu já existiam, conferido).
- `main.js` registra a cena (`import './scenes/not-found.js'`). Sem custo de
  rede extra: o arquivo tem ~2 KB e o vendor é cacheável (Guia §12).
- CSS `.card-404--3d` no `theme.css`: mesmas camadas do hero (canvas z-0,
  fallback radial gradient z-0, conteúdo z-1) com paleta escura da família
  `#8a4fff`; guard redundante para `prefers-reduced-motion` esconder canvas.

### 5 — Escopo do slot WebGL permanece explícito por rota

- home: `has_3d` (hero-particles) — inalterado;
- erro 404: `has_3d` (not-found) — **novo**;
- portfólio (`/portfolio`, `/jwa`): **continua sem** import map nem
  `data-scene` (Cena 3.1 é CSS-only — check 10 do e2e_f303 afirma isso).

## Assunções declaradas

1. Tilt aplicado a `.pcard` (classe existente) e não à `.card-project`
   literal do guia — renomear criaria churn sem ganho; o CSS produzido é
   idêntico ao snippet do Guia §7.
2. O gradiente da 404 é CSS (padrão "zero binários" — a alternativa do guia,
   `lost.webp`, segue adiada pela mesma razão do `hero-fallback.webp`
   da ADR 0020).
3. Valores visuais (`120`, `0.4` drift) são os do Guia §8; se o mantendor
   preferir mais/menos densidade, é um número no controller.
4. Coalesce `title`/`description` pelo idioma ativo é o comportamento desejado
   (espelha o `<title>` já existente).

## Consequências

### Positivas
- SEO passa de nominal → efetivo em home + todos os projetos (1 `<meta>` genérica).
- Portfólio visual e código unificados ao tema (zero inline, zero dívida F2-17
  nas páginas públicas mais visitadas).
- Cards ganham microinteração 3D **acessível** (reduced-motion) e gratuita
  em GPU (CSS transform, sem rAF).
- 404 vira showcase consistente da stack (mesma cena-registrável/pausável,
  mesmo fallback, mesma pipeline Language).
- Absolutamente **nenhum** arquivo PHP de engine/system/banco foi tocado.

### Negativas / débitos
- Cena 2 (logo GLB) segue adiada — depende do asset (mantenedor ciente).
- Meta `og:*`/Twitter Cards não entraram (não pedidas; sugestão separada).
- A 404 carrega o vendor three (~166 KB gzip) — aceitável por ser cacheável
  (Guia §12 §imutável); se virar problema, `data-scene` na 404 pode ser
  servida só em `?debug`.

## Validação executada (não declarada)

- **`tools/e2e/e2e_f303.sh` (10 checks, 10/10):**
  1. `/portfolio` usa `.pgrid`/`.pcard` + chip "All" ativo;
  2. tilt no `theme.css` (preserve-3d, perspective, rotateX/Y, reduced-motion);
  3. `<meta name="description">` na home **e** em `/jwa` (com texto real do seed — `Visual identity for Juvenile World Academy`);
  4. `/jwa`: `meta_title` do seed no `<title>` + `chip-row`;
  5. `/jwa`: sem `style=` inline no HTML (política zero-inline do tema);
  6. 404: status HTTP 404 + `data-scene="not-found"` + `data-count=120` + `data-drift=0.4` + fallback + import map;
  7. `scenes/not-found.js` 200: SceneModule (registerScene/play/pause/resize/dispose, `velocities[]`, `needsUpdate`, `drift`, DPR≤2);
  8. `main.js` importa hero-particles **e** not-found e chama `boot()`;
  9. 404 em pt-br/es-eu traduzida (textos reais das langs ×3) com cena;
  10. escopo: `/jwa` **sem** `"three"` e **sem** `data-scene` (Cena 3.1 CSS-only); `/portfolio` 200 sem wrapper de erro.
- **Suíte completa:** regressao (10) + f212..f218 (14+14+15+15+17+17+16) +
  f301 (13) + f302 (10) + f303 (10) = **151 asserts, 0 FAIL** (na ordem
  `e2e_regressao.sh` → `f2xx` → `f301` → `f302` → `f303`).
- **PHPStan:** `[OK] No errors`.
- **JS:** `node --input-type=module --check` nos 2 arquivos tocados
  (`main.js`, `scenes/not-found.js`).
- **`php -l`** nos 2 controllers tocados (`common/header.php`,
  `error/not_found.php`).
- **Incidente de ambiente desta validação (sem impacto no pacote):** o
  storage volátil do sandbox reciclou `/tmp/fui/dev` entre passos;
  `tools/e2e/setup_sandbox.sh` restaurou (~28 s) e a f301 refez o
  wizard+seed antes da f303 (bateria leve não-destrutiva por contrato).
