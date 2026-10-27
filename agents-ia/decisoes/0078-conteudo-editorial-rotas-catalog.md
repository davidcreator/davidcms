# ADR 0078 — Conteúdo editorial CMS associado às rotas públicas do Catalog

| | |
| --- | --- |
| **Status** | ✅ aceita para implementação incremental |
| **Data** | 2026-10-27 |
| **Agente proponente** | orquestrador → admin/catalog/install |
| **Contexto** | `cms/page` administra registros CMS por `page_id`, enquanto Home, Portfolio, Blog, Ferramentas e formulários públicos são controllers próprios. O requisito é permitir editar o conteúdo editorial de todas as páginas públicas sem duplicar dados de domínio nem substituir seus fluxos. A revisão base está em `docs/Pages-Temas-Revisao-HEAD-2f28.md`. |

## Decisão

1. Adicionar `rms_page.system_key` opcional e único para associar registros CMS a chaves estáveis de rotas públicas. Páginas livres existentes continuam com `NULL`; a migration `upgrade_f10_01_cms_catalog_routes.sql` é aditiva/idempotente.
2. Reutilizar o CRUD/editor/ACL `cms/page`. Páginas de sistema aparecem na lista, têm chave fixa e não podem ser excluídas nem ganhar keyword SEO própria.
3. O Catalog resolve uma allowlist de rotas no `common/header`, busca apenas páginas ativas com `catalog/page::getSystemPage()` e exibe o conteúdo editorial na área compartilhada. Meta title/description só substituem os valores da rota quando explicitamente preenchidos.
4. O conteúdo de blocos BDRE usa o `BlockRenderer` validado existente. HTML legado preserva o mesmo comportamento do editor CMS atual.
5. Páginas de detalhe usam chaves de template compartilhadas (`portfolio_detail`, `blog_post`, `tool_detail`); os dados da entidade continuam nos módulos Portfolio, Blog e Ferramentas. Blog e CMS permanecem ferramentas distintas.
6. Endpoints de pagamento, sitemap XML, APIs e callbacks não são páginas editoriais. A ferramenta Temas será tratada em ADR próprio, sem alterar a appearance do Admin.
7. O patch pode preparar migration e `schema-complete.sql`, mas não executa SQL, não publica e não faz push.

## Alternativas consideradas

1. **Converter todo controller em uma página CMS genérica** — rejeitada: duplicaria modelos/fluxos de Portfolio, Blog, Ferramentas e orçamento.
2. **Armazenar cópias de conteúdo de negócio em blocos CMS** — rejeitada: criaria divergência entre a fonte CMS e os CRUDs especializados.
3. **Descobrir automaticamente qualquer rota executável** — rejeitada: tornaria páginas internas, callbacks e endpoints técnicos editáveis sem contrato explícito.

## Consequências

- ✅ Conteúdo editorial por idioma reutiliza os forms e o editor de blocos existentes.
- ✅ Dados dinâmicos e submissões permanecem nos módulos que já os possuem.
- ✅ Páginas do sistema ficam protegidas contra exclusão acidental.
- ⚠️ A primeira entrega expõe um slot editorial compartilhado por rota; mover/reordenar seções e mapear cada texto hardcoded para campos CMS será feito com a ferramenta Temas/slots em incrementos posteriores.
- ⚠️ A migration precisa ser aplicada pelo operador antes de recursos dependentes de `system_key`; o patch não acessa o banco do usuário.
- **Rollback:** não executar `DROP COLUMN` automaticamente. Reverter o código mantém a coluna e o conteúdo intactos; se for necessário remover o vínculo, exportar/backup dos registros antes e desativar os `system_key` manualmente.

## Rotas iniciais

`home`, `portfolio`, `portfolio_detail`, `blog`, `blog_post`, `tools`, `tool_detail`, `contact`, `quote`, `not_found`.

## Documentos atualizados

- `docs/Dicionario-de-Dados.md`
- `docs/Guia-CMS.md`
- `docs/Arquitetura-MVCL.md`
- `docs/Estrutura-de-Pastas.md`
- `dev/install/sql/schema-complete.sql`
