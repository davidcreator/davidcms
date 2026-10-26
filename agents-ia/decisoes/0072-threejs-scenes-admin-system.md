# ADR 0072: Configuração declarativa das cenas Three.js nativas via System e Admin

- **Status:** aceita para implementação incremental
- **Data:** 2026-10-26
- **Contexto:** a Cena 1 (home) e Cena 4 (404) já existem no Catalog, mas parâmetros estavam hard-coded nos controllers; o Admin não conseguia ativar/desativar ou ajustar a cena. A thumb Blog/CMS é um recurso separado e já possui editor próprio.

## Decisão

1. Criar `System\Library\Three\SceneConfig`, registrado como `three_scene_config` no Registry em `system/framework.php`, para normalização/validação dos parâmetros allowlisted. O serviço é sem estado e não acessa banco.
2. Persistir parâmetros via settings `code=config` existentes e o model `setting/setting`; não criar tabela, migration, arquivo PHP executável nem campo de script para cenas nativas.
3. Expor no formulário existente `admin/setting/setting` os toggles do Hero/404, partículas (50–800), cor `#RRGGBB` e velocidade (0,01–0,20, passo 0,01), protegidos pelos gates ACL/CSRF existentes.
4. Controllers Catalog consomem somente a configuração normalizada. Desativar WebGL mantém texto e fallback.
5. Reusar o Three.js local de `catalog/view/js/three.module.js` no import map nativo. Assets do editor Admin são solicitados de `catalog/view/` por `catalogAssetUrl()`/`pageCatalogAssetUrl()`.
6. Três consumidores do framework (admin, catalog e install) recebem apenas o serviço puro, sem dependência de DB/serviços adicionais; install não invoca os métodos de cena.

## Consequências

- ✅ Edição administrativa controlada; parâmetros verificados uma única vez; sem schema change.
- ✅ Home/404 carregam Three somente quando habilitadas, e o runtime atual mantém lazy-init/fallback.
- ⚠️ Esta fase não oferece scene builder genérico ou upload de modelos GLB; os módulos de cena permanecem allowlisted e versionados em código.
- ⚠️ Testes estáticos não substituem PHP lint e E2E no servidor CMS.

## Documentos atualizados

- `docs/THREEJS_NATIVE_SYSTEM.md`
- `docs/Implementacao-ThreeJS.md`
- `docs/Arquitetura-MVCL.md`
- `docs/Estrutura-de-Pastas.md`
