#!/usr/bin/env bash
# ════════════════════════════════════════════════════════════════════
# e2e_f305.sh — seção "Latest News" da home — F7-02 (ADR 0043)
#
# REESCRITA em F7-02: a bateria original (F3-05, ADR 0023) validava o
# feed WP EXTERNO (crawl + cache + CTA externo) — contrato REVOGADO.
# A seção agora lê o blog INTERNO (posts publicados, cronológico DESC,
# links internos). Numeração e régua histórica preservadas.
#
# Checks:
#   1 home 200 → seção #news presente com kicker real (com posts seed)
#   2 conteúdo REAL do banco aparece: títulos, data e excerpt dos seeds
#     públicos; rascunho NÃO aparece
#   3 metadados: fonte '· Blog', CTA interno SEM target=_blank nem
#     rel=noopener, href interno resolve para post page (anti-link-externo)
#   4 layout do tema: grid-3 + news-card + zero style= na seção
#   5 determinismo de leitura: 2 hits consecutivos → mesmo payload de
#     news; storage SEM cache.news_feed (não há mais crawl nem cache)
#   6 degradação silenciosa (contrato ADR 0023 preservado): 0 posts
#     publicados → home ainda 200, seção se oculta, resto da home íntegro
#   7 i18n ×3: kicker traduzido pt/es/en; título do post coalesce (EN)
#   8 sem side-effects: storage sem artefatos de feed/rss; banco de
#     conteúdo idêntico ao pré-seção (posts blog intactos)
#   9 payload razoável: home com news < 15 KB a mais que home sem news
#  10 ponte: CTA da 1ª news → post page interna 200 com título do post
# ════════════════════════════════════════════════════════════════════
set -u
B="${BASE:-http://127.0.0.1:8090/davidcms/dev}"
C="$B/catalog"
FUI="${FUI:-/var/www/html/davidcms/dev}"
MIRROR="${MIRROR:-/home/user/davidcms/dev}"
CACHE_FILE="$FUI/system/storage/cache/cache.news_feed"
PASS=0; FAIL=0
ck() { if [ "$2" -eq 0 ]; then PASS=$((PASS+1)); echo "$1 pass — $3"; else FAIL=$((FAIL+1)); echo "$1 FAIL — $3"; fi; }
hdr() { grep -i "^HTTP" "$1" | tail -1 | grep -o "[0-9][0-9][0-9]"; }
q(){ sudo mysql -N --default-character-set=utf8mb4 -e "$1"; }

echo '══ setup: estado virgem (wizard + seed f3 + blog) ══'
sudo mysql -e "DROP DATABASE IF EXISTS reamur_test; CREATE DATABASE reamur_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
rm -f $FUI/catalog/config.php $FUI/admin/config.php $FUI/system/storage/install.lock $CACHE_FILE
JW=/tmp/f305w.jar; rm -f $JW
curl -s -c $JW -b $JW "$B/install/index.php?route=install/step_1" -o /dev/null
curl -s -c $JW -b $JW -X POST "$B/install/index.php?route=install/step_1" -o /dev/null
curl -s -c $JW -b $JW -X POST "$B/install/index.php?route=install/step_2" -o /dev/null
curl -s -c $JW -b $JW -X POST "$B/install/index.php?route=install/step_3" \
  --data-urlencode "db_driver=mysqli" --data-urlencode "db_hostname=127.0.0.1" --data-urlencode "db_port=3306" \
  --data-urlencode "db_username=reamur" --data-urlencode "db_password=reamur123" --data-urlencode "db_database=reamur_test" \
  --data-urlencode "db_prefix=rms_" --data-urlencode "http_server=" \
  --data-urlencode "username=admin" --data-urlencode "firstname=David" --data-urlencode "lastname=Almeida" \
  --data-urlencode "email=david@example.com" --data-urlencode "password=SenhaForte!2026" --data-urlencode "confirm=SenhaForte!2026" \
  -o /dev/null
for u in "$MIRROR"/install/sql/upgrade_*.sql; do [ -f "$u" ] && sudo mysql reamur_test < "$u"; done
sudo rm -f $FUI/system/storage/cache/cache.setting.config
q "INSERT INTO reamur_test.rms_blog_post (image,status,date_published,date_added,date_modified) VALUES
   ('',1,'2026-10-20 10:00:00',NOW(),NOW()),
   ('',1,'2026-10-21 09:00:00',NOW(),NOW()),
   ('',0,'2026-10-19 08:00:00',NOW(),NOW())"
q "INSERT INTO reamur_test.rms_blog_post_description (post_id,language_id,title,description,meta_title,meta_description) VALUES
   (1,1,'Notícia antiga da seção','<p>Texto da notícia antiga seedada.</p>','',''),
   (2,1,'Grande lançamento de outubro','<p>Texto de resumo do lançamento com bastante conteúdo para o excerpt aparecer direitinho na home.</p>','',''),
   (3,1,'Rascunho escondido','<p>Não pode aparecer.</p>','','')"
q "INSERT INTO reamur_test.rms_seo_url (\`key\`,\`value\`,language_id,sort_order) VALUES ('blog_id=2','lancamento-outubro',1,0)"

echo '══ 1 — home 200 + seção #news + kicker real ══'
curl -s -D /tmp/f305_a.hdr "$C/" -o /tmp/f305_a.html
OK=0
[ "$(hdr /tmp/f305_a.hdr)" = "200" ] || OK=1
grep -q 'id="news"' /tmp/f305_a.html || OK=1
grep -q 'class="kicker"' /tmp/f305_a.html || OK=1
grep -qE "From the blog|Do blog|Desde el blog" /tmp/f305_a.html || OK=1
ck 1 $OK "news-presente"

echo '══ 2 — conteúdo REAL do banco aparece (título, data, excerpt) ══'
OK=0
grep -q "Grande lançamento de outubro" /tmp/f305_a.html || OK=1
grep -q "Notícia antiga da seção" /tmp/f305_a.html || OK=1
grep -q "2026-10-21" /tmp/f305_a.html || OK=1         # date_published do post novo
grep -q "2026-10-20" /tmp/f305_a.html || OK=1         # e do antigo
grep -q "Post de blog" /tmp/f305_a.html && OK=1       # rascunho NÃO pode pingar como post publicado
grep -q "Rascunho escondido" /tmp/f305_a.html && OK=1 # rascunho ausente
ck 2 $OK "conteudo-real-banco"

echo '══ 3 — metadados: fonte Blog, CTA interno, sem target=_blank ══'
OK=0
grep -o '<p class="news-meta">[^<]*</p>' /tmp/f305_a.html | grep -q "· Blog" || OK=1
# nenhum link do bloco #news abre em nova aba nem rel externo
python3 - <<'PY' > /tmp/f305_sec.txt
import re
h=open('/tmp/f305_a.html',encoding='utf-8',errors='ignore').read()
m=re.search(r'<section[^>]+id="news".*?</section>', h, re.S)
print(m.group(0) if m else '')
PY
grep -q 'target="_blank"' /tmp/f305_sec.txt && OK=1
grep -q 'rel="noopener' /tmp/f305_sec.txt && OK=1
grep -q 'href="http://127.0.0.1:8090/davidcms/dev/catalog/lancamento-outubro"' /tmp/f305_sec.txt || OK=1
grep -q 'href="http://127.0.0.1:8090/davidcms/dev/catalog/index.php?route=blog/blog.info|1"' /tmp/f305_sec.txt || OK=1
ck 3 $OK "metadados-cta-interno"

echo '══ 4 — layout do tema: grid-3 + news-card + zero style= na seção ══'
OK=0
grep -q 'news-card' /tmp/f305_sec.txt || OK=1
grep -q 'grid-3' /tmp/f305_sec.txt || OK=1
grep -q 'style=' /tmp/f305_sec.txt && OK=1
ck 4 $OK "sem-inline"

echo '══ 5 — determinismo: 2º hit idêntico e SEM cache de crawl ══'
OK=0
curl -s "$C/" -o /tmp/f305_b.html
diff -q <(grep -o 'news-card' /tmp/f305_a.html | wc -l) <(grep -o 'news-card' /tmp/f305_b.html | wc -l) > /dev/null || OK=1
diff -q <(grep -A3 'class="news-card"' /tmp/f305_a.html) <(grep -A3 'class="news-card"' /tmp/f305_b.html) > /dev/null || OK=1
[ -e "$CACHE_FILE" ] && { OK=1; echo "  cache.news_feed ressurgiu?!"; }   # legado do feed externo não existe mais
ck 5 $OK "determinismo-sem-crawl"

echo '══ 6 — degradação silenciosa: 0 posts publicados → home ainda 200, seção oculta ══'
OK=0
q "UPDATE reamur_test.rms_blog_post SET status=0 WHERE status=1"
curl -s -D /tmp/f305_c.hdr "$C/" -o /tmp/f305_c.html
[ "$(hdr /tmp/f305_c.hdr)" = "200" ] || OK=1
grep -q 'id="news"' /tmp/f305_c.html && OK=1            # seção oculta sem posts
grep -q 'id="portfolio"' /tmp/f305_c.html || OK=1        # resto da home íntegro
q "UPDATE reamur_test.rms_blog_post SET status=1 WHERE post_id IN (1,2)"  # repõe p/ checks finais
ck 6 $OK "negativo-ok"

echo '══ 7 — i18n ×3: kicker traduzido; conteúdo coalesce (EN) ══'
curl -s "$C/?language=pt-br" -o /tmp/f305_pt.html
curl -s "$C/?language=es-eu" -o /tmp/f305_es.html
curl -s "$C/?language=en-us" -o /tmp/f305_en.html
OK=0
grep -q "Do blog" /tmp/f305_pt.html || OK=1
grep -q "Desde el blog" /tmp/f305_es.html || OK=1
grep -q "From the blog" /tmp/f305_en.html || OK=1
# título só existe em EN no seed → coalesce garante exibição nos 3 idiomas
for f in pt es en; do grep -q "Grande lançamento de outubro" /tmp/f305_$f.html || OK=1; done
ck 7 $OK "i18n-news"

echo '══ 8 — sem side-effects: storage limpo de feed/rss; banco idêntico ══'
OK=0
sudo find $FUI/system/storage -type f \( -name "*rss*" -o -name "*feed*" \) 2>/dev/null | grep -v "session/sess_" | grep -q . && OK=1
CNT=$(q "SELECT (SELECT COUNT(*) FROM reamur_test.rms_project) + (SELECT COUNT(*) FROM reamur_test.rms_tool) + (SELECT COUNT(*) FROM reamur_test.rms_testimonial)")
[ "$CNT" = "17" ] || OK=1   # 10 projetos + 4 tools + 3 depoimentos (seed F3-01) intocados pela seção
[ "$(q "SELECT COUNT(*) FROM reamur_test.rms_blog_post WHERE status=1")" = "2" ] || OK=1  # posts só vagaram de status no check 6 e voltaram
ck 8 $OK "sem-side-effects"

echo '══ 9 — payload razoável: com news < 15 KB a mais que sem news ══'
SZ_FULL=$(wc -c < /tmp/f305_en.html | tr -d ' ')
q "UPDATE reamur_test.rms_blog_post SET status=0 WHERE status=1"
curl -s "$C/?language=en-us" -o /tmp/f305_bare.html
q "UPDATE reamur_test.rms_blog_post SET status=1 WHERE post_id IN (1,2)"
SZ_BARE=$(wc -c < /tmp/f305_bare.html | tr -d ' ')
DELTA=$((SZ_FULL - SZ_BARE))
OK=0
[ "$DELTA" -lt 15360 ] || { OK=1; echo "  payload +$DELTA bytes"; }
[ "$DELTA" -gt 500 ]   || { OK=1; echo "  seção ficou pequena demais (+$DELTA)"; }
ck 9 $OK "payload-ok"

echo '══ 10 — ponte: CTA da 1ª news leva ao post page interno 200 ══'
OK=0
curl -s "$C/?language=en-us" -o /tmp/f305_d.html
HREF=$(grep -o 'href="http[^"]*catalog/lancamento-outubro"' /tmp/f305_d.html | head -1 | sed 's/href="//;s/"//')
[ -n "$HREF" ] || { OK=1; echo "  href da news não achado"; }
if [ -n "$HREF" ]; then
  H=$(curl -s -o /tmp/f305_post.html -w "%{http_code}" "$HREF")
  [ "$H" = "200" ] || { OK=1; echo "  post page → $H"; }
  grep -q "Grande lançamento de outubro" /tmp/f305_post.html || OK=1
fi
ck 10 $OK "ponte-news-post"

echo
echo "════════════════ summary: $PASS pass / $FAIL fail ════════════════"
[ "$FAIL" = "0" ]
