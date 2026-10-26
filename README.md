# Three.js — E2E isolado do sandbox de custom_js

Runner de navegador para testar o mecanismo já existente de execução isolada sem abrir o Admin/Catalog e sem tocar na instalação compartilhada.

**Base consultada antes da preparação:** `davidcreator/davidcms` `main`, HEAD `5a4a11fae4e2ed79422f18c4455cbf7b486c0697`.

## O que o teste faz

- Sobe um servidor HTTP temporário em `127.0.0.1` numa porta aleatória.
- Serve apenas uma fixture em memória e os arquivos locais `threejs-thumb.js`, `three.module.js` e `three.core.js` do checkout.
- Usa Chromium via Playwright com WebGL/SwiftShader, instala um `custom_js` UTF-8 de teste e bloqueia requisições a outras origens.
- Verifica que o script sinaliza a execução com origem opaca `null`, que o iframe tem somente `sandbox="allow-scripts"` e que não houve requisições não locais.

Não visita URLs do DavidCMS, não recebe cookies/storage state, não usa credenciais e não escreve no banco, arquivos de conteúdo ou settings. Pode ser rodado sem ambiente descartável porque seu fixture e servidor são isolados do app.

## Execução

No checkout do DavidCMS, instale/tenha Playwright disponível **somente no ambiente local de QA** e copie o script preservando o caminho abaixo. Execute na raiz do projeto:

```sh
node --check tools/e2e/threejs_admin_preview_sandbox_isolated.cjs
node tools/e2e/threejs_admin_preview_sandbox_isolated.cjs
```

Dependendo do setup do Chromium, o suporte WebGL/SwiftShader pode não estar disponível. Nessa situação o runner falha sem afetar o projeto; não interprete falha de inicialização de WebGL como comprovação de quebra do sandbox.

## Validação desta sessão

`node --check` passou. Playwright não está instalado nesta sessão, então o browser test não foi executado. O checker do projeto para a integração Three.js passou **22/22** no snapshot consultado. Nenhuma escrita foi feita ao GitHub ou à instalação compartilhada.
