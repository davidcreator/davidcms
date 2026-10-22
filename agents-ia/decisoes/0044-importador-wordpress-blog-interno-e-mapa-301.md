# ADR 0044 — Importador WordPress do blog interno (F7-03) e mapa 301

- Status: aceito
- Data: 2026-10-21
- Decisores: David L. Almeida (direção/PO), ChatGPT (agente executor em Arena)
- Contexto: F7-03 entrega a migração de conteúdo do WP legado
  (davidcreator.com) para o blog interno de F7-01/F7-02. Escopo da fase:
  importação administrativa idempotente + imagens locais + mapa 301 +
  remoção física do entulho `catalog/model/feed/news.php` (ADIAR de
  ADR 0043 §renomeação).

## Contexto e forças

1. O WP legado expõe `/wp-json/wp/v2/posts` publicamente (66 posts,
   paginação `X-WP-TotalPages`, `_embed=1` traz `status=publish`,
   `title/content/excerpt.rendered` (HTML) e `featuredmedia`).
2. Permalinks do WP: `/<slug>/` (root-style). No app novo a keyword do
   blog resolve via seo_url — mesmo domínio: nenhuma regra 301 é
   necessária se o slug for preservado; domínios distintos exigem
   redirects no servidor antigo (§301 abaixo).
3. Não há credenciais/REST-import pré-existente no repo (F4-08 era por
   arquivo SQL). O desenho é inédito, seguindo o padrão de módulo do blog.
4. O bootstrap do admin NÃO possui constante de catálogo (`DIR_CATALOG`
   inexistente; apenas `HTTP_SERVER`/`HTTPS_SERVER` por ADR 0037 §1.1) —
   o model precisa localizar `catalog/view/image` por heurística de raiz
   (prod `catalog/` → dev `dev/catalog/`).

## Decisão

1. **Importador administrativo** `admin/blog/import` com ações `preview`
   (lê o endpoint, mostra decisões, NÃO escreve nada) e `run`
   (materializa). Base padrão `https://davidcreator.com`, campos
   `page/per_page` e `public_url` (override do host novo do mapa).
2. **Materialização por post**: `blog_post` (`author_id`=usuário logado,
   `status=1`, `date_published` do WP, `image` local quando houver capa
   bem-sucedida) + `blog_post_description` **somente language_id=1**
   (âncora en-US: o coalesce do model de catálogo já expõe o post nos
   três idiomas, ADR 0042) + `seo_url` `blog_id=N`.
3. **Keywords**: `resolveKeyword` saneia o slug WP para `[a-z0-9-]`
   (fallback `post-<wp_id>`); colisões cross-entidade (slug já ocupado
   por page/etc.) viram sufixo `-2`…`-10` idempotente. Nunca sobrescreve.
4. **Idempotência por família de stem** (sem mudança de schema): antes
   de importar, `alreadyImportedStem` casa `[stem]` ou `[stem]-N` sob
   `key='blog_id=%'` → skip. Sem isso, re-runs gerariam `-3`, `-4`…
   (bug capturado pela bateria F7-03). O relatório conta erros,
   pulados, importados e importados-com-sufixo separadamente.
5. **Imagens**: `downloadImage` com cap 5 MB, verificação real via
   `getimagesizefromstring` (canary anti-MIME-false), nome seguro
   (`preg_replace` da base do slug), gravadas em
   `<catalog>/view/image/blog/<safe>.<ext>` com guard `index.html`
   (anti-listing, ADR 0029). Falha de imagem NÃO aborta o post — cai
   para placeholder no front.
6. **Conteúdo**: `content.rendered` WP vai CRU para o banco (HTML com
   classes `wp-block-*`). Decisão: o front usa o `entry-body` neutro de
   F7-01 (ADR 0042); polimento visual pós-import fica fora do escopo.
   Entidades (`&amp;`, `&nbsp;`, curly quotes) e tags são normalizadas
   só nos campos derivados: `title`/`meta_description` passam por
   `clean()` (decode+strip_tags+trim) — título legível e meta ≤255.
7. **ACL**: o middleware `startup/permission` valida a **rota atual** —
   o importador precisa do grant próprio `blog/import`
   (`upgrade_f7_03_blog_import.sql`, idempotente via `JSON_SEARCH`,
   ADR 0037 §migrações). O controller ADICIONA `hasPermission('modify',
   'blog/blog')` por defesa em profundidade (acesso à operação destrutivo-
   criativa exige modify no módulo). Botão "Importar do WordPress" na
   lista do blog só aparece com modify.
8. **Mapa 301**: no `run` o model grava
   `system/storage/cache/wp-301-map.txt` (par
   `slug-antigo URL-nova-absoluta`, base = `public_url` do form, default
   derivada de `HTTP_SERVER` do admin `…/admin/` → `…/catalog/`).
   Aplicável nos 2 cenários (§301). É artefato de migração, não
   config viva — fica em storage/cache como qualquer outro auxiliar.
9. **Erros**: endpoint inacessível/404/timeout → erro amigável no form
   e zero escrita (contadores permanecem). Timeouts cURL
   connect=5s / total=20s, User-Agent identificável.
10. **Entulho**: `catalog/model/feed/news.php` removido fisicamente
    (zero referências no repo; home já usa `model/blog/blog`, ADR 0043).

## Consequências

- Migração repetível a qualquer momento (WP continuará online até o
  corte), idempotente, sem risco de duplicatas nem de sobrescritas.
- Deploy cPanel: rodar `upgrade_f7_03_blog_import.sql` após F7-01;
  copiar código; botão na lista do blog libera o acesso.
- Sessão humana roda `run` por página (`per_page=50`, 2 execuções
  cobrem os 66 atuais) com os olhos no relatório por linha.

## §301 — snippets de aplicação do mapa

Ver `docs/F7-03-301-snippets.md` (anexo desta entrega): cenário A
(mesmo domínio, diretivas no `.htaccess` do app novo usando o mapa com
`RewriteRule` por linha gerada) e cenário B (domínios distintos,
`Redirect 301` gerado a partir do mapa para colar no `.htaccess` do
WP antigo).

## Alternativas rejeitadas

1. Importador CLI / script one-off fora do admin: sem o gate de ACL e
   sem relatório de decisões por post — operação cega na hora mais
   sensível da migração.
2. Reescrever/massagear o HTML do WP no import (converter blocos,
   sanear atributos): risco de perda silenciosa e escopo explosivo;
   o `entry-body` neutro já produz saída digna (ADR 0042 §estética).
3. Tabela/coluna `wp_id` de rastreio dedicada: mudança de schema para
   uma ocorrência única de import — idempotência por stem-family no
   `seo_url` cobre o mesmo caso sem schema novo (§4).
4. Grant separado por sub-rota sem SQL novo (apenas gate no
   controller): inviável — o middleware valida a rota corrente e
   redirecionaria para `error/permission` antes do controller rodar.

## Referências

- ADR 0042 §blog; ADR 0043 §renomeação; ADR 0037 §1.1/§migrações;
  ADR 0029 (guards); ADR 0030 (validação PR-cleanup: coalesce).
- Bateria de autoridade: `tools/e2e/e2e_f703.sh` (10 checks).
- Endpoint REST do WP: <https://developer.wordpress.org/rest-api/reference/posts/>
