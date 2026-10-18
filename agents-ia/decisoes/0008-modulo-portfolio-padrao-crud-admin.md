# ADR 0008 — Módulo Portfólio no admin: o padrão-ouro dos CRUDs (F2 Tarefa 8)

- **Data**: 2026-10-18
- **Status**: Aceito
- **Contexto**: Fase 2 pendia "primeiro módulo de conteúdo". O usuário escolheu
  **Portfólio** (entidade central do davidalmeida.xyz) como CRUD fundador —
  campos ricos (título multi-idioma, capa, galeria, categorias, SEO, ordenação,
  destaque) que exercitam bem o padrão. Objetivos: (a) funcionar no layout
  XAMPP/subpasta validado no hotfix anterior; (b) virar o molde dos CRUDs da
  Fase 4 (Analise-DavidAlmeida §Fase 4). Escopo cirúrgico: **só admin** — a
  exibição pública fica na Fase 3 (tema/front).

## Decisões

1. **Rota `catalog/project`** — o Dicionario-de-Dados (§linha de tabelas) já
   classifica `project*` no módulo `catalog/`. Grupo `admin/controller/catalog/`
   (pasta vazia do contrato de pastas) ganha sua primeira rota de verdade.
2. **Zero mudança de schema**: as 29 tabelas instaladas pelo wizard já incluíam
   `rms_project`, `rms_project_description`, `rms_project_image`,
   `rms_project_to_category`, `rms_category(+_description)` e `rms_seo_url`.
   Nenhum DDL novo — o módulo é 100% aplicação.
3. **Multi-idioma em blocos** (seguindo ADR 0001): `project_description` tem um
   fieldset por idioma de `rms_language` (independe do `status` do front —
   conteúdo é redigido antes do idioma ir ao ar). Título **en-US obrigatório**,
   demais opcionais com coalesce na listagem (`idioma ativo → en-US`).
4. **SEO real desde o primeiro CRUD**: keyword por idioma gravada em
   `rms_seo_url` (`key='project_id=N'`) — valida formato `^[a-z0-9-]+$` e
   unicidade por idioma (consulta antes; o UNIQUE do banco é a rede final).
   Prepara o terreno do roteador SEO da Fase 3.
5. **Categorias N incluídas** (checkbox + persistência de
   `project_to_category`) mesmo sem CRUD de categorias ainda (F2-9) — tabela
   vazia simplesmente não renderiza o grupo de checkboxes.
6. **Upload próprio do módulo** (`catalog/project.upload`, POST → JSON):
   finfo (MIME real, nunca extensão), allowlist JPG/PNG/WebP/GIF, teto 2 MB,
   nome aleatório com data, destino `catalog/view/image/project/` (espaço
   público do catalog — front lê sem gambiarras). Caminho grava-se só se
   dentro do diretório do módulo e arquivo existente (`imageGuard`).
   Deletar projeto remove filhos (sem FKs no schema: cascata manual) **e**
   desvincula os arquivos físicos (best-effort).
7. **ACL por base-route** (Adendo F2-6 do ADR 0005, intacta): um grant
   `catalog/project` cobre index/add/edit/delete/upload; POST exige `modify`.
   Aparece sozinho na tela de grupos (scan de controller/**.php).
   - **Instalações novas**: seed do wizard nasce com o grant
     (`$seedRoutes = [... , 'catalog/project']`).
   - **Instalações existentes**: `install/sql/upgrade_f2_8_portfolio_grant.sql`
     (idempotente, JSON_SEARCH) **ou** a tela de grupos — mesmo efeito.
8. **Imagens no <img> do admin**: URL da store deriva do `site_url` do admin
   (`…/admin/` → `…/catalog/`) — uso exclusivo de *display*. A const
   `HTTP_CATALOG` formal fica para quando o Contrato-Bootstrap §1.1 ganhar a
   constante (diferido, registrado em "Deltas").
9. **Padrões herdados** (não reinventados): method-grammar `.add/.edit|N`,
   reescrita de URL respeitando gramática (links de ação ficam
   `index.php?route=` — ADR 0006), POST-only mutate + confirm, flash de sessão,
   `chrome()` compartilhado, payload normalizado dedicado (lição F2),
   `t()` de prefixo, coalesce multilíngue, redirect `error/permission`.

## Deltas e não-objetivos (declarados)

- **Paginação/filtros/ordenação por coluna**: diferidos — volume atual não
  justifica; chegam com o segundo CRUD (extração de listagem compartilhada).
- **Editor WYSIWYG**: `description` é textarea pura por ora; editor entra com
  o tema/UX da Fase 3.
- **CRUD de categorias** (`catalog/category`), thumbnails server-side
  (crop/resize) e `HTTP_CATALOG` no contrato: tarefas seguintes.
- **MariaDB 11.8**: `JSON_CONTAINS` não casa strings com escape `\/` do
  `json_encode` em consultas de positividade — usar `JSON_SEARCH` (migração
  e bateria já o fazem). Documentado aqui porque pega de surpresa.
- **php -S** do sandbox tem `upload_max_filesize=2M` — a rejeição de arquivo
  >2MB vem do PHP com mensagem genérica honesta do controller; o próprio
  controller também aplica o teto de 2 MB quando o SAPI permite mais.

## Validação

- Bateria E2E `/tmp/e2e_f28.sh` (17 checks, do zero, layout XAMPP replicado):
  instalação com seed-grant → login → lista vazia → cartão no dashboard →
  form 3 idiomas → validações (título, URL, SEO formato/duplicado) → create
  multilíngue (1+3 linhas + 2 seo_url) → flash/listagem → edição com
  repovoamento e swap de keyword → upload ok/rejeições → galeria bind/
  repovoação → thumb via base do catalog irmão → delete POST-only com
  cascata DB + unlink → pt-BR/es-ES → ACL deny sem grant + migração SQL
  idempotente (2×) → **17/17**.
- Regressão F2-7: **21/21** · lint (`php -l`) em tudo · **PHPStan: 0 erros**
  (92 arquivos, nível do `phpstan.neon` vigente).
