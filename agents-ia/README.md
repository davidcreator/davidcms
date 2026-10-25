# Instruções para agentes — BDREditor

- Produto exclusivo ReamurCMS; Gutenberg é apenas referência, sem código/assets/dependência.
- Preservar os escopos cumulativos `core/file`, `core/audio`, atalhos e clipboard local de blocos.
- Navegação hierárquica é somente leitura: não alterar o JSON, criar undo de conteúdo ou persistir mudanças. As setas da outline só atuam quando o foco está nela; não capturar setas do texto/campos.
- Rótulos de grupos/células entram por `textContent`, nunca HTML livre.
- Preservar foco acessível e mensagens live; não inventar atalhos que capturem navegação nativa.
- Sem schema, banco, migration, endpoint, autosave, preview draft, staging, deploy ou GitHub write.
- Não atribuir numeração oficial nem inventar A03. Não clonar/baixar o projeto inteiro; fontes pontuais apenas.
- Não executar PHP/SQL/browser/app/runtime nesta etapa; usar fixtures e checkers sintéticos.
- Não incluir dumps privados, credenciais ou logs runtime; não pedir credenciais por chat.
