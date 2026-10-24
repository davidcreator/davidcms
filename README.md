# F9-12 — Persistência de blocos do Blog

Pacote de migrations preparadas, documentação, ADR e verificador estático.

- **Base consultada:** GitHub `main` em `d0f8706402a722d24ab76077974d765542999673`.
- **Não executar SQL neste pacote.** Upgrade/rollback foram somente revisados estaticamente.
- **Upgrade:** `dev/install/sql/upgrade_f9_12_blog_block_content.sql`.
- **Rollback seguro:** `dev/install/sql/rollback_f9_12_blog_block_content.sql` (mantém sidecar/dados; downgrade de código para HTML reader).
- **Validação sem banco:** `bash tools/e2e/e2e_f912.sh --check` (Node.js necessário).

Estrutura espelha o projeto: `dev/install/sql`, `docs`, `agents-ia/decisoes` e `tools/e2e`.
