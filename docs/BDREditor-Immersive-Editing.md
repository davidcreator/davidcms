# BDREditor: diagnóstico e edição imersiva

## Erro ao reabrir conteúdo

`json_decode($json, true)` faz com que tanto `{}` como `[]` virem array vazio PHP. Antes do ajuste, `canonicalNode()` devolvia esse array diretamente em `attributes`; `json_encode()` então produzia `"attributes":[]`. O validador JS rejeita arrays nesse campo e apontava `node_fields_invalid`.

A canonicalização agora emite `"attributes":{}` usando `(object)$attributes`. Ao abrir um envelope v2 existente em CMS > Páginas, o controller primeiro valida e então chama `serializeDocument()` para gerar a representação JSON canônica enviada ao editor. Isso corrige em memória os documentos antigos ainda sem mutar o banco. O save explícito já existente persiste a forma corrigida.

## Modo imersivo

O modo é uma camada modal acessível no próprio DOM do editor, sem popup externo e sem tirar o campo hidden do formulário. `role=dialog`, `aria-modal`, foco preso, restauração de foco e Escape tornam a transição reversível. Em viewport estreita, navegação e canvas passam a uma coluna rolável. O editor não dispara autosave e o botão de saída volta à tela CMS para salvar.
