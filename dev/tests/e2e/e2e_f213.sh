#!/usr/bin/env bash
# ═══ Bateria E2E — F2 Tarefa 13: CRUD Ferramentas (tool/tool) ═══
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
find /tmp/fui/dev/catalog/view/image/tool -name "2*" -delete 2>/dev/null
JW=/tmp/f213w.jar; JA=/tmp/f213a.jar; rm -f $JW $JA
python3 - << 'PYEOF'
import base64
open('/tmp/took.png','wb').write(base64.b64decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg=='))
PYEOF
echo "não é imagem" > /tmp/tofake.png

echo '══ 1 — wizard; seed traz grant tool/tool ══'
curl -s -c $JW -b $JW "$B/install/index.php?route=install/step_1" -o /dev/null
curl -s -c $JW -b $JW -X POST "$B/install/index.php?route=install/step_1" -o /dev/null
curl -s -c $JW -b $JW -X POST "$B/install/index.php?route=install/step_2" -o /dev/null
curl -s -c $JW -b $JW -X POST "$B/install/index.php?route=install/step_3" \
  --data-urlencode "db_driver=mysqli" --data-urlencode "db_hostname=127.0.0.1" --data-urlencode "db_port=3306" \
  --data-urlencode "db_username=reamur" --data-urlencode "db_password=reamur123" --data-urlencode "db_database=reamur_test" \
  --data-urlencode "db_prefix=rms_" --data-urlencode "http_server=" \
  --data-urlencode "username=admin" --data-urlencode "firstname=David" --data-urlencode "lastname=Almeida" \
  --data-urlencode "email=david@example.com" --data-urlencode "password=SenhaForte!2026" --data-urlencode "confirm=SenhaForte!2026" \
  -D /tmp/f213_i.hdr -o /dev/null
L=$(loc /tmp/f213_i.hdr); RC=0; [[ "$L" == *step_4* ]] || RC=1
G=$(q "SELECT JSON_SEARCH(permission, 'one', 'tool/tool', NULL, '\$.access') IS NOT NULL FROM reamur_test.rms_user_group WHERE name='Administrator'")
[ "$G" = "1" ] || RC=1
ck 1 $RC "wizard-seed-grant"

echo '══ 2 — login ══'
curl -s -c $JA -b $JA "$B/admin/login" -o /tmp/f213_login.html -w "%{http_code}" | grep -q 200
TOKEN=$(grep -o 'name="user_token" value="[^"]*"' /tmp/f213_login.html | sed 's/.*value="//;s/"//')
CSRF_LOGIN=$(grep -o 'name="csrf_token" value="[^"]*"' /tmp/f213_login.html | sed 's/.*value="//;s/"//')
curl -s -c $JA -b $JA -X POST "$B/admin/index.php?route=common/login" \
  --data-urlencode "username=admin" --data-urlencode "password=SenhaForte!2026" --data-urlencode "user_token=$TOKEN" \
  --data-urlencode "csrf_token=$CSRF_LOGIN" \
  -D /tmp/f213_al.hdr -o /dev/null
L=$(loc /tmp/f213_al.hdr); [[ "$L" == *dashboard* ]]
ck 2 $? "admin-login"

echo '══ 3 — lista vazia honesta ══'
curl -s -c $JA -b $JA "$B/admin/tool/tool" -o /tmp/f213_list0.html -D /tmp/f213_l0.hdr
H=$(hdr /tmp/f213_l0.hdr)
OK=0; [ "$H" = "200" ] || OK=1
grep -q "Connected Tools" /tmp/f213_list0.html || OK=1
grep -qi "No tools yet" /tmp/f213_list0.html || OK=1
ck 3 $OK "lista-vazia"

echo '══ 4 — dashboard cartão Tools ══'
curl -s -c $JA -b $JA "$B/admin/common/dashboard" -o /tmp/f213_dash.html
grep -q "Tools" /tmp/f213_dash.html && grep -q "tool/tool" /tmp/f213_dash.html
ck 4 $? "dashboard-card"

echo '══ 5 — form: 3 idiomas (name/description), vendor/url + upload, SEM keyword ══'
curl -s -c $JA -b $JA "$B/admin/index.php?route=tool/tool.add" -o /tmp/f213_form.html -D /tmp/f213_f.hdr
TOOL_CSRF=$(grep -o 'name="csrf_token" value="[^"]*"' /tmp/f213_form.html | head -1 | sed 's/.*value="//;s/"//')
H=$(hdr /tmp/f213_f.hdr)
OK=0; [ "$H" = "200" ] || OK=1
N=$(grep -o 'id="tab-tool-lang-[0-9]*"' /tmp/f213_form.html | wc -l); [ "$N" = "3" ] || OK=1
grep -q 'id="input-name-1"' /tmp/f213_form.html || OK=1
grep -q 'name="tool_description\[1\]\[description\]"' /tmp/f213_form.html || OK=1
grep -q 'id="input-vendor"' /tmp/f213_form.html || OK=1
grep -q "tool.upload" /tmp/f213_form.html || OK=1
grep -qi "keyword" /tmp/f213_form.html && OK=1
ck 5 $OK "form-multilang-sem-seo"

echo '══ 6 — POST inválido (nome en vazio) → erro, nada persistido ══'
curl -s -c $JA -b $JA -X POST "$B/admin/index.php?route=tool/tool.add" \
  --data-urlencode "csrf_token=$TOOL_CSRF" \
  --data-urlencode "tool_description[1][name]=" \
  -o /tmp/f213_err.html
grep -q "en-US name is required" /tmp/f213_err.html
OK=$?
C=$(q "SELECT COUNT(*) FROM reamur_test.rms_tool"); [ "$C" = "0" ] || OK=1
ck 6 $OK "validacao-nome"

echo '══ 7 — URL inválida (ftp://) → rejeitada ══'
curl -s -c $JA -b $JA -X POST "$B/admin/index.php?route=tool/tool.add" \
  --data-urlencode "csrf_token=$TOOL_CSRF" \
  --data-urlencode "tool_description[1][name]=Three.js" --data-urlencode "url=ftp://three.test" \
  -o /tmp/f213_err2.html
grep -q "must start with http" /tmp/f213_err2.html
OK=$?
C=$(q "SELECT COUNT(*) FROM reamur_test.rms_tool"); [ "$C" = "0" ] || OK=1
ck 7 $OK "validacao-url"

echo '══ 8 — upload do ícone via COMPONENTE → path do módulo; fake rejeitado; GET → JSON ══'
curl -s -c $JA -b $JA -X POST "$B/admin/index.php?route=tool/tool.upload" -F "csrf_token=$TOOL_CSRF" -F "file=@/tmp/took.png;type=image/png" -o /tmp/f213_up1.json
grep -q '"success":true' /tmp/f213_up1.json
A=$?
IPATH=$(grep -o '"path":"[^"]*"' /tmp/f213_up1.json | sed 's/"path":"//;s/"//;s|\\/|/|g')
[ -f "/tmp/fui/dev/catalog/$IPATH" ]
OK=$?
case "$IPATH" in view/image/tool/*) :;; *) OK=1;; esac
curl -s -c $JA -b $JA -X POST "$B/admin/index.php?route=tool/tool.upload" -F "csrf_token=$TOOL_CSRF" -F "file=@/tmp/tofake.png;type=image/png" -o /tmp/f213_up2.json
grep -q '"success":false' /tmp/f213_up2.json
B2=$?
curl -s -c $JA -b $JA "$B/admin/index.php?route=tool/tool.upload" -o /tmp/f213_up3.json
grep -q '"success":false' /tmp/f213_up3.json && grep -q "must be a POST" /tmp/f213_up3.json
ck 8 $(( A + OK + B2 + $? )) "upload-componente"

echo '══ 9 — create multilíngue + vendor + ícone + URL ══'
curl -s -c $JA -b $JA -X POST "$B/admin/index.php?route=tool/tool.add" \
  --data-urlencode "csrf_token=$TOOL_CSRF" \
  --data-urlencode "tool_description[1][name]=Three.js" \
  --data-urlencode "tool_description[1][description]=3D on the web" \
  --data-urlencode "tool_description[2][name]=Three.js" \
  --data-urlencode "tool_description[2][description]=3D na web" \
  --data-urlencode "tool_description[3][name]=" \
  --data-urlencode "vendor=DavidCreator" --data-urlencode "url=https://threejs.org" \
  --data-urlencode "image=$IPATH" --data-urlencode "sort_order=1" --data-urlencode "status=1" \
  -D /tmp/f213_add.hdr -o /dev/null
L=$(loc /tmp/f213_add.hdr); OK=0; [[ "$L" == *tool/tool* ]] || OK=1
P1=$(q "SELECT COUNT(*) FROM reamur_test.rms_tool")
P2=$(q "SELECT COUNT(*) FROM reamur_test.rms_tool_description")
IM=$(q "SELECT image FROM reamur_test.rms_tool WHERE tool_id=1")
VD=$(q "SELECT vendor FROM reamur_test.rms_tool WHERE tool_id=1")
UR=$(q "SELECT url FROM reamur_test.rms_tool WHERE tool_id=1")
[ "$P1" = "1" ] && [ "$P2" = "3" ] && [ "$IM" = "$IPATH" ] && [ "$VD" = "DavidCreator" ] && [ "$UR" = "https://threejs.org" ] || OK=1
ck 9 $OK "create-multilang-vendor-icon"

echo '══ 10 — lista com thumb + vendor + flash ══'
curl -s -c $JA -b $JA "$B/admin/tool/tool" -o /tmp/f213_list1.html
OK=0
grep -q "Three.js" /tmp/f213_list1.html || OK=1
grep -q "DavidCreator" /tmp/f213_list1.html || OK=1
grep -qi "Tool created" /tmp/f213_list1.html || OK=1
grep -q "<img" /tmp/f213_list1.html || OK=1
ck 10 $OK "lista-thumb-flash"

echo '══ 11 — edição repovoa + swap de ícone + toggle status ══'
curl -s -c $JA -b $JA "$B/admin/index.php?route=tool/tool.edit|1" -o /tmp/f213_edit.html -D /tmp/f213_e.hdr
H=$(hdr /tmp/f213_e.hdr)
OK=0; [ "$H" = "200" ] || OK=1
grep -q 'value="Three.js"' /tmp/f213_edit.html || OK=1
grep -q "3D on the web" /tmp/f213_edit.html || OK=1
curl -s -c $JA -b $JA -X POST "$B/admin/index.php?route=tool/tool.upload" -F "csrf_token=$TOOL_CSRF" -F "file=@/tmp/took.png;type=image/png" -o /tmp/f213_up4.json
IPATH2=$(grep -o '"path":"[^"]*"' /tmp/f213_up4.json | sed 's/"path":"//;s/"//;s|\\/|/|g')
[ -n "$IPATH2" ] && [ "$IPATH2" != "$IPATH" ] || OK=1
curl -s -c $JA -b $JA -X POST "$B/admin/index.php?route=tool/tool.edit|1" \
  --data-urlencode "csrf_token=$TOOL_CSRF" \
  --data-urlencode "tool_description[1][name]=Three.js r160" \
  --data-urlencode "tool_description[2][name]=Three.js" \
  --data-urlencode "vendor=" --data-urlencode "url=" \
  --data-urlencode "image=$IPATH2" --data-urlencode "sort_order=9" \
  -D /tmp/f213_editp.hdr -o /dev/null
L=$(loc /tmp/f213_editp.hdr); [[ "$L" == *tool/tool* ]] || OK=1
N1=$(q "SELECT name FROM reamur_test.rms_tool_description WHERE tool_id=1 AND language_id=1")
IM=$(q "SELECT image FROM reamur_test.rms_tool WHERE tool_id=1")
ST=$(q "SELECT status FROM reamur_test.rms_tool WHERE tool_id=1")
VD=$(q "SELECT vendor FROM reamur_test.rms_tool WHERE tool_id=1")
[ "$N1" = "Three.js r160" ] && [ "$IM" = "$IPATH2" ] && [ "$ST" = "0" ] && [ "$VD" = "" ] || OK=1
ck 11 $OK "edicao-swap-status"

echo '══ 12 — delete: GET não muta; POST remove descrições + ARQUIVO físico ══'
curl -s -c $JA -b $JA "$B/admin/index.php?route=tool/tool.delete|1" -D /tmp/f213_dg.hdr -o /dev/null
L=$(loc /tmp/f213_dg.hdr); OK=0
[[ "$L" == *error/permission* ]] || OK=1
N=$(q "SELECT COUNT(*) FROM reamur_test.rms_tool WHERE tool_id=1"); [ "$N" = "1" ] || OK=1
[ -f "/tmp/fui/dev/catalog/$IPATH2" ] || OK=1
curl -s -c $JA -b $JA -X POST "$B/admin/index.php?route=tool/tool.delete|1" --data-urlencode "csrf_token=$TOOL_CSRF" -D /tmp/f213_dp.hdr -o /dev/null
N1=$(q "SELECT COUNT(*) FROM reamur_test.rms_tool")
N2=$(q "SELECT COUNT(*) FROM reamur_test.rms_tool_description")
[ "$N1" = "0" ] && [ "$N2" = "0" ] || OK=1
[ ! -f "/tmp/fui/dev/catalog/$IPATH2" ] || OK=1
L=$(loc /tmp/f213_dp.hdr); [[ "$L" == *tool/tool* ]] || OK=1
ck 12 $OK "delete-cascata-arquivo"

echo '══ 13 — idiomas pt-br / es-eu ══'
curl -s -c $JA -b $JA "$B/admin/index.php?route=tool/tool&language=pt-br" -o /tmp/f213_pt.html
grep -q "Ferramentas Conectadas" /tmp/f213_pt.html
A=$?
curl -s -c $JA -b $JA "$B/admin/index.php?route=tool/tool&language=es-eu" -o /tmp/f213_es.html
grep -q "Herramientas Conectadas" /tmp/f213_es.html
ck 13 $(( A + $? )) "idiomas"

echo '══ 14 — ACL deny + migração idempotente (2x) ══'
sudo mysql reamur_test -e "UPDATE rms_user_group SET permission=JSON_REMOVE(permission, JSON_UNQUOTE(JSON_SEARCH(permission,'one','tool/tool',NULL,'\$.access')), JSON_UNQUOTE(JSON_SEARCH(permission,'one','tool/tool',NULL,'\$.modify'))) WHERE name='Administrator'"
curl -s -c $JA -b $JA "$B/admin/tool/tool" -D /tmp/f213_acl.hdr -o /dev/null
L=$(loc /tmp/f213_acl.hdr); OK=0; [[ "$L" == *error/permission* ]] || OK=1
sudo mysql reamur_test < "$MIRROR/install/sql/upgrade_f2_13_tool_grant.sql"
sudo mysql reamur_test < "$MIRROR/install/sql/upgrade_f2_13_tool_grant.sql"
C=$(q "SELECT (LENGTH(permission) - LENGTH(REPLACE(permission,'tool/tool',''))) DIV 9 FROM reamur_test.rms_user_group WHERE name='Administrator'")
[ "$C" = "2" ] || OK=1
curl -s -c $JA -b $JA "$B/admin/tool/tool" -D /tmp/f213_acl2.hdr -o /dev/null
H=$(hdr /tmp/f213_acl2.hdr); [ "$H" = "200" ] || OK=1
ck 14 $OK "acl-deny-migracao"

echo
echo "═══ RESUMO: $PASS pass, $FAIL fail "
exit $([ $FAIL -eq 0 ] && echo 0 || echo 1)
