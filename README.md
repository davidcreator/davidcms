# Correção de CSS do Admin — Temas e Campos Personalizados

Base consultada antes da edição: HEAD `7d2e63b2fe48f1636d0fdf50e63f9ce6ca3617e7`.

## Causa identificada
Os controllers `design/theme` e `custom_field/definition` montavam o shell via `common/header`, mas seus métodos `chrome()` não registravam `view/css/admin.css`. Outras telas como Pages fazem isso explicitamente. Esse arquivo contém o layout do shell (sidebar/topbar) e os estilos administrativos usados pelas telas; sem ele, o conteúdo ficava sem a apresentação esperada.

## Correção
Os dois controllers passam a adicionar `admin.css` com o mesmo URL versionado por `Header::ASSET_V` usado nas telas CMS existentes, antes de renderizar o header. Lista e formulários dos dois módulos são cobertos.

## Aplicar no clone
Da raiz do clone Git, após extrair script e patch: 

```bash
bash apply-patch.sh ./admin-css-fix.patch 7d2e63b2fe48f1636d0fdf50e63f9ce6ca3617e7
```

Se estiverem em outro local, use os caminhos absolutos do script e do patch. O script exige o HEAD indicado, faz `git apply --check` e depois aplica localmente; não commita, faz push, SQL, staging ou deploy.

A validação do patch é estática. Não acessei `localhost`, sessão, cookies ou credenciais.
