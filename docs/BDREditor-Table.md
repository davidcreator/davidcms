# BDREditor — bloco estruturado de tabela

## Escopo

Primeiro incremento de código após a publicação do backlog BDREditor. O editor continua sendo propriedade do ReamurCMS. Nenhuma integração com Gutenberg/WordPress foi adicionada.

## Contrato implementado

- `format_version: 2` obrigatório.
- `core/table` exige somente `attributes.header: boolean`, conteúdo vazio e 1–20 nós `core/table-row`.
- Cada row não tem atributos nem conteúdo; contém 1–12 nós `core/table-cell`.
- Todas as rows precisam ter exatamente o mesmo número de células.
- Células sem atributos/children contêm apenas runs inline v2. Marcas/links permanecem submetidos à allowlist existente.
- Tamanho de texto agregado das células limitado a 262144 bytes por tabela; limite global de payload existente também se aplica.
- `header=true` marca a primeira row como cabeçalho; `header=false` renderiza todas em `tbody`.
- Não aceita rowspan/colspan, fórmulas, HTML de célula, estilos do documento, scripts nem classes arbitrárias.

## UX incorporada

- O inserter ganha Tabela; padrão inicial com 3 linhas e 3 colunas, incluindo cabeçalho.
- Conteúdo de célula editado inline pelo modelo de runs já existente. A toolbar de formatação/link existente se aplica à célula focada.
- O inspetor oferece toggle do cabeçalho, adicionar/remover última linha e adicionar/remover última coluna. Botões respeitam limites 1×1 a 20×12.
- Ações estruturais passam pelo commit local do editor, portanto participam do undo/redo já existente.
- Outline mostra a tabela como unidade, não rows/cells internas.
- Preview admin usa DOM seguro e renderer público constrói somente tags fixas, reusando escaping de runs.

## Arquivos fonte pontuais

- `dev/system/library/blog/block_document.php` — versão, allowlist, shape, parent/child, dimensões e bytes agregados.
- `dev/system/library/blog/block_renderer.php` — `table/thead/tbody/tr/th/td` fixos; inline output pela função allowlisted.
- `dev/admin/view/js/blog-block-editor.js` — tipo, validação cliente, criação, edição, controles, inspeção e preview.
- `dev/admin/controller/blog/blog.php` e `dev/admin/language/{pt-br,en-us,es-eu}/blog/blog.php` — labels, instruções, botões e aria labels localizados.
- `dev/admin/view/css/blog-block-editor.css` e `dev/catalog/view/css/blog-blocks.css` — classes fixas e comportamento responsivo.
- Outros fontes copiados pontualmente do snapshot base ficam inalterados apenas para oferecer uma unidade de revisão; ver manifest de hashes.

## Verificação estática

O checker verifica fixtures válidas/inválidas, tabela máxima, 21ª linha, 13ª coluna, texto agregado excessivo, presença de regras server-side/renderer, traduções dos três idiomas e CSS de classe fixa. O checker usa apenas o validador cliente em VM sem browser; não chama PHP nem aplicação.

## Limites e próxima validação

- Não validar PHP, browser, aplicação, catálogo, screen reader ou performance agora; staging continua diferido.
- O checker não demonstra equivalência entre PHP e JS em runtime.
- Antes de release, validar a integração PHP/template/catalog em staging descartável, após autorizações/precondições próprias; não é parte desta entrega.
- Incremento seguinte proposto no backlog: bloco arquivo/download, mediante auditoria de caminho local e sem introduzir upload/exclusão de mídia.
