# Three.js — ADR 0076: galeria 3D decorativa do Portfólio

Pacote de overlay com as alterações da ADR 0076, preparado sobre o HEAD consultado `7e598559fafd4ba1a7c8f28aa5df266654fff52d`.

## Escopo
- Toggle `three_portfolio_enabled` desligado por padrão e `three_portfolio_frame_limit` default 12, validado de 1 a 12, no formulário Admin já existente e nos idiomas `pt-br`, `en-us` e `es-eu`.
- Galeria decorativa com capas same-origin (máximo 12), registrada no AssetManifest allowlisted e montada pelo SceneRunner central.
- Anchors/cards HTML permanecem como navegação/SEO; fallback CSS preservado para WebGL indisponível, falha de imagem e movimento reduzido.
- `has_3d` carrega o runtime com Hero **ou** Portfólio ativo; o container Hero respeita o toggle próprio.
- Sem migration, push ou deploy.

## Aplicação
Extraia o conteúdo na raiz do repositório que esteja no HEAD de referência. O pacote contém somente arquivos do overlay e não é uma cópia integral do repositório; não exclua arquivos não listados.

## Verificações realizadas
- `node --check` no novo módulo, `main.js` e checker Admin.
- `node tools/e2e/check_threejs_native_admin.cjs`: 11/11.
- `node tools/e2e/check_threejs_thumb.cjs`: 22/22.

PHP lint não foi executado (PHP CLI indisponível); não houve QA visual em browser/staging. A opção permanece desligada por padrão até esse QA.
