# Three.js — engine única no System, Admin e Catalog

**Base de referência:** `davidcreator/davidcms` `main`, HEAD `11447edc9e09cfdf1c46c43cacafad34a140cdf6`.

## Arquitetura

- `dev/system/library/three/scene_config.php` é a fonte única dos limites/defaults das cenas nativas; `framework.php` registra os serviços Three.js no Registry.
- `dev/system/library/three/asset_manifest.php` mantém a allowlist de módulos e o prefixo público comum.
- O único vendor/runtime de navegador fica em `dev/system/library/three/runtime/`. `three/core/scene-runner.js` é a única camada de criação WebGL, animação, visibilidade, resize e descarte para Hero/404 e thumbs declarativas.
- `three/thumbnail-runner.js` traduz cenas de thumb para a mesma API do SceneRunner. `threejs-thumb.js` é apenas wrapper de descoberta; `custom_js` permanece em adapter separado no iframe sandbox opaco por fronteira de segurança.
- `system/.htaccess` continua negando acesso direto. `catalog/.htaccess` redireciona somente `/three-runtime/*.js` ao adaptador `catalog/controller/common/three.php`; o controller serve apenas nomes resolvidos pelo manifesto, com MIME JS, `nosniff`, ETag e cache HTTP.
- Home/404, Blog/CMS e Admin usam essa mesma origem. Os estilos da thumb permanecem no Catalog, sem renderer concorrente.
- As cenas mantêm fallback e carregamento condicional. Script personalizado não é executado no documento Admin.

## Persistência e segurança

As configurações Hero/404 usam settings existentes e ACL/CSRF já existentes. Sem tabela ou migration. O manifesto aceita nomes fixos e nunca recebe caminho de filesystem arbitrário. Não usar CDN ou duplicar `three.module.js`/runtime sob Catalog.

## Operação

As URLs públicas de módulos usam `/catalog/three-runtime/` para que imports ESM relativos (`three.core.js`, cenas e helpers) continuem resolvendo no mesmo diretório lógico. O `asset_manifest.php` é a única lista de arquivos servidos. Para adicionar módulo Three.js, registrar no manifesto e colocar no caminho `system/library/three/runtime/` correspondente; não criar outra cópia no Catalog.

## Verificação mínima

- `node --check` nos módulos JavaScript tocados.
- `php -l` nas classes/controladores PHP tocados, quando PHP CLI estiver disponível.
- Nenhum teste mutável em instalação persistente/compartilhada. E2E completo é opcional, fora desta mudança de arquitetura.

Esta atualização não foi publicada automaticamente ao GitHub nem aplicada à instalação compartilhada.
