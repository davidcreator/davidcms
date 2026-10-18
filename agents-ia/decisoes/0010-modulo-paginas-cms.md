# ADR 0010 — Módulo de Páginas CMS (`cms/page`, F2 Tarefa 10)

- **Data**: 2026-10-18
- **Status**: Aceito
- **Contexto**: Terceiro CRUD no molde-ouro (0008 projetos → 0009 categorias).
  Páginas institucionais do site (Sobre, Políticas, Cookies) — módulo `cms/`
  do Dicionário-de-Dados, que alimentará o catálogo `information/` na Fase 3.
  Tabelas `rms_page` + `rms_page_description` + `rms_seo_url` **já instaladas
  pelo wizard** (schema 29 tabelas) — **zero DDL novo**, terceira vez
  consecutiva: o DDL-first da Fase 1 segue pagando.

## Decisões

1. **Rota `cms/page`** — primeiro módulo do grupo `cms/` (bucket de conteúdo,
   parallel a `catalog/` e `user/`), mesma gramática de rotas
   (`.add/.edit|N/.delete|N`); ACL base-route; tela de permissões detecta
   pelo scan de controllers.
2. **SEM método `.upload`** — `rms_page` não tem coluna de imagem (páginas
   institucionais não precisam de thumb; o conteúdo visual ficará a cargo do
   editor de tema). Consequência: a **"regra dos três" do componente de
   upload continua pendente** — apenas 2 módulos pedem arquivo
   (project/category), extração segue adiada até o terceiro demandante
   (candidatos: client_logo, tool).
3. **Multi-idioma idem 0008/0009**: fieldset por idioma instalado; **título
   en-US obrigatório** (≤255), description MEDIUMTEXT livre, meta_title/
   meta_description; grid com coalesce idioma ativo → en-US.
4. **SEO por idioma em `rms_seo_url`**, `key='page_id=N'`, unicidade
   **global** entre entidades (colisão com keyword de projeto/categoria é
   rejeitada — a bateria cruza propositalmente com keyword de projeto).
5. **Delete completo num único POST** (confirm; GET jamais muta): apaga
   página + descrições + keywords SEO. Não há relações N:N nem mais
   dependências.
6. **Seed/migração idênticos ao padrão**: seedRoutes do wizard += `cms/page`
   (`['user/user','user/user_group','catalog/project','catalog/category','cms/page']`);
   `install/sql/upgrade_f2_10_cms_page_grant.sql` idempotente
   (JSON_SEARCH — **nunca JSON_CONTAINS**, quebrado no MariaDB 11.8.6 para
   strings com `\/` escapado pelo json_encode do PHP).
7. **Card no dashboard** (`text_manage_pages`) filtrado por ACL
   `hasPermission('access','cms/page')`, idiomas ×3.

## Deltas não-objetivos

- Editor WYSIWYG / blocos → trabalho de tema; textarea simples por ora.
- Sem hierarquia de páginas, sem template por página, sem revisões —
  schema não prevê e não é demanda.
- Resolver keyword → rota no catálogo (`information/information&id=N`) é
  Fase 3.

## Validação

- `/tmp/e2e_f210.sh` — **13 checks, 0 falha**, a partir de DB zerado
  (layout XAMPP replicado em /tmp/fui):
  seed-grant no install · login · lista vazia honesta · card dashboard ·
  form 3 idiomas **sem campo de upload** · validação título en-US ·
  create multilíngue + 2 keywords SEO persistidas · lista com flash ·
  edição repovoa + swap de keyword (limpeza do par antigo) + toggle status ·
  keyword formato inválido + **colisão global com keyword de projeto** ·
  delete GET-sem-mutar + POST com cascata seo · pt-BR/es-EU ·
  ACL deny → migração ×2 (idempotente) → acesso restaurado.
- Regressões no mesmo sandbox: **f27 22/22, f28 17/17, f29 17/17**
  (total 69 asserts verdes).
- PHPStan level-5 (paths pintados) limpo; `php -l` em todos os arquivos.

## Entrega

- `reamurcms-fase2-tarefa10.zip` — 11 arquivos novos/alterados
  (espelho `/home/user/davidcms`, clone+diff contra `@6751867` verificado
  antes de escrever).
