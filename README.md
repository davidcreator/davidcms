# BDREditor — pacote incremental Fase 3

Integração do editor de conteúdo de CMS > Páginas com áudios locais já cadastrados na biblioteca Blog. Esta entrega estende a Fase 2 sem alterações ao GitHub, banco, staging ou produção.

- Picker somente leitura da rota preexistente `blog/blog.audioList`.
- Seleção ou arrastar item de áudio para o canvas; referências locais limitadas a `view/audio/blog/...`.
- Visibilidade condicionada às permissões existentes `modify cms/page` e `modify blog/blog`; sem bypass ou mudança de ACL.
- Sem upload/exclusão, sem habilitar o seletor de arquivos. Vídeo segue URLs HTTPS permitidas; não existe picker local de vídeo confirmado.
- Envelope v2 e campo oculto do formulário preservados; gravação continua manual pelo botão Salvar.

Veja `docs/MEDIA_LIBRARY_PHASE_3.md` para escopo/instalação/validação. Base de referência consultada somente leitura: `main` em `fe19221806a19ba249e4eaa5725c93971bfbcf96`.
