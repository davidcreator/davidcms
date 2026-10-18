#!/usr/bin/env bash
# ═══ Bateria de REGRESSÃO F2 — cobre o coração de f27..f211 ═══
# Marketing só cliente_logo tem sua própria bateria (e2e_f212.sh);
# aqui: wizard/seed/acesso + CRUDs de ouro + SEO global + idiomas + hierarquia.
set -u
B="${B:-http://127.0.0.1:8090/davidcms/dev}"
MIRROR="${MIRROR:-/home/user/davidcms/dev}"
PASS=0; FAIL=0
ck(){ if [ "${2:-0}" = "0" ]; then PASS=$((PASS+1)); echo "$1 pass — $3"; else FAIL=$((FAIL+1)); echo "$1 FAIL — $3"; fi }
hdr(){ grep -i "^HTTP" "$1" | tail -1 | grep -o "[0-9]\{3\}"; }
loc(){ grep -i "^location" "$1" | tail -1 | tr -d '\r' | cut -d' ' -f2-; }
q(){ sudo mysql -N -e "$1"; }

echo '══ setup: estado virgem ══'
sudo mysql -e "DROP DATABASE IF EXISTS reamur_test; CREATE DATABASE reamur_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
rm -f /tmp/fui/dev/catalog/config.php /tmp/fui/dev/admin/config.php
JW=/tmp/regrw.jar; JA=/tmp/regra.jar; rm -f $JW $JA
python3 - << 'PYEOF'
import base64
open('/tmp/rok.png','wb').write(base64.b64decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg=='))
PYEOF

echo '══ 1 — wizard instala; os 7 grants de seed presentes ══'
curl -s -c $JW -b $JW "$B/install/index.php?route=install/step_1" -o /dev/null
curl -s -c $JW -b $JW -X POST "$B/install/index.php?route=install/step_1" -o /dev/null
curl -s -c $JW -b $JW -X POST "$B/install/index.php?route=install/step_2" -o /dev/null
curl -s -c $JW -b $JW -X POST "$B/install/index.php?route=install/step_3" \
  --data-urlencode "db_driver=mysqli" --data-urlencode "db_hostname=127.0.0.1" --data-urlencode "db_port=3306" \
  --data-urlencode "db_username=reamur" --data-urlencode "db_password=reamur123" --data-urlencode "db_database=reamur_test" \
  --data-urlencode "db_prefix=rms_" --data-urlencode "http_server=" \
  --data-urlencode "username=admin" --data-urlencode "firstname=David" --data-urlencode "lastname=Almeida" \
  --data-urlencode "email=david@example.com" --data-urlencode "password=SenhaForte!2026" --data-urlencode "confirm=SenhaForte!2026" \
  -D /tmp/regr_i.hdr -o /dev/null
L=$(loc /tmp/regr_i.hdr); RC=0; [[ "$L" == *step_4* ]] || RC=1
for r in user/user user/user_group catalog/project catalog/category cms/page marketing/testimonial marketing/client_logo tool/tool sale/order; do
  G=$(q "SELECT JSON_SEARCH(permission, 'one', '$r', NULL, '\$.access') IS NOT NULL FROM reamur_test.rms_user_group WHERE name='Administrator'")
  [ "$G" = "1" ] || { RC=1; echo "  grant faltando: $r"; }
done
L3=$(q "SELECT COUNT(*) FROM reamur_test.rms_language"); [ "$L3" = "3" ] || RC=1
ck 1 $RC "wizard-seed-9-grants-idiomas"

echo '══ 2 — login + dashboard tem os 5 cartões de módulo ══'
curl -s -c $JA -b $JA "$B/admin/login" -o /tmp/regr_login.html -w "%{http_code}" | grep -q 200
TOKEN=$(grep -o 'name="user_token" value="[^"]*"' /tmp/regr_login.html | sed 's/.*value="//;s/"//')
curl -s -c $JA -b $JA -X POST "$B/admin/index.php?route=common/login" \
  --data-urlencode "username=admin" --data-urlencode "password=SenhaForte!2026" --data-urlencode "user_token=$TOKEN" \
  -D /tmp/regr_al.hdr -o /dev/null
L=$(loc /tmp/regr_al.hdr); OK=0; [[ "$L" == *dashboard* ]] || OK=1
curl -s -c $JA -b $JA "$B/admin/common/dashboard" -o /tmp/regr_dash.html
for m in catalog/project catalog/category cms/page marketing/testimonial marketing/client_logo tool/tool sale/order; do
  grep -q "$m" /tmp/regr_dash.html || { OK=1; echo "  card faltando: $m"; }
done
ck 2 $OK "login-dashboard-7-cards"

echo '══ 3 — project: create multilang + capa + SEO (base p/ testes globais) ══'
curl -s -c $JA -b $JA -X POST "$B/admin/index.php?route=catalog/project.upload" -F "file=@/tmp/rok.png;type=image/png" -o /tmp/regr_up.json
PPATH=$(grep -o '"path":"[^"]*"' /tmp/regr_up.json | sed 's/"path":"//;s/"//;s|\\/|/|g')
case "$PPATH" in view/image/project/*) :;; *) PPATH=1;; esac
curl -s -c $JA -b $JA -X POST "$B/admin/index.php?route=catalog/project.add" \
  --data-urlencode "project_description[1][name]=Reamur Site" \
  --data-urlencode "project_description[1][keyword]=reamur-site" \
  --data-urlencode "project_description[2][name]=Site Reamur" \
  --data-urlencode "project_description[3][name]=" \
  --data-urlencode "image=$PPATH" --data-urlencode "status=1" \
  -D /tmp/regr_pa.hdr -o /dev/null
L=$(loc /tmp/regr_pa.hdr); OK=0; [[ "$L" == *catalog/project* ]] || OK=1
[ "$PPATH" = "1" ] && OK=1
N=$(q "SELECT COUNT(*) FROM reamur_test.rms_project"); [ "$N" = "1" ] || OK=1
K=$(q "SELECT COUNT(*) FROM reamur_test.rms_seo_url WHERE \`key\`='project_id=1' AND value='reamur-site'"); [ "$K" = "1" ] || OK=1
ck 3 $OK "project-create-seo"

echo '══ 4 — SEO GLOBAL: keyword do projeto colide em categoria e em página ══'
curl -s -c $JA -b $JA -X POST "$B/admin/index.php?route=catalog/category.add" \
  --data-urlencode "category_description[1][name]=Cat X" \
  --data-urlencode "category_description[1][keyword]=reamur-site" \
  -o /tmp/regr_seo1.html
grep -q "already in use" /tmp/regr_seo1.html
A=$?
curl -s -c $JA -b $JA -X POST "$B/admin/index.php?route=cms/page.add" \
  --data-urlencode "page_description[1][title]=Pág X" \
  --data-urlencode "page_description[1][keyword]=reamur-site" \
  -o /tmp/regr_seo2.html
grep -q "already in use" /tmp/regr_seo2.html
ck 4 $(( A + $? )) "seo-global-cross-entity"

echo '══ 5 — category: hierarquia + anti-ciclo server-side + delete sobe filho ══'
curl -s -c $JA -b $JA -X POST "$B/admin/index.php?route=catalog/category.add" \
  --data-urlencode "category_description[1][name]=Pai" -D /tmp/regr_c1.hdr -o /dev/null
curl -s -c $JA -b $JA -X POST "$B/admin/index.php?route=catalog/category.add" \
  --data-urlencode "category_description[1][name]=Filho" --data-urlencode "parent_id=1" -D /tmp/regr_c2.hdr -o /dev/null
# anti-ciclo: editar Pai com parent_id=2 (seu próprio filho) via POST adulterado
curl -s -c $JA -b $JA -X POST "$B/admin/index.php?route=catalog/category.edit|1" \
  --data-urlencode "category_description[1][name]=Pai" --data-urlencode "parent_id=2" \
  -o /tmp/regr_cyc.html
grep -qi "Invalid parent\|own parent" /tmp/regr_cyc.html
A=$?
# delete do Pai: filho sobe p/ raiz
curl -s -c $JA -b $JA -X POST "$B/admin/index.php?route=catalog/category.delete|1" -o /dev/null
PI=$(q "SELECT parent_id FROM reamur_test.rms_category WHERE category_id=2")
[ "$PI" = "0" ] || A=1
ck 5 $A "category-hierarquia-anticyclo-delete"

echo '══ 6 — page: edição com swap de SEO (par antigo sai) ══'
curl -s -c $JA -b $JA -X POST "$B/admin/index.php?route=cms/page.add" \
  --data-urlencode "page_description[1][title]=Sobre" \
  --data-urlencode "page_description[1][keyword]=sobre-nos" \
  -D /tmp/regr_pg.hdr -o /dev/null
curl -s -c $JA -b $JA -X POST "$B/admin/index.php?route=cms/page.edit|1" \
  --data-urlencode "page_description[1][title]=Sobre V2" \
  --data-urlencode "page_description[1][keyword]=sobre-nos-v2" \
  -D /tmp/regr_pg2.hdr -o /dev/null
K1=$(q "SELECT COUNT(*) FROM reamur_test.rms_seo_url WHERE \`key\`='page_id=1' AND value='sobre-nos-v2'")
K0=$(q "SELECT COUNT(*) FROM reamur_test.rms_seo_url WHERE \`key\`='page_id=1' AND value='sobre-nos'")
OK=0; [ "$K1" = "1" ] && [ "$K0" = "0" ] || OK=1
ck 6 $OK "page-seo-swap"

echo '══ 7 — testimonial: upload via componente + create ══'
curl -s -c $JA -b $JA -X POST "$B/admin/index.php?route=marketing/testimonial.upload" -F "file=@/tmp/rok.png;type=image/png" -o /tmp/regr_up2.json
TPATH=$(grep -o '"path":"[^"]*"' /tmp/regr_up2.json | sed 's/"path":"//;s/"//;s|\\/|/|g')
case "$TPATH" in view/image/testimonial/*) :;; *) TPATH=1;; esac
curl -s -c $JA -b $JA -X POST "$B/admin/index.php?route=marketing/testimonial.add" \
  --data-urlencode "testimonial_description[1][author]=Ana" \
  --data-urlencode "image=$TPATH" \
  -D /tmp/regr_tt.hdr -o /dev/null
L=$(loc /tmp/regr_tt.hdr); OK=0; [[ "$L" == *marketing/testimonial* ]] || OK=1
[ "$TPATH" = "1" ] && OK=1
N=$(q "SELECT COUNT(*) FROM reamur_test.rms_testimonial"); [ "$N" = "1" ] || OK=1
ck 7 $OK "testimonial-create"

echo '══ 8 — ACL deny (project) + migração f2_8 idempotente ══'
sudo mysql reamur_test -e "UPDATE rms_user_group SET permission=JSON_REMOVE(permission, JSON_UNQUOTE(JSON_SEARCH(permission,'one','catalog/project',NULL,'\$.access')), JSON_UNQUOTE(JSON_SEARCH(permission,'one','catalog/project',NULL,'\$.modify'))) WHERE name='Administrator'"
curl -s -c $JA -b $JA "$B/admin/catalog/project" -D /tmp/regr_acl.hdr -o /dev/null
L=$(loc /tmp/regr_acl.hdr); OK=0; [[ "$L" == *error/permission* ]] || OK=1
sudo mysql reamur_test < "$MIRROR/install/sql/upgrade_f2_8_portfolio_grant.sql" 2>/dev/null || sudo mysql reamur_test < "$MIRROR/install/sql/upgrade_f2_8_project_grant.sql"
sudo mysql reamur_test < "$MIRROR/install/sql/upgrade_f2_8_portfolio_grant.sql" 2>/dev/null || sudo mysql reamur_test < "$MIRROR/install/sql/upgrade_f2_8_project_grant.sql"
C=$(q "SELECT (LENGTH(permission) - LENGTH(REPLACE(permission,'catalog/project',''))) DIV 15 FROM reamur_test.rms_user_group WHERE name='Administrator'")
[ "$C" = "2" ] || OK=1
curl -s -c $JA -b $JA "$B/admin/catalog/project" -D /tmp/regr_acl2.hdr -o /dev/null
H=$(hdr /tmp/regr_acl2.hdr); [ "$H" = "200" ] || OK=1
ck 8 $OK "acl-migracao-idempotente"

echo '══ 9 — idiomas pt/es spot (headings) ══'
curl -s -c $JA -b $JA "$B/admin/index.php?route=catalog/project&language=pt-br" -o /tmp/regr_p1.html
grep -q "Portfólio" /tmp/regr_p1.html
A=$?
curl -s -c $JA -b $JA "$B/admin/index.php?route=cms/page&language=es-eu" -o /tmp/regr_p2.html
grep -q "Páginas de Contenido" /tmp/regr_p2.html
ck 9 $(( A + $? )) "idiomas-spot"

echo '══ 10 — delete em cascata física (project com capa) ══'
[ -f "/tmp/fui/dev/catalog/$PPATH" ] || OK=1
curl -s -c $JA -b $JA -X POST "$B/admin/index.php?route=catalog/project.delete|1" -o /dev/null
OK=0
N=$(q "SELECT COUNT(*) FROM reamur_test.rms_project"); [ "$N" = "0" ] || OK=1
N2=$(q "SELECT COUNT(*) FROM reamur_test.rms_project_description"); [ "$N2" = "0" ] || OK=1
N3=$(q "SELECT COUNT(*) FROM reamur_test.rms_seo_url WHERE \`key\`='project_id=1'"); [ "$N3" = "0" ] || OK=1
[ ! -f "/tmp/fui/dev/catalog/$PPATH" ] || OK=1
ck 10 $OK "delete-cascata-completa"

echo
echo "═══ RESUMO: $PASS pass, $FAIL fail "
exit $([ $FAIL -eq 0 ] && echo 0 || echo 1)
