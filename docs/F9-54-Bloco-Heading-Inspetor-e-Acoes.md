# F9-54 — Bloco Heading: conteúdo, nível e ações claras

## Alterações

- A aba **Conteúdo** do inspetor agora inclui um editor rich-text para o título, sincronizado com o canvas e preservando as marcas inline existentes.
- O nível do título usa uma lista explícita `H1` a `H6`, com rótulos localizados, em vez de um campo numérico sem contexto.
- A aba **Personalizar** identifica os controles de estilo do Heading; alinhamento permanece disponível como propriedade visual do bloco.
- A toolbar contextual do Heading agora apresenta rótulos visíveis para mover, duplicar, copiar, personalizar, transformar, remover e inserir. O inspetor também nomeia as ações estruturais.
- O bloco Parágrafo mantém os recursos da F9-53. O editor compartilhado continua usando os próprios documentos/configurações de Pages e Blog.
- Complemento posterior: F9-55 aplica o mesmo padrão ao contêiner `core/list` e aos itens `core/list-item`.

## Arquivos completos no pacote

- `dev/admin/view/js/blog-block-editor.js`
- `dev/admin/view/css/blog-block-editor.css`
- `docs/F9-54-Bloco-Heading-Inspetor-e-Acoes.md`
- `docs/F9-53-Bloco-Paragrafo-Inspector-e-Acoes.md` (com referência de complemento)

## Validação e limites

- `node --check` do JavaScript; revisão estática da edição rich-text, sincronização canvas/inspetor, níveis H1–H6, toolbar nomeada e alinhamento de Heading.
- Balanceamento de chaves CSS e integridade CRC do ZIP.
- Sem navegador/PHP/Twig/runtime nesta validação; não foram alterados controllers, banco, migrações, rotas, GitHub ou deploy.
