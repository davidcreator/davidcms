# Índice da entrega F9-05

- Guia: `F9-05-Modelo-Definicoes-Campos.md`
- Contrato machine-readable: `F9-05-Contrato-Definicoes-Campos.json`
- Model backend: `dev/admin/model/custom_field/definition.php`
- ACL dormente: `dev/install/sql/upgrade_f9_05_custom_field_definition_grant.sql`
- Rollback da ACL: `dev/install/sql/rollback_f9_05_custom_field_definition_grant.sql`
- Tool E2E estático: `tools/e2e/e2e_f905.sh --check`

A rota `custom_field/definition` é preparada como permissão dormente; controller, formulário e UI entram na próxima subtarefa. Nenhum valor em `custom_field_value` é criado, alterado ou apagado.
