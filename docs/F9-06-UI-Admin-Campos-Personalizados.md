# Fase 9 · Tarefa 06 — Interface administrativa das definições de campos

**Data:** 2026-10-24 · **Base verificada:** `davidcreator/davidcms`, branch `main`  
**HEAD inicial:** `5fbf66dc64e8001ae7bea9b95aa3455221372cbe`  
**Estado:** controller e formulários administrativos locais; sem execução PHP/DB e sem integração pública.

## Objetivo

Construir a tela administrativa sobre o Model e a ACL dormente entregues na F9-05. Esta subtarefa permite administrar **definições** de campos e grupos para `cms/page`, sem conectar os valores ao lifecycle da página.

## Entrega

- `dev/admin/controller/custom_field/definition.php`
  - listagem de grupos e campos;
  - criar/editar grupos;
  - criar/editar definições de campo;
  - exclusão somente por POST, com CSRF e ACL `modify`;
  - validação de chave, duplicidade, tipo, grupo, visibilidade e label inglês obrigatório.
- `dev/admin/view/template/custom_field/definition_list.twig`
- `dev/admin/view/template/custom_field/definition_form.twig`
- `dev/admin/language/en-us/custom_field/definition.php`
- Tool estático `tools/e2e/e2e_f906.sh --check`.

## Limites e garantias

- Escopo permitido segue exclusivamente `cms/page`.
- Chave e tipo permanecem imutáveis após criação; `localized` não é editável nesta tarefa.
- Somente os seis tipos F9-01: `text`, `textarea`, `number`, `boolean`, `url`, `select`.
- Visibilidade é metadado de definição; público continua sem renderização/consumo.
- A tela nunca grava em `custom_field_value`.
- Exclusão delega as guardas ao Model F9-05; campo com valores e grupo com campos são preservados.
- Não adiciona menu de navegação global; a rota ACL é acessível diretamente e a descoberta/menu pode entrar em tarefa separada após revisão do grupo de navegação.
- Nenhum schema/migration F9-02 foi executado; a instalação de laboratório precisa ter aplicado F9-02 e grants F9-05 antes de abrir a rota.

## Validação

O gate `tools/e2e/e2e_f906.sh --check` verifica estaticamente controller, verbos POST, ACL/CSRF, templates, traduções, imutabilidade dos campos e ausência de integração pública/escrita de valores. Ele **não** equivale a teste PHP, navegador, banco ou runtime. Esses testes aguardam ambiente de laboratório autorizado.

## Arquivos da entrega

- `dev/admin/controller/custom_field/definition.php`
- `dev/admin/view/template/custom_field/definition_list.twig`
- `dev/admin/view/template/custom_field/definition_form.twig`
- `dev/admin/language/en-us/custom_field/definition.php`
- `docs/F9-06-UI-Admin-Campos-Personalizados.md`
- `docs/README.md`
- `agents-ia/decisoes/0062-f9-06-ui-admin-campos-personalizados.md`
- `tools/e2e/e2e_f906.sh`
- `tools/e2e/e2e_f906.cjs`
- `manifest.json` e `sha256sum.txt`
