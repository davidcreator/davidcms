# Implementação Three.js — Front-end do Novo Site

> **Objetivo:** plano de implementação ponta a ponta do Three.js no front-end do davidalmeida.xyz — arquivos a criar, módulos de cena, integração com o ReamurCMS, orçamento de performance e critérios de aceite. É o "como construir"; os conceitos estão no [Guia Three.js](Guia-ThreeJS.md).

| | |
| --- | --- |
| **Escopo** | Engine/runtime canônico em `dev/system/library/three/`; cenas/template e adaptador público em `dev/catalog/`; Admin reutiliza a rota de assets do Catalog |
| **Depende de** | [Guia-ThreeJS.md](Guia-ThreeJS.md) · [Guia-Twig.md](Guia-Twig.md) · ADR 0073 · [roadmap](Analise-DavidAlmeida.md#8-plano-de-migração) |
| **Status** | ✅ Engine compartilhada, Cena 2 com o GLB vigente, Cena 3 etapa 2 e Cena 5 Ferramentas implementadas como opt-in; runtime e cenas passam pela allowlist System/Catalog. QA visual pode ficar para depois e não bloqueia a sequência; os novos toggles seguem desligados por padrão. Ver [`THREEJS_NATIVE_SYSTEM.md`](THREEJS_NATIVE_SYSTEM.md), ADRs 0076 e 0077. |

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
| **Portfólio** | Home (`#portfolio`) | Tilt CSS + galeria 3D decorativa opt-in | 🟢 3ª | Fase 3 / Pós |
| **Ferramentas** | Home (`#tools`) | Até quatro ícones flutuantes, cena opt-in sobre cards HTML | 🟢 5ª | Fase 3 / Pós |
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
    ├── threejs-thumb.js        # wrapper; custom_js isolado no iframe
    └── three/
        ├── main.js             # entrypoint das cenas nativas
        ├── thumbnail-runner.js # adapter de thumbs sobre o SceneRunner
        ├── core/scene-runner.js # única camada WebGL/lifecycle
        ├── core/dom.js         # parse de atributos
        ├── addons/             # loaders do pacote oficial (GLTFLoader + utils)
        ├── components/         # componentes reutilizáveis (particle-field, studio-lighting)
        ├── models/             # assets 3D (logo-da.glb — Cena 2)
        └── scenes/             # Hero, 404, Portfólio e Ferramentas

dev/catalog/
├── .htaccess                   # rota limitada /three-runtime/*.{js,glb} → adaptador Catalog
├── controller/common/three.php # serve somente assets resolvidos pelo manifesto
└── view/
    ├── css/threejs-thumb.css   # apresentação pública das thumbs
    └── template/common/header.twig # carrega módulos somente quando há cena
```

O caminho `system/` permanece negado para acesso HTTP direto. O endpoint Catalog nunca recebe caminho de filesystem livre: o manifesto System aceita nomes fixos e o adaptador aplica `Content-Type`, `nosniff`, `ETag` e cache. Módulos ESM conservam caminhos relativos sob `/catalog/three-runtime/`, evitando duplicação de arquivos ou reescrita do vendor. O `Content-Type` é derivado da extensão registrada no manifesto — `js` vira `application/javascript; charset=utf-8` e `glb` vira `model/gltf-binary`.

---

## 3. Setup self-hosted + import map

Os módulos Three.js atuais são self-hosted em `dev/system/library/three/runtime/`; não baixe assets no deploy nem carregue CDN. `dev/system/library/three/asset_manifest.php` lista cada arquivo permitido e o Catalog os entrega na rota pública `/catalog/three-runtime/{asset}`. O rewrite dessa rota precede o 404 estático; a extensão `system/` continua inacessível diretamente.

O import map do `common/header.twig` só é emitido quando `has_3d` está ativo e aponta para `three_runtime_base`, calculado pelo controller usando o manifesto System. Ele declara duas entradas: `"three"` (o módulo oficial) e `"three/addons/"` (prefixo dos loaders da allowlist), sem as quais o `GLTFLoader` da Cena 2 não resolveria seus imports. A mesma base atende o entrypoint de cenas e o runtime de thumbs no Admin/Catalog. Os imports relativos ESM permanecem dentro de `/catalog/three-runtime/`, sem cópia do vendor e sem URL externa.

> WebGL é carregado apenas nas páginas com cena. Fallback HTML/CSS continua disponível quando desativado ou sem suporte do navegador.

---

## 4. Padrão de módulo de cena (SceneModule)

`dev/system/library/three/runtime/three/core/scene-runner.js` é a única camada WebGL para Hero, 404, Portfólio, Ferramentas e thumbs declarativas. Ela cria o `WebGLRenderer`, câmera e cena; controla lazy-init, resize, `prefers-reduced-motion`, visibilidade, pausa e descarte. As cenas só constroem objetos e implementam `update()`/`dispose()`. O Portfólio mantém até 12 capas em atlas/mesh; Ferramentas usa até quatro ícones/monogramas. Os cards e anchors HTML continuam sendo conteúdo e navegação reais.

```js
import { registerScene } from '../core/scene-runner.js';
import { createParticleField } from '../components/particle-field.js';
import { intAttr, floatAttr } from '../core/dom.js';

registerScene('hero-particles', ({ scene, camera, container, config }) => {
  const field = createParticleField({
    count: intAttr(container, 'count', 400),
    color: config.color ?? '#8a4fff',
    spread: floatAttr(container, 'spread', 12),
  });
  scene.add(field.points);
  const speed = floatAttr(container, 'speed', 0.05);
  const target = { x: 0, y: 0 };
  const onPointer = (event) => {
    target.x = (event.clientX / innerWidth - 0.5) * 0.6;
    target.y = (event.clientY / innerHeight - 0.5) * 0.6;
  };
  addEventListener('pointermove', onPointer, { passive: true });
  return {
    animated: true,
    update({ time }) {
      field.points.rotation.y = time * speed;
      camera.position.x += (target.x - camera.position.x) * 0.05;
      camera.position.y += (-target.y - camera.position.y) * 0.05;
      camera.lookAt(scene.position);
    },
    dispose() { removeEventListener('pointermove', onPointer); },
  };
});
```

A SceneModule **não** cria renderer, não inicia `requestAnimationFrame` e não instala `ResizeObserver` próprio. Isso evita gerenciadores paralelos e centraliza o ciclo de vida. `three/main.js` registra os módulos e chama `boot()`.

O adapter `three/thumbnail-runner.js` monta a cena declarativa usando o mesmo `mountScene()`. O wrapper `threejs-thumb.js` apenas detecta blocos e delega ao adapter. Para `custom_js`, permanece o adaptador especializado no iframe de origem opaca: a fronteira de segurança não é compartilhada com o documento Admin.

---

## 5. Cena 1 — Hero (partículas + parallax)

### Especificação

| Item | Valor |
| --- | --- |
| Arquivo canônico | `system/library/three/runtime/three/scenes/hero-particles.js` |
| Container | `data-scene="hero-particles"`, gerado pelo template do Hero |
| Conteúdo | Campo de partículas + parallax suave de câmera |
| Config via `data-*` | `data-count`, `data-color`, `data-speed`, `data-spread` |
| Renderização | Criada e descartada exclusivamente pelo `SceneRunner` compartilhado |

O módulo Hero cria apenas o campo de partículas e sua atualização. O engine controla lazy-init, render loop, redução de movimento, pausa ao sair da viewport, resize e limpeza de geometria/material. A cena 404 segue o mesmo contrato; a thumb declarativa chama `mountScene()` através de `thumbnail-runner.js`.

### Acceptance criteria (Cena 1)

- [ ] Sem WebGL ou com `prefers-reduced-motion`: fallback preservado;
- [ ] Canvas nasce somente quando o container entra na margem de viewport;
- [ ] Engine pausa ao sair da viewport/aba e libera recursos ao desmontar;
- [ ] DPR permanece limitado pelo perfil da cena;
- [ ] LCP do Hero continua dentro do orçamento da página publicada.

---

## 6. Cena 2 — Logo GLB

Evolui a Cena 1 no mesmo container. O módulo continua sendo `hero-particles.js`; o modelo é **opcional por construção** — sem `data-model`, ou em falha de rede/parse/WebGL, a cena permanece sendo só o campo de partículas (degradação silenciosa, ADR 0023).

```js
// system/library/three/runtime/three/scenes/hero-particles.js — só o bloco da Cena 2
if (config.model) {
  import('three/addons/loaders/GLTFLoader.js')   // dinâmico: só baixa quando há modelo
    .then(({ GLTFLoader }) => {
      new GLTFLoader().load(
        config.model,
        (gltf) => {
          lighting = createStudioLighting({ color: config.color ?? '#8a4fff' });
          scene.add(lighting.group);              // material Standard só é visível com luz
          model = gltf.scene;
          fitToViewport(model, camera, 0.5);      // centraliza e escala pelo bounding box
          scene.add(model);
        },
        undefined,
        () => { lighting = null; }                // partículas seguem como única cena
      );
    })
    .catch(() => {});
}
```

| Item | Valor |
| --- | --- |
| Asset | `dev/system/library/three/runtime/three/models/logo-da.glb` (publicado em `/catalog/three-runtime/three/models/logo-da.glb`) |
| Estado atual | GLB procedural vigente (monograma "DA", 84 triângulos, `MeshStandardMaterial`), aceito pelo mantenedor para continuidade da Cena 2 |
| Preparo do modelo | O asset atual é usado sem compressão adicional; export Blender/Draco/KTX2 pode ser avaliado como melhoria futura, sem bloquear implementação |
| Config | `data-model` no container, vindo do controller via `AssetManifest::heroModelUrl()` |
| Loader | `three/addons/loaders/GLTFLoader.js` (r186) + `BufferGeometryUtils.js` e `SkeletonUtils.js`, todos na allowlist |
| Import map | exige `"three/addons/": "{{ three_runtime_base }}three/addons/"` no `common/header.twig` |
| MIME | derivado por extensão no manifesto (`glb` → `model/gltf-binary`); o controller não muda |
| Degradação | se o modelo falhar (onError), mantém partículas |

Sem tabela, sem migration e sem setting novo: o asset passa pelo mesmo gateway allowlisted da Cena 1 (ADRs 0073/0075).

---

## 7. Cena 3 — Portfólio

### Etapa 1 — tilt CSS

A grade em `dev/catalog/view/template/partial/portfolio.twig` continua sendo conteúdo e navegação HTML. `dev/catalog/view/css/theme.css` aplica `perspective/rotateX/rotateY` nos `.pcard` com transição curta e zera a transformação em `prefers-reduced-motion`.

### Etapa 2 — galeria 3D opcional (ADR 0076)

A galeria é uma camada decorativa sobre a grade; não troca cards, links, SEO ou texto acessível. O canvas recebe `aria-hidden="true"`, fica sem interação própria e reage a `pointermove`, `mouseenter` e `focusin` nos próprios `.pcard`. Quando desativada, sem projetos, sem WebGL ou com movimento reduzido, a grade HTML e o tilt CSS continuam sendo a experiência completa.

- Módulo: `dev/system/library/three/runtime/three/scenes/portfolio-3d.js`, registrado em `three/main.js` e `AssetManifest`.
- Dados: URLs das capas já publicadas no controller, serializadas como atributo de dados escapado; o módulo rejeita origens diferentes da origem da página.
- Rede: o SceneRunner só instancia a cena perto da viewport. `TextureLoader` monta um atlas com até 12 capas; capas ausentes/falhas deixam tiles de cor. Um mesh combinado mantém um draw call de geometria.
- Controles: `three_portfolio_enabled` (default `0`) e `three_portfolio_frame_limit` (default `12`, faixa `1–12`) na tela existente `admin/setting/setting`; persistência por `rms_setting`, sem migration.
- Fallback visual: gradiente CSS atrás da seção; canvas `pointer-events:none`; os anchors continuam à frente e podem receber teclado/foco.

A etapa foi autorizada para implementação antes do cutover em 2026-10-27. Validação visual/performance fica pendente para ativação administrativa em produção, sem bloquear outras implementações.

### Cena 5 — Ferramentas conectadas (ADR 0077)

A seção `#tools` ganha uma camada 3D opt-in com até quatro planos texturizados, alinhados aos ícones dos cards. Movimento flutuante sutil e destaque ao passar o ponteiro ou focar os links não simulam física real nem alteram a navegação HTML.

- Módulo `dev/system/library/three/runtime/three/scenes/tools-3d.js`, importado por `three/main.js` e publicado pelo AssetManifest.
- O controller entrega nome e imagem dos quatro primeiros registros ativos. Imagem same-origin é usada quando disponível; sem imagem ou em falha, a textura mostra uma inicial do nome.
- `three_tools_enabled` (default `0`) usa o formulário e a persistência `rms_setting` existentes; sem migration.
- Sem WebGL/reduced-motion/desativada, os ícones HTML continuam visíveis. O canvas é decorativo, `aria-hidden` e não bloqueia foco, links ou ponteiro.

---

## 8. Cena 4 — Página 404

```twig
{# error/not_found.twig #}
<div class="nf-3d__canvas" aria-hidden="true"
     data-scene="not-found" data-count="120" data-drift="0.4">
  <div class="nf-3d__fallback" data-fallback></div>
</div>
```

Partículas com deriva aleatória ("fog") + texto HTML por cima. Arquivo próprio `scenes/not-found.js` reaproveitando `particle-field.js` com `drift` (velocidade por partícula).

---

## 9. Integração com o ReamurCMS

### 9.1 Controller → Twig

```php
// catalog/controller/common/home.php (trecho)
// O template usa a URL Catalog base + a rota pública do manifesto; nunca aponta para System direto.
$data['has_3d'] = $threeJs['hero_enabled'] || $portfolioEnabled || $tools3dEnabled; // união independente das cenas da Home
$data['three_runtime_base'] = $base . $this->three_asset_manifest->publicBasePath();

// Cena 2: o modelo é um asset allowlisted; '' quando não publicado (só partículas).
$heroModel = $this->three_asset_manifest->heroModelUrl();
$data['hero3d'] = $threeJs['hero'] + ['model' => $heroModel !== null ? $base . $heroModel : ''];
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

1. **Versionamento de assets** por query string no controller (ex.: `threejs-thumb.js?v={{ asset_version }}`), valor de config, bump a cada release;
2. **Headers** no servidor para a rota `/catalog/three-runtime/`: `Cache-Control: public, max-age=31536000, immutable` (só com versionamento!). O runtime vive em `system/library/three/runtime/` e é publicado apenas pelo gateway allowlisted;
3. **Compressão:** brotli/gzip para `.js`, `.wasm`, `.glb` (mime `model/gltf-binary`);
4. **Precarregar o hero:** `<link rel="modulepreload" href="{{ three_runtime_base }}three/main.js">` no `head` da home; o fallback é o gradiente CSS (`.hero-3d__fallback`), sem binário;
5. Commitar novos assets do runtime **uma vez por upgrade** de versão, anotando a versão (ex.: `r186`) no PR.

---

## 13. Cronograma

| Semana (Fase 3) | Entrega |
| --- | --- |
| S1 | Setup self-hosted + `scene-runner` + Cena 1 (partículas) em staging |
| S2 | Integração controller/Twig + idiomas + QA matriz de dispositivos |
| S3 | Cena 2 (logo GLB) — depende do asset 3D pronto |
| S4 | Portfólio: tilt CSS + galeria 3D opcional (ADR 0076) |
| S5 | Ferramentas: ícones 3D opcionais sobre cards HTML (ADR 0077) |

---

## 14. Troubleshooting

| Sintoma | Causa provável | Correção |
| --- | --- | --- |
| Canvas em branco, sem erro | import map depois do módulo / caminho errado | conferir ordem no `head` e paths com `asset_base` |
| `Unexpected token` no addon | addon importando `three` sem import map | import map precisa cobrir `three/addons/` |
| Zoom/mobile com FPS baixo | DPR 3–4 | `min(devicePixelRatio, 2)` |
| Modelo GLB não carrega | MIME/CORS ou Draco ausente | servir `.glb` como `model/gltf-binary`; conferir `libs/draco/` |
| Memória subindo a cada visita | falta limpeza de recursos/listeners | revisar `sceneHandle.dispose()` e o descarte central do SceneRunner |
| Cena "pisca" no carregamento | fallback removido tarde | só chamar `clearFallback()` após 1º frame renderizado |
| Lighthouse INP alto | rAF durante scroll pesado | verificar pausa fora da viewport; reduzir partículas |

---

## 15. Definition of Done

Uma cena só está pronta quando:

1. ✅ Factory registrada em `main.js` e executada pelo SceneRunner; renderer, ciclo de vida e descarte pertencem à engine;
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
