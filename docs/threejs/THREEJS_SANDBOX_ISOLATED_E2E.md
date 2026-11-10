# Plano E2E isolado — Three.js custom_js

Fixture sem conexão com o site: servidor loopback, HTML in-memory, assets Three.js self-hosted locais, request interception por origem. O script de teste informa o resultado por `postMessage` e o runner valida `event.origin === 'null'`, a flag de sandbox estrita e a ausência de requests não locais.

Esse teste prova o isolamento e o encoding/execution do runtime em browser. Não valida login, CMS Admin, salvamento de draft, nem uma página pública Blog/CMS com conteúdo publicado.
