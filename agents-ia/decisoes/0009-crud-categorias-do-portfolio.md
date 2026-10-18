# ADR 0009 — CRUD de Categorias do portfólio (F2 Tarefa 9)

- **Data**: 2026-10-18
- **Status**: Aceito
- **Contexto**: Segundo CRUD no molde do ADR 0008 (padrão-ouro), completando o
  eixo do portfólio: projetos (F2-8) + categorias N:N. Tabelas já instaladas
  pelo wizard (`rms_category`, `rms_category_description`,
  `rms_project_to_category`, `rms_seo_url`) — zero DDL novo, como no F2-8.

## Decisões

1. **Rota `catalog/category`** com a mesma gramática do 0008
   (`.add/.edit|N/.delete|N`);
   tela de permissões detecta sozinha (scan controller/**.php).
2. **Hierarquia simples (`parent_id` auto-referente)** com **guarda anti-ciclo
   em camadas**: dropdown do form já omite a própria categoria e TODOS os
   descendentes (expansão de fronteira no model); validação server-side
   rejeita POST adulterado com `parent_id` inválido. Árvore pequena ⇒
   expansão por loop em PHP, sem CTE/materialized path (diferido se/v quando
   precisar de breadcrumbs extensos).
3. **Delete honesto**: filhos **sobem um nível** (parent_id=0 — nunca órfãos
   de árvore), descrições e keywords da categoria são apagadas, a relação
   N:N (`project_to_category`) é limpa e a imagem física é removida
   (best-effort), tudo em um único POST com confirm (GET jamais muta).
4. **SEO compartilhado**: keyword por idioma vive na mesma `rms_seo_url`
   (`key='category_id=N'`) e valida unicidade **global** por idioma — a
   keyword de uma categoria não pode colidir com a de um projeto (a Fase 3,
   que resolve keywords → rotas, agradece).
5. **Multi-idioma** idem ao 0008 (fieldset por idioma; nome en-US obrigatório;
   coalesce idioma ativo → en-US na grade, com nome do pai resolvido pela
   mesma coalesce).
6. **Upload duplicado de propósito** (`catalog/category.upload` →
   `view/image/category/`): "regra dos três" — a extração do componente
   compartilhado de upload acontece quando o terceiro módulo pedir arquivo
   (provável F2-10). Decisão consciente contra abstração prematura.
7. **Seed/migração simétricos ao F2-8**: seedRoutes do wizard += 
   `catalog/category`; `install/sql/upgrade_f2_9_category_grant.sql`
   (idempotente) para instalações existentes ou a tela de grupos.
8. **Regressão resiliente a reset**: e2e do F2-7 e do F2-8 foram
   reescritos neste ciclo (sandbox volátil — /tmp não sobrevive) e passam a
   ser alvo de commit futuro na pasta `dev/tests/` pendência conhecida do
   plano (phpunit/e2e em CI — ADR 0004).

## Deltas não-objetivos

- Dropdown de pai plano (sem indentação hierárquica), breadcrumbs e slugs
  aninhados na Fase 3.
- Sem paginação ainda (mesma decisão do 0008).
- Editor WYSIWYG deslocado para o trabalho de tema.

## Validação

- `/tmp/e2e_f29.sh` — 17 checks do zero (layout XAMPP replicado):
  seed-grant no install, login, lista vazia, cartão dashboard, form
  3 idiomas + select de pai, validações (nome, keyword formato/dup GLOBAL,
  anti-ciclo de dropdown e de POST adulterado), upload ok/rejeite, edição
  com imagem + SEO swap, hierarquia (filho no banco; edit do filho oferece
  pai de volta), integração projeto→categoria (N:N persiste e repovoa),
  delete reparenta/limpa/unlink, pt-BR/es-EU, ACL deny + migração
  idempotente 2× → **17/17**.
- Regressões: **F2-8 17/17** · **F2-7 22/22** (21 + asserção extra 14a) ·
  **PHPStan: 0 erros** · `php -l` em tudo.
