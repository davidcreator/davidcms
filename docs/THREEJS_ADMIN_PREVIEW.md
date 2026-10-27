# Admin Preview: fluxo do custom_js

`renderPreviewBlock()` é usado pela Prévia completa do editor Blog/CMS e cria um stage Three.js. Para esse stage, o código opcional é convertido a UTF-8/Base64 via `TextEncoder`/`btoa` e escrito usando `setAttribute`, sem interpolar JS em HTML. O atributo não é adicionado pelo cartão de edição do bloco.

A execução é delegada ao runtime existente `threejs-thumb.js`, do runtime do System, publicado aos editores Blog/CMS pela rota allowlisted `/catalog/three-runtime/threejs-thumb.js`. O runtime detecta `[data-threejs-thumb][data-custom-js]`, decodifica Base64 e usa seu fluxo `mountSandbox()` no iframe de origem opaca. O iframe não tem `allow-same-origin`; a política CSP bloqueia rede (`connect-src 'none'`) e recursos externos. A cena declarativa continua sendo validada pelo runtime existente.

O atributo Base64 não é mecanismo de segurança por si só; o limite de segurança é o iframe sandbox e a política aplicada pelo runtime. Por isso, não se deve substituir esse caminho por `eval`/`new Function` diretamente no documento Admin.
