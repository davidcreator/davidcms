# Fase 9 · Tarefa 09 — Navegação administrativa para campos personalizados

**Data:** 2026-10-24 · **Base verificada:** `davidcreator/davidcms`, branch `main`  
**HEAD inicial:** `fa40f72ac93f16840642b6dc263636c7e211bdd7`  
**Estado:** link de menu/labels; sem alteração de CRUD, banco ou produção.

## Objetivo

A rota administrativa `custom_field/definition` já existe desde F9-06, mas não era descoberta pela sidebar. Esta tarefa fecha o adiamento anotado na ADR 0062.

## Alteração

- Adiciona **Campos personalizados** ao grupo Content do menu administrativo, apontando para a rota real `custom_field/definition`.
- Mantém a política ACL-driven do `common/header`: item só é construído quando `hasPermission('access', 'custom_field/definition')` retorna verdadeiro.
- O método `matchRoute` existente reconhece automaticamente sub-ações `.add_group`, `.edit_field`, etc. por prefixo da rota base; não foi alterado.
- Adiciona labels em `en-us`, `pt-br` e `es-eu`.

## Segurança/escopo

- Não concede permissões novas nem altera o seed/upgrade F9-05.
- Usuários sem `access` não veem o item; a proteção do endpoint continua na ACL do router/controller.
- `modify` segue necessário para criar, editar ou excluir; visibilidade do link não altera capabilities.
- Sem mudança em Model, CMS/publicação, schema, valores, catálogo, idiomas de conteúdo ou banco.

## Validação

`tools/e2e/e2e_f909.sh --check` inspeciona a rota/item/ícone/label, presença da checagem ACL pré-existente e as três traduções. É uma validação estática, não browser/PHP/runtime. Não foi feito deploy nem escrita no GitHub.

## Arquivos

- `dev/admin/controller/common/header.php`
- `dev/admin/language/en-us/common/header.php`
- `dev/admin/language/pt-br/common/header.php`
- `dev/admin/language/es-eu/common/header.php`
- `docs/F9-09-Navegacao-Campos-Personalizados.md`
- `docs/README.md`
- `agents-ia/decisoes/0065-f9-09-navegacao-campos-personalizados.md`
- `tools/e2e/e2e_f909.sh` e `.cjs`
- `manifest.json` e `sha256sum.txt`
