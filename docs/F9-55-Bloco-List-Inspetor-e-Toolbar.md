# F9-55 — Bloco List: inspetor e ações claras

## Escopo e alterações

- O inspetor do contêiner `core/list` expõe o tipo da lista com rótulos localizados (**Marcadores** ou **Numerada**) e uma área de gestão dos itens.
- Cada `core/list-item` aparece como editor rich-text no inspetor da lista, sincronizado com o canvas e preservando a formatação inline. Ao selecionar o item individualmente, o inspetor também permite editar seu texto e removê-lo.
- A ação **Adicionar item** insere um `core/list-item` pelo fluxo de inserção já existente.
- A remoção de itens pelo inspetor da lista fica desabilitada quando resta apenas um. O comportamento estrutural existente de `deleteNode()` — criar item fallback ao remover o último item por outras vias — permanece intacto.
- A toolbar contextual e as ações do inspetor de `core/list` e `core/list-item` recebem rótulos visíveis, no mesmo padrão aplicado a Parágrafo e Heading. O painel de aparência identifica separadamente a lista e o item.
- Os textos novos estão localizados em português, inglês e espanhol. A implementação permanece no editor compartilhado, sem alterar os fluxos independentes de Pages e Blog.

## Arquivos completos no pacote

- `dev/admin/view/js/blog-block-editor.js`
- `dev/admin/view/css/blog-block-editor.css`
- `docs/F9-55-Bloco-List-Inspetor-e-Toolbar.md`
- `docs/F9-54-Bloco-Heading-Inspetor-e-Acoes.md`
- `docs/F9-53-Bloco-Paragrafo-Inspector-e-Acoes.md`
- `docs/F9-52-BDREditor-Workspace-Fullscreen.md`

## Validação e limites

- `node --check` e verificações estáticas da edição dos itens, sincronização dos editores duplicados, seletor de tipo, ação de inserção, remoção protegida no inspetor e rótulos contextuais.
- Verificação de balanceamento de chaves CSS e integridade do ZIP.
- Não foi feito teste em navegador/PHP/Twig/runtime. Nenhum controller, banco, migração, rota, GitHub ou deploy foi alterado.
