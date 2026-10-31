# F9-64 — Bloco Table: inspetor e toolbar

## Alterações

- O inspetor de `core/table` apresenta uma grade editável, célula a célula, para todos os `core/table-row` e `core/table-cell`; a formatação inline é preservada.
- Os editores das células no canvas e no inspetor usam o mesmo identificador e fluxo de atualização, mantendo o conteúdo sincronizado sem reconstruir a grade durante a digitação.
- Mantidos os controles existentes para adicionar/remover linhas e colunas e alternar o cabeçalho; limites e validação estrutural da tabela permanecem inalterados.
- A aba **Personalizar** identifica os estilos da tabela; toolbar contextual e ações do inspetor recebem rótulos, com texto abaixo dos ícones e tooltips para ferramentas icon-only.
- Os novos títulos e instruções do inspetor estão localizados em português, inglês e espanhol.

## Validação

- `node --check` passou.
- Bateria estática completa: **150 verificações aprovadas**, incluindo render/validação/criação de todos os tipos do catálogo e testes específicos para a matriz de células, sincronização e controles da tabela.
- Os 9 testes URL do hotfix F9-61 passaram; análise profunda do CSS sem erros; ZIP validado com `unzip -t`.
- Sem navegador/runtime PHP disponível: edição interativa e responsividade visual devem ser confirmadas no ambiente da aplicação. Nenhum controller, banco, migração, rota, GitHub ou deploy foi alterado.

## Arquivos completos no pacote

- `dev/admin/view/js/blog-block-editor.js`
- `dev/admin/view/css/blog-block-editor.css`
- `tools/f9-57-static-battery.py`
- `tools/f9-61-preview-url-smoke.js`
- Esta documentação e referências F9-52 a F9-63.
