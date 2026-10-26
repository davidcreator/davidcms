# BDREditor — mídia Blog e thumbs Three.js

Pacote incremental verificado contra `davidcreator/davidcms`, branch `main`, HEAD `d80b07cdbe704c633d1b286101ec695314355b9b` (consulta somente leitura; sem clone completo ou escrita GitHub).

Inclui upload seguro de MP3/OGG/WAV (20 MiB) e MP4/WebM/OGV (100 MiB), seleção e drag/drop na biblioteca Blog, blocos locais de áudio/vídeo e integração no BDREditor de Blog e CMS > Páginas. O bloco `core/threejs_thumb` oferece edição inline no cartão e no Inspetor para formas/luzes, aparece renderizado no cartão do canvas e no modo Prévia de Blog/CMS e aceita script JavaScript opcional por thumb.

Three.js 0.186.1 e seu módulo `three.core.js` são self-hosted. O JavaScript customizado roda em iframe sandbox de origem opaca, sem acesso ao DOM/cookies do CMS e com CSP bloqueando rede/recursos externos. A thumb pública informa falhas de carregamento/execução; insira JavaScript puro, sem tags `<script>`. Use apenas scripts confiáveis: um loop infinito ainda pode travar a thumb/aba. ACLs existentes `modify blog/blog` e `modify cms/page`; o conteúdo de página usa envelope v2 no campo existente e salvamento manual, sem migration.

Sem exclusão de arquivos, migrations, escrita GitHub, staging ou deploy. Documentação: `docs/BLOG_MEDIA_UPLOAD.md`, `docs/THREEJS_THUMB.md` e `docs/IMPORT_FORMATS.md`; checkers estáticos e runner Playwright opcional em `tools/e2e/`; ver `docs/THREEJS_CMS_E2E.md`.

O runner Playwright exige uma página CMS descartável, sessão local privada e Chromium; não foi executado sem runtime autenticado. PHP CLI não estava instalado, portanto o lint PHP não foi executado.
