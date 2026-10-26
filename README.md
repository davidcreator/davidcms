# ReamurCMS — engine Three.js centralizada no System

Patch incremental para o HEAD consultado `5a4a11fae4e2ed79422f18c4455cbf7b486c0697`.

## O que muda

- O vendor, o runtime de cenas e o runtime de thumbs passam a ter uma única fonte em `dev/system/library/three/runtime/`.
- System registra `AssetManifest`; Catalog expõe apenas nomes allowlisted por `/catalog/three-runtime/...`. `dev/system/.htaccess` continua negando acesso direto ao núcleo.
- O endpoint inclui cache/ETag e MIME JavaScript; imports relativos dos módulos ESM continuam resolvendo na rota pública.
- Home/404, Blog/CMS e Admin usam a mesma engine/runtime. CSS de apresentação da thumb permanece no Catalog.
- Sem CDN, cópia paralela, migration ou alteração de conteúdo/settings.

## Aplicação do patch

1. Faça backup da instalação/branch.
2. Copie o conteúdo preservando os caminhos relativos para a raiz do DavidCMS.
3. Remova os arquivos listados em `DELETE_FILES.txt` após confirmar que as novas versões em System estão presentes. A lista elimina a cópia anterior no Catalog.
4. Não edite a pasta `system` para torná-la publicamente acessível; o acesso web deve continuar exclusivamente pela rota allowlisted do Catalog.

A rota depende de mod_rewrite, já usado pelo `.htaccess` do Catalog. O caminho permitido é fixo; o controller não aceita caminhos arbitrários.

## Validação desta etapa

- `node --check` passou para os módulos JS do runtime.
- Dois checkers estáticos existentes passaram: `check_threejs_thumb.cjs` (22/22) e `check_threejs_native_admin.cjs` (9/9).
- PHP CLI não está disponível nesta sessão; portanto não pude executar `php -l` nos arquivos PHP. Nenhum E2E contra a instalação foi feito.

Esta entrega é um patch para revisão/aplicação, não foi enviada ao GitHub nem aplicada ao ambiente compartilhado.
