#!/usr/bin/env bash
# ═══ Bateria E2E — F2 Tarefa 16: Webhooks + Log (setting/webhook) ═══
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
JW=/tmp/f216w.jar; JA=/tmp/f216a.jar; rm -f $JW $JA

echo '══ 1 — wizard; seed traz grant setting/webhook (11ª rota) ══'
curl -s -c $JW -b $JW "$B/install/index.php?route=install/step_1" -o /dev/null
curl -s -c $JW -b $JW -X POST "$B/install/index.php?route=install/step_1" -o /dev/null
curl -s -c $JW -b $JW -X POST "$B/install/index.php?route=install/step_2" -o /dev/null
curl -s -c $JW -b $JW -X POST "$B/install/index.php?route=install/step_3" \
  --data-urlencode "db_driver=mysqli" --data-urlencode "db_hostname=127.0.0.1" --data-urlencode "db_port=3306" \
  --data-urlencode "db_username=reamur" --data-urlencode "db_password=reamur123" --data-urlencode "db_database=reamur_test" \
  --data-urlencode "db_prefix=rms_" --data-urlencode "http_server=" \
  --data-urlencode "username=admin" --data-urlencode "firstname=David" --data-urlencode "lastname=Almeida" \
  --data-urlencode "email=david@example.com" --data-urlencode "password=SenhaForte!2026" --data-urlencode "confirm=SenhaForte!2026" \
  -D /tmp/f216_i.hdr -o /dev/null
L=$(loc /tmp/f216_i.hdr); RC=0; [[ "$L" == *step_4* ]] || RC=1
G=$(q "SELECT JSON_SEARCH(permission, 'one', 'setting/webhook', NULL, '\$.access') IS NOT NULL FROM reamur_test.rms_user_group WHERE name='Administrator'")
[ "$G" = "1" ] || RC=1
ck 1 $RC "wizard-seed-grant"

echo '══ 2 — login ══'
curl -s -c $JA -b $JA "$B/admin/login" -o /tmp/f216_login.html -w "%{http_code}" | grep -q 200
TOKEN=$(grep -o 'name="user_token" value="[^"]*"' /tmp/f216_login.html | sed 's/.*value="//;s/"//')
curl -s -c $JA -b $JA -X POST "$B/admin/index.php?route=common/login" \
  --data-urlencode "username=admin" --data-urlencode "password=SenhaForte!2026" --data-urlencode "user_token=$TOKEN" \
  -D /tmp/f216_al.hdr -o /dev/null
L=$(loc /tmp/f216_al.hdr); [[ "$L" == *dashboard* ]]
ck 2 $? "admin-login"

echo '══ 3 — lista vazia honesta ══'
curl -s -c $JA -b $JA "$B/admin/setting/webhook" -o /tmp/f216_list0.html -D /tmp/f216_l0.hdr
H=$(hdr /tmp/f216_l0.hdr)
OK=0; [ "$H" = "200" ] || OK=1
grep -q "Webhooks" /tmp/f216_list0.html || OK=1
grep -qi "No webhooks yet" /tmp/f216_list0.html || OK=1
[ "$(grep -o 'secret-mask' /tmp/f216_list0.html | wc -l)" = "0" ] || OK=1
ck 3 $OK "lista-vazia"

echo '══ 4 — dashboard cartão Webhooks ══'
curl -s -c $JA -b $JA "$B/admin/common/dashboard" -o /tmp/f216_dash.html
grep -q "Webhooks" /tmp/f216_dash.html && grep -q "setting/webhook" /tmp/f216_dash.html
ck 4 $? "dashboard-card"

echo '══ 5 — form: datalist de triggers, gerador de secret, hint https ══'
curl -s -c $JA -b $JA "$B/admin/index.php?route=setting/webhook.add&language=en-us" -o /tmp/f216_form.html -D /tmp/f216_f.hdr
H=$(hdr /tmp/f216_f.hdr)
OK=0; [ "$H" = "200" ] || OK=1
grep -q 'list="trigger-options"' /tmp/f216_form.html || OK=1
grep -q 'btn-gen-secret' /tmp/f216_form.html || OK=1
grep -q 'placeholder="https://"' /tmp/f216_form.html || OK=1
grep -q 'order_add' /tmp/f216_form.html || OK=1
ck 5 $OK "form-operacional"

echo '══ 6 — validações da régua §10 ══'
curl -s -c $JA -b $JA -X POST "$B/admin/index.php?route=setting/webhook.add&language=en-us" \
  --data-urlencode "trigger=Bad Trigger!" --data-urlencode "url=http://inseguro.dev/hook" \
  --data-urlencode "secret=curto" --data-urlencode "description=Teste" \
  -o /tmp/f216_v.html
OK=0
grep -q "Invalid trigger" /tmp/f216_v.html || OK=1
grep -q 'must start with https://' /tmp/f216_v.html || OK=1
grep -q "16 to 64" /tmp/f216_v.html || OK=1
C=$(q "SELECT COUNT(*) FROM reamur_test.rms_webhook"); [ "$C" = "0" ] || OK=1
ck 6 $OK "validacoes"

echo '══ 7 — create ok + secret MASCARADO na grade ══'
curl -s -c $JA -b $JA -X POST "$B/admin/index.php?route=setting/webhook.add&language=en-us" \
  --data-urlencode "trigger=order_add" --data-urlencode "url=https://hooks.example.dev/orders" \
  --data-urlencode "secret=aabbccddeeff0011aabbccddeeff0011" --data-urlencode "description=ERP sync" --data-urlencode "status=1" \
  -D /tmp/f216_c.hdr -o /dev/null
L=$(loc /tmp/f216_c.hdr); OK=0; [[ "$L" == *setting/webhook* ]] || OK=1
N=$(q "SELECT COUNT(*) FROM reamur_test.rms_webhook WHERE \`trigger\`='order_add'"); [ "$N" = "1" ] || OK=1
curl -s -c $JA -b $JA "$B/admin/setting/webhook?language=en-us" -o /tmp/f216_list1.html
grep -q "order_add" /tmp/f216_list1.html || OK=1
grep -q "aabbccddeeff0011aabbccddeeff0011" /tmp/f216_list1.html && OK=1   # NÃO pode vazar secret integral
grep -q "Webhook created" /tmp/f216_list1.html || OK=1
ck 7 $OK "create-secret-mascarado"

echo '══ 8 — edit: desativa (checkbox ausente) + descrição nova ══'
W1=$(q "SELECT webhook_id FROM reamur_test.rms_webhook WHERE \`trigger\`='order_add'")
curl -s -c $JA -b $JA -X POST "$B/admin/index.php?route=setting/webhook.edit|$W1&language=en-us" \
  --data-urlencode "trigger=order_add" --data-urlencode "url=https://hooks.example.dev/orders" \
  --data-urlencode "secret=aabbccddeeff0011aabbccddeeff0011" --data-urlencode "description=ERP sync (hold)" \
  -D /tmp/f216_e.hdr -o /dev/null
L=$(loc /tmp/f216_e.hdr); OK=0; [[ "$L" == *setting/webhook* ]] || OK=1
S=$(q "SELECT status FROM reamur_test.rms_webhook WHERE webhook_id=$W1"); [ "$S" = "0" ] || OK=1
D=$(q "SELECT description FROM reamur_test.rms_webhook WHERE webhook_id=$W1"); [ "$D" = "ERP sync (hold)" ] || OK=1
ck 8 $OK "edit-status-off"

echo '══ 9 — duplicata trigger+url: create ok, edit colide → rejeitado ══'
curl -s -c $JA -b $JA -X POST "$B/admin/index.php?route=setting/webhook.add&language=en-us" \
  --data-urlencode "trigger=order_edit" --data-urlencode "url=https://hooks.example.dev/orders-b" \
  --data-urlencode "secret=11223344556677889900aabbccddeeff" --data-urlencode "description=ERP sync B" \
  -D /tmp/f216_c2.hdr -o /dev/null
W2=$(q "SELECT webhook_id FROM reamur_test.rms_webhook WHERE \`trigger\`='order_edit'")
OK=0; [ -n "$W2" ] || OK=1
# edit do W2 colidindo com o par (order_add, /orders) do W1
curl -s -c $JA -b $JA -X POST "$B/admin/index.php?route=setting/webhook.edit|$W2&language=en-us" \
  --data-urlencode "trigger=order_add" --data-urlencode "url=https://hooks.example.dev/orders" \
  --data-urlencode "secret=11223344556677889900aabbccddeeff" --data-urlencode "description=ERP sync B" \
  -o /tmp/f216_dup.html
grep -q "already exists" /tmp/f216_dup.html || OK=1
T=$(q "SELECT \`trigger\` FROM reamur_test.rms_webhook WHERE webhook_id=$W2"); [ "$T" = "order_edit" ] || OK=1
ck 9 $OK "duplicata-rejeitada"

echo '══ 10 — fixtures de entregas (dispatcher gera; admin só lê) ══'
sudo mysql reamur_test <<SQLFIX
INSERT INTO rms_webhook_log (webhook_id,delivery,payload,http_code,duration,attempt,date_added) VALUES
 ($W1,'d1','{"order_id":7,"action":"order_add"}',200,0.123,1,NOW()),
 ($W1,'d2','{"error":"timeout"}',500,1.456,3,NOW() - INTERVAL 1 HOUR),
 ($W2,'d3','{"order_id":8,"action":"order_edit"}',200,0.210,1,NOW() - INTERVAL 2 HOUR);
SQLFIX
N=$(q "SELECT COUNT(*) FROM reamur_test.rms_webhook_log"); [ "$N" = "3" ]
ck 10 $? "fixtures-log"

echo '══ 11 — logs|1 filtra 2; logs|2 filtra 1; logs|0 mostra todas ══'
curl -s -c $JA -b $JA "$B/admin/index.php?route=setting/webhook.logs|$W1&language=en-us" -o /tmp/f216_l1.html
curl -s -c $JA -b $JA "$B/admin/index.php?route=setting/webhook.logs|$W2&language=en-us" -o /tmp/f216_l2.html
curl -s -c $JA -b $JA "$B/admin/index.php?route=setting/webhook.logs|0&language=en-us"  -o /tmp/f216_l0f.html
OK=0
[ "$(grep -o 'class="delivery-' /tmp/f216_l1.html | wc -l)" = "2" ] || OK=1
[ "$(grep -o 'class="delivery-' /tmp/f216_l2.html | wc -l)" = "1" ] || OK=1
[ "$(grep -o 'class="delivery-' /tmp/f216_l0f.html | wc -l)" = "3" ] || OK=1
grep -q ">500<" /tmp/f216_l1.html || OK=1
ck 11 $OK "logs-filtros"

echo '══ 12 — detalhe da entrega: payload pretty, read-only ══'
LID1=$(q "SELECT webhook_log_id FROM reamur_test.rms_webhook_log WHERE delivery='d1'")
curl -s -c $JA -b $JA "$B/admin/index.php?route=setting/webhook.log|$LID1&language=en-us" -o /tmp/f216_log1.html -D /tmp/f216_lg1.hdr
H=$(hdr /tmp/f216_lg1.hdr)
OK=0; [ "$H" = "200" ] || OK=1
grep -q "order_id&quot;: 7" /tmp/f216_log1.html || OK=1
grep -q '>d1<' /tmp/f216_log1.html || OK=1
grep -q 'payload-body' /tmp/f216_log1.html || OK=1
ck 12 $OK "log-detalhe"

echo '══ 13 — delete webhook 2: cascata manual nas ENTREGAS dele apenas ══'
curl -s -c $JA -b $JA -X POST "$B/admin/index.php?route=setting/webhook.delete|$W2" -D /tmp/f216_d.hdr -o /dev/null
L=$(loc /tmp/f216_d.hdr); OK=0; [[ "$L" == *setting/webhook* ]] || OK=1
N2=$(q "SELECT COUNT(*) FROM reamur_test.rms_webhook WHERE webhook_id=$W2"); [ "$N2" = "0" ] || OK=1
N3=$(q "SELECT COUNT(*) FROM reamur_test.rms_webhook_log WHERE webhook_id=$W2"); [ "$N3" = "0" ] || OK=1
N4=$(q "SELECT COUNT(*) FROM reamur_test.rms_webhook_log WHERE webhook_id=$W1"); [ "$N4" = "2" ] || OK=1
ck 13 $OK "delete-cascata"

echo '══ 14 — GET em delete jamais exclui ══'
curl -s -c $JA -b $JA "$B/admin/index.php?route=setting/webhook.delete|$W1" -D /tmp/f216_gd.hdr -o /dev/null
L=$(loc /tmp/f216_gd.hdr); OK=0
[[ "$L" == *error/permission* ]] || OK=1
N=$(q "SELECT COUNT(*) FROM reamur_test.rms_webhook WHERE webhook_id=$W1"); [ "$N" = "1" ] || OK=1
ck 14 $OK "get-delete-nao-muta"

echo '══ 15 — tamper: status=9 coercido; http:// rejeitado sem mutação ══'
curl -s -c $JA -b $JA -X POST "$B/admin/index.php?route=setting/webhook.edit|$W1&language=en-us" \
  --data-urlencode "trigger=order_add" --data-urlencode "url=https://hooks.example.dev/orders" \
  --data-urlencode "secret=aabbccddeeff0011aabbccddeeff0011" --data-urlencode "description=ERP sync T" \
  --data-urlencode "status=9" -D /tmp/f216_t1.hdr -o /dev/null
S=$(q "SELECT status FROM reamur_test.rms_webhook WHERE webhook_id=$W1"); OK=0
{ [ "$S" = "0" ] || [ "$S" = "1" ]; } || OK=1
curl -s -c $JA -b $JA -X POST "$B/admin/index.php?route=setting/webhook.edit|$W1&language=en-us" \
  --data-urlencode "trigger=order_add" --data-urlencode "url=http://quebrado.dev/hook" \
  --data-urlencode "secret=aabbccddeeff0011aabbccddeeff0011" --data-urlencode "description=ERP sync T" \
  -o /tmp/f216_t2.html
U=$(q "SELECT url FROM reamur_test.rms_webhook WHERE webhook_id=$W1")
[ "$U" = "https://hooks.example.dev/orders" ] || OK=1
grep -q 'must start with https://' /tmp/f216_t2.html || OK=1
ck 15 $OK "tamper-coercao"

echo '══ 16 — idiomas pt-br / es-eu (depois reseta p/ en-us) ══'
curl -s -c $JA -b $JA "$B/admin/index.php?route=setting/webhook&language=pt-br" -o /tmp/f216_pt.html
grep -q "nos eventos do site" /tmp/f216_pt.html
A=$?
curl -s -c $JA -b $JA "$B/admin/index.php?route=setting/webhook&language=es-eu" -o /tmp/f216_es.html
grep -q "en los eventos del sitio" /tmp/f216_es.html
BBB=$?
curl -s -c $JA -b $JA "$B/admin/index.php?route=setting/webhook&language=en-us" -o /dev/null
ck 16 $(( A + BBB )) "idiomas"

echo '══ 17 — ACL deny + migração idempotente (2x) ══'
sudo mysql reamur_test -e "UPDATE rms_user_group SET permission=JSON_REMOVE(permission, JSON_UNQUOTE(JSON_SEARCH(permission,'one','setting/webhook',NULL,'\$.access')), JSON_UNQUOTE(JSON_SEARCH(permission,'one','setting/webhook',NULL,'\$.modify'))) WHERE name='Administrator'"
curl -s -c $JA -b $JA "$B/admin/setting/webhook" -D /tmp/f216_acl.hdr -o /dev/null
L=$(loc /tmp/f216_acl.hdr); OK=0; [[ "$L" == *error/permission* ]] || OK=1
sudo mysql reamur_test < "$MIRROR/install/sql/upgrade_f2_16_webhook_grant.sql"
sudo mysql reamur_test < "$MIRROR/install/sql/upgrade_f2_16_webhook_grant.sql"
C=$(q "SELECT (LENGTH(permission) - LENGTH(REPLACE(permission,'setting/webhook',''))) DIV 15 FROM reamur_test.rms_user_group WHERE name='Administrator'")
[ "$C" = "2" ] || OK=1
curl -s -c $JA -b $JA "$B/admin/setting/webhook" -D /tmp/f216_acl2.hdr -o /dev/null
H=$(hdr /tmp/f216_acl2.hdr); [ "$H" = "200" ] || OK=1
ck 17 $OK "acl-deny-migracao"

echo
echo "═══ RESUMO: $PASS pass, $FAIL fail "
exit $([ $FAIL -eq 0 ] && echo 0 || echo 1)
