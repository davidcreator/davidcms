# KZ-63 — D09=A / F918-08

**Base esperada:** `1f864b80`  
**Escopo:** contrato F9-05, checker estático e registros de governança.

## Decisão e evidência

D09=A, autorizado pelo responsável, substitui a decisão B anterior. A auditoria F11-16 concluiu que `dev/install/sql/schema.sql` é um artefato compartilhado do repositório com conteúdo de fases posteriores — incluindo a seção F9-25 `rms_cms_block_pattern` — e não uma entrega da fase F9-05. O instalador atual importa `dev/install/sql/schema-complete.sql`.

O contrato F9-05 agora explicita a fronteira entre a entrega histórica (Model de definições + ACL dormente) e o artefato compartilhado. O checker deixou de exigir que `schema.sql` esteja ausente e valida o escopo documentado. Nenhum arquivo SQL/schema foi alterado. F918-08 foi marcado como resolvido.

## Verificações

- `node --check tools/e2e/e2e_f905.cjs` — PASS.
- Verificação direcionada do escopo D09 (contrato, checker, adição F9-25 e entrada atual do instalador) — PASS.
- `node tools/e2e/e2e_f905.cjs` — **continua retornando 1** na asserção independente que proíbe `declare(strict_types=1)` no Model. A mesma falha foi reproduzida com o checker original em `1f864b80`; ela não é parte de F918-08 e não foi alterada neste pacote. Portanto, este relatório **não** declara o checker integral como PASS.
- `bash tools/verify-checksums.sh` — PASS, 33 arquivos, 0 divergências. Os manifestos raiz e `tools/CHECKSUMS.txt` também foram conferidos: 33/33 cada.
- `git diff --check` e invariantes D09 — PASS.
- Nenhum PHP, SQL, browser, aplicação ou banco foi executado.

## Limites preservados

F9-26 e D10/F918-09 permanecem inalterados; `release_ready` continua `false`; nenhum hash/base do gate F9-27 foi atualizado. O pacote não foi enviado ao GitHub nem aplicado a uma instalação.

## Aplicação

Copie os caminhos do pacote sobre a raiz do repositório em `1f864b80` e revise o diff. A divergência independente `strict_types` deve ser decidida separadamente; não foi incluída como mudança automática de D09.
