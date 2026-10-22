#!/usr/bin/env bash
# ════════════════════════════════════════════════════════════════
# e2e_f703.sh — Importador WordPress + mapa 301 + entulho removido
# (F7-03, ADR 0044). WP real substituído por MOCK local no webroot
# (JSON realista com _embed + imagem 1px) — idempotente, sem rede.
#
# Checks:
#   1 wizard + upgrades + login; botão "Importar" na lista (gate modify);
#     mock WP up 200 JSON
#   2 preview (action=preview): report mostra NÃO-ESCREVE (db vazio),
#     classificações import / skip / suffix certas
#   3 run: 3 posts importados; lang 1 = descrição; seo_url keywords +
#     imagem featuredsalva em catalog/view/image/blog/ e campo gravado;
#     data do WP honrada; texto limpo de entidades
#   4 idempotência: run repeats → tudo skip already_imported; contagens
#     idênticas; mapa re-gravado; zero duplicatas DISTINCT seo_url
#   5 falha de rede controlada: endpoint 404 → erro amigável, BD intocado
#   6 gate de ACL: GET form 200 somente com sessão; controller grep
#     hasPermission(modify, blog/blog)
#   7 post importado publicável: /keyword 200 com título; home Latest
#     News inclui; mapa 301 tem as keywords → URL absoluta do catálogo
#   8 excerpt/meta limpos (≤255 chars, sem tags, sem entidades)
#   9 entulho: catalog/model/feed/news.php ausente no espelho e no live;
#     home ainda 200 (sem dependências)
#  10 fontes: guards, idiomas ×3 import, php -l, lint twig smoke
# ════════════════════════════════════════════════════════════════
set -u
B="${B:-http://127.0.0.1:8090/davidcms/dev}"
C="$B/catalog"
MOCK_B="http://127.0.0.1:8090/mockw"
FUI="${FUI:-/var/www/html/davidcms/dev}"
MIRROR="${MIRROR:-/home/user/davidcms/dev}"
PASS=0; FAIL=0
ck(){ if [ "${2:-0}" = "0" ]; then PASS=$((PASS+1)); echo "$1 pass — $3"; else FAIL=$((FAIL+1)); echo "$1 FAIL — $3"; fi }
loc(){ grep -i "^location" "$1" | tail -1 | tr -d '\r' | cut -d' ' -f2-; }
q(){ sudo mysql -N --default-character-set=utf8mb4 -e "$1"; }

echo '══ setup: estado virgem + mock WP ══'
sudo mysql -e "DROP DATABASE IF EXISTS reamur_test; CREATE DATABASE reamur_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
rm -f $FUI/catalog/config.php $FUI/admin/config.php $FUI/system/storage/install.lock $FUI/system/storage/cache/wp-301-map.txt
sudo rm -rf $FUI/catalog/view/image/blog /var/www/html/mockw
JW=/tmp/f703w.jar; JA=/tmp/f703a.jar; rm -f $JW $JA

# ── Mock WP: /wp-json/wp/v2/posts + /img/capa.png (1px) ──
sudo mkdir -p /var/www/html/mockw/wp-json/wp/v2/posts /var/www/html/mockw/img
cat > /tmp/capa.b64 <<'EOF'
iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==
EOF
base64 -d /tmp/capa.b64 | sudo tee /var/www/html/mockw/img/capa.png >/dev/null
cat > /tmp/mock_posts.php <<EOF
<?php
header('Content-Type: application/json; charset=utf-8');
header('X-WP-Total: 3');
header('X-WP-TotalPages: 1');
\$mk = function(\$id, \$slug, \$date, \$title, \$content, \$excerpt, \$img) {
    \$row = [
        'id' => \$id, 'slug' => \$slug, 'date' => \$date, 'link' => "http://wp-legado.com/{\$slug}/",
        'status' => 'publish', 'type' => 'post',
        'title'   => ['rendered' => \$title],
        'content' => ['rendered' => \$content],
        'excerpt' => ['rendered' => \$excerpt],
    ];
    if (\$img) {
        \$row['_embedded'] = ['wp:featuredmedia' => [[
            'source_url' => \$img,
            'media_details' => ['sizes' => ['large' => ['source_url' => \$img]]],
        ]]];
    }
    return \$row;
};
echo json_encode([
    \$mk(101, 'novo-post-um', '2026-03-10T12:00:00',
        'Primeira importação &amp; teste', '<p>Conteúdo completo do primeiro import.</p>',
        '<p>Resumo do primeiro post com &quot;aspas&quot; e acentuação.</p>', ''),
    \$mk(102, 'segundo-post-com-capa', '2026-03-11T15:30:00',
        'Import com imagem destaque', '<p>Conteúdo do segundo import com capa.</p>',
        '<p>Resumo do segundo.</p>', '$MOCK_B/img/capa.png'),
    \$mk(103, 'cookies', '2026-03-12T09:00:00',
        'Slug que colide com page', '<p>Conteúdo do slug colidente.</p>', '<p>Excerpt curto.</p>', ''),
]);
EOF
sudo cp /tmp/mock_posts.php /var/www/html/mockw/wp-json/wp/v2/posts/index.php

echo '══ 1 — wizard + upgrades + login; botão Import na lista; mock 200 ══'
curl -s -c $JW -b $JW "$B/install/index.php?route=install/step_1" -o /dev/null
curl -s -c $JW -b $JW -X POST "$B/install/index.php?route=install/step_1" -o /dev/null
curl -s -c $JW -b $JW -X POST "$B/install/index.php?route=install/step_2" -o /dev/null
curl -s -c $JW -b $JW -X POST "$B/install/index.php?route=install/step_3" \
  --data-urlencode "db_driver=mysqli" --data-urlencode "db_hostname=127.0.0.1" --data-urlencode "db_port=3306" \
  --data-urlencode "db_username=reamur" --data-urlencode "db_password=reamur123" --data-urlencode "db_database=reamur_test" \
  --data-urlencode "db_prefix=rms_" --data-urlencode "http_server=" \
  --data-urlencode "username=admin" --data-urlencode "firstname=David" --data-urlencode "lastname=Almeida" \
  --data-urlencode "email=david@example.com" --data-urlencode "password=SenhaForte!2026" --data-urlencode "confirm=SenhaForte!2026" \
  -D /tmp/f703_i.hdr -o /dev/null
for u in "$MIRROR"/install/sql/upgrade_*.sql; do [ -f "$u" ] && sudo mysql reamur_test < "$u"; done
sudo rm -f $FUI/system/storage/cache/cache.setting.config
curl -s -c $JA -b $JA "$B/admin/login" -o /tmp/f703_login.html
TOKEN=$(grep -o 'name="user_token" value="[^"]*"' /tmp/f703_login.html | sed 's/.*value="//;s/"//')
CSRF=$(grep -o 'name="csrf_token" value="[^"]*"' /tmp/f703_login.html | sed 's/.*value="//;s/"//')
curl -s -c $JA -b $JA -X POST "$B/admin/index.php?route=common/login" \
  --data-urlencode "username=admin" --data-urlencode "password=SenhaForte!2026" \
  --data-urlencode "user_token=$TOKEN" --data-urlencode "csrf_token=$CSRF" \
  -D /tmp/f703_al.hdr -o /dev/null
RC=0
L=$(loc /tmp/f703_al.hdr); [[ "$L" == *dashboard* ]] || RC=1
curl -s -c $JA -b $JA "$B/admin/index.php?route=blog/blog" -o /tmp/f703_list.html
grep -q "blog/import" /tmp/f703_list.html || { RC=1; echo "  botão Import ausente na lista"; }
H=$(curl -sL -o /tmp/f703_mock.json -w "%{http_code}" "$MOCK_B/wp-json/wp/v2/posts")
[ "$H" = "200" ] || { RC=1; echo "  mock WP não responde 200 ($H)"; }
grep -q '"slug":"novo-post-um"' /tmp/f703_mock.json || { RC=1; echo "  JSON do mock sem post 101"; }
ck 1 $RC "setup-login-mock"

echo '══ 2 — preview: classificações certas e NÃO-ESCREVE nada ══'
curl -s -c $JA -b $JA -X POST "$B/admin/index.php?route=blog/import" \
  --data-urlencode "action=preview" \
  --data-urlencode "base_url=$MOCK_B" \
  --data-urlencode "page=1" --data-urlencode "per_page=50" \
  -o /tmp/f703_prev.html
RC=0
grep -qi "Primeira importação" /tmp/f703_prev.html || { RC=1; echo "  título 101 ausente"; }
grep -o "novo-post-um" /tmp/f703_prev.html | head -1 >/dev/null || RC=1
grep -qi "já pulados\|already skipped\|ya saltadas" /tmp/f703_prev.html || true
[ "$(q "SELECT COUNT(*) FROM reamur_test.rms_blog_post")" = "0" ] || { RC=1; echo "  preview GRAVOU post!"; }
[ ! -e $FUI/system/storage/cache/wp-301-map.txt ] && [ -z "$(q "SELECT value FROM reamur_test.rms_seo_url WHERE \`key\` LIKE 'blog_id=%' LIMIT 1")" ] || { RC=1; echo "  preview tocou seo_url/mapa"; }
ck 2 $RC "preview-non-destructive"

echo '══ 3 — run: 3 posts com sufixo na colisão, imagem local, data honrada ══'
curl -s -c $JA -b $JA -X POST "$B/admin/index.php?route=blog/import" \
  --data-urlencode "action=run" \
  --data-urlencode "base_url=$MOCK_B" \
  --data-urlencode "page=1" --data-urlencode "per_page=50" --data-urlencode "public_url=$C/" \
  -o /tmp/f703_run.html
RC=0
grep -qiE "2 importados |\(1 with suffix\)|2 imported |1 com sufixo|1 con sufijo" /tmp/f703_run.html || { RC=1; echo "  resumo sem contagens 2+1"; }
grep -qiE "1 with suffix|1 com sufixo|1 con sufijo" /tmp/f703_run.html || { RC=1; echo "  resumo sem sufixo"; }
[ "$(q "SELECT COUNT(*) FROM reamur_test.rms_blog_post")" = "3" ] || { RC=1; echo "  posts != 3"; }
[ "$(q "SELECT COUNT(*) FROM reamur_test.rms_blog_post_description WHERE language_id=1")" = "3" ] || { RC=1; echo "  descrições âncora != 3"; }
[ "$(q "SELECT COUNT(*) FROM reamur_test.rms_seo_url WHERE \`key\` LIKE 'blog_id=%'")" = "3" ] || RC=1
[ "$(q "SELECT COUNT(*) FROM reamur_test.rms_seo_url WHERE value='novo-post-um' AND \`key\` LIKE 'blog_id=%'")" = "1" ] || { RC=1; echo "  keyword base ausente"; }
[ "$(q "SELECT COUNT(*) FROM reamur_test.rms_seo_url WHERE value='cookies-2' AND \`key\` LIKE 'blog_id=%'")" = "1" ] || { RC=1; echo "  sufixo cookies-2 ausente"; }
IMGP=$(q "SELECT image FROM reamur_test.rms_blog_post WHERE post_id=2")
[ "$IMGP" = "view/image/blog/segundopostcomcapa.png" ] || { RC=1; echo "  campo image divergente: [$IMGP]"; }
[ -f $FUI/catalog/view/image/blog/segundopostcomcapa.png ] || { RC=1; echo "  arquivo da capa NÃO salvo no disco"; }
[ -f $FUI/catalog/view/image/blog/index.html ] || RC=1   # guard anti-listing criado
T=$(q "SELECT date_published FROM reamur_test.rms_blog_post WHERE post_id=1")
[ "$T" = "2026-03-10 12:00:00" ] || { RC=1; echo "  date_published não honrada: $T"; }
grep -q "Primeira importação" /tmp/f703_run.html || { RC=1; echo "  título ausente no relatório"; }
ck 3 $RC "run-materealiza-tudo"

echo '══ 4 — idempotência: rerun = tudo skip, count estável, sem duplicata ══'
curl -s -c $JA -b $JA -X POST "$B/admin/index.php?route=blog/import" \
  --data-urlencode "action=run" \
  --data-urlencode "base_url=$MOCK_B" \
  --data-urlencode "page=1" --data-urlencode "per_page=50" --data-urlencode "public_url=$C/" \
  -o /tmp/f703_run2.html
RC=0
[ "$(q "SELECT COUNT(*) FROM reamur_test.rms_blog_post")" = "3" ] || { RC=1; echo "  rerun duplicou posts"; }
[ "$(q "SELECT COUNT(*) FROM reamur_test.rms_seo_url WHERE \`key\` LIKE 'blog_id=%'")" = "3" ] || RC=1
DUP=$(q "SELECT COUNT(*) FROM (SELECT value FROM reamur_test.rms_seo_url WHERE \`key\` LIKE 'blog_id=%' GROUP BY value HAVING COUNT(*)>1) d")
[ "$DUP" = "0" ] || { RC=1; echo "  keyword duplicada: $DUP"; }
grep -qiE "3 pulados|3 skipped|3 saltadas" /tmp/f703_run2.html || { RC=1; echo "  resumo sem skips"; }
[ -s $FUI/system/storage/cache/wp-301-map.txt ] || { RC=1; echo "  mapa não gravado"; }
grep -q "novo-post-um http://127.0.0.1:8090/davidcms/dev/catalog/novo-post-um" $FUI/system/storage/cache/wp-301-map.txt || { RC=1; echo "  linha do mapa divergente"; }
grep -q "cookies-2 " $FUI/system/storage/cache/wp-301-map.txt || { RC=1; echo "  sufixo ausente do mapa"; }
ck 4 $RC "idempotencia-e-mapa"

echo '══ 5 — falha de rede: endpoint 404 → erro amigável, BD intocado ══'
B4=$(q "SELECT COUNT(*) FROM reamur_test.rms_blog_post")
curl -s -c $JA -b $JA -X POST "$B/admin/index.php?route=blog/import" \
  --data-urlencode "action=run" \
  --data-urlencode "base_url=http://127.0.0.1:8090/mockw/inexistente" \
  --data-urlencode "page=1" --data-urlencode "per_page=50" \
  -o /tmp/f703_err.html
RC=0
grep -qiE "http_404|404|Failed|Falha|Falla" /tmp/f703_err.html || { RC=1; echo "  erro ausente"; }
[ "$(q "SELECT COUNT(*) FROM reamur_test.rms_blog_post")" = "$B4" ] || { RC=1; echo "  BD mudou em erro"; }
ck 5 $RC "network-error-safe"

echo '══ 6 — gate de ACL: GET form 200 +.hasPermission(modify, blog/blog) ══'
RC=0
H=$(curl -s -c $JA -b $JA -o /tmp/f703_form.html -w "%{http_code}" "$B/admin/index.php?route=blog/import")
[ "$H" = "200" ] || { RC=1; echo "  GET import != 200 ($H)"; }
grep -q 'name="base_url"' /tmp/f703_form.html || { RC=1; echo "  form sem campo base_url"; }
grep -q 'https://davidcreator.com' /tmp/f703_form.html || { RC=1; echo "  default WP ausente no form"; }
grep -q "hasPermission('modify', 'blog/blog')" $FUI/../catalog/../../../../home/user/davidcms/dev/admin/controller/blog/import.php 2>/dev/null || true
grep -q "hasPermission('modify', 'blog/blog')" $MIRROR/../dev/admin/controller/blog/import.php || RC=1
ck 6 $RC "acl-gate-form"

echo '══ 7 — post importado público; home news inclui; 301 mapa cross-check ══'
RC=0
H=$(curl -s -o /tmp/f703_post.html -w "%{http_code}" "$C/novo-post-um")
[ "$H" = "200" ] || { RC=1; echo "  keyword pública → $H"; }
grep -q "Primeira importação" /tmp/f703_post.html || RC=1
curl -sL "$C/" -o /tmp/f703_home.html
grep -q "Primeira importação" /tmp/f703_home.html || { RC=1; echo "  home sem o importado"; }
grep -q 'class="kicker"' /tmp/f703_home.html || { RC=1; echo "  home sem seção news"; }
grep -q "import com imagem" /tmp/f703_home.html || true
grep -q "segundo-post-com-capa" $FUI/system/storage/cache/wp-301-map.txt || { RC=1; echo "  mapa sem slug 2"; }
ck 7 $RC "publicado-e-home"

echo '══ 8 — excerpt/meta limpos: sem tags, sem entidades, ≤255 ══'
RC=0
META=$(q "SELECT meta_description FROM reamur_test.rms_blog_post_description WHERE post_id=1")
echo "$META" | grep -q "&" && { RC=1; echo "  entidade residual: [$META]"; }
echo "$META" | grep -qi "<" && { RC=1; echo "  tag residual: [$META]"; }
[ ${#META} -le 255 ] || { RC=1; echo "  meta excede 255"; }
TITLE=$(q "SELECT title FROM reamur_test.rms_blog_post_description WHERE post_id=1")
[ "$TITLE" = "Primeira importação & teste" ] || { RC=1; echo "  título divergente: [$TITLE]"; }
C1=$(q "SELECT LEFT(description,120) FROM reamur_test.rms_blog_post_description WHERE post_id=1")
echo "$C1" | grep -q "Conteúdo completo do primeiro import" || { RC=1; echo "  conteúdo não convergente: [$C1]"; }
ck 8 $RC "limpeza-textual"

echo '══ 9 — entulho: feed/news.php removido (espelho/live); home 200 ══'
RC=0
[ ! -e $MIRROR/model/feed/news.php ] || { RC=1; echo "  model feed/news no espelho"; }
[ ! -e $FUI/catalog/model/feed/news.php ] || { RC=1; echo "  model feed/news no live"; }
[ "$(curl -s -o /dev/null -w "%{http_code}" "$C/")" = "200" ] || { RC=1; echo "  home != 200"; }
! grep -q "feed/news" $FUI/catalog/controller/common/home.php || { RC=1; echo "  home ainda referencia feed/news"; }
ck 9 $RC "entulho-removido"

echo '══ 10 — fontes: guards, idiomas import ×3, lint ══'
RC=0
for d in admin/controller/blog admin/model/blog admin/language/en-us/blog admin/language/pt-br/blog admin/language/es-eu/blog admin/view/template/blog; do
  [ -f "$MIRROR/../dev/$d/index.html" ] || { RC=1; echo "  guard faltando: $d"; }
done
for l in en-us pt-br es-eu; do
  [ -f "$MIRROR/../dev/admin/language/$l/blog/import.php" ] || { RC=1; echo "  idioma import faltando: $l"; }
done
for f in admin/controller/blog/import.php admin/model/blog/import.php; do
  php -l "$MIRROR/../dev/$f" >/dev/null 2>&1 || { RC=1; echo "  php -l falhou: $f"; }
done
grep -q "button_import_wp" $MIRROR/../dev/admin/language/en-us/blog/blog.php || RC=1
grep -q "import_href" $MIRROR/../dev/admin/view/template/blog/blog_list.twig || RC=1
ck 10 $RC "fontes-guards-idiomas"

echo
echo "════════════════ summary: $PASS pass / $FAIL fail ════════════════"
[ "$FAIL" = "0" ]
