# Three.js — execução sandbox do script na Prévia completa Blog/CMS

Patch incremental para o snapshot upstream consultado em `e7c0b314278f1de178a9f2eb1530c5fe5ee8d6cf`.

O arquivo principal substitui `dev/admin/view/js/blog-block-editor.js`; o checker substitui `tools/e2e/check_threejs_thumb.cjs`. Os caminhos do ZIP são relativos à raiz do DavidCMS, não caminhos absolutos do computador.

Na Prévia completa, o bloco `core/threejs_thumb` serializa `custom_js` como UTF-8/Base64 no atributo `data-custom-js`. O runtime self-hosted já carregado pelos controllers Blog/CMS observa esse atributo e executa o script apenas no iframe sandbox opaco existente (`sandbox="allow-scripts"`, CSP com `connect-src 'none'`). O código personalizado não é avaliado no contexto da página Admin. O cartão inline do bloco não recebe o atributo e permanece declarativo.

## Aplicação e teste

1. Faça backup dos dois arquivos existentes.
2. Copie os dois arquivos deste pacote preservando os caminhos relativos.
3. Na raiz do projeto, execute:

```sh
node --check dev/admin/view/js/blog-block-editor.js
node tools/e2e/check_threejs_thumb.cjs
```

O checker necessita do restante da árvore-fonte do DavidCMS; não roda isoladamente a partir deste pacote. Este pacote não publica nem altera GitHub, não faz deploy, não executa migrations e não modifica configurações ou conteúdo.

## Limites da validação

Validação feita contra snapshot seletivo do HEAD acima: sintaxe JS aprovada e **21/21 verificações estáticas** aprovadas. Não houve teste de browser do preview nesta sessão. Conteúdo Blog/CMS público continua sem posts/páginas publicados disponíveis para teste; esta mudança permite exercitar o script pelo Preview do Admin, mas a confirmação visual do WebGL depende de executar o fluxo no seu ambiente.
