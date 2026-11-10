# Especificação da cena ThreeJS — build completa (Frente BO)

Extraído de `bo_ref/bundle_js.txt` (código real da amostra), **sem executar nada**.

## Medição

| Item | Valor |
|---|---|
| HTML | 953,239 bytes |
| JS (bundle) | 892,049 bytes |
| CSS (Tailwind + custom) | 60,271 bytes |
| Runtime | Three.js **r186** + React **19.2.6** (Vite, módulo inline) |

## Constantes da cena `g3`

| Constante | Valor | Sentido |
|---|---|---|
| `ho` | 320 | extensão do voo em Z (8 → −312) |
| `L_` | 9 | nave 9 unidades atrás da câmera |
| `rm` | 14 | total de orbes |
| `TRAIL` / `TRAIL_LIFE` | 60 / 0.95s | trilha de partículas |
| `vR` | 384400 | km exibidos no HUD (distância Lua) |
| fog | `FogExp2(0x121516, 0.0072)` | profundidade |
| câmera | `PerspectiveCamera(66, aspect, .1, 800)` em `(0,1.5,8)` | FOV 66 → ~82 no warp |

## Objetos construídos

| Método | Quantidade | Detalhe |
|---|---|---|
| `buildStars` | 1500 + 430 + 150 | 3 camadas `Points`, cores acento/coral/aço |
| `buildNebulae` | 7 | sprites com textura radial procedural |
| `buildPlanets` | 5 | `SphereGeometry` + bandas em canvas 2D, anéis `RingGeometry`, wireframe, glow |
| `buildAsteroids` | 46 | `IcosahedronGeometry` deformado, 3 materiais |
| `buildAstronaut` | 2 | cápsula, visor, mochila, braços, pernas |
| `buildGates` | 8 | 2 toros cada + 4 marcadores, pulso ao cruzar |
| `buildOrbs` | 14 | octaedro + wireframe + halo; coleta com `burst` |
| `buildShip` | 1 | ver abaixo |
| `buildTrail` | 60 | `Points` com `vertexColors`,寿命 0.95s |

## A nave (o que faltou no BN)

```text
  fuselagem          CylinderGeometry(0.5, 0.42, 1.9, 18) rot X 90
  bico               ConeGeometry(0.42, 1.25, 18) rot X -90, z=-1.58, coral
  cabine             CylinderGeometry(0.44, 0.3, 0.45, 14), z=1.15
  anel_luminoso      CylinderGeometry(0.505, 0.505, 0.14, 18), z=0.35
  canopy             SphereGeometry(0.3,16,12) escala (1,0.8,1.6)
  asas               ExtrudeGeometry de Shape (0,0)->(1.6,.7)->(1.6,1.15)->(0,.95), depth .06, espelhada
  armas              2 caixas (.07,.16,.5) em x=+-1.98, z=.62
  chamas             2 Cones transladados, rot X 90, z=1.37, blending aditivo
  luz                PointLight(0x5cc6d0, 3.2, 14) em z=2
  escala             0.95
```

## Interações (o que também faltou)

- **scroll**: setProgress(scrollY/(scrollHeight-innerHeight)) -> camera z = 8 - progress*320
- **mouse**: pointermove -> setPointer(x/innerWidth-.5, y/innerHeight-.5); alvo da nave = (px*12, -py*7) limitado a (-6.5..6.5, -3.6..3.6)
- **autopiloto**: sem ponteiro por 3.5s, ou pointer:coarse -> persegue o proximo orbe (nextOrb)
- **inercia**: lerp com 1-exp(-dt*4.2) (2.4 no coarse); roll/pitch/yaw derivados da velocidade
- **coleta**: orbe coletado se (dx^2+dy^2) < E^2 (E=2 desktop, 2.7 coarse) e z dentro da janela de 1 frame
- **feedback**: burst RingGeometry por 0.6s + toast 'Orbe coletado n/14'; ao completar 'Todos os orbes coletados'
- **gates**: 8 toros, pulso ao cruzar, opacidade animada
- **fases**: IntersectionObserver em section[data-stage] -> toast 'Fase alcançada'
- **warp**: velocidade da camera -> 0..1, aumenta FOV, chamas e velocidade da trilha
- **reduced_motion**: matchMedia('(prefers-reduced-motion: reduce)') desliga bob/deriva e acelera o lerp

## UI/acompanhamento

- **loader**: overlay 1700ms + fade 900ms, anel giratorio, barra 1.6s
- **hud**: header fixo com nav, contador ORBES nn/14, 3 bandeiras de idioma (pt/en/es), barra de progresso
- **typewriter**: bR(): 85ms/char, pausa 1900ms, apaga 42ms/char, rotaciona Creative Developer / Desenvolvedor Criativo / Desarrollador Creativo
- **reveal**: IntersectionObserver threshold .15, translateY(28px) -> 0
- **distancia**: Math.round(progress*384400).toLocaleString("en-US") km
- **fases**: 9
- **catalogo**: {'projetos': 9, 'galerias': '6 imagens por projeto (exceto JWA e Farmer)', 'modal': 'teclado Esc/setas, thumbnails, RedBubble'}
- **contato**: form -> mailto: (sem backend), honeypot website
- **i18n**: ['pt', 'en', 'es']

## O que é código executável na build

- WebGLRenderer + requestAnimationFrame (tick)
- event listeners: resize, pointermove, scroll, keydown
- canvas 2D para texturas procedurais (estrelas, nebulosas, bandas dos planetas)
- import de three via bundle (r186) e React 19.2.6 via createRoot
- document/window/localStorage/history.replaceState

---

Documento gerado por `validacao/bo_spec.py`. O bundle original está em
`bo_ref/bundle_js.txt` / `bundle_css.txt` / `build.html` para referência.

**Nenhum trecho foi copiado para dentro de um `.reamurtheme.json`.**
