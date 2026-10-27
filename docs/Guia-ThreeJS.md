# Guia Three.js — ReamurCMS

> **Objetivo:** orientar o uso do **Three.js** no front-end do novo davidalmeida.xyz — da cena básica à integração com os templates Twig do ReamurCMS, sempre com olho em performance e acessibilidade.

---

## Sumário

1. [Por que Three.js neste projeto](#1-por-que-threejs-neste-projeto)
2. [Instalação](#2-instalação)
3. [Anatomia de uma cena](#3-anatomia-de-uma-cena)
4. [Exemplo completo: hero 3D](#4-exemplo-completo-hero-3d)
5. [Carregando modelos (glTF/GLB)](#5-carregando-modelos-gltfglb)
6. [Integração com Twig/PHP](#6-integração-com-twigphp)
7. [Performance](#7-performance)
8. [Acessibilidade](#8-acessibilidade)
9. [Ideias para o davidalmeida.xyz](#9-ideias-para-o-davidalmeidaxyz)
10. [Referências](#10-referências)

---

## 1. Por que Three.js neste projeto

O posicionamento do site é **"Creative Developer"** — design + código. Um hero com uma cena 3D sutil (logo/modelo reagindo ao mouse/scroll) comunica essa identidade no primeiro olhar, sem depender de vídeo pesado. O Three.js é a biblioteca WebGL mais madura para isso: API de alto nível, loaders prontos e ótima documentação.

> **Princípio do projeto:** 3D como *tempero*, não como prato principal. O site deve funcionar 100% sem WebGL (fallback estático) e carregar a cena **depois** do conteúdo.

---

## 2. Instalação

**Padrão obrigatório — módulo local self-hosted** (sem import remoto/CDN em runtime):

```html
<!-- common/header.twig emite isto apenas em rotas has_3d. -->
<script type="importmap">
{
  "imports": {
    "three": "{{ three_runtime_base }}three.module.js"
  }
}
</script>
<script type="module" src="{{ three_runtime_base }}three/main.js" defer></script>
```

Os módulos importam `three` pelo alias local. Vendor, runtime e licença têm uma única fonte em `dev/system/library/three/runtime/`; `AssetManifest` permite expor apenas os módulos cadastrados por `/catalog/three-runtime/`, pois `system/` é negado para acesso HTTP direto. Não mantenha cópias no Catalog nem URLs CDN. O campo de script da thumb BDREditor executa apenas no iframe sandbox opaco, sem DOM/cookies do CMS e com rede bloqueada; use a API limitada documentada em `docs/THREEJS_THUMB.md`.

> A cena só é carregada em rotas que declaram `has_3d`; WebGL permanece aprimoramento progressivo e o HTML/CSS/fallback funciona sem ele.

---

## 3. Anatomia de uma cena

Toda cena Three.js tem 5 ingredientes:

```js
import * as THREE from 'three';

// 1. CENA — o "mundo" onde os objetos vivem
const scene = new THREE.Scene();

// 2. CÂMERA — o olho do usuário
const camera = new THREE.PerspectiveCamera(
  50,                                   // FOV (campo de visão)
  container.clientWidth / container.clientHeight, // aspect
  0.1,                                  // near
  100                                   // far
);
camera.position.set(0, 0, 4);

// 3. RENDERER — desenha a cena no <canvas> via WebGL
const renderer = new THREE.WebGLRenderer({ antialias: true, alpha: true });
renderer.setSize(container.clientWidth, container.clientHeight);
renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2)); // ver §7
container.appendChild(renderer.domElement);

// 4. OBJETOS — geometria + material = mesh
const geometry = new THREE.TorusKnotGeometry(1, 0.3, 200, 32);
const material = new THREE.MeshStandardMaterial({ color: 0x8a4fff, metalness: 0.6, roughness: 0.25 });
const mesh = new THREE.Mesh(geometry, material);
scene.add(mesh);

// luzes (MeshStandardMaterial precisa de luz)
scene.add(new THREE.AmbientLight(0xffffff, 0.4));
const dir = new THREE.DirectionalLight(0xffffff, 1.2);
dir.position.set(3, 5, 4);
scene.add(dir);

// 5. LOOP — requestAnimationFrame
function tick() {
  mesh.rotation.y += 0.005;
  mesh.rotation.x += 0.0025;
  renderer.render(scene, camera);
  requestAnimationFrame(tick);
}
tick();

// resize responsivo
window.addEventListener('resize', () => {
  camera.aspect = container.clientWidth / container.clientHeight;
  camera.updateProjectionMatrix();
  renderer.setSize(container.clientWidth, container.clientHeight);
});
```

---

## 4. Exemplo completo: hero 3D

### 4.1 Template Twig

```twig
{# partial/hero-3d.twig #}
<section class="hero">
  <div id="hero-3d" class="hero__canvas" aria-hidden="true"
       data-model="{{ hero.model_url }}"
       data-intensity="{{ hero.particles ?? 350 }}">
    {# fallback estático se WebGL falhar #}
    <img src="{{ hero.fallback_image }}" alt="" loading="eager">
  </div>

  <div class="hero__content">
    <p class="hero__hello">{{ hero.kicker }}</p>
    <h1>{{ hero.title }} <span>{{ hero.highlight }}</span></h1>
    <p class="hero__subtitle">{{ hero.subtitle }}</p>
  </div>
</section>

{% block scripts %}
  <script type="module" src="catalog/three-runtime/three/scenes/hero-particles.js"></script>
{% endblock %}
```

### 4.2 `system/library/three/runtime/three/scenes/hero-particles.js`

```js
import * as THREE from 'three';

const container = document.getElementById('hero-3d');

// Lazy-init: só inicializa quando o hero entra na viewport
const start = () => {
  const scene  = new THREE.Scene();
  const camera = new THREE.PerspectiveCamera(50, container.clientWidth / container.clientHeight, 0.1, 100);
  camera.position.z = 4;

  let renderer;
  try {
    renderer = new THREE.WebGLRenderer({ antialias: true, alpha: true });
  } catch {
    return; // WebGL indisponível → mantém a imagem de fallback
  }
  renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
  renderer.setSize(container.clientWidth, container.clientHeight);
  container.innerHTML = '';                 // remove o fallback
  container.appendChild(renderer.domElement);

  // --- partículas de fundo (quantidade vem do data-attribute) ---
  const count = parseInt(container.dataset.intensity ?? '350', 10);
  const positions = new Float32Array(count * 3);
  for (let i = 0; i < count * 3; i++) positions[i] = (Math.random() - 0.5) * 12;
  const pGeo = new THREE.BufferGeometry();
  pGeo.setAttribute('position', new THREE.BufferAttribute(positions, 3));
  const points = new THREE.Points(pGeo, new THREE.PointsMaterial({ color: 0x8a4fff, size: 0.035, transparent: true, opacity: 0.8 }));
  scene.add(points);

  // --- parallax com o mouse ---
  let targetX = 0, targetY = 0;
  window.addEventListener('pointermove', (e) => {
    targetX = (e.clientX / window.innerWidth  - 0.5) * 0.6;
    targetY = (e.clientY / window.innerHeight - 0.5) * 0.6;
  }, { passive: true });

  const clock = new THREE.Clock();
  (function tick() {
    const t = clock.getElapsedTime();
    points.rotation.y = t * 0.05;

    camera.position.x += (targetX - camera.position.x) * 0.05;
    camera.position.y += (-targetY - camera.position.y) * 0.05;
    camera.lookAt(scene.position);

    renderer.render(scene, camera);
    requestAnimationFrame(tick);
  })();

  window.addEventListener('resize', () => {
    camera.aspect = container.clientWidth / container.clientHeight;
    camera.updateProjectionMatrix();
    renderer.setSize(container.clientWidth, container.clientHeight);
  });
};

// IntersectionObserver = só gasta GPU quando visível (ver §7)
const io = new IntersectionObserver((entries) => {
  if (entries[0].isIntersecting) { start(); io.disconnect(); }
}, { threshold: 0.1 });

// respeita prefers-reduced-motion (ver §8)
if (window.matchMedia('(prefers-reduced-motion: no-preference)').matches) {
  io.observe(container);
}
```

---

## 5. Carregando modelos (glTF/GLB)

Preferência por **GLB** (binário, um arquivo só, texturas embutidas). Otimize com [gltf-transform](https://gltf-transform.dev/) ou exportação comprimida (Draco/Meshopt).

```js
import { GLTFLoader } from 'three/addons/loaders/GLTFLoader.js';
import { DRACOLoader } from 'three/addons/loaders/DRACOLoader.js';

const draco = new DRACOLoader().setDecoderPath('catalog/three-runtime/three/addons/libs/draco/'); // futuros arquivos precisam entrar na allowlist System
const loader = new GLTFLoader().setDRACOLoader(draco);

loader.load(
  container.dataset.model,              // vindo do Twig (data-model)
  (gltf) => {
    const model = gltf.scene;
    model.scale.setScalar(1.2);
    scene.add(model);
  },
  undefined,
  (err) => console.error('Falha ao carregar modelo 3D:', err)
);
```

> **Orçamento:** hero ≤ 150 KB de modelo comprimido. Use `KTX2/Basis` para texturas quando possível.

---

## 6. Integração com Twig/PHP

O controller alimenta a cena via `$data` → Twig → `data-*`/`json_encode` → JS:

```php
// catalog/controller/common/home.php (trecho)
$data['hero'] = [
    'kicker'         => $this->language->get('hero_kicker'),   // "I'm"
    'title'          => 'David L. Almeida',
    'highlight'      => $this->language->get('hero_role'),     // "Creative Developer"
    'subtitle'       => $this->language->get('hero_subtitle'),
    'model_url'      => $this->three_asset_manifest->heroModelUrl(),  // Cena 2: asset allowlisted (ADR 0075)
    'particles'      => 400,                                          // fallback = gradiente CSS no tema
];
```

```twig
{# injetar config completa como JSON, quando necessário #}
<script>
  window.hero3dConfig = {{ hero|json_encode|raw }};
</script>
```

```js
const cfg = window.hero3dConfig ?? {};
```

**Regra:** nunca escrever JS "solto" com PHP interpolado dentro de template — sempre `json_encode` ou `data-*`.

---

## 7. Performance

| Técnica | Por quê |
| --- | --- |
| `setPixelRatio(min(devicePixelRatio, 2))` | Telas 3x/4x explodem o custo de fill-rate |
| `IntersectionObserver` p/ lazy-init | Não gasta GPU/CPU antes do hero aparecer |
| Pausar `requestAnimationFrame` quando a aba/hero sair de vista | Bateria e INP |
| `powerPreference: 'high-performance'` no renderer | GPUs dedicadas em laptops dual-GPU |
| Poucas geometrias + `BufferGeometry` | Evitar draw calls desnecessários |
| Instancing (`InstancedMesh`) p/ objetos repetidos | Milhares de partículas/props por 1 draw call |
| Frustum culling (padrão) + `mesh.frustumCulled` | Cortar o que está fora da câmera |
| Comprimir modelos (Draco/Meshopt) e texturas (KTX2) | Download menor |
| `renderer.info` em dev | Vigiar draw calls/triângulos |
| `dispose()` de geometrias/materiais no teardown | Evitar vazamento de VRAM em SPAs/reloads |

Pausa fora da viewport:

```js
let running = true;
new IntersectionObserver(([e]) => {
  running = e.isIntersecting;
}).observe(container);

(function tick() {
  if (running) renderer.render(scene, camera);
  requestAnimationFrame(tick);
})();
```

---

## 8. Acessibilidade

1. **`prefers-reduced-motion`**: se o usuário pede menos movimento, mostrar o fallback estático (ver §4.2).
2. **Canvas decorativo:** `aria-hidden="true"` no container; o conteúdo textual do hero é HTML real.
3. **Foco e teclado:** interações 3D nunca devem ser o único caminho para ação (CTAs em HTML).
4. **Contraste:** elementos 3D de fundo não podem prejudicar a leitura do título (overlay/scrim CSS se preciso).

---

## 9. Ideias para o davidalmeida.xyz

| Seção | Ideia 3D | Complexidade |
| --- | --- | --- |
| **Hero** | Partículas formando o logotipo "DA" + parallax de mouse | ⭐ Baixa |
| **Hero (fase 2)** | Modelo GLB do logotipo girando suave com iluminação de estúdio | ⭐⭐ Média |
| **Portfólio** | Cards com leve tilt 3D (CSS/`transform`) — *sem* WebGL | ⭐ Baixa |
| **Portfólio (fase 2)** | Cena de galeria: projetos como "quadros" em ambiente 3D navegável | ⭐⭐⭐ Alta |
| **Ferramentas** | Ícones (Fluxa, Quotia, Vertex, Solis) flutuando com física sutil | ⭐⭐ Média |
| **404** | Cena 3D lúdica ("página perdida no espaço") | ⭐ Baixa |
| **Transições** | Scroll-driven: câmera acompanha a rolagem entre seções | ⭐⭐⭐ Alta (avaliar INP) |

Ordem sugerida: **hero com partículas (Fase 3 do roadmap)** → logo GLB → restante pós-lançamento.

---

## 10. Referências

- [Three.js — docs oficiais](https://threejs.org/docs/) e [exemplos](https://threejs.org/examples/)
- [GLTFLoader](https://threejs.org/docs/#examples/en/loaders/GLTFLoader) · [Draco](https://threejs.org/docs/#examples/en/loaders/DRACOLoader)
- [gltf.report](https://gltf.report/) — inspecionar modelos · [gltf-transform](https://gltf-transform.dev/) — otimizar
- [Guia Twig §8](Guia-Twig.md#8-estrutura-do-tema-do-novo-site) — onde os templates 3D moram
- [Análise §5](Analise-DavidAlmeida.md#5-diagnóstico) — por que 3D faz parte do novo site

---

<p align="center">Feito para brilhar — com responsabilidade e ≤ 150 KB de modelo ✨</p>
