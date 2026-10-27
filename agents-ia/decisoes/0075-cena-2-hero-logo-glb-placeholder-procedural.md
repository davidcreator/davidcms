# ADR 0075 — Cena 2 do Hero: logo GLB servido pela allowlist do runtime

- **Status:** aceita; Cena 2 implementada com o GLB atual autorizado pelo mantenedor
- **Data:** 2026-10-26
- **Contexto:** A ADR 0021 adiou a Cena 2 (logo GLB) até o asset real existir e recusou explicitamente um "GLB placeholder gerado" por violar o princípio de zero binários artificiais das ADRs 0019/0020. O mantenedor reabriu o item, solicitou localizar `logo-da.glb` e autorizou a geração do asset caso ele não existisse, **sobrepondo** a negativa da ADR 0021. O runtime já está centralizado no System com entrega allowlisted pelo Catalog (ADRs 0072–0074), então a Cena 2 precisa caber nesse gateway sem criar um segundo caminho de asset.

## Decisão

1. O asset canônico é `dev/system/library/three/runtime/three/models/logo-da.glb`, entregue publicamente por `/catalog/three-runtime/three/models/logo-da.glb`. Fica **no System**, junto do runtime, e não em `catalog/view/assets/3d/` como previa o `docs/Implementacao-ThreeJS.md` §6 — manter uma única origem de assets evita cópia paralela e respeita a ADR 0073.
2. O arquivo atual é um **placeholder procedural válido** (monograma "DA": 168 vértices, 252 índices, 84 triângulos, um `MeshStandardMaterial` com `baseColorFactor` da marca). O binário foi gerado por script e **validado com o próprio `GLTFLoader` r186 em Node** antes de ser versionado. Não é um binário fictício: é um GLB 2.0 conforme que o loader real abre. O override da ADR 0021 é registrado aqui. O mantenedor autorizou usar este asset como a versão vigente da Cena 2 e prosseguir; um export futuro do Blender fica como melhoria opcional, não como bloqueio.
3. O gateway aceita a extensão `glb` sem caminho livre: a regex da rota passa a `\.(?:js|glb)` e o `AssetManifest` resolve `three/models/logo-da.glb`; o MIME é derivado por extensão na tabela `TYPES` (`glb` → `model/gltf-binary`). O controller `common/three.php` não muda: já publica o `Content-Type` devolvido pelo manifesto.
4. `GLTFLoader` r186 e suas dependências (`BufferGeometryUtils`, `SkeletonUtils`) entram na allowlist em `three/addons/`; o import map do `common/header.twig` ganha `"three/addons/": "{{ three_runtime_base }}three/addons/"`, necessário porque o loader importa de bare specifier `'three'` e de caminhos relativos `../utils/*`.
5. O `AssetManifest` expõe `heroModelUrl()` (escopo estreito, `null` quando não publicado) e o controller publica esse valor como `data-model` no container `[data-scene="hero-particles"]`. Sem tabela, sem migration e sem setting novo.
6. A Cena 2 é **opcional por construção**: o `GLTFLoader` é importado por `import()` dinâmico dentro de `hero-particles.js`; ausência de `data-model`, falha de rede, falha de parse ou ausência de WebGL mantêm as partículas como única cena (degradação silenciosa, ADR 0023). O rig de luz de estúdio só é criado dentro do `onLoad`.
7. A extensão `glb` também entra na regra F6-06 do `catalog/.htaccess`, para que um modelo ausente devolva 404 nativo em vez de ruído de rota no log.

## Alternativas consideradas

1. **Manter a Cena 2 adiada (ADR 0021)** — rejeitada pelo mantenedor, que autorizou o placeholder.
2. **Colocar o GLB em `catalog/view/assets/3d/` (guia §6)** — rejeitada: cria uma segunda origem de assets fora do gateway allowlisted da ADR 0073.
3. **Servir o GLB por leitura direta de filesystem no controller** — rejeitada pelo mesmo motivo das ADRs 0073/0074: o controller nunca recebe caminho livre.
4. **Substituir o hero por uma cena só de logo** — rejeitada: a Cena 2 evolui a Cena 1 no mesmo container; as partículas continuam sendo o fallback.
5. **Carregar o loader por `<script type="module">` estático** — rejeitada: obrigaria toda página com `has_3d` a baixar o `GLTFLoader` mesmo sem modelo publicado.

## Consequências

- ✅ A Cena 2 liga ponta a ponta sem segunda origem de asset, sem migration e sem setting novo.
- ✅ Falha do modelo não quebra a home: partículas permanecem e nada é logado como erro fatal.
- ✅ `system/` segue inacessível diretamente e a allowlist continua sendo o único caminho de publicação.
- ⚠️ O GLB vigente é procedural e compacto; foi aceito pelo mantenedor para seguir com o projeto. Um export do Blender e compressão Draco/KTX2 podem ser avaliados depois, sem bloquear a Cena 2.
- ⚠️ O `GLTFLoader` e dois utilitários passam a ser publicáveis; qualquer addon novo exige registro explícito no manifesto.

## Documentos relacionados

- [ADR 0019/0020 — tema e hero F3](../decisoes/0020-hero-threejs-cena-1-particulas-parallax-e-slot-webgl-no-chrome.md)
- [ADR 0021 — decisão sobreposta (Cena 2 adiada e placeholder recusado)](0021-portfolio-com-seo-real-tilt-css-nos-cards-e-404-com-fog-de-particulas.md)
- [ADR 0073 — runtime canônico no System com entrega allowlisted](0073-threejs-runtime-system-asset-gateway.md)
- [ADR 0074 — ciclo de vida compartilhado das cenas](0074-threejs-shared-scene-runner.md)
- `docs/Implementacao-ThreeJS.md` §6
- `docs/THREEJS_NATIVE_SYSTEM.md`
