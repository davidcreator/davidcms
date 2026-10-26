# BDREditor — mídia Blog e thumbs Three.js

Pacote incremental verificado contra `davidcreator/davidcms`, branch `main`, HEAD `37e0615ec48a05ab42a193e2f25028f529fc12d4` (consulta somente leitura; sem clone completo ou escrita GitHub). As 13 fontes-base verificadas coincidiram byte a byte com o pacote anterior.

Inclui upload seguro de MP3/OGG/WAV (20 MiB) e MP4/WebM/OGV (100 MiB) para a biblioteca Blog, seleção e arrastar/soltar, blocos locais de áudio/vídeo e integração no BDREditor de Blog e CMS > Páginas. Acrescenta o bloco `core/threejs_thumb`: cenas 3D declarativas, validadas por allowlist, com Three.js 0.186.1 self-hosted; sem JavaScript arbitrário, CDN, modelos ou recursos externos.

Segurança: MIME detectado, nomes aleatórios, pastas dedicadas e POST+CSRF+ACL `modify blog/blog`; CMS também exige `modify cms/page`. Sem exclusão, migrations, escrita GitHub, staging ou deploy. O conteúdo da página continua usando envelope v2 no campo existente e salvamento manual do formulário.

Documentação: `docs/BLOG_MEDIA_UPLOAD.md`, `docs/THREEJS_THUMB.md` e `docs/IMPORT_FORMATS.md`. Checkers estáticos em `tools/e2e/`. Eles não substituem o roteiro funcional no CMS local com permissões e ambiente configurados. PHP CLI não estava instalado nesta sessão; lint PHP e validação funcional no backend permanecem pendentes.
