# BDREditor file/download — checker estático

Executar da raiz do pacote:

```sh
node tools/e2e/e2e_bdre_file_static.cjs
```

A ferramenta carrega o validador puro do editor em Node VM sem DOM/browser, verifica fixture válida/inválidas, helper de path, fontes backend, endpoints ACL/CSRF, renderer, picker e traduções.

Não chama PHP, SQL, servidor, banco, navegador, app, staging ou deploy. PASS significa apenas que os contratos estáticos verificados continuam presentes e que o validador cliente tratou as fixtures como esperado. Não certifica MIME em produção, permissões do filesystem, ZipArchive, renderer PHP ou fluxo HTTP real.
