# Implementação Three.js — Front-end do Novo Site

> **Objetivo:** plano de implementação ponta a ponta do Three.js no front-end do davidalmeida.xyz — arquivos a criar, módulos de cena, integração com o ReamurCMS, orçamento de performance e critérios de aceite. É o "como construir"; os conceitos estão no [Guia Three.js](Guia-ThreeJS.md).

| | |
| --- | --- |
| **Escopo** | Engine/runtime canônico em `dev/system/library/three/`; cenas/template e adaptador público em `dev/catalog/`; Admin reutiliza a rota de assets do Catalog |
| **Depende de** | [Guia-ThreeJS.md](Guia-ThreeJS.md) · [Guia-Twig.md](Guia-Twig.md) · ADR 0073 · [roadmap](Analise-DavidAlmeida.md#8-plano-de-migração) |
| **Status** | ✅ Configuração declarativa System/Admin e runtime self-hosted centralizado no System; Catalog serve somente a allowlist por rota pública cacheável. Ver [`THREEJS_NATIVE_SYSTEM.md`](THREEJS_NATIVE_SYSTEM.md). |

---

## Sumário

1. [Visão geral da implementação](#1-visão-geral-da-implementação)
2. [Estrutura de arquivos de front-end](#2-estrutura-de-arquivos-de-front-end)
3. [Setup self-hosted + import map](#3-setup-self-hosted--import-map)
4. [Padrão de módulo de cena (SceneModule)](#4-padrão-de-módulo-de-cena-scenemodule)
5. [Cena 1 — Hero (partículas + parallax)](#5-cena-1--hero-partículas--parallax)
6. [Cena 2 — Logo GLB](#6-cena-2--logo-glb)
7. [Cena 3 — Portfólio](#7-cena-3--portfólio)
8. [Cena 4 — Página 404](#8-cena-4--página-404)
9. [Integração com o ReamurCMS](#9-integração-com-o-reamurcms)
10. [Orçamento de performance](#10-orçamento-de-performance)
11. [Testes e QA](#11-testes-e-qa)
12. [Deploy e cache de assets](#12-deploy-e-cache-de-assets)
13. [Cronograma](#13-cronograma)
14. [Troubleshooting](#14-troubleshooting)
15. [Definition of Done](#15-definition-of-done)

---

## 1. Visão geral da implementação

| Cena | Onde | Técnica | Prioridade | Fase |
| --- | --- | --- | --- | --- |
| **Hero** | Home (`common/home`) | Partículas WebGL + parallax de mouse | 🔴 1ª | Fase 3 |
| **Logo GLB** | Hero (evolução) | GLTFLoader + Draco, luz de estúdio | 🟡 2ª | Fase 3 |
| **Portfólio** | `cms/portfolio` | Tilt 3D em CSS (sem WebGL) → galeria 3D futura | 🟢 3ª | Fase 3 / Pós |
| **404** | `error/not_found` | Cena lúdica leve | ⚪ 4ª | Fase 6 |

**Estratégia:**
- **Self-hosted**: arquivos do Three.js servidos pelo próprio domínio (sem CDN em produção);
- **ES Modules nativos** com *import map* — sem bundler, sem build step, casando com o workflow PHP;
- **Lazy-init obrigatório** via `IntersectionObserver` — a cena só nasce quando o container entra na viewport;
- **Fallback-first**: HTML/CSS funcional sem WebGL; 3D é aprimoramento progressivo.

---

## 2. Estrutura de arquivos de front-end

A implementação mantém a engine e suas dependências em uma única origem protegida do núcleo. O Catalog só expõe arquivos nominalmente allowlisted pelo manifesto System; Admin e Catalog usam o mesmo endpoint e runtime.

```text
dev/system/library/three/
├── scene_config.php            # política/configuração declarativa das cenas
├── asset_manifest.php          # allowlist, caminho público e arquivos do runtime
├── THREE-LICENSE.txt           # licença da distribuição oficial Three.js
└── runtime/
    ├── three.module.js         # módulo oficial self-hosted
    ├── three.core.js           # dependência do módulo oficial
    ├── threejs-thumb.js        # runtime de thumbs; scripts opcionais só no sandbox
    └── three/
        ├── main.js             # entrypoint das cenas nativas
        ├── core/               # ciclo de vida e helpers
        ├── components/         # componentes reutilizáveis
        └── scenes/             # Hero e 404

dev/catalog/
├── .htaccess                   # rota limitada /three-runtime/*.js → adaptador Catalog
├── controller/common/three.php # serve somente assets resolvidos pelo manifesto
└── view/
    ├── css/threejs-thumb.css   # apresentação pública das thumbs
    └── template/common/header.twig # carrega módulos somente quando há cena
```

O caminho `system/` permanece negado para acesso HTTP direto. O endpoint Catalog nunca recebe caminho de filesystem livre: o manifesto System aceita nomes fixos e o adaptador aplica `Content-Type`, `nosniff`, `ETag` e cache. Módulos ESM conservam caminhos relativos sob `/catalog/three-runtime/`, evitando duplicação de arquivos ou reescrita do vendor.

---

## 3. Setup self-hosted + import map

Os módulos Three.js atuais são self-hosted em `dev/system/library/three/runtime/`; não baixe assets no deploy nem carregue CDN. `dev/system/library/three/asset_manifest.php` lista cada arquivo permitido e o Catalog os entrega na rota pública `/catalog/three-runtime/{asset}`. O rewrite dessa rota precede o 404 estático; a extensão `system/` continua inacessível diretamente.

O import map do `common/header.twig` só é emitido quando `has_3d` está ativo e aponta para `three_runtime_base`, calculado pelo controller usando o manifesto System. A mesma base atende o entrypoint de cenas e o runtime de thumbs no Admin/Catalog. Os imports relativos ESM permanecem dentro de `/catalog/three-runtime/`, sem cópia do vendor e sem URL externa.

> WebGL é carregado apenas nas páginas com cena. Fallback HTML/CSS continua disponível quando desativado ou sem suporte do navegador.

---

## 4. Padrão de módulo de cena (SceneModule)

Contrato que **toda** cena implementa — é o que o `main.js` orquestra:

```js
// js/three/core/scene-runner.js
export const SCENES = {}; // registry: { 'hero-particles': factoryFn }

export function registerScene(name, factory) {
  SCENES[name] = factory;
}

/**
 * Inicializa as cenas declaradas na página (data-scene="nome")
 * com lazy-init, pausa fora da viewport e limpeza.
 */
export function boot() {
  document.querySelectorAll('[data-scene]').forEach((container) => {
    const name = container.dataset.scene;
    const factory = SCENES[name];

    if (!factory) {
      console.warn(`[three] cena não registrada: ${name}`);
      return;
    }

    // Respeita prefers-reduced-motion: mantém o fallback do HTML
    if (!window.matchMedia('(prefers-reduced-motion: no-preference)').matches) {
      return;
    }

    const io = new IntersectionObserver(async ([entry]) => {
      if (!entry.isIntersecting) return;
      io.disconnect();

      const scene = await factory(container, readConfig(container));
      if (!scene) return; // WebGL indisponível → fallback permanece

      watchVisibility(container, scene);
    }, { rootMargin: '200px' }); // pré-carrega 200px antes de aparecer

    io.observe(container);
  });
}

function readConfig(container) {
  return {
    ...container.dataset,
    ...(window[`${container.dataset.scene}Config`] ?? {}),
  };
}

function watchVisibility(container, scene) {
  new IntersectionObserver(([e]) => {
    e.isIntersecting ? scene.play() : scene.pause();
  }).observe(container);

  document.addEventListener('visibilitychange', () => {
    document.hidden ? scene.pause() : scene.play();
  });
}
```

```js
// js/three/core/webgl-support.js
export function createRenderer(container) {
  try {
    const { WebGLRenderer } = await import('three');
    // ...
  } catch {
    return null;
  }
}
```

```js
// js/three/core/dom.js
export function clearFallback(container) { container.innerHTML = ''; }
export function intAttr(container, attr, fallback) {
  const v = parseInt(container.dataset[attr], 10);
  return Number.isFinite(v) ? v : fallback;
}
```

Cada SceneModule retorna a interface:

```ts
interface SceneHandle {
  play(): void;     // inicia/retoma o rAF
  pause(): void;    // pausa o rAF (fora da viewport/aba oculta)
  resize(): void;   // recalcula aspect + setSize
  dispose(): void;  // libera geometrias, materiais, renderer
}
```

---

## 5. Cena 1 — Hero (partículas + parallax)

### Especificação

| Item | Valor |
| --- | --- |
| Arquivo | `js/three/scenes/hero-particles.js` |
| Container | `#hero-3d` com `data-scene="hero-particles"` no `partial/hero-3d.twig` |
| Conteúdo | Campo de partículas (padrão 400) + parallax suave de câmera |
| Config via `data-*` | `data-count` (partículas), `data-color` (hex), `data-speed` |
| Fallback | `<img src="hero-fallback.webp">` dentro do container |

### Implementação (esqueleto de referência)

```js
// js/three/scenes/hero-particles.js
import * as THREE from 'three';
import { registerScene } from '../core/scene-runner.js';
import { createParticleField } from '../components/particle-field.js';
import { clearFallback, intAttr } from '../core/dom.js';

export default registerScene('hero-particles', (container, cfg) => {
  const camera = new THREE.PerspectiveCamera(50, container.clientWidth / container.clientHeight, 0.1, 100);
  camera.position.z = 4;

  const renderer = new THREE.WebGLRenderer({ antialias: true, alpha: true });
  renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
  renderer.setSize(container.clientWidth, container.clientHeight);

  clearFallback(container);
  container.appendChild(renderer.domElement);

  const scene = new THREE.Scene();
  const field = createParticleField({
    count: intAttr(container, 'count', 400),
    color: cfg.color ?? '#8a4fff',
    spread: 12,
  });
  scene.add(field.points);

  const target = { x: 0, y: 0 };
  const onPointer = (e) => {
    target.x = (e.clientX / window.innerWidth  - 0.5) * 0.6;
    target.y = (e.clientY / window.innerHeight - 0.5) * 0.6;
  };
  window.addEventListener('pointermove', onPointer, { passive: true });

  let rafId = null;
  const clock = new THREE.Clock();

  function frame() {
    const t = clock.getElapsedTime();
    field.points.rotation.y = t * 0.05;
    camera.position.x += (target.x - camera.position.x) * 0.05;
    camera.position.y += (-target.y - camera.position.y) * 0.05;
    camera.lookAt(scene.position);
    renderer.render(scene, camera);
    rafId = requestAnimationFrame(frame);
  }

  const handle = {
    play()  { if (!rafId) rafId = requestAnimationFrame(frame); },
    pause() { cancelAnimationFrame(rafId); rafId = null; },
    resize() {
      camera.aspect = container.clientWidth / container.clientHeight;
      camera.updateProjectionMatrix();
      renderer.setSize(container.clientWidth, container.clientHeight);
    },
    dispose() {
      handle.pause();
      window.removeEventListener('pointermove', onPointer);
      field.dispose();
      renderer.dispose();
      renderer.domElement.remove();
    },
  };

  window.addEventListener('resize', handle.resize);
  return handle;
});
```

```js
// js/three/components/particle-field.js
import * as THREE from 'three';

export function createParticleField({ count = 400, color = '#8a4fff', spread = 12 } = {}) {
  const positions = new Float32Array(count * 3);
  for (let i = 0; i < count * 3; i++) positions[i] = (Math.random() - 0.5) * spread;

  const geometry = new THREE.BufferGeometry();
  geometry.setAttribute('position', new THREE.BufferAttribute(positions, 3));

  const material = new THREE.PointsMaterial({ color, size: 0.035, transparent: true, opacity: 0.8 });
  const points = new THREE.Points(geometry, material);

  return { points, dispose: () => { geometry.dispose(); material.dispose(); } };
}
```

```js
// js/three/main.js
import { boot } from './core/scene-runner.js';
import './scenes/hero-particles.js';   // auto-registro via registerScene()

boot();
```

### Acceptance criteria (Cena 1)

- [ ] Sem WebGL / com `prefers-reduced-motion`: só aparece a imagem fallback;
- [ ] Canvas nasce só quando o hero entra a 200px da viewport;
- [ ] Pausa ao rolar para fora e ao trocar de aba;
- [ ] `devicePixelRatio` limitado a 2;
- [ ] LCP do hero ≤ 2,5s na página publicada (medir com Lighthouse mobile).

---

## 6. Cena 2 — Logo GLB

Evolui a Cena 1 no mesmo container: adiciona o modelo `logo-da.glb` com rig de luz de estúdio.

```js
// js/three/scenes/hero-logo.js — diferenças em relação à cena 1
import { GLTFLoader } from 'three/addons/loaders/GLTFLoader.js';
import { DRACOLoader } from 'three/addons/loaders/DRACOLoader.js';
import { createStudioLighting } from '../components/studio-lighting.js';

const draco = new DRACOLoader().setDecoderPath(`${window.THREE_RUNTIME_BASE}three/addons/libs/draco/`); // futuro: registrar os arquivos no AssetManifest System
new GLTFLoader().setDRACOLoader(draco).load(cfg.model, (gltf) => {
  const model = gltf.scene;
  normalizeToViewport(model, camera);  // util: centraliza e escala pelo bounding box
  scene.add(model);
});
```

| Item | Valor |
| --- | --- |
| Asset | `catalog/view/assets/3d/logo-da.glb` (≤ 150 KB, Draco + texturas KTX2) |
| Preparo do modelo | exportar do Blender → otimizar com `npx @gltf-transform/cli optimize in.glb logo-da.glb --compress draco` |
| Config | `data-model` no container (caminho vindo do controller/Twig) |
| Degradação | se o modelo falhar (onError), mantém partículas |

---

## 7. Cena 3 — Portfólio

**Etapa 1 (Fase 3, sem WebGL):** tilt 3D por CSS nos cards — efeito barato e acessível:

```css
/* view/css/portfolio.css */
.card-project { transform-style: preserve-3d; transition: transform .25s ease; }
.card-project:hover { transform: perspective(800px) rotateX(4deg) rotateY(-4deg) translateY(-4px); }
@media (prefers-reduced-motion: reduce) { .card-project:hover { transform: none; } }
```

**Etapa 2 (pós-lançamento):** `scenes/portfolio-3d.js` — galeria navegável (quadros 3D com as capas dos projetos como texturas). Fora do escopo do lançamento; backlog registrado na [Análise](Analise-DavidAlmeida.md).

---

## 8. Cena 4 — Página 404

```twig
{# error/not_found.twig #}
<div data-scene="not-found" data-count="120">
  <img src="{{ base }}catalog/view/assets/3d/lost.webp" alt="">
</div>
```

Partículas com deriva aleatória ("fog") + texto HTML por cima. Arquivo próprio `scenes/not-found.js` reaproveitando `particle-field.js` com `drift` (velocidade por partícula).

---

## 9. Integração com o ReamurCMS

### 9.1 Controller → Twig

```php
// catalog/controller/common/home.php (trecho)
$threeRuntimeBase = $this->three_asset_manifest->publicBasePath();
// O template usa a URL Catalog base + $threeRuntimeBase; nunca aponta para System direto.

$data['has_3d'] = true;   // ativa o import map no base.twig apenas nesta rota
$data['asset_base'] = $this->config->get('site_url');

$data['hero'] = [
    'kicker'    => $this->language->get('hero_kicker'),
    'title'     => 'David L. Almeida',
    'highlight' => $this->language->get('hero_role'),
    'subtitle'  => $this->language->get('hero_subtitle'),
    'particles' => 400,
    'model'     => 'catalog/view/assets/3d/logo-da.glb',
    'fallback'  => 'catalog/view/assets/3d/hero-fallback.webp',
];
```

```php
// catalog/language/pt-br/common/home.php
$_['hero_kicker']    = 'Eu sou';
$_['hero_role']      = 'Creative Developer';
$_['hero_subtitle']  = 'Transformo ideias em realidade com criatividade e responsabilidade.';
```

### 9.2 Template

```twig
{# partial/hero-3d.twig #}
<section class="hero">
  <div id="hero-3d" class="hero__canvas" aria-hidden="true"
       data-scene="hero-particles"
       data-count="{{ hero.particles }}"
       data-model="{{ asset_base }}{{ hero.model }}">
    <img src="{{ asset_base }}{{ hero.fallback }}" alt="" width="1920" height="1080" fetchpriority="high">
  </div>

  <div class="hero__content">
    <p class="hero__hello">{{ hero.kicker }}</p>
    <h1>{{ hero.title }} <span>{{ hero.highlight }}</span></h1>
    <p>{{ hero.subtitle }}</p>
  </div>
</section>

<script>window['hero-particlesConfig'] = {{ hero|json_encode|raw }};</script>
```

### 9.3 Regras de integração

1. **Textos sempre via Language** — nada hardcoded no Twig/JS;
2. **Caminhos com `site_url`** (`asset_base`) para funcionar em `/`, `/dev/` e staging;
3. **Config por `json_encode|raw`** ou `data-*` — nunca interpolar PHP em `<script>` solto;
4. **Idioma da UI** não afeta a cena (3D é decorativo — `aria-hidden`);
5. Cache Twig em produção exige `rm -rf storage/cache/template/*` após alterar templates (ver [Guia Twig §9](Guia-Twig.md#9-cache-e-performance)).

---

## 10. Orçamento de performance

| Métrica | Budget | Como medir |
| --- | --- | --- |
| Modelo GLB (hero) | ≤ 150 KB (Draco) | `ls -lh` + Network |
| three.module.js + addons usados | ≤ 700 KB / ~180 KB gzip | Build self-hosted única |
| Draw calls por cena | ≤ 10 | `renderer.info.render.calls` em dev |
| Triângulos por cena | ≤ 100 mil | `renderer.info.render.triangles` |
| devicePixelRatio | ≤ 2 | `setPixelRatio` |
| LCP mobile (home) | ≤ 2,5 s | Lighthouse |
| INP | ≤ 200 ms | PageSpeed / Web Vitals |
| FPS durante scroll | ≥ 50 | Chrome DevTools Performance |

**Alertas automáticos em dev:** logar `renderer.info` uma vez por cena (`console.table`) apenas quando `?debug=1` na URL.

---

## 11. Testes e QA

### Checklist funcional (por cena)

- [ ] Renderiza no Chrome, Firefox, Safari (WebKit) e Edge
- [ ] Renderiza em mobile (Android Chrome + iOS Safari)
- [ ] Fallback ativa com WebGL bloqueado (chrome://flags → Disable WebGL)
- [ ] Não renderiza com `prefers-reduced-motion: reduce` ativo
- [ ] Pausa fora da viewport e com aba oculta (vigiar CPU no DevTools)
- [ ] Resize mantém proporção sem stretch
- [ ] Navegação ida-e-volta não acumula renderers (sem leak: `dispose()`)

### Matriz mínima de dispositivos

| Dispositivo | Foco |
| --- | --- |
| Desktop mid (GPU integrada) | FPS e parallax |
| iPhone SE / Android básico | DPR, bateria, fallback |
| Leitor de tela (NVDA/VoiceOver) | hero anunciado só pelo HTML (canvas oculto) |

---

## 12. Deploy e cache de assets

1. **Versionamento de assets** por query string no controller: `main.js?v={{ asset_version }}` (valor de config, bump a cada release);
2. **Headers** no servidor para `catalog/view/assets/` e `js/vendor/`: `Cache-Control: public, max-age=31536000, immutable` (só com versionamento!);
3. **Compressão:** brotli/gzip para `.js`, `.wasm`, `.glb` (mime `model/gltf-binary`);
4. **Precarregar o hero:** `<link rel="preload" as="image" href="hero-fallback.webp">` no `head` da home;
5. Commitar `js/vendor/three*` **uma vez por upgrade** de versão, com a versão anotada no PR.

---

## 13. Cronograma

| Semana (Fase 3) | Entrega |
| --- | --- |
| S1 | Setup self-hosted + `scene-runner` + Cena 1 (partículas) em staging |
| S2 | Integração controller/Twig + idiomas + QA matriz de dispositivos |
| S3 | Cena 2 (logo GLB) — depende do asset 3D pronto |
| S4 | Portfólio etapa 1 (tilt CSS) + 404 + budgets validados no Lighthouse |

---

## 14. Troubleshooting

| Sintoma | Causa provável | Correção |
| --- | --- | --- |
| Canvas em branco, sem erro | import map depois do módulo / caminho errado | conferir ordem no `head` e paths com `asset_base` |
| `Unexpected token` no addon | addon importando `three` sem import map | import map precisa cobrir `three/addons/` |
| Zoom/mobile com FPS baixo | DPR 3–4 | `min(devicePixelRatio, 2)` |
| Modelo GLB não carrega | MIME/CORS ou Draco ausente | servir `.glb` como `model/gltf-binary`; conferir `libs/draco/` |
| Memória subindo a cada visita | falta `dispose()` | revisar `SceneHandle.dispose` e remoção de listeners |
| Cena "pisca" no carregamento | fallback removido tarde | só chamar `clearFallback()` após 1º frame renderizado |
| Lighthouse INP alto | rAF durante scroll pesado | verificar pausa fora da viewport; reduzir partículas |

---

## 15. Definition of Done

Uma cena só está pronta quando:

1. ✅ Está no padrão SceneModule (`play/pause/resize/dispose`) registrada em `main.js`;
2. ✅ Lazy-init + pausa fora da viewport + `prefers-reduced-motion` + fallback verificados;
3. ✅ Dados vindos do controller via Language/`json_encode` (nada hardcoded);
4. ✅ Budgets de performance medidos e dentro da tabela (§10);
5. ✅ Checklist de QA (§11) executado em staging;
6. ✅ Documentação atualizada (este doc + [Guia-ThreeJS.md](Guia-ThreeJS.md) se o padrão mudou).

---

## Veja também

- [Guia-ThreeJS.md](Guia-ThreeJS.md) — conceitos, anatomia da cena e boas práticas
- [Guia-Twig.md](Guia-Twig.md#8-estrutura-do-tema-do-novo-site) — estrutura de templates
- [Arquitetura-MVCL.md](Arquitetura-MVCL.md) — fluxo controller → view
- [Analise-DavidAlmeida.md](Analise-DavidAlmeida.md#8-plano-de-migração) — Fase 3 do roadmap
