# Three.js — engine única no System, Admin e Catalog

**Base de referência:** `davidcreator/davidcms` `main`, HEAD `7e598559fafd4ba1a7c8f28aa5df266654fff52d`.

## Arquitetura

- `dev/system/library/three/scene_config.php` é a fonte única dos limites/defaults das cenas nativas; `framework.php` registra os serviços Three.js no Registry.
- `dev/system/library/three/asset_manifest.php` mantém a allowlist de módulos e o prefixo público comum.
- O único vendor/runtime de navegador fica em `dev/system/library/three/runtime/`. `three/core/scene-runner.js` é a única camada de criação WebGL, animação, visibilidade, resize e descarte para Hero/404/Portfólio e thumbs declarativas.
- `three/thumbnail-runner.js` traduz cenas de thumb para a mesma API do SceneRunner. `threejs-thumb.js` é apenas wrapper de descoberta; `custom_js` permanece em adapter separado no iframe sandbox opaco por fronteira de segurança.
- `system/.htaccess` continua negando acesso direto. `catalog/.htaccess` redireciona somente `/three-runtime/*.{js,glb}` ao adaptador `catalog/controller/common/three.php`; o controller serve apenas nomes resolvidos pelo manifesto, com MIME derivado da extensão (`application/javascript` para `js`, `model/gltf-binary` para `glb`), `nosniff`, ETag e cache HTTP.
- Cena 2 do Hero (ADR 0075): `logo-da.glb` vive em `runtime/three/models/` e é publicado pelo mesmo gateway. `three/addons/loaders/GLTFLoader.js` e seus utilitários estão na allowlist; o import map inclui `three/addons/`. O modelo é carregado por `import()` dinâmico em `hero-particles.js` — sem `data-model` ou em falha de rede/parse/WebGL, a cena permanece só com partículas. O GLB versionado é um placeholder procedural até o export real do Blender ser entregue.
- Cena 3 etapa 2 (ADR 0076) usa a mesma engine/gateway: `portfolio-3d.js` compõe até 12 capas same-origin num atlas e numa geometria combinada; a grade HTML continua acessível. O toggle é opt-in, default desligado, e o módulo é allowlisted no manifesto.
- Home/404, Blog/CMS e Admin usam essa mesma origem. Os estilos da thumb permanecem no Catalog, sem renderer concorrente.
- As cenas mantêm fallback e carregamento condicional. Script personalizado não é executado no documento Admin.

## Persistência e segurança

As configurações Hero/404/Portfólio usam settings existentes e ACL/CSRF já existentes. A galeria usa `three_portfolio_enabled` (default `0`) e `three_portfolio_frame_limit` (default `12`, validado de 1 a 12); a persistência segue `rms_setting`. Sem tabela ou migration. O manifesto aceita nomes fixos e nunca recebe caminho de filesystem arbitrário. Não usar CDN ou duplicar `three.module.js`/runtime sob Catalog.

## Operação

As URLs públicas de módulos usam `/catalog/three-runtime/` para que imports ESM relativos (`three.core.js`, `three/addons/*`, cenas e helpers) continuem resolvendo no mesmo diretório lógico. O `asset_manifest.php` é a única lista de arquivos servidos. Para adicionar módulo Three.js (ou um asset 3D como o `logo-da.glb`), registrar no manifesto e colocar no caminho `system/library/three/runtime/` correspondente; não criar outra cópia no Catalog.

## Verificação mínima

- `node --check` nos módulos JavaScript tocados.
- `php -l` nas classes/controladores PHP tocados, quando PHP CLI estiver disponível.
- Nenhum teste mutável em instalação persistente/compartilhada. E2E completo é opcional, fora desta mudança de arquitetura.

A atualização da galeria 3D nesta entrega é uma alteração de workspace para revisão; não foi enviada automaticamente ao GitHub nem aplicada à instalação compartilhada.
