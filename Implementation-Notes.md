# Prévia privada completa de CMS Pages

## Escopo

Implementação local baseada no HEAD remoto `44d9334a78c51eef41cd01a0cef14b79b8371ccb` (consultado antes das edições), conforme o plano aprovado. A prévia POST privada passa a usar os templates reais `common/header`, `common/footer` e `cms/page_info` do Catalog, incluindo os overrides públicos publicados e allowlisted do tema, os tokens de tema ativos, links de navegação/Home, e campos personalizados públicos.

O formulário envia os valores não salvos dos custom fields públicos. No servidor, a implementação confere os field IDs e language IDs contra definições existentes, valida os valores pelo tipo/opções permitidos, aplica idioma/fallback en-US, e só renderiza grupos e campos com visibilidade `public`. A validação ocorre sem gravar valores.

## Segurança e limites preservados

- Acesso por ACL `modify cms/page`; método POST e CSRF.
- Resposta `private, no-store`; CSP restritiva, `X-Frame-Options: DENY`, `frame-ancestors 'none'`, no-referrer e meta robots noindex.
- Renderer usa somente raiz fixa do Catalog e a allowlist existente de templates publicados do tema; cache Twig desligado.
- `custom_js` de Three.js permanece removido; só a cena/runtime aprovados podem renderizar.
- Media continua no renderer/allowlists de Pages existentes. Nada é salvo e nenhum link para o draft atual é gerado; o seletor de idioma aponta para Home.
- Pages continua separado do módulo Blog; Blog é usado somente como renderer já existente de blocos/compatibilidade de mídia.

## Arquivos alterados

- `dev/admin/controller/cms/page.php` — contexto Catalog de leitura, validação de idioma/custom fields e composição do shell.
- `dev/admin/model/cms/page.php` — consulta somente de leitura dos links legais públicos para o footer.
- `dev/admin/model/custom_field/value.php` — leitura de labels por language ID opcional; chamadas existentes mantêm o idioma padrão.
- `dev/admin/view/template/cms/page_form.twig` e `dev/admin/view/js/cms-page-preview.js` — envio POST dos metadados e valores públicos não salvos.
- `dev/system/library/template/twig.php` — renderer opt-in em ambiente Twig isolado com raiz fixa do Catalog.
- `dev/catalog/view/template/common/header.twig` — meta robots apenas quando a prévia privada sinaliza esse contexto.

## Aplicação e validação

- Patch: `CMS-Pages-Full-Preview.patch` (paths relativos a `dev/`; `patch -p1` na raiz do repositório).
- Aplicação de patch validada contra a cópia baseline: aplicou sem conflito e gerou árvore idêntica à entrega.
- `node --check` do JS passou.
- PHP CLI não está disponível nesta sessão; portanto, PHP lint e runtime não foram executados. Twig/runtime também não foram executados.
- Nenhum GitHub write/push, deploy/staging, migration, ou persistência de draft foi feito.

## Estado

Entrega local preparada e ainda não aplicada ao repositório do usuário. A árvore baseline original permanece separada em `../baseline/`; arquivos de referência não alterados ficam na árvore de entrega para contextualizar o renderer.
