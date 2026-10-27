# ADR 0077 — Cena 5: ícones 3D flutuantes das Ferramentas

- **Status:** aceita para implementação incremental por autorização do mantenedor
- **Data:** 2026-10-27
- **Agentes:** system, admin e catalog
- **Contexto:** a seção pública `#tools` já renderiza os registros ativos de Fluxa, Quotia, Vertex e Solis em cards HTML; cada um pode ter imagem ou cair numa inicial textual. O seed F3-01 deixa as imagens vazias, então a experiência não pode depender de logotipos ainda não enviados. A camada deve reutilizar a engine e o SceneRunner existentes, manter os links como navegação real e não acrescentar dependências, rotas ou schema.

## Decisão

1. A Cena 5 será uma **camada decorativa opcional** sobre os cards de Ferramentas da home. Os cards, seus títulos, descrições e links HTML continuam sendo o conteúdo e a navegação; o canvas será `aria-hidden="true"` e não interceptará ponteiro, foco ou teclado.
2. O módulo canônico será `dev/system/library/three/runtime/three/scenes/tools-3d.js`, registrado por `registerScene('tools-3d', …)`. Renderer, câmera, lazy-init, resize, reduced-motion, pausa e descarte continuam exclusivamente no `core/scene-runner.js`. A cena não criará renderer, `requestAnimationFrame`, `ResizeObserver` ou dependência de física.
3. A cena representa no máximo quatro cards, com movimento flutuante sinusoidal leve e resposta visual a `mouseenter`/`focusin` nos cards reais. Isso atende à ideia de física sutil sem simulação física nem novo pacote.
4. A configuração será `three_tools_enabled`, toggle na tela `setting/setting`, default `0`. Ela entra em `SceneConfig`, no model allowlist, no controller/form Admin e nos idiomas `en-us`, `pt-br` e `es-eu`. Persistência em `rms_setting`; nenhuma tabela ou migration.
5. O controller passa à cena apenas o nome e a imagem de cada uma das quatro primeiras ferramentas. Imagens só são usadas se forem same-origin; sem imagem ou em falha, a textura usa uma inicial derivada do nome. Não se inventam nem se embutem logotipos. O seed atual não inclui imagens, portanto esse fallback é necessário desde o primeiro uso.
6. O container `data-scene="tools-3d"` só existe quando a configuração está ativa e há ferramentas. `has_3d` passa a ser a união independente de Hero, Portfólio e Ferramentas habilitados; nenhuma cena reativa outra.
7. `three/scenes/tools-3d.js` entra no AssetManifest e em `three/main.js`. Nenhum asset binário ou addon novo é necessário. O carregamento das imagens começa somente após o lazy-init do SceneRunner.
8. Sem WebGL, com reduced motion, cena desligada, ausência de ferramentas ou falha de textura, os ícones/iniciais HTML e os links permanecem visíveis e funcionais. O CSS continua sendo o fallback.

## Alternativas consideradas

1. **Substituir os cards por canvas** — rejeitada: perderia conteúdo semântico, links reais e navegação por teclado.
2. **Modelar cada ferramenta como GLB** — rejeitada: não há esses modelos no repositório nem no seed; adicionaria binários e custo de rede sem necessidade.
3. **Instalar uma biblioteca de física** — rejeitada: movimento decorativo simples não justifica dependência, payload ou integração nova.
4. **Fazer apenas tilt CSS** — rejeitada para esta entrega: a proposta aprovada é a cena nativa Three.js, com a grade HTML preservada.

## Consequências

- ✅ Reaproveita o SceneRunner, o gateway allowlisted e a tela de configuração já existentes.
- ✅ O 3D é opt-in e não depende de upload de imagens para continuar funcionando.
- ✅ O HTML continua completo; a cena nunca é o único meio de identificar ou abrir uma ferramenta.
- ⚠️ Os monogramas são fallback visual, não substituem os logos oficiais; imagens reais podem ser carregadas pelos fluxos existentes de Administração.
- ⚠️ Até quatro materiais/quadros decorativos são renderizados; qualquer aumento de limite ou mudança para assets 3D exige revisão própria.

## Documentos relacionados

- [ADR 0072 — configuração declarativa de cenas](0072-threejs-scenes-admin-system.md)
- [ADR 0073 — runtime System e gateway allowlisted](0073-threejs-runtime-system-asset-gateway.md)
- [ADR 0074 — SceneRunner compartilhado](0074-threejs-shared-scene-runner.md)
- [ADR 0076 — galeria 3D opcional do Portfólio](0076-cena-3-etapa-2-galeria-3d-do-portfolio.md)
- `docs/Guia-ThreeJS.md`
- `docs/Implementacao-ThreeJS.md`
- `dev/catalog/view/template/partial/tools.twig`
