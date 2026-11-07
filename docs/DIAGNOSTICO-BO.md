# Diagnóstico — BO: por que o tema não trouxe a nave nem as interações

**Data:** 2026-11-07 · **HEAD:** `ad7643de6ca2`
**Sua correção:** *"No tema não veio a nave e as interações do threejs veio só a parte visual básica"* — **procede**. O BN entregou o fallback declarativo (pontilhado CSS + `data-scene`), não a cena real.

## 1. O que a build que você colou realmente contém

Bundle salvo em `bo_ref/bundle_js.txt` (extraído do HTML real de
`https://davidalmeida.xyz/threejs/?lang=pt`), **sem executar nada**:

| Medição | Valor |
|---|---|
| HTML | 952.888 bytes |
| JS (módulo inline) | **891.703 bytes** |
| CSS (Tailwind + custom) | 60.271 bytes |
| Runtime | **Three.js r186 + React 19.2.6** (Vite) |

A cena é a classe **`g3`** (~14,7 KB): `buildStars`, `buildNebulae`,
`buildPlanets`, `buildAsteroids`, `buildAstronaut`, `buildGates`,
`buildOrbs`, `buildShip`, `buildTrail`, `tick()`. Constantes medidas:
`ho=320`, `L_=9`, `rm=14`, `TRAIL=60`, `TRAIL_LIFE=.95`, `vR=384400`,
`FogExp2(0x121516,.0072)`, `PerspectiveCamera(66,...)`.

Objetos: **2.080 estrelas**, 7 nebulosas, 5 planetas (anéis/wireframe/glow),
46 asteroides, 2 astronautas, 8 gates, **14 orbes**, trilha de 60 partículas
e **1 nave** (fuselagem `CylinderGeometry(.5,.42,1.9,18)`, bico
`ConeGeometry(.42,1.25,18)`, asas `ExtrudeGeometry`, 2 chamas aditivas,
`PointLight` ciano).

Interações reais: `setProgress()` por scroll (câmera `8 → −312`),
`setPointer()` por `pointermove` (nave persegue o mouse com inércia),
autopiloto após 3,5 s / em touch, `collect()` com raio 2 (2,7 no coarse),
`burst` + toast `n/14`, pulso nos gates, `warp` alterando FOV/chamas/trilha,
`prefers-reduced-motion`. UI: loader 1,7 s, HUD com ORBES e bandeiras pt/en/es,
typewriter, `IntersectionObserver` de fases, distância em km, catálogo com
modal de 9 projetos, formulário `mailto:`.

Especificação completa: `ESPECIFICACAO-CENA.md` e `especificacao.json`.

## 2. Onde o `.reamurtheme.json` bloqueia isso (provado)

`bo_ref/prova.php` com as **classes reais** do HEAD — **12 OK / 0 FALHAS**:
`ThemePackage`, `ConditionEngine`, `TokenRegistry`, `ThemeSnapshot`.

| Verificação | Resultado |
|---|---|
| bundle JS **892.049 bytes** > `MAX_BYTES` 524.288 | ✅ excede |
| bundle JS > limite por fonte 131.072 | ✅ excede (6,8×) |
| JS + CSS = 952.320 bytes > `MAX_BYTES` | ✅ excede |
| `validateConfig()` com `<script>` | **`template_source_invalid`** |
| `validateConfig()` com chave nova `scene_module` | **`config_key_invalid`** |
| JS como texto puro num template | passa, **mas vira conteúdo escapado pelo autoescape** — não executa |
| `ConditionEngine::scenes()` | `["", "hero-particles", "portfolio-3d", "tools-3d", "not-found"]` |
| cena de nave na allowlist | **não existe** |
| `slots()` | `["hero","home_top","footer_top"]` |
| `SCENE_KINDS` | `rect, text, button, card, section` |
| `SCENE_MAX_LAYERS` | 48 |

Ou seja: o tema **não tem onde guardar código**, o validador **rejeita
`<script>`**, a allowlist **não conhece a cena**, e o `scene-runner.js` do
Catálogo só faz `boot()` nas 4 cenas genéricas. O `theme-scene.twig` emite
apenas atributos `data-*`. Isso é consequência direta do **Recorte A** que
você mesmo fixou (`ask_user` → `seguranca=manter`: *"dados validados, sem
código executável"*).

**Erro de comunicação meu:** no BN eu escrevi que "o WebGL era montado em
runtime pelo `scene-runner.js`" sem deixar claro que esse runtime **não é a
sua cena `g3`** — ele desenha partículas/portfólio/ferramentas genéricos.
A nave e as interações nunca estiveram no escopo do que eu entreguei, e eu
devia ter dito isso antes de chamar aquilo de "tema completo".

## 3. Caminhos possíveis (nenhum executado ainda)

| Caminho | O que muda | O que exige de você |
|---|---|---|
| **A — Recorte B dentro do CMS** | Registrar cena `david-universe` no renderer self-hosted; portar `g3` para um módulo Three.js enxuto **sem React e sem o bundle de 892 KB**; tema passa a `data-scene="david-universe"` | Autorizar **código executável**, `SCENES`/`ScenePresetRegistry`/`scene-runner.js`/`asset_manifest.php`/`CHECKSUMS.txt`. Sem migration. Você aplica |
| **B — Projeto standalone** | Entregar HTML + módulo Three.js + CSS que roda a cena real, **fora do `.reamurtheme.json` e sem tocar no ReamurCMS** | Autorizar código executável num pacote à parte; decidir depois a integração |
| **C — Recorte A mantido** | Nave/planetas/orbes/gates em **SVG inline** + `kaz_scene` (≤48 camadas) + CSS | Fidelidade **visual estática** apenas. **Sem** pilotagem, coleta, scroll-driven ou toasts |
| **D — Só especificação** | Levar à pauta D01–D10 e aguardar decisão de arquitetura | Nenhuma implementação agora |

Os caminhos **A** e **B** revertem uma decisão sua explícita
(`seguranca=manter`) e tocam em contratos/allowlist que as frentes
anteriores mantiveram congelados. Por isso **não os executei por conta
própria**: preciso da sua autorização.

## 4. O que este pacote NÃO faz

Não entrega novo `.reamurtheme.json`, não entrega ZIP, não altera `dev/`,
não escreve no GitHub, não dispara CI/runtime/migration/deploy, não copia
nenhum trecho do bundle para dentro de um tema, não converte `PENDING`/
`NOT_RUN`/`BLOCKED_NOT_RUN` em `PASS`, não decide D01–D10 e **não declara
release**. `release_approved:false`, F9-27 `BLOCKED_NOT_RUN`, F9-18
`release_ready:false`, 10 `PENDING_DECISION` permanecem.

Os patches A/B da BL continuam **propostas não aplicadas** (o HEAD tem
`dev/` intacto), e o aceite visual da BN continua pendente.
