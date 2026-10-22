#!/usr/bin/env bash
# ═══ Bateria E2E — F2 Tarefa 14: CRUD Pedidos/Orçamentos (sale/order) ═══
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
JW=/tmp/f214w.jar; JA=/tmp/f214a.jar; rm -f $JW $JA

echo '══ 1 — wizard; seed traz grant sale/order + 6 status ══'
curl -s -c $JW -b $JW "$B/install/index.php?route=install/step_1" -o /dev/null
curl -s -c $JW -b $JW -X POST "$B/install/index.php?route=install/step_1" -o /dev/null
curl -s -c $JW -b $JW -X POST "$B/install/index.php?route=install/step_2" -o /dev/null
curl -s -c $JW -b $JW -X POST "$B/install/index.php?route=install/step_3" \
  --data-urlencode "db_driver=mysqli" --data-urlencode "db_hostname=127.0.0.1" --data-urlencode "db_port=3306" \
  --data-urlencode "db_username=reamur" --data-urlencode "db_password=reamur123" --data-urlencode "db_database=reamur_test" \
  --data-urlencode "db_prefix=rms_" --data-urlencode "http_server=" \
  --data-urlencode "username=admin" --data-urlencode "firstname=David" --data-urlencode "lastname=Almeida" \
  --data-urlencode "email=david@example.com" --data-urlencode "password=SenhaForte!2026" --data-urlencode "confirm=SenhaForte!2026" \
  -D /tmp/f214_i.hdr -o /dev/null
L=$(loc /tmp/f214_i.hdr); RC=0; [[ "$L" == *step_4* ]] || RC=1
G=$(q "SELECT JSON_SEARCH(permission, 'one', 'sale/order', NULL, '\$.access') IS NOT NULL FROM reamur_test.rms_user_group WHERE name='Administrator'")
[ "$G" = "1" ] || RC=1
S=$(q "SELECT COUNT(DISTINCT order_status_id) FROM reamur_test.rms_order_status"); [ "$S" = "6" ] || RC=1
ck 1 $RC "wizard-seed-grant-status"

echo '══ 2 — login ══'
curl -s -c $JA -b $JA "$B/admin/login" -o /tmp/f214_login.html -w "%{http_code}" | grep -q 200
TOKEN=$(grep -o 'name="user_token" value="[^"]*"' /tmp/f214_login.html | sed 's/.*value="//;s/"//')
CSRF_LOGIN=$(grep -o 'name="csrf_token" value="[^"]*"' /tmp/f214_login.html | sed 's/.*value="//;s/"//')
curl -s -c $JA -b $JA -X POST "$B/admin/index.php?route=common/login" \
  --data-urlencode "username=admin" --data-urlencode "password=SenhaForte!2026" --data-urlencode "user_token=$TOKEN" \
  --data-urlencode "csrf_token=$CSRF_LOGIN" \
  -D /tmp/f214_al.hdr -o /dev/null
L=$(loc /tmp/f214_al.hdr); [[ "$L" == *dashboard* ]]
ck 2 $? "admin-login"
curl -s -c $JA -b $JA "$B/admin/common/dashboard" -o /tmp/f214_csrf.html
CSRF=$(grep -o 'meta name="csrf-token" content="[^"]*"' /tmp/f214_csrf.html | sed 's/.*content="//;s/"//')

echo '══ 3 — lista vazia honesta ══'
curl -s -c $JA -b $JA "$B/admin/sale/order" -o /tmp/f214_list0.html -D /tmp/f214_l0.hdr
H=$(hdr /tmp/f214_l0.hdr)
OK=0; [ "$H" = "200" ] || OK=1
grep -q "Orders" /tmp/f214_list0.html || OK=1
grep -qi "No orders yet" /tmp/f214_list0.html || OK=1
ck 3 $OK "lista-vazia"

echo '══ 4 — dashboard cartão Orders ══'
curl -s -c $JA -b $JA "$B/admin/common/dashboard" -o /tmp/f214_dash.html
grep -q "Orders" /tmp/f214_dash.html && grep -q "sale/order" /tmp/f214_dash.html
ck 4 $? "dashboard-card"

echo '══ 5 — form: cliente, select de status (6), dinâmica de itens, SEM campo upload/SEO ══'
curl -s -c $JA -b $JA "$B/admin/index.php?route=sale/order.add&language=en-us" -o /tmp/f214_form.html -D /tmp/f214_f.hdr
H=$(hdr /tmp/f214_f.hdr)
OK=0; [ "$H" = "200" ] || OK=1
grep -q 'id="input-firstname"' /tmp/f214_form.html || OK=1
grep -q 'id="input-email"' /tmp/f214_form.html || OK=1
grep -q 'id="input-status"' /tmp/f214_form.html || OK=1
N=$(grep -c '<option value=' /tmp/f214_form.html); [ "$N" = "6" ] || OK=1
grep -q 'btn-add-product' /tmp/f214_form.html || OK=1
grep -qi "upload\|keyword" /tmp/f214_form.html && OK=1
ck 5 $OK "form-operacional"

echo '══ 5b — POST sem CSRF é rejeitado sem criar pedido ══'
curl -s -c $JA -b $JA -X POST "$B/admin/index.php?route=sale/order.add&language=en-us" \
  --data-urlencode "firstname=Sem Token" --data-urlencode "email=no-token@test.dev" \
  -o /tmp/f214_csrf_err.html
grep -qi "security token is invalid or expired" /tmp/f214_csrf_err.html
OK=$?
C=$(q "SELECT COUNT(*) FROM reamur_test.rms_order"); [ "$C" = "0" ] || OK=1
ck 5b $OK "post-sem-csrf"

echo '══ 6 — validações: nome vazio + e-mail inválido + item sem nome ══'
curl -s -c $JA -b $JA -X POST "$B/admin/index.php?route=sale/order.add&language=en-us" \
  --data-urlencode "csrf_token=$CSRF" \
  --data-urlencode "firstname=" --data-urlencode "email=sem-arroba" \
  -o /tmp/f214_err.html
grep -q "First name is required" /tmp/f214_err.html
A=$?
grep -q "valid e-mail" /tmp/f214_err.html
BB=$?
curl -s -c $JA -b $JA -X POST "$B/admin/index.php?route=sale/order.add&language=en-us" \
  --data-urlencode "csrf_token=$CSRF" \
  --data-urlencode "firstname=Ana" --data-urlencode "email=ana@test.dev" \
  --data-urlencode "order_product[0][name]=" --data-urlencode "order_product[0][price]=10" \
  -o /tmp/f214_err2.html
grep -q "Item row incomplete" /tmp/f214_err2.html
CC=$?
C=$(q "SELECT COUNT(*) FROM reamur_test.rms_order"); [ "$C" = "0" ] || CC=1
ck 6 $(( A + BB + CC )) "validacoes"

echo '══ 7 — create com 2 itens + 1 linha vazia → totais SERVER-SIDE + histórico inicial ══'
curl -s -c $JA -b $JA -X POST "$B/admin/index.php?route=sale/order.add&language=en-us" \
  --data-urlencode "csrf_token=$CSRF" \
  --data-urlencode "firstname=Ana" --data-urlencode "lastname=Souza" \
  --data-urlencode "email=ana@test.dev" --data-urlencode "telephone=+55 19 98877-6655" \
  --data-urlencode "comment=Quero landing page" \
  --data-urlencode "order_status_id=1" \
  --data-urlencode "order_product[0][name]=Landing Page" --data-urlencode "order_product[0][quantity]=2" --data-urlencode "order_product[0][price]=1500.50" \
  --data-urlencode "order_product[1][name]=Hospedagem" --data-urlencode "order_product[1][quantity]=3" --data-urlencode "order_product[1][price]=10" \
  --data-urlencode "order_product[2][name]=" --data-urlencode "order_product[2][quantity]=" --data-urlencode "order_product[2][price]=" \
  -D /tmp/f214_add.hdr -o /dev/null
L=$(loc /tmp/f214_add.hdr); OK=0; [[ "$L" == *sale/order* ]] || OK=1
P1=$(q "SELECT COUNT(*) FROM reamur_test.rms_order")
P2=$(q "SELECT COUNT(*) FROM reamur_test.rms_order_product")
TOT=$(q "SELECT total FROM reamur_test.rms_order WHERE order_id=1")
LT=$(q "SELECT total FROM reamur_test.rms_order_product WHERE name='Landing Page'")
HI=$(q "SELECT COUNT(*) FROM reamur_test.rms_order_history WHERE order_id=1 AND order_status_id=1")
[ "$P1" = "1" ] && [ "$P2" = "2" ] && [ "$(echo "$TOT" | cut -d. -f1)" = "3031" ] && [ "$LT" = "3001.0000" ] && [ "$HI" = "1" ] || { OK=1; echo "  dbg TOT=$TOT LT=$LT HI=$HI P2=$P2"; }
ck 7 $OK "create-totais-server-historico"

echo '══ 8 — lista com flash + total formatado + status ══'
curl -s -c $JA -b $JA "$B/admin/sale/order" -o /tmp/f214_list1.html
OK=0
grep -q "Ana Souza" /tmp/f214_list1.html || OK=1
grep -q "3031.00" /tmp/f214_list1.html || OK=1
grep -q "Received" /tmp/f214_list1.html || OK=1
grep -qi "Order created" /tmp/f214_list1.html || OK=1
ck 8 $OK "lista-flash-total"

echo '══ 8b — filtros de cliente/status e busca sem resultados ══'
curl -s -c $JA -b $JA "$B/admin/index.php?route=sale/order&filter_customer=Ana&filter_status=1&language=en-us" -o /tmp/f214_filter.html
OK=0
grep -q "Ana Souza" /tmp/f214_filter.html || OK=1
grep -q "Showing 1" /tmp/f214_filter.html || OK=1
curl -s -c $JA -b $JA "$B/admin/index.php?route=sale/order&filter_email=missing%40example.test&language=en-us" -o /tmp/f214_filter_empty.html
grep -q "No orders match these filters" /tmp/f214_filter_empty.html || OK=1
ck 8b $OK "filtros-server-side"

echo '══ 9 — edição repovoa (itens+histórico visíveis); MUDAR status → +1 histórico; total recalculado ══'
curl -s -c $JA -b $JA "$B/admin/index.php?route=sale/order.edit|1" -o /tmp/f214_edit.html -D /tmp/f214_e.hdr
H=$(hdr /tmp/f214_e.hdr)
OK=0; [ "$H" = "200" ] || OK=1
grep -q 'value="Ana"' /tmp/f214_edit.html || OK=1
grep -q 'value="Landing Page"' /tmp/f214_edit.html || OK=1
grep -q "Status History" /tmp/f214_edit.html || OK=1
grep -q "Received" /tmp/f214_edit.html || OK=1
curl -s -c $JA -b $JA -X POST "$B/admin/index.php?route=sale/order.edit|1" \
  --data-urlencode "csrf_token=$CSRF" \
  --data-urlencode "firstname=Ana" --data-urlencode "lastname=Souza" \
  --data-urlencode "email=ana@test.dev" \
  --data-urlencode "order_status_id=3" \
  --data-urlencode "order_product[0][name]=Landing Page" --data-urlencode "order_product[0][quantity]=1" --data-urlencode "order_product[0][price]=2000" \
  -D /tmp/f214_editp.hdr -o /dev/null
L=$(loc /tmp/f214_editp.hdr); [[ "$L" == *sale/order* ]] || OK=1
HI=$(q "SELECT COUNT(*) FROM reamur_test.rms_order_history WHERE order_id=1")
HIS=$(q "SELECT order_status_id FROM reamur_test.rms_order_history WHERE order_id=1 ORDER BY order_history_id DESC LIMIT 1")
TOT=$(q "SELECT total FROM reamur_test.rms_order WHERE order_id=1")
ST=$(q "SELECT order_status_id FROM reamur_test.rms_order WHERE order_id=1")
DM=$(q "SELECT date_modified > date_added FROM reamur_test.rms_order WHERE order_id=1")
[ "$HI" = "2" ] && [ "$HIS" = "3" ] && [ "$TOT" = "2000.0000" ] && [ "$ST" = "3" ] || { OK=1; echo "  dbg HI=$HI HIS=$HIS TOT=$TOT ST=$ST"; }
ck 9 $OK "edicao-status-historico-recalc"

echo '══ 10 — edição SEM mudar status → histórico NÃO cresce ══'
curl -s -c $JA -b $JA -X POST "$B/admin/index.php?route=sale/order.edit|1" \
  --data-urlencode "csrf_token=$CSRF" \
  --data-urlencode "firstname=Ana" --data-urlencode "lastname=Silva" \
  --data-urlencode "email=ana@test.dev" \
  --data-urlencode "order_status_id=3" \
  --data-urlencode "order_product[0][name]=Landing Page" --data-urlencode "order_product[0][quantity]=1" --data-urlencode "order_product[0][price]=2000" \
  -D /tmp/f214_editq.hdr -o /dev/null
HI=$(q "SELECT COUNT(*) FROM reamur_test.rms_order_history WHERE order_id=1")
OK=0; [ "$HI" = "2" ] || OK=1
LN=$(q "SELECT lastname FROM reamur_test.rms_order WHERE order_id=1"); [ "$LN" = "Silva" ] || OK=1
ck 10 $OK "edicao-sem-status-change"

echo '══ 11 — delete: GET não muta; POST apaga as 3 tabelas ══'
curl -s -c $JA -b $JA "$B/admin/index.php?route=sale/order.delete|1" -D /tmp/f214_dg.hdr -o /dev/null
L=$(loc /tmp/f214_dg.hdr); OK=0
[[ "$L" == *error/permission* ]] || OK=1
N=$(q "SELECT COUNT(*) FROM reamur_test.rms_order WHERE order_id=1"); [ "$N" = "1" ] || OK=1
curl -s -c $JA -b $JA -X POST "$B/admin/index.php?route=sale/order.delete|1" -D /tmp/f214_dp.hdr -o /dev/null --data-urlencode "csrf_token=$CSRF"
N1=$(q "SELECT COUNT(*) FROM reamur_test.rms_order")
N2=$(q "SELECT COUNT(*) FROM reamur_test.rms_order_product")
N3=$(q "SELECT COUNT(*) FROM reamur_test.rms_order_history")
[ "$N1" = "0" ] && [ "$N2" = "0" ] && [ "$N3" = "0" ] || OK=1
L=$(loc /tmp/f214_dp.hdr); [[ "$L" == *sale/order* ]] || OK=1
ck 11 $OK "delete-cascata-3-tabelas"

echo '══ 12 — idiomas pt-br / es-eu ══'
curl -s -c $JA -b $JA "$B/admin/index.php?route=sale/order&language=pt-br" -o /tmp/f214_pt.html
grep -q "Pedidos e Orçamentos" /tmp/f214_pt.html
A=$?
curl -s -c $JA -b $JA "$B/admin/index.php?route=sale/order&language=es-eu" -o /tmp/f214_es.html
grep -q "Pedidos y Presupuestos" /tmp/f214_es.html
ck 12 $(( A + $? )) "idiomas"

echo '══ 13 — ACL deny + migração idempotente (2x) ══'
sudo mysql reamur_test -e "UPDATE rms_user_group SET permission=JSON_REMOVE(permission, JSON_UNQUOTE(JSON_SEARCH(permission,'one','sale/order',NULL,'\$.access')), JSON_UNQUOTE(JSON_SEARCH(permission,'one','sale/order',NULL,'\$.modify'))) WHERE name='Administrator'"
curl -s -c $JA -b $JA "$B/admin/sale/order" -D /tmp/f214_acl.hdr -o /dev/null
L=$(loc /tmp/f214_acl.hdr); OK=0; [[ "$L" == *error/permission* ]] || OK=1
sudo mysql reamur_test < "$MIRROR/install/sql/upgrade_f2_14_sale_order_grant.sql"
sudo mysql reamur_test < "$MIRROR/install/sql/upgrade_f2_14_sale_order_grant.sql"
C=$(q "SELECT (LENGTH(permission) - LENGTH(REPLACE(permission,'sale/order',''))) DIV 10 FROM reamur_test.rms_user_group WHERE name='Administrator'")
[ "$C" = "2" ] || OK=1
curl -s -c $JA -b $JA "$B/admin/sale/order" -D /tmp/f214_acl2.hdr -o /dev/null
H=$(hdr /tmp/f214_acl2.hdr); [ "$H" = "200" ] || OK=1
ck 13 $OK "acl-deny-migracao"

echo '══ 14 — status inválido via POST adulterado → rejeitado ══'
curl -s -c $JA -b $JA -X POST "$B/admin/index.php?route=sale/order.add&language=en-us" \
  --data-urlencode "csrf_token=$CSRF" \
  --data-urlencode "firstname=Bob" --data-urlencode "email=" --data-urlencode "order_status_id=99" \
  -D /tmp/f214_st.hdr -o /tmp/f214_st.html
grep -q "valid e-mail\|Invalid status" /tmp/f214_st.html
A=$?
curl -s -c $JA -b $JA -X POST "$B/admin/index.php?route=sale/order.add&language=en-us" \
  --data-urlencode "csrf_token=$CSRF" \
  --data-urlencode "firstname=Bob" --data-urlencode "email=bob@test.dev" --data-urlencode "order_status_id=99" \
  -o /tmp/f214_st2.html
grep -q "Invalid status" /tmp/f214_st2.html
BBB=$?
C=$(q "SELECT COUNT(*) FROM reamur_test.rms_order"); [ "$C" = "0" ] || BBB=1
ck 14 $(( A + BBB )) "status-adulterado"

echo '══ 15 — preço adulterado (negativo/texto) → 0.0000 seguro; total confiável ══'
curl -s -c $JA -b $JA -X POST "$B/admin/index.php?route=sale/order.add&language=en-us" \
  --data-urlencode "csrf_token=$CSRF" \
  --data-urlencode "firstname=Eve" --data-urlencode "email=eve@test.dev" \
  --data-urlencode "order_product[0][name]=Item X" --data-urlencode "order_product[0][quantity]=5" --data-urlencode "order_product[0][price]=-100" \
  --data-urlencode "order_product[1][name]=Item Y" --data-urlencode "order_product[1][quantity]=2" --data-urlencode "order_product[1][price]=abc" \
  -D /tmp/f214_eve.hdr -o /dev/null
TOT=$(q "SELECT total FROM reamur_test.rms_order WHERE order_id=2")
OK=0; [ "$TOT" = "0.0000" ] || OK=1
Q=$(q "SELECT quantity FROM reamur_test.rms_order_product WHERE name='Item X'"); [ "$Q" = "5" ] || OK=1
ck 15 $OK "preco-adulterado-saneado"

echo
echo "═══ RESUMO: $PASS pass, $FAIL fail "
exit $([ $FAIL -eq 0 ] && echo 0 || echo 1)
