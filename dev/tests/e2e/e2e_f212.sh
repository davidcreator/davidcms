#!/usr/bin/env bash
# ═══ Bateria E2E — F2 Tarefa 12: CRUD Logos de Clientes (marketing/client_logo) ═══
# Roda contra o sandbox /tmp/fui (layout /davidcms/dev no :8090). VIRGEM por padrão.
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
find /tmp/fui/dev/catalog/view/image/client_logo -name "2*" -delete 2>/dev/null
JW=/tmp/f212w.jar; JA=/tmp/f212a.jar; rm -f $JW $JA
python3 - << 'PYEOF'
import base64
open('/tmp/lok.png','wb').write(base64.b64decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg=='))
PYEOF
echo "não é imagem" > /tmp/lfake.png

echo '══ 1 — wizard; seed traz grant marketing/client_logo ══'
curl -s -c $JW -b $JW "$B/install/index.php?route=install/step_1" -o /dev/null
curl -s -c $JW -b $JW -X POST "$B/install/index.php?route=install/step_1" -o /dev/null
curl -s -c $JW -b $JW -X POST "$B/install/index.php?route=install/step_2" -o /dev/null
curl -s -c $JW -b $JW -X POST "$B/install/index.php?route=install/step_3" \
  --data-urlencode "db_driver=mysqli" --data-urlencode "db_hostname=127.0.0.1" --data-urlencode "db_port=3306" \
  --data-urlencode "db_username=reamur" --data-urlencode "db_password=reamur123" --data-urlencode "db_database=reamur_test" \
  --data-urlencode "db_prefix=rms_" --data-urlencode "http_server=" \
  --data-urlencode "username=admin" --data-urlencode "firstname=David" --data-urlencode "lastname=Almeida" \
  --data-urlencode "email=david@example.com" --data-urlencode "password=SenhaForte!2026" --data-urlencode "confirm=SenhaForte!2026" \
  -D /tmp/f212_i.hdr -o /dev/null
L=$(loc /tmp/f212_i.hdr); RC=0; [[ "$L" == *step_4* ]] || RC=1
G=$(q "SELECT JSON_SEARCH(permission, 'one', 'marketing/client_logo', NULL, '\$.access') IS NOT NULL FROM reamur_test.rms_user_group WHERE name='Administrator'")
[ "$G" = "1" ] || RC=1
ck 1 $RC "wizard-seed-grant"

echo '══ 2 — login ══'
curl -s -c $JA -b $JA "$B/admin/login" -o /tmp/f212_login.html -w "%{http_code}" | grep -q 200
TOKEN=$(grep -o 'name="user_token" value="[^"]*"' /tmp/f212_login.html | sed 's/.*value="//;s/"//')
curl -s -c $JA -b $JA -X POST "$B/admin/index.php?route=common/login" \
  --data-urlencode "username=admin" --data-urlencode "password=SenhaForte!2026" --data-urlencode "user_token=$TOKEN" \
  -D /tmp/f212_al.hdr -o /dev/null
L=$(loc /tmp/f212_al.hdr); [[ "$L" == *dashboard* ]]
ck 2 $? "admin-login"

echo '══ 3 — lista vazia honesta ══'
curl -s -c $JA -b $JA "$B/admin/marketing/client_logo" -o /tmp/f212_list0.html -D /tmp/f212_l0.hdr
H=$(hdr /tmp/f212_l0.hdr)
OK=0; [ "$H" = "200" ] || OK=1
grep -q "Client Logos" /tmp/f212_list0.html || OK=1
grep -qi "No client logos yet" /tmp/f212_list0.html || OK=1
ck 3 $OK "lista-vazia"

echo '══ 4 — dashboard cartão Client Logos ══'
curl -s -c $JA -b $JA "$B/admin/common/dashboard" -o /tmp/f212_dash.html
grep -q "Client Logos" /tmp/f212_dash.html && grep -q "marketing/client_logo" /tmp/f212_dash.html
ck 4 $? "dashboard-card"

echo '══ 5 — form single-table: sem fieldset de idioma, name/url/sort/status + upload ══'
curl -s -c $JA -b $JA "$B/admin/index.php?route=marketing/client_logo.add" -o /tmp/f212_form.html -D /tmp/f212_f.hdr
H=$(hdr /tmp/f212_f.hdr)
OK=0; [ "$H" = "200" ] || OK=1
N=$(grep -c "<legend>" /tmp/f212_form.html); [ "$N" = "0" ] || OK=1
grep -q 'id="input-name"' /tmp/f212_form.html || OK=1
grep -q 'id="input-url"' /tmp/f212_form.html || OK=1
grep -q "client_logo.upload" /tmp/f212_form.html || OK=1
grep -qi "keyword\|fieldset" /tmp/f212_form.html && OK=1
ck 5 $OK "form-single-table"

echo '══ 6 — POST inválido (nome vazio) → erro, nada persistido ══'
curl -s -c $JA -b $JA -X POST "$B/admin/index.php?route=marketing/client_logo.add" \
  --data-urlencode "name=" \
  -o /tmp/f212_err.html
grep -q "Name is required" /tmp/f212_err.html
OK=$?
C=$(q "SELECT COUNT(*) FROM reamur_test.rms_client_logo"); [ "$C" = "0" ] || OK=1
ck 6 $OK "validacao-nome"

echo '══ 7 — URL inválida (ftp://) → rejeitada ══'
curl -s -c $JA -b $JA -X POST "$B/admin/index.php?route=marketing/client_logo.add" \
  --data-urlencode "name=SEVEN A" --data-urlencode "url=ftp://sevena.test" \
  -o /tmp/f212_err2.html
grep -q "must start with http" /tmp/f212_err2.html
OK=$?
C=$(q "SELECT COUNT(*) FROM reamur_test.rms_client_logo"); [ "$C" = "0" ] || OK=1
ck 7 $OK "validacao-url"

echo '══ 8 — upload real via COMPONENTE → path do módulo; fake rejeitado; GET → JSON ══'
curl -s -c $JA -b $JA -X POST "$B/admin/index.php?route=marketing/client_logo.upload" -F "file=@/tmp/lok.png;type=image/png" -o /tmp/f212_up1.json
grep -q '"success":true' /tmp/f212_up1.json
A=$?
LPATH=$(grep -o '"path":"[^"]*"' /tmp/f212_up1.json | sed 's/"path":"//;s/"//;s|\\/|/|g')
[ -f "/tmp/fui/dev/catalog/$LPATH" ]
OK=$?
case "$LPATH" in view/image/client_logo/*) :;; *) OK=1;; esac
curl -s -c $JA -b $JA -X POST "$B/admin/index.php?route=marketing/client_logo.upload" -F "file=@/tmp/lfake.png;type=image/png" -o /tmp/f212_up2.json
grep -q '"success":false' /tmp/f212_up2.json
B2=$?
curl -s -c $JA -b $JA "$B/admin/index.php?route=marketing/client_logo.upload" -o /tmp/f212_up3.json
grep -q '"success":false' /tmp/f212_up3.json && grep -q "must be a POST" /tmp/f212_up3.json
ck 8 $(( A + OK + B2 + $? )) "upload-componente"

echo '══ 9 — create completo com logo + URL ══'
curl -s -c $JA -b $JA -X POST "$B/admin/index.php?route=marketing/client_logo.add" \
  --data-urlencode "name=SEVEN A" --data-urlencode "url=https://sevena.example.com" \
  --data-urlencode "image=$LPATH" --data-urlencode "sort_order=1" --data-urlencode "status=1" \
  -D /tmp/f212_add.hdr -o /dev/null
L=$(loc /tmp/f212_add.hdr); OK=0; [[ "$L" == *marketing/client_logo* ]] || OK=1
P1=$(q "SELECT COUNT(*) FROM reamur_test.rms_client_logo")
IM=$(q "SELECT image FROM reamur_test.rms_client_logo WHERE client_logo_id=1")
UR=$(q "SELECT url FROM reamur_test.rms_client_logo WHERE client_logo_id=1")
ST=$(q "SELECT status FROM reamur_test.rms_client_logo WHERE client_logo_id=1")
[ "$P1" = "1" ] && [ "$IM" = "$LPATH" ] && [ "$UR" = "https://sevena.example.com" ] && [ "$ST" = "1" ] || OK=1
ck 9 $OK "create-com-logo-url"

echo '══ 10 — lista com thumb + flash + link ══'
curl -s -c $JA -b $JA "$B/admin/marketing/client_logo" -o /tmp/f212_list1.html
OK=0
grep -q "SEVEN A" /tmp/f212_list1.html || OK=1
grep -qi "Client logo created" /tmp/f212_list1.html || OK=1
grep -q "<img" /tmp/f212_list1.html || OK=1
grep -q "sevena.example.com" /tmp/f212_list1.html || OK=1
ck 10 $OK "lista-thumb-flash"

echo '══ 11 — edição repovoa + swap de logo + toggle de status ══'
curl -s -c $JA -b $JA "$B/admin/index.php?route=marketing/client_logo.edit|1" -o /tmp/f212_edit.html -D /tmp/f212_e.hdr
H=$(hdr /tmp/f212_e.hdr)
OK=0; [ "$H" = "200" ] || OK=1
grep -q 'value="SEVEN A"' /tmp/f212_edit.html || OK=1
curl -s -c $JA -b $JA -X POST "$B/admin/index.php?route=marketing/client_logo.upload" -F "file=@/tmp/lok.png;type=image/png" -o /tmp/f212_up4.json
LPATH2=$(grep -o '"path":"[^"]*"' /tmp/f212_up4.json | sed 's/"path":"//;s/"//;s|\\/|/|g')
[ -n "$LPATH2" ] && [ "$LPATH2" != "$LPATH" ] || OK=1
curl -s -c $JA -b $JA -X POST "$B/admin/index.php?route=marketing/client_logo.edit|1" \
  --data-urlencode "name=SEVEN A Ltda" --data-urlencode "url=" \
  --data-urlencode "image=$LPATH2" --data-urlencode "sort_order=9" \
  -D /tmp/f212_editp.hdr -o /dev/null
L=$(loc /tmp/f212_editp.hdr); [[ "$L" == *marketing/client_logo* ]] || OK=1
N1=$(q "SELECT name FROM reamur_test.rms_client_logo WHERE client_logo_id=1")
IM=$(q "SELECT image FROM reamur_test.rms_client_logo WHERE client_logo_id=1")
ST=$(q "SELECT status FROM reamur_test.rms_client_logo WHERE client_logo_id=1")
[ "$N1" = "SEVEN A Ltda" ] && [ "$IM" = "$LPATH2" ] && [ "$ST" = "0" ] || OK=1
ck 11 $OK "edicao-swap-status"

echo '══ 12 — delete: GET não muta; POST remove + ARQUIVO físico ══'
curl -s -c $JA -b $JA "$B/admin/index.php?route=marketing/client_logo.delete|1" -D /tmp/f212_dg.hdr -o /dev/null
L=$(loc /tmp/f212_dg.hdr); OK=0
[[ "$L" == *error/permission* ]] || OK=1
N=$(q "SELECT COUNT(*) FROM reamur_test.rms_client_logo WHERE client_logo_id=1"); [ "$N" = "1" ] || OK=1
[ -f "/tmp/fui/dev/catalog/$LPATH2" ] || OK=1
curl -s -c $JA -b $JA -X POST "$B/admin/index.php?route=marketing/client_logo.delete|1" -D /tmp/f212_dp.hdr -o /dev/null
N1=$(q "SELECT COUNT(*) FROM reamur_test.rms_client_logo")
[ "$N1" = "0" ] || OK=1
[ ! -f "/tmp/fui/dev/catalog/$LPATH2" ] || OK=1
L=$(loc /tmp/f212_dp.hdr); [[ "$L" == *marketing/client_logo* ]] || OK=1
ck 12 $OK "delete-com-arquivo"

echo '══ 13 — idiomas pt-br / es-eu ══'
curl -s -c $JA -b $JA "$B/admin/index.php?route=marketing/client_logo&language=pt-br" -o /tmp/f212_pt.html
grep -q "Logos de Clientes" /tmp/f212_pt.html
A=$?
curl -s -c $JA -b $JA "$B/admin/index.php?route=marketing/client_logo&language=es-eu" -o /tmp/f212_es.html
grep -q "Logotipos de Clientes" /tmp/f212_es.html
ck 13 $(( A + $? )) "idiomas"

echo '══ 14 — ACL deny + migração idempotente (2x) ══'
sudo mysql reamur_test -e "UPDATE rms_user_group SET permission=JSON_REMOVE(permission, JSON_UNQUOTE(JSON_SEARCH(permission,'one','marketing/client_logo',NULL,'\$.access')), JSON_UNQUOTE(JSON_SEARCH(permission,'one','marketing/client_logo',NULL,'\$.modify'))) WHERE name='Administrator'"
curl -s -c $JA -b $JA "$B/admin/marketing/client_logo" -D /tmp/f212_acl.hdr -o /dev/null
L=$(loc /tmp/f212_acl.hdr); OK=0; [[ "$L" == *error/permission* ]] || OK=1
sudo mysql reamur_test < "$MIRROR/install/sql/upgrade_f2_12_marketing_client_logo_grant.sql"
sudo mysql reamur_test < "$MIRROR/install/sql/upgrade_f2_12_marketing_client_logo_grant.sql"
C=$(q "SELECT (LENGTH(permission) - LENGTH(REPLACE(permission,'marketing/client_logo',''))) DIV 21 FROM reamur_test.rms_user_group WHERE name='Administrator'")
[ "$C" = "2" ] || OK=1
curl -s -c $JA -b $JA "$B/admin/marketing/client_logo" -D /tmp/f212_acl2.hdr -o /dev/null
H=$(hdr /tmp/f212_acl2.hdr); [ "$H" = "200" ] || OK=1
ck 14 $OK "acl-deny-migracao"

echo
echo "═══ RESUMO: $PASS pass, $FAIL fail "
exit $([ $FAIL -eq 0 ] && echo 0 || echo 1)
