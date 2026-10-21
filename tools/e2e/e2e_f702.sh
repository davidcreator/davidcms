#!/usr/bin/env bash
# ════════════════════════════════════════════════════════════════
# e2e_f702.sh — Blog interno: frente pública + home + sitemap
# (F7-02, ADR 0043). Seeds via SQL (rapidez) — CRUD admin já coberto em f701.
#
# Checks:
#   1 wizard + upgrades ⇒ router com blog no live + tabelas prontas
#   2 /blog vazio 200 c/ no_results; home SEM seção #news (degradação)
#   3 seed 2 public + 1 rascunho: /blog lista 2, sem rascunho, ordem DESC
#   4 keyword pública resolve post page (título+conteúdo); hrefs da lista
#     reescritos p/ keyword (rewrite reverso do router)
#   5 rota gramatical info|2 estável 200 + meta description renderizada
#   6 rascunho ⇒ 404 honesto (info|3 e keyword inexistente)
#   7 home Latest News mostra posts INTERNOS, link sem target=_blank,
#     seção #news presente agora
#   8 sitemap.xml: XML 200, alias /blog + keyword do post + lastmod
#   9 fontes: home SEM feed/news nem davidcreator.com, partial sem
#     target=_blank, router alias + SEO_ROUTES blog_id já (de F7-01)
#  10 guards index.html + idiomas ×3 + php -l nos novos voando
# ════════════════════════════════════════════════════════════════
set -u
B="${B:-http://127.0.0.1:8090/davidcms/dev}"
C="$B/catalog"
FUI="${FUI:-/var/www/html/davidcms/dev}"
MIRROR="${MIRROR:-/home/user/davidcms/dev}"
PASS=0; FAIL=0
ck(){ if [ "${2:-0}" = "0" ]; then PASS=$((PASS+1)); echo "$1 pass — $3"; else FAIL=$((FAIL+1)); echo "$1 FAIL — $3"; fi }
code(){ curl -s -o /dev/null -w "%{http_code}" "$1"; }
q(){ sudo mysql -N --default-character-set=utf8mb4 -e "$1"; }

echo '══ setup: estado virgem ══'
sudo mysql -e "DROP DATABASE IF EXISTS reamur_test; CREATE DATABASE reamur_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
rm -f $FUI/catalog/config.php $FUI/admin/config.php $FUI/system/storage/install.lock
JW=/tmp/f702w.jar; rm -f $JW

echo '══ 1 — wizard + upgrades ⇒ blog pronto na frente pública ══'
curl -s -c $JW -b $JW "$B/install/index.php?route=install/step_1" -o /dev/null
curl -s -c $JW -b $JW -X POST "$B/install/index.php?route=install/step_1" -o /dev/null
curl -s -c $JW -b $JW -X POST "$B/install/index.php?route=install/step_2" -o /dev/null
curl -s -c $JW -b $JW -X POST "$B/install/index.php?route=install/step_3" \
  --data-urlencode "db_driver=mysqli" --data-urlencode "db_hostname=127.0.0.1" --data-urlencode "db_port=3306" \
  --data-urlencode "db_username=reamur" --data-urlencode "db_password=reamur123" --data-urlencode "db_database=reamur_test" \
  --data-urlencode "db_prefix=rms_" --data-urlencode "http_server=" \
  --data-urlencode "username=admin" --data-urlencode "firstname=David" --data-urlencode "lastname=Almeida" \
  --data-urlencode "email=david@example.com" --data-urlencode "password=SenhaForte!2026" --data-urlencode "confirm=SenhaForte!2026" \
  -D /tmp/f702_i.hdr -o /dev/null
for u in "$MIRROR"/install/sql/upgrade_*.sql; do [ -f "$u" ] && sudo mysql reamur_test < "$u"; done
sudo rm -f $FUI/system/storage/cache/cache.setting.config
RC=0
[ "$(q "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='reamur_test' AND table_name='rms_blog_post'")" = "1" ] || RC=1
grep -q "'blog'      => 'blog/blog'" $FUI/catalog/controller/startup/router.php || RC=1
grep -q "blog_id" $FUI/catalog/controller/startup/router.php || RC=1
[ "$(code "$C/blog")" = "200" ] || { RC=1; echo "  /blog não 200"; }
ck 1 $RC "wizard-router-blog"

echo '══ 2 — /blog vazio 200 c/ mensagem; home SEM #news ══'
RC=0
curl -s "$C/blog" -o /tmp/f702_list0.html
grep -qiE "No posts published yet|Ainda não há posts|no hay entradas publicadas" /tmp/f702_list0.html || RC=1
curl -sL "$C/" -o /tmp/f702_home0.html
grep -q 'id="news"' /tmp/f702_home0.html && { RC=1; echo "  #news presente sem posts (deveria ocultar)"; }
ck 2 $RC "vazio-degradacao"

echo '══ 3 — seed: 2 públicos + 1 rascunho; /blog lista ordem DESC ══'
q "INSERT INTO reamur_test.rms_blog_post (image,status,date_published,date_added,date_modified) VALUES
   ('',1,'2026-10-21 10:00:00',NOW(),NOW()),
   ('view/image/project/hero.jpg',1,'2026-10-21 20:00:00',NOW(),NOW()),
   ('',0,'2026-10-23 09:00:00',NOW(),NOW())"
q "INSERT INTO reamur_test.rms_blog_post_description (post_id,language_id,title,description,meta_title,meta_description) VALUES
   (1,1,'Primeiro post antigo','<p>Conteúdo do primeiro post antigo.</p>','Meta title 1','Meta descricao do post 1'),
   (1,2,'Primer post antiguo','<p>Contenido ES post 1.</p>','',''),
   (1,3,'Meu primeiro post antigo','<p>Conteúdo PT post 1.</p>','',''),
   (2,1,'Post mais novo com resumo longo','<p>Este texto de resumo precisa ser bem longo mesmo, passando dos cento e sessenta caracteres para a bateria comprovar que o excerpt corta com reticências sem quebrar tags HTML nem entidades do meio.</p>','',''),
   (3,1,'Rascunho invisível','<p>Não pode aparecer.</p>','','')"
q "INSERT INTO reamur_test.rms_seo_url (\`key\`,\`value\`,language_id,sort_order) VALUES
   ('blog_id=1','meu-primeiro-post',1,0),
   ('blog_id=1','mi-primer-post',2,0),
   ('blog_id=1','meu-primeiro-post-antigo',3,0)"
RC=0
curl -s "$C/blog" -o /tmp/f702_list1.html
grep -q "Primeiro post antigo" /tmp/f702_list1.html || { RC=1; echo "  post 1 ausente"; }
grep -q "Post mais novo com resumo longo" /tmp/f702_list1.html || { RC=1; echo "  post 2 ausente"; }
grep -q "Rascunho invisível" /tmp/f702_list1.html && { RC=1; echo "  rascunho VAZOU na lista"; }
NOVO=$(grep -bo "Post mais novo" /tmp/f702_list1.html | head -1 | cut -d: -f1)
VELHO=$(grep -bo "Primeiro post antigo" /tmp/f702_list1.html | head -1 | cut -d: -f1)
[ "${NOVO:-9}" -lt "${VELHO:-0}" ] || { RC=1; echo "  ordem DESC quebrada ($NOVO >= $VELHO)"; }
grep -q "…" /tmp/f702_list1.html || { RC=1; echo "  excerpt sem reticências"; }
ck 3 $RC "lista-ordem-seed"

echo '══ 4 — keyword pública resolve; hrefs limpos na lista ══'
RC=0
H=$(code "$C/meu-primeiro-post"); [ "$H" = "200" ] || { RC=1; echo "  /meu-primeiro-post = $H"; }
curl -s "$C/meu-primeiro-post" -o /tmp/f702_post.html
grep -q "Conteúdo do primeiro post antigo" /tmp/f702_post.html || RC=1
grep -q "Primeiro post antigo" /tmp/f702_post.html || RC=1
grep -q 'catalog/meu-primeiro-post"' /tmp/f702_list1.html || { RC=1; echo "  href limpo ausente na lista"; }
grep -o 'href="[^"]*blog/blog.info|2[^"]*"' /tmp/f702_list1.html | grep -qF "info|" || RC=1   # sem keyword → não-limpo estável
grep -q 'src="http://127.0.0.1:8090/davidcms/dev/catalog/view/image/project/hero.jpg"' /tmp/f702_list1.html || grep -q 'src="http://127.0.0.1:8090/davidcms/dev/catalog/view/image/project/hero.jpg\?' /tmp/f702_list1.html || { RC=1; echo "  imagem não hidratada como absoluta"; }
ck 4 $RC "keyword-publica-rewrite"

echo '══ 5 — rota gramatical estável + meta description ══'
RC=0
H=$(code "$C/index.php?route=blog/blog.info|2"); [ "$H" = "200" ] || { RC=1; echo "  info|2 = $H"; }
curl -s "$C/meu-primeiro-post" | grep -q 'name="description" content="Meta descricao do post 1"' || { RC=1; echo "  meta description ausente"; }
curl -s "$C/meu-primeiro-post" | grep -qi "<title>Meta title 1" || { RC=1; echo "  meta title ausente"; }
ck 5 $RC "gramatical-e-meta"

echo '══ 6 — rascunho ⇒ 404 honesto (id+keyword inexistente) ══'
RC=0
H=$(code "$C/index.php?route=blog/blog.info|3"); [ "$H" = "404" ] || { RC=1; echo "  draft info|3 = $H (esperado 404)"; }
H=$(code "$C/keyword-que-nao-existe-blog"); [ "$H" = "404" ] || { RC=1; echo "  keyword fantasma = $H"; }
ck 6 $RC "rascunho-404"

echo '══ 7 — home Latest News: posts INTERNOS, sem target=_blank ══'
RC=0
curl -sL "$C/" -o /tmp/f702_home1.html
grep -q 'id="news"' /tmp/f702_home1.html || { RC=1; echo "  seção #news ausente com posts"; }
grep -q "Post mais novo com resumo longo" /tmp/f702_home1.html || { RC=1; echo "  post interno ausente na home"; }
grep -q "Primeiro post antigo" /tmp/f702_home1.html || RC=1
grep -q "Rascunho invisível" /tmp/f702_home1.html && { RC=1; echo "  rascunho vazou na home"; }
grep -o '<a class="news-cta"[^>]*>' /tmp/f702_home1.html | grep -q 'target="_blank"' && { RC=1; echo "  link interno com target=_blank"; }
grep -q "davidcreator.com ↗" /tmp/f702_home1.html && { RC=1; echo "  assinatura WP externa residual"; }
ck 7 $RC "home-latest-news-interna"

echo '══ 8 — sitemap.xml: XML, /blog, keyword, lastmod ══'
RC=0
curl -s -D /tmp/f702_sm.hdr "$C/sitemap.xml" -o /tmp/f702_sm.xml
grep -qi "content-type: application/xml" /tmp/f702_sm.hdr || { RC=1; echo "  content-type não-XML"; }
grep -q "/blog<" /tmp/f702_sm.xml || grep -qE "dev/catalog/blog</loc>" /tmp/f702_sm.xml || { RC=1; echo "  alias /blog ausente"; }
grep -q "meu-primeiro-post" /tmp/f702_sm.xml || { RC=1; echo "  keyword do post ausente"; }
grep -qE "<lastmod>$(date +%Y-%m-%d)</lastmod>" /tmp/f702_sm.xml || { RC=1; echo "  lastmod (date_modified=NOW do seed) ausente"; }
grep -q "meu-primeiro-post-antigo" /tmp/f702_sm.xml && { RC=1; echo "  keyword pt duplicou alvo (dedup por preferir en)"; }
ck 8 $RC "sitemap-com-blog"

echo '══ 9 — fontes: feed WP aposentado + partial limpa ══'
RC=0
grep -q "feed/news" $FUI/catalog/controller/common/home.php && { RC=1; echo "  home ainda carrega feed/news"; }
grep -q "blog/blog" $FUI/catalog/controller/common/home.php || { RC=1; echo "  home não hidrata blog/blog"; }
grep -q 'target="_blank"' $FUI/catalog/view/template/partial/news.twig && { RC=1; echo "  partial com target=_blank"; }
grep -q "news_blog_href" $FUI/catalog/view/template/partial/news.twig || RC=1
grep -q "blog_id" $FUI/catalog/controller/common/sitemap.php || RC=1
grep -q "absoluteImage" $MIRROR/catalog/controller/blog/blog.php || { RC=1; echo "  hidratação imagem ausente"; }
grep -q "text_news_visit_blog" $FUI/catalog/language/en-us/common/home.php || RC=1
ck 9 $RC "fontes-sem-feed-externo"

echo '══ 10 — guards + idiomas + lint nos arquivos novos ══'
RC=0
for d in catalog/controller/blog catalog/model/blog catalog/view/template/blog catalog/language/en-us/blog catalog/language/pt-br/blog catalog/language/es-eu/blog; do
  [ -f "$MIRROR/$d/index.html" ] || { RC=1; echo "  guard faltando: $d"; }
done
for l in en-us pt-br es-eu; do
  [ -f "$MIRROR/catalog/language/$l/blog/blog.php" ] || { RC=1; echo "  idioma faltando: $l"; }
done
for f in catalog/controller/blog/blog.php catalog/model/blog/blog.php catalog/controller/common/home.php catalog/controller/common/sitemap.php; do
  php -l "$MIRROR/$f" >/dev/null 2>&1 || { RC=1; echo "  php -l falhou: $f"; }
done
for t in catalog/view/template/blog/blog_list.twig catalog/view/template/blog/blog_info.twig catalog/view/template/partial/news.twig; do
  [ -f "$MIRROR/$t" ] || { RC=1; echo "  twig faltando: $t"; }
done
ck 10 $RC "guards-idiomas-lint"

echo
echo "════════════════ summary: $PASS pass / $FAIL fail ════════════════"
[ "$FAIL" = "0" ]
