# BDREditor — aviso de alterações não salvas

Incremento cumulativo do editor de conteúdo BDREditor, limitado ao feedback de estado no formulário POST já existente.

## Comportamento

- Ao abrir o formulário sem erros, a região de estado indica que as alterações estão salvas. Mudanças em campos do formulário ou no modelo do BDREditor atualizam o estado para **alterações não salvas**.
- Campos hidden usados para serialização (`blog_blocks[...]` e `blog_editor_ready[...]`) e o token CSRF não disparam falsos estados dirty.
- Ao submeter um formulário válido, o status passa a **salvando**; ele não afirma que a persistência terminou. A resposta de sucesso continua sendo o flash do POST existente e o redirect já implementado pelo controller.
- Se o servidor devolver o formulário com erro, o aviso de erro inicializa o estado como não salvo. Falha de validação no cliente interrompe o envio e mantém o aviso.
- O `beforeunload` nativo só é suprimido durante submissão válida; ao retornar por `pageshow`, a proteção é restabelecida.
- A indicação usa região `role=status`, `aria-live=polite` e `aria-atomic=true`, com texto localizado em PT-BR, EN-US e ES-EU.

## Escopo excluído

Nenhum autosave, endpoint, requisição adicional, persistência nova, alteração de schema, banco, migrations, staging, deploy ou escrita GitHub. O envio continua sendo o POST existente, com validação do formulário e flash de sucesso do servidor. Não foi criado diálogo customizado: navegadores controlam a apresentação do aviso de saída nativo.

## Arquivos desta etapa

- `dev/admin/view/js/blog-block-editor-init.js` — controle de dirty/saving e integração com POST e aviso `beforeunload`.
- `dev/admin/view/css/blog-block-editor.css` — estilos visíveis para os estados.
- `dev/admin/controller/blog/blog.php` e `dev/admin/language/{pt-br,en-us,es-eu}/blog/blog.php` — passagem e localização das mensagens.
- `tools/e2e/e2e_bdre_unsaved_changes_static.cjs` — checker de integração e guardrails.

## Verificação

```sh
node --check dev/admin/view/js/blog-block-editor-init.js
node tools/e2e/e2e_bdre_unsaved_changes_static.cjs
```

O checker novo é estático, não substitui execução em browser real. PHP lint depende do interpretador `php`, indisponível no ambiente usado para esta entrega.
