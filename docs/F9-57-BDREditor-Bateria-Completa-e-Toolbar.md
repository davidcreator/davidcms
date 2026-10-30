# F9-57 — Bateria completa de blocos e apresentação da toolbar

## Diretriz visual aplicada

- Sempre que a toolbar contextual exibe um rótulo junto do ícone, o rótulo fica **abaixo** dele, centralizado; não ao lado direito.
- Ferramentas com ícones convencionais continuam podendo ficar sem texto visível. Elas mantêm nome acessível e `title` nativo com nome e descrição, exibido ao passar o mouse.
- A regra vale para toolbars contextuais sem alterar os controles/ações das abas do inspetor ou demais botões do editor.

## Bateria de cobertura estática

Executado `tools/f9-57-static-battery.py`: a bateria original passou 124 verificações, cobrindo os 21 tipos do catálogo principal e os quatro tipos estruturais aninhados. Na F9-58, o mesmo script foi ampliado com verificações específicas para Button (128 verificações). Na F9-59, foram adicionadas verificações do Callout (131 verificações). Na F9-60, foram adicionados testes de More Informations e dos tamanhos Desktop/Tablet/Mobile; a execução atual passou **139 verificações**. Cada tipo foi conferido quanto à validação do schema, renderização de preview e ícone; os blocos principais também foram conferidos quanto à categoria e caminho de criação. A bateria ainda verifica modos de edição, ações de tabela/lista e apresentação acessível de rótulos/tooltips.

**Catálogo principal (21):** Parágrafo, Heading/Título, List, Image, Quote, Video Embed, Video File, Thumb 3D, Button, Callout, Details, Spacer, Code, Separator, Group, Columns, Gallery, Cover, Table, File e Audio.

**Tipos estruturais aninhados (4):** List Item, Column, Table Row e Table Cell.

Validações complementares: `node --check` passou; o CSS foi analisado pelo parser `tinycss2` sem erros (555 regras no nível superior). O arquivo do teste está incluído no pacote para reexecução.

## Limites da bateria

É uma bateria completa de **cobertura estática do código**, não uma sessão real de interação. Navegador e runtime PHP não estão disponíveis neste ambiente; portanto, não afirmo testes reais de inserção, edição, hover, preview responsivo ou submissão nos fluxos Pages/Blog. Esses pontos devem ser exercitados no ambiente de execução da aplicação antes de deploy. Nenhum controller, banco, migração, rota, GitHub ou deploy foi alterado.

## Arquivos completos no pacote

- `dev/admin/view/js/blog-block-editor.js`
- `dev/admin/view/css/blog-block-editor.css`
- `tools/f9-57-static-battery.py`
- `docs/F9-57-BDREditor-Bateria-Completa-e-Toolbar.md`
- Documentação F9-52 a F9-56 referenciada pelas entregas anteriores.
