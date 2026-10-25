# BDREditor — Fase 3: mídias locais já cadastradas

**Base de referência:** `main` em `fe19221806a19ba249e4eaa5725c93971bfbcf96` (somente leitura). Esta entrega é incremental sobre Fase 2 e não altera GitHub, banco, staging ou produção.

## Escopo implementado

- CMS > Páginas integra o picker existente da biblioteca de áudio do Blog (`blog/blog.audioList`), com busca, paginação e seleção de MP3/OGG/WAV locais já provisionados.
- A seleção preenche `core/audio` no documento v2 existente. Também é possível arrastar um item da biblioteca para o canvas para inserir o bloco de áudio.
- Referências aceitas permanecem restritas a `view/audio/blog/...` e são validadas no picker e no editor.
- O modal e o botão só aparecem quando a pessoa tem simultaneamente `modify` em `cms/page` e `modify` em `blog/blog`. A rota existente exige a permissão de Blog; nenhuma ACL foi alterada nem contornada.
- O seletor de documentos/arquivos e upload permanecem desabilitados na página. Não há upload nem exclusão de áudio.
- Vídeo continua por URL HTTPS dos hosts já permitidos pelo editor; não foi encontrada biblioteca de vídeo local confirmada, portanto não foi criado picker de vídeo.
- Se MP3/OGG/WAV forem escolhidos no comando genérico **Importar arquivo**, a mensagem agora explica que áudio não é formato de importação documental e encaminha ao bloco Áudio e à biblioteca existente. Isso não envia nem cadastra o arquivo local.
- Mantidos o formulário CMS, o campo oculto/envelope v2, a importação local, o salvamento manual e os limites da Fase 2.

## Instalação

Aplicar/substituir os arquivos deste pacote preservando os caminhos relativos a partir da raiz do ReamurCMS. Não há migration nem dependência nova. `blog-audio-media.js` é uma cópia do cliente preexistente do HEAD de referência, acrescida do suporte para arrastar referências de áudio até o editor.

## Validação

Executar localmente:

```sh
node tools/e2e/check_single_page_ribbon.cjs
node tools/e2e/check_preview_import.cjs
node tools/e2e/check_audio_catalog.cjs
node --check dev/admin/view/js/blog-block-editor.js
node --check dev/admin/view/js/blog-block-editor-init.js
node --check dev/admin/view/js/blog-audio-media.js
```

Os checks são estáticos/contratuais. Ainda é necessário validar em runtime que o picker aparece para uma conta com as duas permissões, não aparece sem `modify blog/blog`, e selecionar/arrastar áudio salva ao enviar o formulário e reaparece ao reabrir. Não foi executado teste browser/CMS real nem PHP lint neste ambiente.
