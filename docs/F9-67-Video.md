# F9-67 — Interações do bloco Video com a biblioteca de mídia

## Ajuste de entendimento

O bloco da captura é `core/video_embed` (rótulo visível “Vídeo”), não `core/video_file` (“Vídeo local”). O hotfix anterior conectou o bloco local, mas não adicionou a interação que o usuário esperava no bloco Video da captura. Esta entrega corrige o tipo efetivamente usado no canvas.

## Comportamento implementado

- No inspetor e na barra contextual do bloco `core/video_embed`, o botão **Selecionar ou trocar vídeo** abre a biblioteca independente CMS Pages diretamente na aba Vídeo.
- Na biblioteca é possível enviar um arquivo MP4/WebM/OGV e selecionar qualquer vídeo existente. A seleção define o vídeo local no bloco; escolher outro substitui o arquivo associado.
- O campo YouTube/Vimeo permanece. Informar uma URL externa volta o bloco ao modo de incorporação; escolher mídia local troca para o vídeo local. A prévia administrativa mostra um player local com controles e resolve caminhos pelo `catalogBase`/`/catalog/`.
- A renderização pública suporta o player local validado ou a incorporação externa YouTube/Vimeo já existente. Nenhum HTML arbitrário ou `custom_js` é executado.
- Na aba Vídeo da biblioteca, cada vídeo tem **Excluir vídeo**. A ação confirma permanentemente a exclusão, alerta que blocos que referenciam o arquivo deixam de reproduzi-lo, e envia apenas o nome do arquivo com ACL `modify`, token CSRF e proteção contra traversal/symlink. Exclui apenas mídia CMS Pages; não altera arquivos do Blog.
- A seleção, upload, substituição e exclusão pertencem à biblioteca CMS Pages. O fluxo independente do Blog não é redirecionado para essa biblioteca.

## Arquivos completos incluídos

Controladores CMS Pages e Blog (invalidação de cache), model da biblioteca Pages, template CMS Pages, cliente da biblioteca, editor compartilhado, validador/renderer PHP, strings pt-BR/es-EU/en-US, bateria estática, smokes e documentação F9-52 a F9-67.

## Verificações

- `node --check` passou no editor compartilhado, inicializador Pages e cliente da biblioteca.
- Smoke da biblioteca confirmou a URL de mídia em `/catalog/`; o smoke do helper validou 9 casos.
- Bateria estática: **187 verificações aprovadas**.
- A integridade do ZIP foi verificada. PHP CLI e navegador real não estão disponíveis; portanto não alego lint/runtime PHP nem interação real de navegador/staging.
