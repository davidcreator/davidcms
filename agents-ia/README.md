# Instruções para agentes — BDREditor

- Editor exclusivo ReamurCMS; Gutenberg é referência apenas, sem código/assets/dependência.
- `core/file` e `core/audio` permanecem com escopos locais e guardrails documentados.
- Clipboard de bloco é interno, volátil e limitado à instância atual; não usar navigator clipboard, HTML ou transferência entre documentos.
- Copiar/colar exige IDs novos e validação do documento completo antes de um único commit de histórico. Não zerar/substituir conteúdo quando falhar.
- Colagem de texto permanece `text/plain`; não usar `execCommand`.
- Sem schema, banco, migration, endpoint, autosave, draft, staging, deploy ou GitHub write.
- Não atribuir numeração oficial nem inventar A03. Não clonar/baixar o projeto inteiro; usar arquivos pontuais.
- Não executar PHP/SQL/browser/app/runtime nesta etapa; usar checkers e fixtures sintéticas.
- Não incluir dumps privados, credenciais ou logs runtime; não pedir credenciais por chat.
