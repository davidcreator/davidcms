# ADR 0074 — Ciclo de vida compartilhado para cenas Three.js e thumbs declarativas

- **Status:** aceita para implementação incremental
- **Data:** 2026-10-26
- **Contexto:** O runtime Three.js foi centralizado no System e publicado pelo gateway allowlisted (ADR 0073), mas as cenas nativas e as thumbs declarativas ainda tinham caminhos de inicialização/renderização separados. A duplicação aumenta o risco de divergência em WebGL fallback, resize, pausa, reduced-motion e descarte.

## Decisão

1. `dev/system/library/three/runtime/three/core/scene-runner.js` é a única camada confiável que cria renderer/câmera, agenda renderização e administra lazy-init, resize, visibilidade, `prefers-reduced-motion` e descarte.
2. Cenas nativas registram factories que recebem o contexto da engine e retornam somente comportamento/limpeza da cena; não criam renderer nem mantêm loops ou observadores de ciclo de vida próprios.
3. `three/thumbnail-runner.js` adapta thumbs declarativas para `mountScene()` do mesmo SceneRunner. Os defaults/configurações e o fallback existentes são preservados.
4. `custom_js` fica fora desse caminho confiável: continua sendo executado somente pelo adaptador isolado em iframe sandbox de origem opaca, sem execução no documento Admin.
5. A allowlist do manifesto System permanece a única forma de expor novos módulos pelo gateway Catalog. Sem cópias em Catalog, mudança de schema ou migration.
6. Consumidores Admin/Catalog mantêm suas APIs/markup atuais; mudanças cross-app devem continuar verificando os pontos de entrada e configuração de cada consumidor.

## Consequências

- ✅ Um só ciclo de vida WebGL para cenas nativas e thumbs declarativas, reduzindo implementações concorrentes.
- ✅ Fronteira de segurança preservada para scripts personalizados.
- ⚠️ Factories de cena existentes precisam seguir o contrato comum; integrações devem preservar fallbacks e configuração.
- ⚠️ Verificações estáticas não substituem validação funcional em browser, que não faz parte da verificação mínima desta entrega.

## Documentos relacionados

- [ADR 0072 — Configuração declarativa das cenas Three.js nativas via System e Admin](0072-threejs-scenes-admin-system.md)
- [ADR 0073 — Runtime Three.js canônico no System com entrega allowlisted pelo Catalog](0073-threejs-runtime-system-asset-gateway.md)
- `docs/THREEJS_NATIVE_SYSTEM.md`
- `docs/THREEJS_THUMB.md`
- `docs/Implementacao-ThreeJS.md`
