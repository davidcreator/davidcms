# CMS > Páginas — biblioteca de áudio, vídeo e downloads

**Status:** patch local, sem aplicação ao repositório, sem GitHub write, deploy, banco de dados ou migration executada.

## Base

Preparado contra o `main` em `a1e82d4e0ed8621b096e59387db2bdae85371de8` (`chore: clean up obsolete project and temporary files`). Confirme o HEAD novamente antes de aplicar; não aplique sobre uma base diferente sem revisar o diff.

## Arquivos

- `CMS-Pages-Media-Separation.patch` — patch unificado pronto para revisão/aplicação local.
- `tree/dev/...` — cópias completas dos arquivos finais alterados.

## Incluído

- Roots dedicados: `catalog/view/audio/cms/page/`, `catalog/view/video/cms/page/`, `catalog/view/download/cms/page/`. A biblioteca de imagens existente em `catalog/view/image/cms/page/` passa a usar o fluxo genérico.
- Áudio: MP3, OGG, WAV, até 20 MiB.
- Vídeo: MP4, WebM, OGV, até 100 MiB.
- Downloads: PDF, DOCX, XLSX, PPTX, ODT, ODS, CSV e TXT, até 2 MiB.
- ACL `modify cms/page`, GET para listagem, POST com CSRF para upload, validação por extensão + MIME detectado, nome aleatório, guardas de symlink/raiz. Sem pastas ou exclusão.
- Compatibilidade de leitura/renderização para paths legados `view/audio/blog/`, `view/video/blog/` e `view/download/blog/` já presentes em documentos de Pages. O validador é contextual: o modo Blog continua aceitando somente roots Blog; Pages aceita roots próprios e legado.
- Seletor e uploads Pages usam somente rotas/modelo/roots próprios; o controller não chama os endpoints de mídia do Blog.
- Nenhuma mudança de schema, migration ou conteúdo salvo.

## Verificação realizada

- `node --check` passou para `cms-page-media.js` e `blog-block-editor.js`.
- PHP CLI não está disponível nesta sessão; portanto, não foi possível executar lint/runtime PHP nem testes de integração. Não foram acessados dados de produção ou staging.
