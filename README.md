# BDREditor — mídia Blog e thumbs Three.js

Pacote incremental verificado contra `davidcreator/davidcms`, branch `main`, HEAD `e33a47e7c26b36e4b1e4fe8a31990f1605f27740` (consulta somente leitura; sem clone completo ou escrita GitHub).

Inclui upload seguro de MP3/OGG/WAV (20 MiB) e MP4/WebM/OGV (100 MiB) para a biblioteca Blog, seleção e arrastar/soltar, blocos locais de áudio/vídeo e integração no BDREditor de Blog e CMS > Páginas. Acrescenta o bloco `core/threejs_thumb`: cenas 3D declarativas, com controles no Inspetor para adicionar/remover formas e luzes e editar seus atributos; validadas por allowlist, com Three.js 0.186.1 self-hosted, sem JavaScript arbitrário, CDN, modelos ou recursos externos.

Segurança: MIME detectado, nomes aleatórios, pastas dedicadas e POST+CSRF+ACL `modify blog/blog`; CMS também exige `modify cms/page`. Sem exclusão, migrations, escrita GitHub, staging ou deploy. O conteúdo da página continua usando envelope v2 no campo existente e salvamento manual do formulário.

Documentação: `docs/BLOG_MEDIA_UPLOAD.md`, `docs/THREEJS_THUMB.md` e `docs/IMPORT_FORMATS.md`. Checkers estáticos em `tools/e2e/`. Eles não substituem o roteiro funcional no CMS local com permissões e ambiente configurados. PHP CLI não estava instalado nesta sessão; lint PHP e validação funcional no backend permanecem pendentes.
