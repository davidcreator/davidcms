# Instruções para agentes — BDREditor

- Produto-alvo: editor nativo exclusivo do ReamurCMS. Gutenberg é referência apenas; não importar pacote, código ou assets.
- Pacote cumulativo: manter escopos já definidos de `core/file` e `core/audio`; áudio somente local, sem upload/exclusão.
- Escopo novo: os atalhos de marcas/link só devem atuar no conteúdo editável; undo/redo nativo de inputs e navegação de selects devem ser preservados.
- Não usar `execCommand`, HTML arbitrário, scripts ou origem remota para atalhos/ajuda.
- Sem alteração de schema, banco, migrations, endpoints, autosave ou persistência compartilhada.
- Não atribuir numeração oficial nem abrir tarefa A03 sem aprovação.
- Não baixar/clonar o projeto inteiro; usar fontes pontuais. Não escrever no GitHub, não fazer deploy e não executar staging.
- Não executar PHP/SQL/browser/app/runtime nesta etapa; usar apenas checkers/fixtures sintéticos permitidos.
- Não incluir dumps privados, credenciais ou logs runtime; não pedir credenciais por chat.
