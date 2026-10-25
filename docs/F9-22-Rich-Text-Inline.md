# Fase 9 · Tarefa 22 — Rich text inline versionado

**HEAD-base de `main`:** `54748166dc5b474edac1a8e28e436bd333a926c2` (`docs: add f9-21 editor roadmap and contract docs`).  
**Escopo:** primeira implementação de alto impacto do roadmap: edição inline e rich-text estruturado para conteúdo textual do Blog, com compatibilidade v1 e renderer server-side allowlisted.

## Funcionalidade incluída

- Substitui textareas dentro do canvas de blocos por `contenteditable` controlado pelo documento JSON.
- Toolbar contextual com negrito, itálico, sublinhado, tachado, código e criação/remoção de links; atalhos Ctrl/Meta+B/I/U/K.
- Seleção persistida por offsets ao aplicar marks/links; undo/redo existente inclui mudanças inline.
- Enter cria quebra de linha dentro do bloco atual. Divisão/mesclagem inteligente em blocos é parte de F9-23, não deste escopo.
- Paste/drop de conteúdo textual aceita `text/plain` somente. DOM permitido é convertido novamente em runs; tags/atributos desconhecidos não são serializados.
- Barra e textos principais de formatação são localizados em PT-BR, EN-US e ES-EU.

## Modelo e compatibilidade

- `format_version: 1`: documentos antigos continuam com `content: string` e permanecem válidos.
- `format_version: 2`: blocos de texto usam `content: [{text, marks, href}]`; blocos estruturais mantêm `content: ""`.
- Abrir um v1 faz upgrade para v2 em memória no editor. A persistência ocorre só no POST normal existente, não ao abrir; não há autosave.
- `convertLegacy()` segue produzindo v1; a UI converte texto puro em run sem marks.
- Não há alteração da migration F9-12 nem do esquema SQL.

## Validação e segurança

- PHP valida versões 1/2, forma exata dos runs, allowlist de marks, URLs, limites por bloco/documento e tipos que podem conter link.
- Renderer emite elementos fixos (`strong`, `em`, `u`, `s`, `code`, `a`) e escapa texto/URL; não aceita HTML arbitrário. Links recebem `rel` fixo, sem `target` vindo do documento.
- `contenteditable` é reconstruído com DOM APIs; não usa `innerHTML` nem `execCommand`. Paste/drop não traz HTML do clipboard.
- O validador do servidor é autoridade; validação client-side não substitui POST/renderer.

## Arquivos principais

`dev/system/library/blog/block_document.php` e `block_renderer.php`; `dev/admin/view/js/blog-block-editor.js`, `blog-block-editor-init.js`, CSS, Twig, controller e as três traduções. `tests/fixtures/rich_text_v2.json` contém 11 cenários sintéticos (compatibilidade, marks, links seguros/inseguros, escape, shape, botão, run vazio e versão futura).

## Verificação realizada

- `node --check` aplicado apenas aos arquivos JS do editor, inicializador e checker estático.
- Checker de fonte F9-22: PASS; 3 idiomas e 11 fixtures inspecionados. JSON de fixtures parseado.
- Não executamos PHP, SQL, migration, browser, app, renderer funcional ou E2E. Portanto sintaxe/runtime PHP e comportamento da seleção/clipboard precisam validação futura autorizada em ambiente isolado.
- Nenhuma escrita no GitHub; pacote local baseado no HEAD acima. F9-12 não é presumida aplicada.

## Próxima evolução

F9-23: inserter pesquisável/slash, toolbar estrutural, duplicar/transformar bloco, outline do documento e divisão/mesclagem de blocos por teclado; manter a arquitetura inline v2 e os mesmos guardrails.
