# F9-61 — Hotfix de `safeActionUrl` no preview

## Causa e correção

O console apontou um `ReferenceError`: `renderPreviewBlock()` chamava `safeActionUrl()` para renderizar `core/button` (e as rotas de preview de links inline e `core/video_embed`), mas a função não existia no editor. Como essa exceção acontecia durante a montagem do documento de prévia, ela interrompia a renderização do preview.

Foi adicionada `safeActionUrl(value)`, que delega a `validActionUrl(value)` e devolve a URL/caminho validado ou `null`. Dessa forma, destinos inseguros continuam sem href; a renderização pode seguir sem interromper o documento.

## Validação

- `node --check dev/admin/view/js/blog-block-editor.js` passou.
- Bateria estática ampla: **141 verificações aprovadas**, cobrindo todos os blocos principais/aninhados e a presença do helper nos caminhos do preview.
- `tools/f9-61-preview-url-smoke.js` executou **9 casos** sobre os corpos reais das funções de validação extraídas do JS: HTTP/HTTPS e caminho local válidos; `javascript:`, URL protocol-relative, FTP, credenciais embutidas, vazio e espaços iniciais rejeitados.
- A bateria não é um teste de browser. A exceção específica fica corrigida no código-fonte, mas a confirmação do preview montado/clicado ainda depende de executar no navegador da aplicação.

## Arquivos completos no pacote

- `dev/admin/view/js/blog-block-editor.js`
- `dev/admin/view/css/blog-block-editor.css`
- `tools/f9-57-static-battery.py`
- `tools/f9-61-preview-url-smoke.js`
- Esta documentação e referências das entregas F9-52 a F9-60.
