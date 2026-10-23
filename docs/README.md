# Índice da entrega F9-03

- Auditoria narrativa e limites: `F9-03-Auditoria-Banco-DavidAlmeida.md`
- Inventário sanitizado do dump: `F9-03-Inventario-Banco-DavidAlmeida.json`
- Tool E2E estático local: `tools/e2e/e2e_f903.sh --check`
- Modo real, após aplicar no repositório: `tools/e2e/e2e_f903.sh --check --sql dev/install/sql/bd-davidalmeida.sql`

O pacote **não inclui o dump real** e não executa SQL. O inventário contém somente metadados, colunas e contagens; nenhum valor/credencial foi copiado.
