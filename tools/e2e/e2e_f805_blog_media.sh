#!/usr/bin/env bash
# ════════════════════════════════════════════════════════════════
# e2e_f805_blog_media.sh — biblioteca de imagens de capa do Blog (F8-05, ADR 0049)
# ATENÇÃO: reinstala o site e recria o banco descartável reamur_test.
#
# Checks:
#   1 login + form: contrato name/image, modal, JS e CSRF
#   2 ACL/CSRF: endpoint mutável sem token rejeitado; path traversal bloqueado
#   3 pasta + MediaUpload: imagem válida salva somente em catalog/view/image/blog/
#   4 busca/paginação server-side: 25 arquivos → páginas 24+1; busca filtra
#   5 exclusão segura: pastas não vazias e imagem referenciada são bloqueadas
#   6 CRUD Blog exige CSRF; após remover a referência, exclusão de imagem funciona
#   7 pasta vazia é removível; arquivos de teste limpos ao final
# ════════════════════════════════════════════════════════════════
set -u
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
B="${B:-http://127.0.0.1:8090/davidcms/dev}"
FUI="${FUI:-/var/www/html/davidcms/dev}"
MIRROR="${MIRROR:-$ROOT/dev}"
PASS=0; FAIL=0
BLOG_ROOT="$FUI/catalog/view/image/blog"
BLOG_MEDIA="$BLOG_ROOT/f805-e2e"
BLOG_ROOT_CREATED=0
[ -d "$BLOG_ROOT" ] || BLOG_ROOT_CREATED=1
PNG=/tmp/f805-blog-cover.png
INVALID=/tmp/f805-blog-invalid.txt
JAR=/tmp/f805-blog-media.jar

ck(){ if [ "${2:-0}" = "0" ]; then PASS=$((PASS+1)); echo "$1 pass — $3"; else FAIL=$((FAIL+1)); echo "$1 FAIL — $3"; fi }
loc(){ grep -i "^location" "$1" | tail -1 | tr -d '\r' | cut -d' ' -f2-; }
q(){ sudo mysql -N --default-character-set=utf8mb4 -e "$1"; }
jget(){ php -r '$d=json_decode((string)file_get_contents($argv[1]), true); $v=$d[$argv[2]] ?? ""; if (is_bool($v)) { echo $v ? "1" : "0"; } elseif (is_scalar($v)) { echo $v; }' "$1" "$2"; }
jcount(){ php -r '$d=json_decode((string)file_get_contents($argv[1]), true); echo count($d[$argv[2]] ?? []);' "$1" "$2"; }
cleanup(){
  rm -rf "$BLOG_MEDIA"
  if [ "$BLOG_ROOT_CREATED" = "1" ]; then rm -f "$BLOG_ROOT/index.html"; rmdir "$BLOG_ROOT" 2>/dev/null || true; fi
  rm -f "$PNG" "$INVALID" "$JAR" /tmp/f805_*.json /tmp/f805_*.html /tmp/f805_*.hdr
}
trap cleanup EXIT

echo '══ setup: ambiente descartável reamur_test ══'
rm -rf "$BLOG_MEDIA"
sudo mysql -e "DROP DATABASE IF EXISTS reamur_test; CREATE DATABASE reamur_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
rm -f "$FUI/catalog/config.php" "$FUI/admin/config.php" "$FUI/system/storage/install.lock"
curl -s -c "$JAR" -b "$JAR" "$B/install/index.php?route=install/step_1" -o /dev/null
curl -s -c "$JAR" -b "$JAR" -X POST "$B/install/index.php?route=install/step_1" -o /dev/null
curl -s -c "$JAR" -b "$JAR" -X POST "$B/install/index.php?route=install/step_2" -o /dev/null
curl -s -c "$JAR" -b "$JAR" -X POST "$B/install/index.php?route=install/step_3" \
  --data-urlencode "db_driver=mysqli" --data-urlencode "db_hostname=127.0.0.1" --data-urlencode "db_port=3306" \
  --data-urlencode "db_username=reamur" --data-urlencode "db_password=reamur123" --data-urlencode "db_database=reamur_test" \
  --data-urlencode "db_prefix=rms_" --data-urlencode "http_server=" \
  --data-urlencode "username=admin" --data-urlencode "firstname=David" --data-urlencode "lastname=Almeida" \
  --data-urlencode "email=david@example.com" --data-urlencode "password=SenhaForte!2026" --data-urlencode "confirm=SenhaForte!2026" \
  -D /tmp/f805_i.hdr -o /dev/null
for u in "$MIRROR"/install/sql/upgrade_*.sql; do [ -f "$u" ] && sudo mysql reamur_test < "$u"; done
sudo rm -f "$FUI/system/storage/cache/cache.setting.config"

curl -s -c "$JAR" -b "$JAR" "$B/admin/login" -o /tmp/f805_login.html
TOKEN=$(grep -o 'name="user_token" value="[^"]*"' /tmp/f805_login.html | sed 's/.*value="//;s/"//')
LOGIN_CSRF=$(grep -o 'name="csrf_token" value="[^"]*"' /tmp/f805_login.html | sed 's/.*value="//;s/"//')
curl -s -c "$JAR" -b "$JAR" -X POST "$B/admin/index.php?route=common/login" \
  --data-urlencode "username=admin" --data-urlencode "password=SenhaForte!2026" \
  --data-urlencode "user_token=$TOKEN" --data-urlencode "csrf_token=$LOGIN_CSRF" \
  -D /tmp/f805_login.hdr -o /dev/null
curl -s -c "$JAR" -b "$JAR" "$B/admin/index.php?route=blog/blog.add" -o /tmp/f805_form.html
BLOG_CSRF=$(grep -o 'name="csrf_token" value="[^"]*"' /tmp/f805_form.html | sed 's/.*value="//;s/"//')

RC=0
[ -n "$BLOG_CSRF" ] || RC=1
grep -q 'name="image" id="input-image"' /tmp/f805_form.html || RC=1
grep -q 'id="blogMediaModal"' /tmp/f805_form.html || RC=1
grep -q 'blog-media.js' /tmp/f805_form.html || RC=1
ck 1 "$RC" "login-form-contrato-csrf-modal"

echo '══ 2 — CSRF e confinamento de caminhos ══'
MEDIA_URL="$B/admin/index.php?route=blog/blog.mediaFolder"
curl -s -c "$JAR" -b "$JAR" -X POST "$MEDIA_URL" \
  --data-urlencode "folder=" --data-urlencode "name=f805-no-token" -o /tmp/f805_no_token.json
RC=0
[ "$(jget /tmp/f805_no_token.json success)" = "0" ] || RC=1
[ ! -e "$FUI/catalog/view/image/blog/f805-no-token" ] || RC=1
curl -s -c "$JAR" -b "$JAR" -X POST "$MEDIA_URL" \
  --data-urlencode "csrf_token=$BLOG_CSRF" --data-urlencode "folder=../" --data-urlencode "name=escaped" \
  -o /tmp/f805_traversal.json
[ "$(jget /tmp/f805_traversal.json success)" = "0" ] || RC=1
[ ! -e "$FUI/catalog/view/image/escaped" ] || RC=1
ck 2 "$RC" "post-sem-token-e-traversal-bloqueados"

echo '══ 3 — pasta + upload validado pelo MediaUpload ══'
curl -s -c "$JAR" -b "$JAR" -X POST "$MEDIA_URL" \
  --data-urlencode "csrf_token=$BLOG_CSRF" --data-urlencode "folder=" --data-urlencode "name=f805-e2e" \
  -o /tmp/f805_folder.json
RC=0
[ "$(jget /tmp/f805_folder.json success)" = "1" ] || RC=1
[ -f "$BLOG_MEDIA/index.html" ] || RC=1
printf 'not an image' > "$INVALID"
curl -s -c "$JAR" -b "$JAR" -X POST "$B/admin/index.php?route=blog/blog.mediaUpload" \
  -F "csrf_token=$BLOG_CSRF" -F "folder=f805-e2e" -F "file=@$INVALID;type=text/plain" \
  -o /tmp/f805_invalid_upload.json
[ "$(jget /tmp/f805_invalid_upload.json success)" = "0" ] || RC=1
ck 3a "$RC" "upload-rejeita-tipo-invalido"
printf '%s' 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+nmVsAAAAASUVORK5CYII=' | base64 -d > "$PNG"
RC=0
curl -s -c "$JAR" -b "$JAR" -X POST "$B/admin/index.php?route=blog/blog.mediaUpload" \
  -F "csrf_token=$BLOG_CSRF" -F "folder=f805-e2e" -F "file=@$PNG;type=image/png" \
  -o /tmp/f805_upload.json
UPLOADED_PATH=$(jget /tmp/f805_upload.json path)
[[ "$UPLOADED_PATH" == view/image/blog/f805-e2e/* ]] || RC=1
[ -f "$FUI/catalog/$UPLOADED_PATH" ] || RC=1
[ "$(jget /tmp/f805_upload.json success)" = "1" ] || RC=1
ck 3 "$RC" "upload-restrito-blog"

echo '══ 4 — busca e paginação server-side ══'
for i in $(seq -w 1 25); do cp "$FUI/catalog/$UPLOADED_PATH" "$BLOG_MEDIA/page-$i.png"; done
LIST_URL="$B/admin/index.php?route=blog/blog.mediaList"
curl -s -G -c "$JAR" -b "$JAR" "$LIST_URL" \
  --data-urlencode "folder=f805-e2e" --data-urlencode "search=page-" --data-urlencode "page=1" \
  -o /tmp/f805_page1.json
curl -s -G -c "$JAR" -b "$JAR" "$LIST_URL" \
  --data-urlencode "folder=f805-e2e" --data-urlencode "search=page-" --data-urlencode "page=2" \
  -o /tmp/f805_page2.json
curl -s -G -c "$JAR" -b "$JAR" "$LIST_URL" \
  --data-urlencode "folder=f805-e2e" --data-urlencode "search=page-02" --data-urlencode "page=1" \
  -o /tmp/f805_search.json
RC=0
[ "$(jget /tmp/f805_page1.json success)" = "1" ] || RC=1
[ "$(jget /tmp/f805_page1.json total)" = "25" ] || RC=1
[ "$(jget /tmp/f805_page1.json pages)" = "2" ] || RC=1
[ "$(jcount /tmp/f805_page1.json files)" = "24" ] || RC=1
[ "$(jcount /tmp/f805_page2.json files)" = "1" ] || RC=1
[ "$(jget /tmp/f805_search.json total)" = "1" ] || RC=1
[ "$(jget /tmp/f805_search.json success)" = "1" ] || RC=1
ck 4 "$RC" "busca-paginacao-no-servidor"

echo '══ 5 — referência em uso bloqueia exclusão ══'
curl -s -c "$JAR" -b "$JAR" -X POST "$B/admin/index.php?route=blog/blog.add" \
  --data-urlencode "csrf_token=$BLOG_CSRF" \
  --data-urlencode "blog_description[1][title]=F805 media reference" \
  --data-urlencode "blog_description[1][keyword]=f805-media-reference" \
  --data-urlencode "image=$UPLOADED_PATH" --data-urlencode "status=0" \
  -D /tmp/f805_add.hdr -o /dev/null
RC=0
[ "$(q "SELECT COUNT(*) FROM reamur_test.rms_blog_post WHERE image='$UPLOADED_PATH'")" = "1" ] || RC=1
curl -s -c "$JAR" -b "$JAR" -X POST "$B/admin/index.php?route=blog/blog.mediaDelete" \
  --data-urlencode "csrf_token=$BLOG_CSRF" --data-urlencode "type=file" --data-urlencode "path=$UPLOADED_PATH" \
  -o /tmp/f805_in_use.json
[ "$(jget /tmp/f805_in_use.json success)" = "0" ] || RC=1
[ -f "$FUI/catalog/$UPLOADED_PATH" ] || RC=1
curl -s -c "$JAR" -b "$JAR" -X POST "$B/admin/index.php?route=blog/blog.mediaDelete" \
  --data-urlencode "csrf_token=$BLOG_CSRF" --data-urlencode "type=folder" \
  --data-urlencode "path=view/image/blog/f805-e2e" -o /tmp/f805_not_empty.json
[ "$(jget /tmp/f805_not_empty.json success)" = "0" ] || RC=1
ck 5 "$RC" "exclusao-em-uso-ou-pasta-nao-vazia-bloqueada"

echo '══ 6 — CRUD Blog exige CSRF; arquivo livre pode ser excluído ══'
curl -s -c "$JAR" -b "$JAR" -X POST "$B/admin/index.php?route=blog/blog.delete|1" -o /dev/null
RC=0
[ "$(q "SELECT COUNT(*) FROM reamur_test.rms_blog_post WHERE image='$UPLOADED_PATH'")" = "1" ] || RC=1
curl -s -c "$JAR" -b "$JAR" -X POST "$B/admin/index.php?route=blog/blog.delete|1" \
  --data-urlencode "csrf_token=$BLOG_CSRF" -o /dev/null
[ "$(q "SELECT COUNT(*) FROM reamur_test.rms_blog_post WHERE image='$UPLOADED_PATH'")" = "0" ] || RC=1
curl -s -c "$JAR" -b "$JAR" -X POST "$B/admin/index.php?route=blog/blog.mediaDelete" \
  --data-urlencode "csrf_token=$BLOG_CSRF" --data-urlencode "type=file" --data-urlencode "path=$UPLOADED_PATH" \
  -o /tmp/f805_deleted.json
[ "$(jget /tmp/f805_deleted.json success)" = "1" ] || RC=1
[ ! -e "$FUI/catalog/$UPLOADED_PATH" ] || RC=1
ck 6 "$RC" "crud-csrf-e-exclusao-de-imagem-livre"

echo '══ 7 — pasta vazia pode ser excluída ══'
curl -s -c "$JAR" -b "$JAR" -X POST "$MEDIA_URL" \
  --data-urlencode "csrf_token=$BLOG_CSRF" --data-urlencode "folder=" --data-urlencode "name=f805-empty" \
  -o /tmp/f805_empty_create.json
curl -s -c "$JAR" -b "$JAR" -X POST "$B/admin/index.php?route=blog/blog.mediaDelete" \
  --data-urlencode "csrf_token=$BLOG_CSRF" --data-urlencode "type=folder" \
  --data-urlencode "path=view/image/blog/f805-empty" -o /tmp/f805_empty_delete.json
RC=0
[ "$(jget /tmp/f805_empty_create.json success)" = "1" ] || RC=1
[ "$(jget /tmp/f805_empty_delete.json success)" = "1" ] || RC=1
[ ! -e "$FUI/catalog/view/image/blog/f805-empty" ] || RC=1
ck 7 "$RC" "pasta-vazia-excluida"

echo
echo "════════════════ summary: $PASS pass / $FAIL fail ════════════════"
[ "$FAIL" = "0" ]
