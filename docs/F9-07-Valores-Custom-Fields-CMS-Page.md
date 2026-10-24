# Fase 9 · Tarefa 07 — Valores de campos personalizados em CMS/page (admin)

**Data:** 2026-10-24 · **Repositório verificado:** `davidcreator/davidcms`, branch `main`  
**HEAD inicial:** `9151ba7c8d9d00772c788388d58104ce430460f5`  
**Estado:** integração no formulário administrativo e adapter de valores; sem execução em PHP/banco/produção.

## Objetivo

Ativar a primeira entidade aprovada na F9-01: campos personalizados associados a `cms/page`. Esta tarefa conecta definições F9-05/F9-06 a valores compartilhados ou localizados, mantendo a saída pública do catálogo inalterada.

## O que foi preparado

- Novo Model `dev/admin/model/custom_field/value.php`:
  - busca somente definições da entidade `cms/page` em grupos ativos;
  - lê valores existentes por página;
  - grava apenas field IDs ativos e language IDs válidos;
  - `localized=0` usa `language_id=0`; `localized=1` usa idiomas ativos;
  - valida número compatível com `DECIMAL(38,6)`, boolean, URL HTTP(S), select e required;
  - faz upsert de valores em transação local, com rollback se uma escrita retornar falha;
  - remove valores de `cms/page` no fluxo de exclusão da página.
- `dev/admin/controller/cms/page.php`:
  - carrega adapter/definições e apresenta erro de validação antes de salvar;
  - salva valores após criar/editar a página;
  - bloqueia exclusão da página se a limpeza de seus valores reportar falha.
- `dev/admin/view/template/cms/page_form.twig`:
  - controles admin-only para `text`, `textarea`, `number`, `boolean`, `url` e `select`;
  - controles localizados repetem por idioma ativo.
- Strings `en-us` para controles e mensagens.
- Tool estático `tools/e2e/e2e_f907.sh --check`.

## Limites e segurança

- Whitelist rígida: `cms/page` apenas; nunca aceita valor de outra entidade.
- Os valores são editados exclusivamente na tela administrativa já protegida pela ACL de `cms/page`; não foi criada rota pública.
- A flag de visibilidade `public` não é consumida nesta subtarefa. Não há alteração de controller/template do catálogo ou do front-end.
- Regras de required/formatos são repetidas no controller/model; identificadores de campo/idioma recebidos do cliente são confrontados com definições e idiomas autorizados.
- A transação cobre o lote de valores customizados, não a gravação prévia do conteúdo base da página. Se o lote de valores falhar, o usuário recebe mensagem explícita: a página base pode já estar salva; reabrir e corrigir/repetir é necessário. Uma transação única para todo o CRUD será uma tarefa própria se desejada.
- A exclusão de página remove valores do mesmo entity ID antes de apagar o registro (schema F9-02 não usa FKs). Este fluxo depende de F9-02 estar aplicado.

## Pré-requisitos para laboratório

1. Aplicar manualmente e com backup a migração F9-02 no ambiente descartável.
2. Aplicar a ACL F9-05 no grupo administrativo (ou confirmar grants equivalentes).
3. Publicar/aplicar F9-06 e F9-07 na mesma cópia de laboratório.
4. Testar criar/editar página com cada tipo, combinações localizado/compartilhado, required, opção inválida, exclusão e reabertura.

Não executei nenhum desses passos. PHP e banco não estão disponíveis no ambiente de preparação; o Tool E2E desta tarefa é estático e não atesta runtime.

## Arquivos da entrega

- `dev/admin/model/custom_field/value.php`
- `dev/admin/controller/cms/page.php`
- `dev/admin/view/template/cms/page_form.twig`
- `dev/admin/language/en-us/cms/page.php`
- `docs/F9-07-Valores-Custom-Fields-CMS-Page.md`
- `docs/README.md`
- `agents-ia/decisoes/0063-f9-07-valores-campos-cms-page.md`
- `tools/e2e/e2e_f907.sh`
- `tools/e2e/e2e_f907.cjs`
- `manifest.json` e `sha256sum.txt`
