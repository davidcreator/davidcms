# BDREditor — prévia, importação local e organização drag-and-drop

Pacote incremental preparado sobre `main` do ReamurCMS, HEAD verificado em modo somente leitura em 25/10/2026: `1b725e95ce204107306b1fad0d6f5acefe8925cb`.

## O que esta etapa acrescenta

- Prévia renderizada do rascunho atual, no próprio editor, alternável em **Exibir**. A prévia lê o estado em memória e não salva nem publica.
- Importação no navegador por seletor de arquivo ou arrastar/soltar: `.docx`, `.xlsx`, `.pptx`, `.html`, `.htm`, `.xhtml` e `.imscc` (Canvas LMS).
- Conversão para os blocos BDREditor v2 suportados: texto, títulos, listas, citações, separadores e tabelas dentro dos limites do editor.
- Excel: primeira aba; até 20 linhas e 12 colunas; fórmulas são omitidas. Word: texto, formatação inline básica e tabelas. PowerPoint: texto dos slides. Canvas IMSCC: páginas HTML de `wiki_content/pages`.
- O HTML é convertido por allowlist; scripts, estilos, formulários, iframes arbitrários, SVG/Canvas e outros elementos ativos não são executados nem importados.
- Arrastar blocos para reordenar continua habilitado; arrastar arquivo local sobre o editor chama o mesmo importador.

## Limites de mídia e privacidade

Importação é **local ao navegador**: não há upload, requisição de rede, salvamento automático ou gravação de arquivos no servidor. Imagens e áudio só viram blocos quando a referência já aponta para a biblioteca local permitida (`image/`, `view/image/blog/` e `view/audio/blog/`; áudio MP3/OGG/WAV). Links de vídeo são aceitos apenas quando a fonte já é uma incorporação HTTPS permitida pelo editor (YouTube/Vimeo); arquivos de vídeo locais e mídias embutidas em DOCX/PPTX não são extraídos nem enviados. O importador mostra avisos quando omite conteúdo ou mídia.

Essa limitação respeita a seleção de conversão local sem upload. Para gravar arquivos de mídia enviados do computador seria preciso aprovar separadamente biblioteca/raiz de armazenamento, endpoint, permissões, limites e política de segurança; esta entrega não faz isso.

Limites do pacote ZIP lido localmente: até 25 MiB de arquivo, 8 MiB por entrada, 30 MiB descompactados, até 10.000 entradas e limite de 4 MiB no JSON convertido. Não inclui ZIP genérico na biblioteca de downloads; `.imscc`/OOXML são lidos apenas em memória pelo importador.

## Arquivos e integração

Copie os arquivos sob `dev/` para os mesmos caminhos no projeto. O controller inclui `blog-block-import.js` antes do editor. A tela CMS mantém envio manual pelo formulário existente, campo `description` e envelope `REAMURCMS_BDRE_JSON_V2:`.

## Testes disponíveis

```sh
node tools/e2e/check_single_page_ribbon.cjs
node tools/e2e/check_preview_import.cjs
node --check dev/admin/view/js/blog-block-import.js
node --check dev/admin/view/js/blog-block-editor.js
node --check dev/admin/view/js/blog-block-editor-init.js
```

Os checkers são estáticos/contratuais, não substituem teste de navegador. A prévia em `preview/index.html` é um mock funcional local e não se conecta ao CMS. PHP não está instalado neste ambiente; o lint PHP, renderização Twig e round-trip real ainda precisam ocorrer em desenvolvimento autorizado.

Nenhuma escrita GitHub, banco, staging, produção, migration ou deploy foi feita.
