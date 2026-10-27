# Fase 10 — Pages: conteúdo editorial nas rotas públicas

**Base remota consultada:** `ac03b9687b8762ee919fe8d9bef48807989bf817` (`feat(theme): implement F10-03 Catalog theme integration`).

## Gap audit
O CMS já guardava conteúdo por `system_key` e o renderer `cms/slot` existia, mas fora da Home o `common/header` consultava slots para metadados e o conteúdo não era incluído no corpo das páginas. Isso deixava a edição de Portfolio, Blog landing, Tools, Contact, Quote e 404 invisível na publicação.

## Incremento proposto (sem nova numeração formal)
- Exibir slots existentes em Portfolio (grade e detalhe), Blog (apenas landing/listagem), Tools (grade e detalhe), Contact, Quote e 404.
- Memoizar dados e HTML do slot por request, evitando query/render duplicados entre página e header.
- Não alterar CRUD/fluxos de Portfolio, posts do Blog, registros de ferramentas, formulários, checkout, ou 404 técnico.
- Blog post individual permanece sem conteúdo CMS; a landing do Blog é a única superfície editorial nesse módulo.
- Sem nova migration/schema, sem alterar núcleo compartilhado.

## Validação e limites
Patch validado localmente com `git apply --check` sobre arquivos seletivos do HEAD. Não foram executados PHP CLI, browser, banco, migration, localhost, push, staging ou deploy.

## Aplicação local do patch

Extraia os arquivos do pacote. A partir do diretório raiz do clone Git que deve receber a alteração, execute:

```bash
bash /caminho/para/apply-patch.sh /caminho/para/F10-Pages-Routes.patch ac03b9687b8762ee919fe8d9bef48807989bf817
```

Se o script e o patch forem extraídos na raiz do clone, use:

```bash
bash apply-patch.sh F10-Pages-Routes.patch ac03b9687b8762ee919fe8d9bef48807989bf817
```

O script exige o HEAD esperado, roda `git apply --check` e só então aplica o patch. Ele não cria commit nem faz push/deploy/SQL.
