# Fase 9 · Tarefa 08 — Exibição pública seletiva de campos custom em CMS/page

**Data:** 2026-10-24 · **Repositório verificado:** `davidcreator/davidcms`, `main`  
**HEAD inicial:** `4c79b4865db2cdf05e98a2684da4f035807f89c2`  
**Estado:** preparação local para revisão; nenhuma execução PHP/SQL/banco/produção.

## Objetivo

Fechar a primeira fatia vertical do MVP F9-01 para páginas CMS: exibir no site somente campos cujas definições estejam explicitamente marcadas `visibility=public` e que tenham valor salvo.

## Comportamento preparado

- Model público independente `dev/catalog/model/custom_field/value.php`.
- A consulta exige simultaneamente:
  - página ainda publicada (`page.status=1`);
  - entidade `cms/page`;
  - grupo ativo;
  - definição `visibility=public`;
  - tipo pertencente à whitelist de seis tipos F9-01.
- Localização: valor/label/grupo do idioma atual com fallback en-US; campos compartilhados leem `language_id=0`.
- `boolean` é traduzido; `select` mostra o label da opção configurada, nunca o valor cru como HTML.
- O controller inclui somente campos com conteúdo; valor público é limitado a 5.000 caracteres na saída.
- O Twig usa autoescape global do engine e o bloco novo **não** usa `|raw`; textarea mantém quebras de linha via `white-space:pre-wrap`.

## Segurança e limites

- Campos `admin_only`, grupos inativos, páginas em rascunho/inativas e tipos desconhecidos não aparecem.
- Custom values nunca são interpretados como HTML, script, URL clicável ou template.
- Nenhum admin route, CRUD ou schema é alterado.
- A descrição HTML original da página continua com o comportamento preexistente (`description|raw`); a F9-08 não modifica esse caminho. A garantia de escape aqui cobre apenas o novo bloco de custom fields.
- Requer migração F9-02 aplicada e código F9-07 para existir dado. A ausência do schema no site não foi testada; aplicar primeiro em laboratório e publicar o conjunto de dependências em ordem.

## Validação

`tools/e2e/e2e_f908.sh --check` valida estaticamente os predicados de publicação/visibilidade, locale/fallback, whitelist, saída Twig escapada e ausência de `raw` no bloco novo. Isso não é PHP lint, teste de banco, navegador ou prova de resposta em produção. PHP não está disponível nesta preparação.

## Arquivos

- `dev/catalog/model/custom_field/value.php`
- `dev/catalog/controller/cms/page.php`
- `dev/catalog/view/template/cms/page_info.twig`
- `dev/catalog/language/en-us/cms/page.php`
- `dev/catalog/language/pt-br/cms/page.php`
- `dev/catalog/language/es-eu/cms/page.php`
- `docs/F9-08-Exibicao-Publica-Campos-CMS-Page.md`
- `docs/README.md`
- `agents-ia/decisoes/0064-f9-08-exibicao-publica-campos-cms-page.md`
- `tools/e2e/e2e_f908.sh` e `.cjs`
- `manifest.json` e `sha256sum.txt`
