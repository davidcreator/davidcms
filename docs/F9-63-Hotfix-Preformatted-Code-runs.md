# F9-63 — Hotfix: conteúdo string de Preformatted Code

## Causa e correção

O traceback mostrou `buildInlineDom()` recebendo `runs.forEach`, mas o conteúdo de `core/code` é uma **string** literal, não uma lista de runs inline. Na F9-62, `core/code` foi incluído indevidamente na condição de renderização rich-text compartilhada, antes da área de texto pré-formatado. Ao selecionar/inserir o bloco, a exceção interrompia o inspetor e o commit.

Corrigi o roteamento: `core/code` foi removido da condição que chama `buildInlineDom()` e permanece na seção dedicada com `<textarea data-edit-code>`, sincronizada com o editor do canvas. Nenhuma string de código é convertida ou executada.

## Validação

- `node --check` passou.
- Bateria estática: **146 verificações aprovadas**, incluindo confirmação de que `core/code` segue pelo editor literal e não pelo rich-text.
- Os 9 testes de URL do hotfix F9-61 continuam aprovados; análise profunda do CSS sem erros; integridade do ZIP validada.
- Não há navegador/runtime PHP disponível aqui; o fluxo de clique/seleção deve ser confirmado no ambiente da aplicação.

## Arquivos completos no pacote

- `dev/admin/view/js/blog-block-editor.js`
- `dev/admin/view/css/blog-block-editor.css`
- `tools/f9-57-static-battery.py`
- `tools/f9-61-preview-url-smoke.js`
- Esta documentação e referências F9-52 a F9-62.
