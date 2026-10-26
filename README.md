# BDREditor — mídia Blog e thumbs Three.js

Pacote incremental verificado contra `davidcreator/davidcms`, branch `main`, HEAD `875a08ce5e0484537a517c7435b5b610987450dd` (consulta somente leitura; sem clone completo ou escrita GitHub).

Inclui upload seguro de MP3/OGG/WAV (20 MiB) e MP4/WebM/OGV (100 MiB), seleção e drag/drop na biblioteca Blog, blocos locais de áudio/vídeo e integração no BDREditor de Blog e CMS > Páginas. O bloco `core/threejs_thumb` oferece controles no Inspetor para formas, luzes e atributos de cena, além de campo JavaScript opcional por thumb.

Three.js 0.186.1 e seu módulo `three.core.js` são self-hosted. O JavaScript customizado roda em iframe sandbox de origem opaca, sem acesso ao DOM/cookies do CMS e com CSP bloqueando rede/recursos externos. Use apenas scripts confiáveis: um loop infinito ainda pode travar a thumb/aba. ACLs existentes `modify blog/blog` e `modify cms/page`; o conteúdo de página usa envelope v2 no campo existente e salvamento manual, sem migration.

Sem exclusão de arquivos, migrations, escrita GitHub, staging ou deploy. Documentação: `docs/BLOG_MEDIA_UPLOAD.md`, `docs/THREEJS_THUMB.md` e `docs/IMPORT_FORMATS.md`; checkers estáticos em `tools/e2e/`.

A verificação funcional no CMS local continua pendente. PHP CLI não estava instalado nesta sessão, portanto o lint PHP não foi executado.
