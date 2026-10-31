# F9-68 — Interações do bloco Cover com a biblioteca CMS Pages

## Implementação

- O bloco `core/cover` recebe ação rotulada **Selecionar ou trocar imagem** na barra contextual e no inspetor. A ação abre a biblioteca CMS Pages na aba Imagens e passa o ID do Cover alvo.
- Na biblioteca, é possível enviar uma nova imagem e aplicá-la imediatamente à capa, ou escolher uma imagem existente para substituir a atual. O cover mantém os campos de caminho, texto alternativo e alinhamento; seu conteúdo interno continua editável pelos blocos aninhados.
- As linhas de imagem e vídeo da biblioteca CMS Pages oferecem exclusão por arquivo. A exclusão de imagem pede confirmação, avisa que capas/blocos que referenciem o arquivo deixarão de exibi-lo, e usa a rota `cms/page.mediaDelete` com ACL `modify`, CSRF, tipo permitido, nome de arquivo limitado ao diretório e recusa de symlinks.
- A biblioteca CMS Pages continua independente da biblioteca e do armazenamento do Blog. Uploads/exclusões de imagens nessa implementação são limitados ao conteúdo CMS Pages.
- A versão dos assets foi elevada para `threejs-15-cover-media-actions`.

## Verificações

- `node --check` nos arquivos JavaScript alterados e smokes de URL da biblioteca/helper passaram.
- Bateria estática: **190 verificações aprovadas**.
- O ZIP contém arquivos completos e passou na checagem de integridade. Navegador real, PHP CLI e staging não estavam disponíveis; não alego validação runtime.
