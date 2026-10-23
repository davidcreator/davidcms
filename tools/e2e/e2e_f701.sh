#!/usr/bin/env bash
# ════════════════════════════════════════════════════════════════
# e2e_f701.sh — Blog interno: base + admin CRUD (F7-01, ADR 0042)
# Espelho do batelado F2 (cms/page) com ordering cronológico.
#
# Checks:
#   1 wizard + upgrades ⇒ tabelas rms_blog_post(_description) criadas
#   2 upgrade idempotente (2ª passagem) + grants blog/blog no Administrator
#   3 login ⇒ card blog/blog no dashboard + lista vazia 200 c/ mensagem
#   4 add multilíngue completo: 1 post, 3 descrições, 3 keywords
#     seo_url blog_id=1, status/date_published/título EN persistidos
#   5 validação: keyword com formato inválido rejeitada (200 + erro, 0 insert)
#   6 unicidade GLOBAL: keyword já usada por cms/page rejeita no blog
#   7 edit persiste swap de título/keyword, status→rascunho, seo_url ressinc
#   8 lista mostra o post editado (título, data, rótulo rascunho)
#   9 delete (POST) limpa post + descrições + keywords
#  10 fontes: router com blog_id + alias 'blog', guards index.html
# ════════════════════════════════════════════════════════════════
set -u
B="${B:-http://127.0.0.1:8090/davidcms/dev}"
FUI="${FUI:-/var/www/html/davidcms/dev}"
MIRROR="${MIRROR:-/home/user/davidcms/dev}"
PASS=0; FAIL=0
ck(){ if [ "${2:-0}" = "0" ]; then PASS=$((PASS+1)); echo "$1 pass — $3"; else FAIL=$((FAIL+1)); echo "$1 FAIL — $3"; fi }
loc(){ grep -i "^location" "$1" | tail -1 | tr -d '\r' | cut -d' ' -f2-; }
q(){ sudo mysql -N -e "$1"; }

echo '══ setup: estado virgem ══'
sudo mysql -e "DROP DATABASE IF EXISTS reamur_test; CREATE DATABASE reamur_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
rm -f $FUI/catalog/config.php $FUI/admin/config.php $FUI/system/storage/install.lock
JW=/tmp/f701w.jar; JA=/tmp/f701a.jar; rm -f $JW $JA

echo '══ 1 — wizard instala; tabelas do blog nascem nos upgrades ══'
curl -s -c $JW -b $JW "$B/install/index.php?route=install/step_1" -o /dev/null
curl -s -c $JW -b $JW -X POST "$B/install/index.php?route=install/step_1" -o /dev/null
curl -s -c $JW -b $JW -X POST "$B/install/index.php?route=install/step_2" -o /dev/null
curl -s -c $JW -b $JW -X POST "$B/install/index.php?route=install/step_3" \
  --data-urlencode "db_driver=mysqli" --data-urlencode "db_hostname=127.0.0.1" --data-urlencode "db_port=3306" \
  --data-urlencode "db_username=reamur" --data-urlencode "db_password=reamur123" --data-urlencode "db_database=reamur_test" \
  --data-urlencode "db_prefix=rms_" --data-urlencode "http_server=" \
  --data-urlencode "username=admin" --data-urlencode "firstname=David" --data-urlencode "lastname=Almeida" \
  --data-urlencode "email=david@example.com" --data-urlencode "password=SenhaForte!2026" --data-urlencode "confirm=SenhaForte!2026" \
  -D /tmp/f701_i.hdr -o /dev/null
for u in "$MIRROR"/install/sql/upgrade_*.sql; do [ -f "$u" ] && sudo mysql reamur_test < "$u"; done
sudo rm -f $FUI/system/storage/cache/cache.setting.config
RC=0
L=$(loc /tmp/f701_i.hdr); [[ "$L" == *step_4* ]] || RC=1
[ "$(q "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='reamur_test' AND table_name IN ('rms_blog_post','rms_blog_post_description')")" = "2" ] || RC=1
q "DESCRIBE reamur_test.rms_blog_post" | grep -qw "date_published" || RC=1
q "DESCRIBE reamur_test.rms_blog_post" | grep -qw "image" || RC=1
q "SHOW INDEX FROM reamur_test.rms_blog_post" | grep -q "status_published" || RC=1
ck 1 $RC "wizard-tabelas-blog"

echo '══ 2 — upgrade idempotente + grants blog/blog ══'
RC=0
B4=$(q "SELECT JSON_LENGTH(JSON_EXTRACT(permission,'$.access')) FROM reamur_test.rms_user_group WHERE name='Administrator'")
sudo mysql reamur_test < "$MIRROR/install/sql/upgrade_f7_01_blog.sql" 2>/dev/null
[ "$(q "SELECT JSON_SEARCH(JSON_EXTRACT(permission,'$.access'),'one','blog/blog') IS NOT NULL FROM reamur_test.rms_user_group WHERE name='Administrator'")" = "1" ] || RC=1
[ "$(q "SELECT JSON_SEARCH(JSON_EXTRACT(permission,'$.modify'),'one','blog/blog') IS NOT NULL FROM reamur_test.rms_user_group WHERE name='Administrator'")" = "1" ] || RC=1
AF=$(q "SELECT JSON_LENGTH(JSON_EXTRACT(permission,'$.access')) FROM reamur_test.rms_user_group WHERE name='Administrator'")
[ "$B4" = "$AF" ] || RC=1   # guard JSON_SEARCH: 2ª passagem não duplica
ck 2 $RC "grants-idempotentes"

echo '══ 3 — login; card blog/blog no dashboard; lista vazia 200 ══'
curl -s -c $JA -b $JA "$B/admin/login" -o /tmp/f701_login.html
TOKEN=$(grep -o 'name="user_token" value="[^"]*"' /tmp/f701_login.html | sed 's/.*value="//;s/"//')
CSRF=$(grep -o 'name="csrf_token" value="[^"]*"' /tmp/f701_login.html | sed 's/.*value="//;s/"//')
curl -s -c $JA -b $JA -X POST "$B/admin/index.php?route=common/login" \
  --data-urlencode "username=admin" --data-urlencode "password=SenhaForte!2026" \
  --data-urlencode "user_token=$TOKEN" --data-urlencode "csrf_token=$CSRF" \
  -D /tmp/f701_al.hdr -o /dev/null
RC=0
L=$(loc /tmp/f701_al.hdr); [[ "$L" == *dashboard* ]] || RC=1
curl -s -c $JA -b $JA "$B/admin/common/dashboard" -o /tmp/f701_dash.html
grep -q "blog/blog" /tmp/f701_dash.html || { RC=1; echo "  card blog/blog ausente"; }
H=$(curl -s -c $JA -b $JA -o /tmp/f701_list0.html -w "%{http_code}" "$B/admin/index.php?route=blog/blog")
[ "$H" = "200" ] || RC=1
grep -qEi "No posts yet|Nenhum post ainda|no hay entradas" /tmp/f701_list0.html || RC=1
ck 3 $RC "card-lista-vazia"

# F8-05: o formulário expõe o token de sessão; mutations sem ele devem falhar.
curl -s -c $JA -b $JA "$B/admin/index.php?route=blog/blog.add" -o /tmp/f701_blog_form.html
BLOG_CSRF=$(grep -o 'name="csrf_token" value="[^"]*"' /tmp/f701_blog_form.html | sed 's/.*value="//;s/"//')
RC=0
[ -n "$BLOG_CSRF" ] || RC=1
curl -s -c $JA -b $JA -X POST "$B/admin/index.php?route=blog/blog.add" \
  --data-urlencode "blog_description[1][title]=CSRF sem token" \
  --data-urlencode "blog_description[1][keyword]=csrf-sem-token" \
  -o /tmp/f701_csrf_bad.html
grep -qiE "security token|token de segurança|token de seguridad" /tmp/f701_csrf_bad.html || RC=1
[ "$(q "SELECT COUNT(*) FROM reamur_test.rms_blog_post")" = "0" ] || RC=1
ck 3a $RC "blog-add-exige-csrf"

echo '══ 4 — add multilíngue completo (post + 3 descr + 3 keywords) ══'
curl -s -c $JA -b $JA -X POST "$B/admin/index.php?route=blog/blog.add" \
  --data-urlencode "csrf_token=$BLOG_CSRF" \
  --data-urlencode "blog_description[1][title]=Meu primeiro post" \
  --data-urlencode "blog_description[1][description]=<p>Conteúdo EN.</p>" \
  --data-urlencode "blog_description[1][keyword]=meu-primeiro-post" \
  --data-urlencode "blog_description[2][title]=Mi primera entrada" \
  --data-urlencode "blog_description[2][description]=<p>Contenido ES.</p>" \
  --data-urlencode "blog_description[2][keyword]=mi-primera-entrada" \
  --data-urlencode "blog_description[3][title]=O meu primeiro post" \
  --data-urlencode "blog_description[3][description]=<p>Conteúdo PT.</p>" \
  --data-urlencode "blog_description[3][keyword]=o-meu-primeiro-post" \
  --data-urlencode "image=uploads/2026/capa.jpg" \
  --data-urlencode "date_published=2026-10-21 09:30" \
  --data-urlencode "status=1" \
  -D /tmp/f701_pa.hdr -o /dev/null
RC=0
L=$(loc /tmp/f701_pa.hdr); [[ "$L" == *blog/blog* ]] || { RC=1; echo "  Location inesperado: $L"; }
[ "$(q "SELECT COUNT(*) FROM reamur_test.rms_blog_post")" = "1" ] || RC=1
[ "$(q "SELECT COUNT(*) FROM reamur_test.rms_blog_post_description WHERE post_id=1")" = "3" ] || RC=1
[ "$(q "SELECT COUNT(*) FROM reamur_test.rms_seo_url WHERE \`key\`='blog_id=1'")" = "3" ] || RC=1
[ "$(q "SELECT status FROM reamur_test.rms_blog_post WHERE post_id=1")" = "1" ] || RC=1
q "SELECT date_published FROM reamur_test.rms_blog_post WHERE post_id=1" | grep -q "2026-10-21 09:30" || RC=1
[ "$(q "SELECT COUNT(*) FROM reamur_test.rms_blog_post_description WHERE post_id=1 AND language_id=1 AND title='Meu primeiro post'")" = "1" ] || RC=1
[ "$(q "SELECT image FROM reamur_test.rms_blog_post WHERE post_id=1")" = "uploads/2026/capa.jpg" ] || RC=1
ck 4 $RC "add-multilingue-seo"

echo '══ 4b — edit sem CSRF rejeitado sem UPDATE ══'
curl -s -c $JA -b $JA -X POST "$B/admin/index.php?route=blog/blog.edit|1" \
  --data-urlencode "blog_description[1][title]=Edição sem token" \
  --data-urlencode "blog_description[1][keyword]=edicao-sem-token" \
  -o /tmp/f701_csrf_edit.html
RC=0
grep -qiE "security token|token de segurança|token de seguridad" /tmp/f701_csrf_edit.html || RC=1
[ "$(q "SELECT title FROM reamur_test.rms_blog_post_description WHERE post_id=1 AND language_id=1")" = "Meu primeiro post" ] || RC=1
ck 4b $RC "blog-edit-exige-csrf"

echo '══ 5 — keyword inválida (formato) rejeitada sem INSERT ══'
curl -s -c $JA -b $JA -X POST "$B/admin/index.php?route=blog/blog.add" \
  --data-urlencode "csrf_token=$BLOG_CSRF" \
  --data-urlencode "blog_description[1][title]=Post ruim" \
  --data-urlencode "blog_description[1][keyword]=Meu Post!" \
  --data-urlencode "status=1" \
  -o /tmp/f701_bad.html
RC=0
grep -qiE "apenas letras minúsculas|lowercase letters|solo minúsculas|Keyword do idioma|Keyword of language|Palabra clave del idioma" /tmp/f701_bad.html || { RC=1; echo "  erro de formato ausente"; }
[ "$(q "SELECT COUNT(*) FROM reamur_test.rms_blog_post")" = "1" ] || RC=1
ck 5 $RC "keyword-formato"

echo '══ 6 — unicidade GLOBAL: keyword de cms/page rejeita no blog ══'
curl -s -c $JA -b $JA -X POST "$B/admin/index.php?route=cms/page.add" \
  --data-urlencode "page_description[1][title]=Política de Cookies" \
  --data-urlencode "page_description[1][keyword]=cookies" \
  -D /tmp/f701_pg.hdr -o /dev/null
curl -s -c $JA -b $JA -X POST "$B/admin/index.php?route=blog/blog.add" \
  --data-urlencode "csrf_token=$BLOG_CSRF" \
  --data-urlencode "blog_description[1][title]=Post invadindo keyword" \
  --data-urlencode "blog_description[1][keyword]=cookies" \
  --data-urlencode "status=1" \
  -o /tmp/f701_taken.html
RC=0
grep -qiE "já é usada|already|ya la usa" /tmp/f701_taken.html || { RC=1; echo "  erro de unicidade ausente"; }
[ "$(q "SELECT COUNT(*) FROM reamur_test.rms_blog_post")" = "1" ] || RC=1
[ "$(q "SELECT COUNT(*) FROM reamur_test.rms_seo_url WHERE value='cookies' AND \`key\` LIKE 'blog_id=%'")" = "0" ] || RC=1
ck 6 $RC "keyword-cross-entidade"

echo '══ 7 — edit: swap título/keyword, status→rascunho, resync seo ══'
H=$(curl -s -c $JA -b $JA -o /tmp/f701_edit_form.html -w "%{http_code}" "$B/admin/index.php?route=blog/blog.edit|1")
curl -s -c $JA -b $JA -X POST "$B/admin/index.php?route=blog/blog.edit|1" \
  --data-urlencode "csrf_token=$BLOG_CSRF" \
  --data-urlencode "blog_description[1][title]=Primeiro post editado (v2)" \
  --data-urlencode "blog_description[1][description]=<p>Revisado.</p>" \
  --data-urlencode "blog_description[1][keyword]=post-editado" \
  --data-urlencode "blog_description[2][title]=Entrada editada" \
  --data-urlencode "blog_description[2][keyword]=entrada-editada" \
  --data-urlencode "blog_description[3][title]=Post editado" \
  --data-urlencode "blog_description[3][keyword]=post-editado-pt" \
  --data-urlencode "image=" \
  --data-urlencode "date_published=2026-10-20 18:00" \
  -D /tmp/f701_pe.hdr -o /dev/null
RC=0
[ "$H" = "200" ] || { RC=1; echo "  GET edit != 200 ($H)"; }
grep -q 'value="Meu primeiro post"' /tmp/f701_edit_form.html || { RC=1; echo "  form não pré-preenche título"; }
L=$(loc /tmp/f701_pe.hdr); [[ "$L" == *blog/blog* ]] || RC=1
[ "$(q "SELECT COUNT(*) FROM reamur_test.rms_blog_post_description WHERE post_id=1 AND language_id=1 AND title LIKE 'Primeiro post editado%'")" = "1" ] || RC=1
[ "$(q "SELECT COUNT(*) FROM reamur_test.rms_seo_url WHERE \`key\`='blog_id=1' AND value='post-editado'")" = "1" ] || RC=1
[ "$(q "SELECT COUNT(*) FROM reamur_test.rms_seo_url WHERE \`key\`='blog_id=1' AND value='meu-primeiro-post'")" = "0" ] || RC=1
[ "$(q "SELECT COUNT(*) FROM reamur_test.rms_seo_url WHERE \`key\`='blog_id=1'")" = "3" ] || RC=1
[ "$(q "SELECT status FROM reamur_test.rms_blog_post WHERE post_id=1")" = "0" ] || RC=1   # checkbox ausente = rascunho
[ "$(q "SELECT image FROM reamur_test.rms_blog_post WHERE post_id=1")" = "" ] || RC=1
ck 7 $RC "edit-resync-rascunho"

echo '══ 8 — lista mostra post editado (título, data, rascunho) ══'
curl -s -c $JA -b $JA "$B/admin/index.php?route=blog/blog" -o /tmp/f701_list1.html
RC=0
grep -q "editado" /tmp/f701_list1.html || RC=1
grep -q "2026-10-20" /tmp/f701_list1.html || RC=1
grep -qEi "Rascunho|Draft|Borrador" /tmp/f701_list1.html || RC=1
ck 8 $RC "lista-com-post"

echo '══ 9 — delete (POST) limpa tudo: main + descrições + keywords ══'
curl -s -c $JA -b $JA -X POST "$B/admin/index.php?route=blog/blog.delete|1" \
  --data-urlencode "csrf_token=$BLOG_CSRF" -D /tmp/f701_pd.hdr -o /dev/null
RC=0
L=$(loc /tmp/f701_pd.hdr); [[ "$L" == *blog/blog* ]] || RC=1
[ "$(q "SELECT COUNT(*) FROM reamur_test.rms_blog_post WHERE post_id=1")" = "0" ] || RC=1
[ "$(q "SELECT COUNT(*) FROM reamur_test.rms_blog_post_description WHERE post_id=1")" = "0" ] || RC=1
[ "$(q "SELECT COUNT(*) FROM reamur_test.rms_seo_url WHERE \`key\`='blog_id=1'")" = "0" ] || RC=1
[ "$(q "SELECT COUNT(*) FROM reamur_test.rms_seo_url WHERE \`key\` LIKE 'blog_id=%'")" = "0" ] || RC=1
ck 9 $RC "delete-limpeza-total"

echo '══ 10 — fontes: router pronto p/ F7-02 + guards + upgrade ══'
RC=0
grep -q "blog_id" $FUI/catalog/controller/startup/router.php || RC=1
grep -q "'blog'      => 'blog/blog'" $FUI/catalog/controller/startup/router.php || RC=1
grep -qF 'blog\/blog' $FUI/catalog/controller/startup/router.php || RC=1
grep -q "blog_id" $FUI/admin/model/blog/blog.php || RC=1
for d in admin/controller/blog admin/model/blog admin/language/en-us/blog admin/language/pt-br/blog admin/language/es-eu/blog admin/view/template/blog; do
  [ -f "$FUI/$d/index.html" ] || { RC=1; echo "  guard faltando: $d"; }
done
grep -q "CREATE TABLE IF NOT EXISTS" $FUI/install/sql/upgrade_f7_01_blog.sql || RC=1
grep -q "status_published" $FUI/install/sql/upgrade_f7_01_blog.sql || RC=1
grep -q "text_manage_blog" $FUI/admin/language/en-us/common/dashboard.php || RC=1
grep -q "text_manage_blog" $FUI/admin/language/pt-br/common/dashboard.php || RC=1
grep -q "text_manage_blog" $FUI/admin/language/es-eu/common/dashboard.php || RC=1
grep -q "hasPermission('access', 'blog/blog')" $FUI/admin/controller/common/dashboard.php || RC=1
ck 10 $RC "fontes-router-guards"

echo
echo "════════════════ summary: $PASS pass / $FAIL fail ════════════════"
[ "$FAIL" = "0" ]
