# F9-87 — Fluxo de criação Pages CMS + BDREditor em tela cheia

## Objetivo

Separar a criação de páginas da tela de edição rápida do Pages CMS. A rota de inclusão abre uma interface própria de criação, com BDREditor em modo imersivo, em vez de reutilizar o formulário geral `page_form`.

## Fluxo entregue

1. O comando existente **Adicionar página** continua usando a rota `cms/page.add`, agora renderizada por `cms/page_create_editor`.
2. O primeiro idioma abre diretamente no BDREditor em tela cheia. A barra de contexto tem campo de título editável, sincronizado com o título do formulário e com o estado de salvamento.
3. SEO, URL amigável, status, ordem e campos personalizados permanecem disponíveis no formulário de criação, após sair da tela imersiva; traduções adicionais continuam acessíveis pelas abas de idioma.
4. O formulário só faz a gravação final após validação, CSRF e validação do documento BDRE. Uma visita à tela ou o cancelamento não cria um registro vazio.
5. Salvar retorna à lista Pages; cancelar também retorna à lista. Erros de validação reapresentam a interface com os dados submetidos.

## Preservação e limites

- A edição de páginas existentes continua usando o fluxo anterior nesta primeira fase; o formulário `page_form` e seus campos não foram removidos.
- O fluxo do Blog e o formato persistido BDRE não mudaram. Só a versão de cache dos assets compartilhados foi atualizada.
- Chaves de recuperação local de rascunho usam identificador aleatório por tela de criação, preservado na URL e no POST, para não misturar conteúdo de duas páginas novas e ainda permitir recuperação após recarregar a tela. O conteúdo fica em `sessionStorage`; não cria registros no servidor.
- Não há migração, alteração de schema, staging, gravação no GitHub ou deploy nesta entrega.

## Verificação

- `node tools/f9-87-page-create-workflow-smoke.js`
- Smoke tests de regressão F9-61, F9-66 e F9-76–F9-86.
- `python3 tools/f9-57-static-battery.py`, `node --check` e parsing CSS com `tinycss2`.

PHP CLI e navegador real não estão disponíveis; portanto não declaro execução de PHP de runtime nem teste real de interação no browser.
