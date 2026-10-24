# Índice da entrega F9-06

- Plano/escopo: `F9-06-UI-Admin-Campos-Personalizados.md`
- Controller: `dev/admin/controller/custom_field/definition.php`
- Lista: `dev/admin/view/template/custom_field/definition_list.twig`
- Formulário: `dev/admin/view/template/custom_field/definition_form.twig`
- Idioma fonte: `dev/admin/language/en-us/custom_field/definition.php`
- ADR: `agents-ia/decisoes/0062-f9-06-ui-admin-campos-personalizados.md`
- Tool: `tools/e2e/e2e_f906.sh --check`

Dependências: Model/schema e ACL das F9-02/F9-05 precisam existir no ambiente de laboratório antes de testar a rota. Esta entrega não grava valores nem renderiza campos nas páginas públicas.
