# ADR 0020: Hero Three.js (Cena 1 — partículas + parallax) e slot WebGL condicional no chrome do catalog

- **Status:** Aceita
- **Data:** 2026-10-19
- **Autor:** Cascade (Coevolve AI)
- **Tarefa:** Fase 3 — Tarefa 02 (F3-02)
- **Regras relacionadas:** agents-ia/regras/00-contexto-global.md, 03-protocolo-anti-alucinacao.md; docs/Guia-ThreeJS.md §9, docs/Implementacao-ThreeJS.md §3–§11; ADR 0016 (ErrorException), ADR 0019 (tema F3-01; hero estático provisório)

## Contexto

O item "Hero com Three.js" do checklist Fase 3 (Análise §8) é o próximo passo
após a F3-01 entregar o tema base e a home composta. A ADR 0019 registrou o
hero **estático** como estado provisório com o débito explícito: *"3D na
F3-02; slot de assets WebGL no chrome"*. Esta tarefa quita esse débito.

Restrições herdadas:

- Engine lean: **zero** conteúdo exclusivo do upstream (sem módulos blog/chat/mooc).
- Vendor **self-hosted e pinado** (Guia §3 — sem CDN em runtime).
- Nenhum texto da cena fora da Language; a cena é decorativa (`aria-hidden`).
- Escopo cirúrgico: sensível ao DoD real, sem reescrever o chrome.

## Decisão

### 1 — Árvore de front-end (Guia §2, exatamente)

```text
dev/catalog/view/
├── js/
│   ├── vendor/
│   │   └── three.module.js           # r169, build .module.min (687 KB min / 166 KB gzip)
│   └── three/                        # Nosso código-fonte (SceneModule)
│       ├── main.js                   # entrypoint: imports a cena + boot()
│       ├── core/
│       │   ├── scene-runner.js       # registerScene, SCENES, boot(), readConfig, watchVisibility
│       │   └── dom.js                # clearFallback, intAttr, floatAttr
│       ├── components/
│       │   └── particle-field.js     # createParticleField (BufferGeometry + PointsMaterial)
│       └── scenes/
│           └── hero-particles.js     # Cena 1 (play/pause/resize/dispose)
└── template/partial/
    └── hero-3d.twig                  # substitui partial/hero.twig na home
```

`three-addons/` (GLTFLoader/DRACOLoader para o logo GLB) entra **somente
na tarefa da Cena 2** — o import map já a referencia (adicional, sem custo
de rede enquanto nada a importar), mas a pasta não é criada agora
(escopo cirúrgico — evita pacote de ~500 KB sem uso).

### 2 — Vendor: three.js r169, build mínima

- Fonte: `https://cdn.jsdelivr.net/npm/three@0.169.0/build/three.module.min.js`.
- Pin: versão **0.169.0** (mesma recomendada no Guia §3). Anotada aqui e no
  cabeçalho do pacote de entrega; futuras trocas documentam no PR.
- Seleção `.module.min` (687 KB / 166 KB gzip) em vez de `.module` (1,3 MB):
  respeita o **orçamento §10** ("three.module.js + addons ≤ 700 KB /
  ~180 KB gzip") — os outros builds estouram o teto. Conteúdo interno
  confirmado r169 (constante `REVISION = "169"`).
- Nenhum CDN em runtime: o import map aponta para `catalog/view/js/vendor/…`
  (self-hosted, funciona offline e sem CSP externa).

### 3 — Import map + módulo no chrome: slot `has_3d` degradável

Em vez de duplicar o `<head>` por rota, o chrome comum de F2-4/F3-01
(`common/header.twig`) ganhou um **bloco condicional**

```twig
{% if has_3d|default(false) %}
<script type="importmap">{"imports":{"three":"…vendor/three.module.js","three/addons/":"…vendor/three-addons/"}}</script>
<script type="module" src="…js/three/main.js" defer></script>
{% endif %}
```

- `has_3d` e `asset_base` fluem do controller da rota (ex.: home) para o
  header via o mesmo `$data` do sub-controller — **nenhuma rota precisa
  ser tocada**: quem não declara `has_3d` herda `false` (default do Twig),
  mantendo o head enxuto e sem 404 de módulo.
- **Ordem import map → módulo garantida** dentro do mesmo bloco (Guia §3
  ressalva essa dependência; o e2e_f302 a testa com `grep -n`).
- Apache serve `vendor/…` e `js/three/…` como estáticos (200 confirmado
  no e2e), sem MIME/CORS adicionais — JS módulo puro funciona com o
  `Content-Type: text/javascript` padrão do servidor.

### 4 — Config da cena em `data-*` (números não são texto de UI)

O controller da home (`common/home.php`) declara:

```php
'has_3d'     => true,
'asset_base' => $base,
'hero3d'     => [
    'count' => 400,       // budget §10 do guia
    'color' => '#8a4fff', // roxo da marca
    'speed' => 0.05,      // rad/s de rotação
],
```

e o `hero-3d.twig` serializa como `data-count`/`data-color`/`data-speed`.
Justificativa (Guia §5 espec + regra de Language): valores numéricos/cor/
timing não são "texto de interface" e por isso não passam pela camada
Language; os textos do hero (kicker/título/tagline/lead/CTAs/socials)
permanecem 100% nas línguas × 3.

### 5 — Fallback: gradiente de marca, **zero binário**

O Guia §5 especifica `<img src="hero-fallback.webp">`. Substituído por:

```html
<div class="hero-3d__fallback" data-fallback></div>
```

com CSS `.hero-3d__fallback { background: radial-gradient(…roxo profundo…) }`.

- ADR 0019 já decidiu: **nenhum binário de imagem entra nos pacotes até o
  admin subir assets reais**. Manter o `.webp` do guia geraria um asset sem
  conteúdo de marca (e risco de versão desatualizada perpetuar em produção).
- O gradiente usa a paleta do tema (`--accent #6d28d9` e complementares do
  `#8a4fff` da cena) — identidade visual preservada.
- Comportamento **equivalente** ao do `<img>`: fica no DOM até o 1º frame
  renderizar (`clearFallback`), e é a renderização final em todos os casos
  de bloqueio (WebGL ausente, reduced-motion, exceção na factory).
  O check 9 do e2e_f302 **afirma positivamente** que nenhuma URL
  `hero-fallback.webp` é referenciada (e que uma hipotética seria 404).
- Se o admin um dia subir `catalog/view/assets/3d/hero-fallback.webp`, a
  transição é local: trocar o `<div data-fallback>` pelo `<img>` do guia —
  nenhuma linha de JS muda (o `clearFallback` já remove `[data-fallback]`).

### 6 — Lifecycle: lazy-init, pausa inteligente, anti-leak

`core/scene-runner.js` implementa o contrato do Guia §4 na íntegra:

- **Lazy-init**: `IntersectionObserver` com `rootMargin: 200px` — o
  WebGLRenderer só nasce com o hero quase na viewport (economia inicial).
- **`prefers-reduced-motion`**: a factory nunca é invocada; permanece apenas
  o fallback. Redundância: `@media (prefers-reduced-motion: reduce)` em
  CSS esconde canvas residual.
- **Pausa fora da viewport + aba oculta** via `watchVisibility` —
  INP §10 protegido durante scroll pesado.
- **Anti-leak**: `pagehide → dispose()` de todas as cenas ativas (bfcache
  protegido; Guia §11 checklist "navegação ida-e-volta não acumula renderers").
- **`pagehide` apenas `once: true`** — sem overlay de listeners (o próprio
  runner é singleton da página).

### 7 — SceneModule hero-particles (Guia §5 na íntegra)

`scenes/hero-particles.js` implementa a interface completa:

- `play()/pause()/resize()/dispose()` (§4).
- `DPR ≤ 2` (`setPixelRatio` — orçamento §10).
- Campo de partículas (`components/particle-field.js`): 400 pontos,
  `BufferGeometry`, `PointsMaterial` (1 draw call, latência constante).
- Parallax suavizado de câmera no `frame()` (alvo do `pointermove`,
  lerp 0.05/frame — INP §10 protegido, nenhum bloqueio por evento).
- Cor padrão `#8a4fff` se `cfg.color` ausente (Guia §5).
- Falha do `WebGLRenderer` (`try/catch`) → `return null` → fallback permanece.

### 8 — Nenhuma rota mexida além da home

`catalog/project` e detalhe (`/jwa`) renderizam **sem** import map e sem
`data-scene` (e2e_f302 check 7) — confirma que o slot é escopado.

## Assunções declaradas

1. A cor `#8a4fff` das partículas é a do Guia §5 (decisão estética do guia,
   preservada); se o mantenedor preferir o `--accent` exato do CSS, é um
   caractere no `home.php`.
2. Loader/Draco/GLB adiados à Cena 2 (logo GLB — cronograma S3 do guia).
3. O `home.php` injeta `asset_base` a partir de `site_url`; com site sob
   subpath (`/davidcms/dev/`) funciona porque `site_url` já o contém
   (F2-17/ADR 0017 — confirmado no e2e, link da home no output).
4. A async factory no `IntersectionObserver` é browser-assente: Chrome ≥ 63,
   FF ≥ 67, Safari ≥ 11.1 — cobre a matriz §11 (desktop mid + iPhone SE +
   Android básico).
5. Adequação visual final do hero (fundo escuro + texto da seção) depende
   da **Cena 2** (logo GLB) para coesão total; com Cena 1, o hero é um
   campo de partículas sobre o gradiente — aceita pelo mantenedor na F3-01
   como etapa da implementação Three.js.

## Consequências

### Positivas

- Hero agora **dinâmico** (campo de partículas + parallax), pré-requisito
  da Fase 3, sem quebrar o layout existente.
- Slot WebGL é **escopado por rota** (declarado com `has_3d`) — home em 3D,
  resto do site leve.
- Fallback sem binário mantém o pacote instalável e no padrão ADR 0019.
- Budget §10 confirmado: three.min.js (166 KB gzip), partículas = 1 draw
  call, DPR ≤ 2, nenhum asset por rota.
- Sem nenhum texto fora da Language (cena é `aria-hidden` e sem DOM textual).

### Negativas / débitos

- Logo GLB (Cena 2) **ainda ausente** — o hero ganhou partículas mas não o
  logotipo 3D; ADR permanece como pendência da Fase 3 (cronograma S3).
- O import map de `three-addons/` referencia uma pasta que **só existirá na
  Cena 2**; hoje produz 404 se um módulo tentar importar dela (nenhum tenta
  — escopo cirúrgico). Mitigação adiada: criar a pasta vazia na Cena 2.
- `three.module.js` servido sem compressão confirmada (dev sandbox é
  Apache simples); gzip/brotli em staging é tarefa de deploy (Guia §12).

## Validação executada (não declarada)

- **`tools/e2e/e2e_f302.sh` (10 checks, 10/10):**
  1. data-scene e `data-*` no HTML da home;
  2. import map **antes** do módulo na ordem do `<head>`;
  3. `main.js` 200 + invoca `boot()` + importa a cena;
  4. `hero-particles.js`: SceneModule completo (registerScene/play/pause/resize/dispose/WebGLRenderer/DPR≤2/pointermove);
  5. `scene-runner.js`: reduced-motion guard + IntersectionObserver(rootMargin 200) + visibilitychange + pagehide;
  6. `three.module.js` 200 (>100 KB, ≤750 KB — budget), REVISION 169 vivo;
  7. slot WebGL escopado: `/jwa` 200 **sem** `"three"` e **sem** `data-scene`;
  8. pt/es trazem mesma cena (data-scene) + textos de hero traduzidos;
  9. fallback gradiente no DOM + CSS; nenhum `.webp` no HTML;
  10. smoke 200 sem wrapper de erro.
- **Regressão inteira:** regressao (10) + f212..f218 (14+14+15+15+17+17+16)
  + f301 (13) + f302 (10) = **141 asserts, 0 FAIL** (ordem validada na prática:
  e2e_regressao.sh → f2xx → f301 → f302).
- **PHPStan:** `[OK] No errors`.
- **JS:** validação ESM via `node --input-type=module --check` nos 5
  módulos (main, scene-runner, dom, particle-field, hero-particles).
- **Correções reais desta validação:**
  doctrineblock `* data-*/window.*` em scene-runner (fechava comentário
  prematuramente — node --check encontrou na 1ª corrida); controller home:
  `has_3d/asset_base/hero3d` estavam **antes** de `$data = [...]` literal
  e eram sobrescritos (movidos para dentro do array); permissões do sandbox:
  `chown www-data` do setup travava o wizard ao tocar `config.php` (F2-18
  detecta `chown -R a+w` — documentado como lock do install); espaço de
  storage do Arena reciclou `/tmp/fui/dev` no meio (recolocado com
  `cp -a` do espelho, permissões, wizard re-rodado).
- **`node` disponível no sandbox do agente:** `/usr/bin/node v20.20.2` —
  validação JS feita na máquina, não é declaração.
