# ADR 0076 — Cena 3 etapa 2: galeria 3D do Portfólio

- **Status:** aceita para implementação incremental; etapa antecipada por autorização do mantenedor
- **Data:** 2026-10-27
- **Contexto:** A ADR 0021 entregou a Cena 3 etapa 1 como tilt CSS puro nos cards do portfólio e deixou a etapa 2 (galeria 3D navegável) explicitamente fora do escopo do lançamento. O `docs/Implementacao-ThreeJS.md` §7 descreve a etapa 2 em uma linha — `scenes/portfolio-3d.js`, "quadros 3D com as capas dos projetos como texturas" — sem contrato, sem orçamento e sem critério de acessibilidade. Desde então o runtime passou a ter origem única no System com entrega allowlisted pelo Catalog (ADRs 0072–0074) e o ciclo de vida das cenas foi centralizado no `SceneRunner` (ADR 0074), então a etapa 2 precisa ser especificada dentro dessas restrições antes de qualquer código. A grade atual é HTML real e acessível: `dev/catalog/view/template/partial/portfolio.twig` renderiza `.pgrid` > `.pcard` (âncoras com nome, cliente, badge e `<img loading="lazy">` da capa, ou a inicial do nome quando não há imagem), alimentada pelo controller com `$data['projects']`.

## Decisão

1. A etapa 2 nasce como **camada decorativa opcional sobre a grade existente**, nunca como substituta dela. A cena é `aria-hidden="true"` e a navegação/SEO continuam nos anchors `.pcard` reais — mesma postura de acessibilidade adotada nas cenas Hero e 404 (ADR 0020). Nenhuma rota, URL ou âncora muda.
2. O módulo canônico é `dev/system/library/three/runtime/three/scenes/portfolio-3d.js`, auto-registrado por `registerScene('portfolio-3d', …)`, devolvendo apenas `{ animated, update(), dispose() }`. `WebGLRenderer`, câmera, `ResizeObserver`, lazy-init, `prefers-reduced-motion`, pausa ao sair da viewport e descarte permanecem exclusivamente no `core/scene-runner.js` (ADR 0074) — a cena não cria `requestAnimationFrame` próprio.
3. O container é um elemento novo `[data-scene="portfolio-3d"]` dentro de `#portfolio`, `aria-hidden="true"`, com fallback declarado em `data-fallback` (gradiente CSS, como `.hero-3d__fallback` e `.nf-3d__fallback`). Ele só é emitido quando a cena está habilitada e há projetos. `has_3d` é a união de Hero habilitado com Portfólio habilitado e projetos; o container Hero também omite `data-scene` quando o Hero está desativado, para que uma cena não reative a outra.
4. Textura: as capas já publicadas em `$data['projects'][*]['image']` entram como `THREE.TextureLoader`. O `TextureLoader` é exportado pelo próprio `three.module.js`, portanto **nenhum addon novo** entra na allowlist. As imagens são da mesma origem (sob `$base`), sem CDN e sem CORS externo. Projeto sem capa → quadro com cor da marca (o placeholder de inicial da grade permanece no HTML).
5. Orçamento e carregamento: no máximo um número limitado de quadros (chave declarativa) e **carregamento lazy por interseção** — a montagem já acontece via `IntersectionObserver` no `SceneRunner`, então nenhuma textura é buscada no `load` da página. Texturas recebem `colorSpace = SRGBColorSpace`; como as capas vêm do banco do usuário, o módulo tolera dimensões arbitrárias (sem `mipmaps` forçado) para não degradar visualmente.
6. Configuração declarativa, sem tabela ou migration, pela tela existente `setting/setting`: `three_portfolio_enabled` (default `0`, toggle) e `three_portfolio_frame_limit` (default `12`, inteiro validado de 1 a 12). As chaves entram em `SceneConfig::DEFAULTS`/`validateSettings()` e no `runtime()` como `portfolio_enabled` + `portfolio`; o model mantém sua allowlist atualizada. Labels, ajuda e erros usam os três idiomas do Admin.
7. Registro no gateway: `three/scenes/portfolio-3d.js` entra em `AssetManifest::FILES` e passa a ser importado por `three/main.js`. A rota `three-runtime/` já cobre `.js`; nada de cópia paralela sob Catalog (ADR 0073).
8. Navegação **no HTML, não no canvas**: foco e setas/Enter operam nos `.pcard` reais; a cena apenas reage ao item em foco (`focusin`/`mouseenter`) e usa `pointermove` para um parallax leve do rig, como no Hero. Nada de um segundo modelo de navegação inacessível dentro do canvas.
9. Degradação e desligamento: sem WebGL, com `prefers-reduced-motion`, com a cena desabilitada pela configuração ou sem projetos, a etapa 1 (tilt CSS) permanece como única experiência 3D e o HTML não regride. Falha de carregamento de textura nunca rejeita a cena.

## Alternativas consideradas

1. **Substituir a grade HTML por uma galeria em canvas** — rejeitada: perde SEO, acessibilidade e os links reais, contrariando as ADRs 0020/0021.
2. **Modelar cada projeto como GLB no `GLTFLoader`** — rejeitada: exigiria um asset 3D por projeto (nenhum existe) e multiplicaria o custo de rede do gateway.
3. **Carregar todas as capas no `load` da página** — rejeitada: estoura o orçamento de rede da home/portfólio e trava a primeira dobra.
4. **`CubeCamera`/render target para reflexos** — rejeitada: custo de GPU desproporcional para uma camada decorativa; iluminação fixa barata (como a do Hero) é suficiente.
5. **Colocar o módulo em `catalog/view/js/three/`** — rejeitada: segunda origem de assets, proibida pela ADR 0073.
6. **Criar uma rota pública nova só para o portfólio 3D** — rejeitada: a rota `three-runtime/` já publica o módulo; a navegação continua nos anchors.

## Consequências

- ✅ Especificação fechada sem tocar em gateway, `SceneRunner`, tabelas ou settings existentes.
- ✅ SEO e acessibilidade intactos: a galeria 3D é enriquecimento progressivo sobre a grade.
- ✅ Reaproveita o `TextureLoader` do módulo oficial — a allowlist não cresce com addons.
- ✅ O mantenedor autorizou antecipar a etapa normalmente pós-lançamento em 2026-10-27; o toggle fica desligado por padrão até ativação administrativa.
- ⚠️ A ativação efetiva em produção ainda depende de QA visual/performance e do cutover; as chaves ficam na tela existente e não exigem migration.
- ⚠️ As capas são assets publicados pelo usuário via banco: sem garantia de potência de dois nem de compressão, o que limita o ganho visual da galeria em coleções com imagens grandes.

## Documentos relacionados

- [ADR 0020 — Cena 1 do Hero e slot WebGL no chrome](0020-hero-threejs-cena-1-particulas-parallax-e-slot-webgl-no-chrome.md)
- [ADR 0021 — Cena 3 etapa 1 (tilt CSS) e Cena 4 (404)](0021-portfolio-com-seo-real-tilt-css-nos-cards-e-404-com-fog-de-particulas.md)
- [ADR 0073 — runtime canônico no System com entrega allowlisted](0073-threejs-runtime-system-asset-gateway.md)
- [ADR 0074 — ciclo de vida compartilhado das cenas](0074-threejs-shared-scene-runner.md)
- [ADR 0075 — Cena 2 do Hero: logo GLB pela allowlist](0075-cena-2-hero-logo-glb-placeholder-procedural.md)
- `docs/Implementacao-ThreeJS.md` §7
- `dev/catalog/view/template/partial/portfolio.twig`
- `dev/catalog/view/css/theme.css` (etapa 1: `.pcard` com tilt e guard `prefers-reduced-motion`)
