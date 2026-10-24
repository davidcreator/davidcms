# ADR 0065 — F9-09: Entrada ACL-driven no menu para custom field definitions

**Data:** 2026-10-24 · **Agente:** admin / orquestrador.  
**Status:** alteração local para revisão; não publicada pelo agente.  
**Base verificada:** `davidcreator/davidcms` @ `fa40f72ac93f16840642b6dc263636c7e211bdd7`.

## Contexto

A F9-06 entregou controller e telas para `custom_field/definition`, mas deixou navegação global para subtarefa posterior. A F9-05 já prepara `access`/`modify` para Administrator; o header constrói a sidebar dinamicamente filtrando cada rota por `hasPermission('access', route)`.

## Decisão

1. Inserir a rota real `custom_field/definition` no grupo Content da sidebar, com ícone Bootstrap Icons e rótulo traduzido.
2. Reusar sem alterar a checagem ACL existente do `buildMenu`; não adicionar bypass nem conceder permissão.
3. Adicionar string de navegação em `en-us`, `pt-br` e `es-eu`.
4. Reusar `matchRoute` que já considera sub-ações por prefixo (`base.add`, `base.edit|N`); nenhuma regra de correspondência extra é necessária.

## Consequências

- Admins com `access` encontram as definições pela sidebar; sem esse grant, o link não aparece.
- A capability `modify` permanece independente, como antes: link é navegação, não autorização de escrita.
- O menu continua sem link em rotas que não tenham controller real; a rota já existe desde F9-06.
- Nenhuma alteração de dados, ACL persistida, layout HTML global ou frontend foi introduzida.

## Validação

`tools/e2e/e2e_f909.sh --check` valida o item e os idiomas estaticamente. Runtime/UI de navegação deve ser conferido em laboratório; PHP/browser não foram executados aqui.
