# KZ-62 — D08=A / F918-07

**Base esperada:** `52e1dade`  
**Escopo:** alinhamento do contrato F9-25, checker estático e registros de governança.

## Decisão e limites

O responsável autorizou **D08=A**, substituindo a decisão anteriormente registrada como B: documentar no F9-25 e verificar no checker os comportamentos que já existem. Este pacote não acrescenta funcionalidade, endpoint, armazenamento ou alteração de schema.

- Padrões permanecem privados por administrador; a implementação existente exige ACL `cms/block_pattern` e permissão de modificação no contexto Blog/Página. Escritas também exigem a ACL de modificação dedicada e CSRF; o proprietário vem da sessão autenticada.
- Notas e presets usam `localStorage` no navegador. A recuperação temporária de rascunhos usa `sessionStorage`, é por usuário/contexto/registro, expira em 24 horas e só restaura após confirmação. Isso **não** é autosave no servidor.
- O envio explícito do formulário Blog/Página continua sendo a gravação autoritativa. O histórico D05=A é separado, iniciado por ação explícita; restaurar uma versão altera o editor em memória e ainda requer Save.
- O texto `XMLHttpRequest` no header `X-Requested-With` não é uso da API XMLHttpRequest.
- `catalog_context_preview` continua `DESIGN_GATE_PENDING`; F9-26 não foi alterado. D09/D10 não foram redecididos e mantêm as decisões B já registradas; F918-08/F918-09 continuam abertos. `release_ready` continua `false`.

## Verificações executadas

- `node --check tools/e2e/e2e_f925_static.cjs` — PASS.
- `node tools/e2e/e2e_f925_static.cjs .` — PASS (idiomas, ACL/CSRF/propriedade, armazenamento local, recuperação e histórico; 4 fixtures sintéticas).
- `bash tools/verify-checksums.sh` — PASS, 33 arquivos verificados, 0 divergências.
- `git diff --check` e invariantes de governança — PASS.
- Não foram executados PHP, SQL, navegador, aplicação ou acesso a banco.

## Aplicação

Copie os caminhos do pacote sobre a raiz do repositório em `52e1dade` e revise o diff antes de commit. O pacote não foi enviado ao GitHub nem aplicado a uma instalação.
