# F9-56 — Bloco Quote: inspetor e toolbar

## Alterações

- O inspetor do bloco `core/quote` agora oferece edição rich-text do conteúdo, sincronizada com a citação no canvas e preservando formatação inline.
- A aba **Personalizar** identifica os controles como estilo da citação, mantendo os controles seguros existentes de cor, borda, espaçamento, tipografia e alinhamento.
- A toolbar contextual e as ações do inspetor exibem rótulos claros, incluindo mover, duplicar, copiar, personalizar, transformar, remover e inserir.
- Os novos textos são localizados em português, inglês e espanhol. O modelo do Quote permanece sem filhos, conforme validação existente; não foram adicionados campos estruturais nem alterados fluxos de Pages/Blog.
- A implementação mantém os aprimoramentos anteriores de Parágrafo, Heading e List no editor compartilhado.

## Arquivos completos no pacote

- `dev/admin/view/js/blog-block-editor.js`
- `dev/admin/view/css/blog-block-editor.css`
- `docs/F9-56-Bloco-Quote-Inspetor-e-Toolbar.md`
- `docs/F9-55-Bloco-List-Inspetor-e-Toolbar.md`
- `docs/F9-54-Bloco-Heading-Inspetor-e-Acoes.md`
- `docs/F9-53-Bloco-Paragrafo-Inspector-e-Acoes.md`
- `docs/F9-52-BDREditor-Workspace-Fullscreen.md`

## Validação e limites

- Sintaxe JavaScript via `node --check`; verificações estáticas para editor do Quote, sincronização, rótulos e estilos; balanceamento de chaves CSS e integridade CRC do ZIP.
- Não foi realizado teste de navegador ou runtime PHP/Twig. Nenhum controller, banco, migração, rota, GitHub ou deploy foi alterado.
