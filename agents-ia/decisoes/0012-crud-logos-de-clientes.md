# ADR 0012 — CRUD de Logos de Clientes (`marketing/client_logo`, F2 Tarefa 12)

- **Data**: 2026-10-18
- **Status**: Aceito
- **Contexto**: Quinto CRUD no molde-ouro e o **primeiro single-table** do
  projeto (sem `*_description`, sem SEO, sem datas — o Dicionário-de-Dados
  define `name`/`url` como campos únicos; consumidor = home). Terceiro
  consumidor de imagem e por isso o melhor testemunho de que o MediaUpload
  (ADR 0011) vira uso corrente: o controller ficou **menor** que o de
  categorias mesmo com a mesma superfície de upload.

## Decisões

1. **Rota `marketing/client_logo`** — fecha a dupla do grupo `marketing/`
   (testimonial+client_logo, Dicionário-de-Dados); mesma gramática de rotas
   (.add/.edit|N/.delete|N POST/.upload POST→JSON, ACL base-route).
2. **Sem multi-idioma**: form simples (nome, URL, ordenação, status + logo);
   grade sem coalesce. Primeiro CRUD a não ter `<fieldset>` por idioma.
3. **Validações**: `name` obrigatório (≤128); `url` **opcional**, mas se
   preenchida precisa iniciar por `http(s)://` (regex `~^https?://~i`);
   imagem via `guard` do MediaUpload (só dentro de `view/image/client_logo/`).
4. **Declaração de suposição**: logo pode ficar vazio (schema permite `''`,
   grade mostra "(no logo)") — nunca bloqueia o fluxo editorial; a home
   decide exibir ou não.
5. **Delete** POST+confirm: linha única + arquivo físico best-effort.
6. **Seed/migração padrão**: seedRoutes do wizard += `marketing/client_logo`
   (7 rotas nativas); `upgrade_f2_12_marketing_client_logo_grant.sql`
   idempotente (JSON_SEARCH).
7. **Baterias agora persistem**: sandbox Arena resetou de novo durante a
   tarefa (apagou php/mariadb//tmp — receita rebuild: apt stack +
   Apache :8090 Alias→bind-mount /var/www/html/davidcms→/tmp/fui +
   composer install no storage). e2e_f212.sh e e2e_regressao.sh (que cobre
   o coração do f27..f211 perdidos) vivem em `dev/tests/e2e/` **dentro do
   repo** — encerrando a pendência do ADR 0004; rodar sempre na raiz do
   mirror vivo. **Lições de harness registradas**: o contador de ocorrências
   JSON de migração usa `DIV <len(rota)>` (project=15, testimonial/
   client_logo=21) — off-by-one drena o check 8/14; wizard precisa de
   /tmp/fui gravável pelo www-data (bind-mount + chmod a+w).
8. **Auditoria de idiomas "justa"** ( refinamento da lição F2-11): validar
   que keys duplicadas (`column_image`/`entry_image` = 'Logo') têm o MESMO
   valor traduzido (deliberado), em vez de proibir valor == EN.

## Deltas não-objetivos

- Redimensionamento/thumb dedicado: grade serve a imagem original (mesma
  decisão F2-8).
- Troca de logo não remove o arquivo antigo (housekeeping futuro, legado).
- Campo URL sem verificação de alcançabilidade (HEAD) — validação é de
  formato, não de destino.

## Validação

- `dev/tests/e2e/e2e_f212.sh` — **14 checks, 0 falha**, do zero (layout
  /davidcms/dev :8090 bind-mount): seed-grant · login · lista vazia · card
  dashboard · form single-table (sem fieldset/idioma/keyword) · validações
  nome e URL (ftp rejeitado) · upload real via componente (+fake rejeitado,
  +GET→JSON) · create com logo+URL persistidos · lista thumb+flash+link ·
  edição swap logo + toggle status · delete GET-sem-mutar + POST com
  arquivo físico · pt-BR/es-EU · ACL deny → migração ×2 → 200.
- `dev/tests/e2e/e2e_regressao.sh` — **10 checks, 0 falha**: 7 grants de
  seed + 3 idiomas · 5 cards · SEO global cruzado page×project×category ·
  hierarquia + anti-ciclo + delete-sobe-filho · swap SEO em page ·
  testimonial com componente · ACL deny+migração idempotente (project) ·
  pt/es spot · cascata completa com remoção física.
- PHPStan level-5 limpo; `php -l` geral; clone+diff contra `@8788cd1` antes
  de escrever (repo ≡ espelho; únicos extraterrestres: ADRs 0010/0011 ainda
  não comitados — pendência de docs, não de código).

## Entrega

- `reamurcms-fase2-tarefa12.zip` — módulo completo + dashboard/seed/
  migração + guards + as duas baterias em `dev/tests/e2e/`.
