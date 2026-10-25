# BDREditor — aviso de alterações não salvas

Incremento cumulativo sobre **BDREditor-Hierarchical-Reordering**, para o editor exclusivo de conteúdo do ReamurCMS.

## O que mudou

- Estado visível e acessível **salvo / alterações não salvas / salvando** no formulário atual.
- Edições nos campos e no BDREditor marcam o formulário como não salvo; inputs hidden de serialização e CSRF são ignorados.
- O aviso nativo de saída permanece ativo apenas enquanto há alterações pendentes. Submissão válida ao POST existente mostra **salvando** sem declarar sucesso prematuramente; o flash/redirect do servidor segue como confirmação de persistência.
- Mensagens localizadas PT-BR, EN-US e ES-EU.

Não inclui autosave, API/endpoint, armazenamento novo, mudança de schema, banco, staging, migrations, deploy ou GitHub write.

## Verificações

```sh
node --check dev/admin/view/js/blog-block-editor.js
node --check dev/admin/view/js/blog-block-editor-init.js
node tests/check_hierarchical_reordering.js
node tools/e2e/e2e_bdre_hierarchical_reordering_static.cjs
node tools/e2e/e2e_bdre_unsaved_changes_static.cjs
```

Os checkers cumulativos anteriores também foram executados. O checker novo reporta **22 PASS** estáticos. `php -l` não pôde ser executado porque PHP não está instalado; não houve validação real em navegador/leitor de tela.

- Especificação: `docs/BDREditor-Unsaved-Changes-Warning.md`
- Checker: `tools/e2e/e2e_bdre_unsaved_changes_static.cjs`
- Manifesto: `SHA256SUMS`
