# BDREditor — upload seguro de áudio e vídeo

Pacote incremental sobre `davidcreator/davidcms` `main` em `d94b46e82502dd75e5c6da43b7fcca3c47b0db8d` (consulta somente leitura).

Inclui upload de MP3/OGG/WAV (20 MiB) e MP4/WebM/OGV (100 MiB) para a biblioteca Blog, seleção e arrastar/soltar, além de um bloco `core/video_file` com renderização local. Aplicado à interface de Blog e CMS > Páginas.

Segurança: allowlist de extensão e MIME detectado, nome aleatório, pastas dedicadas, POST+CSRF+ACL `modify blog/blog`; CMS também exige `modify cms/page`. Sem exclusão, migrations, escrita GitHub, staging ou deploy. A configuração de limites do PHP/webserver e a pasta gravável precisam ser conferidas no ambiente de destino.

Ver `docs/BLOG_MEDIA_UPLOAD.md` para regras operacionais e roteiro de validação. Os testes desta entrega são estáticos; upload e reprodução ainda precisam de validação em CMS local com arquivos reais.
