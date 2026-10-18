#!/usr/bin/env bash
# ═══ Bateria E2E — F2 Tarefa 15: Inbox de Contato (communication/contact_message) ═══
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
JW=/tmp/f215w.jar; JA=/tmp/f215a.jar; rm -f $JW $JA

echo '══ 1 — wizard; seed traz grant communication/contact_message ══'
curl -s -c $JW -b $JW "$B/install/index.php?route=install/step_1" -o /dev/null
curl -s -c $JW -b $JW -X POST "$B/install/index.php?route=install/step_1" -o /dev/null
curl -s -c $JW -b $JW -X POST "$B/install/index.php?route=install/step_2" -o /dev/null
curl -s -c $JW -b $JW -X POST "$B/install/index.php?route=install/step_3" \
  --data-urlencode "db_driver=mysqli" --data-urlencode "db_hostname=127.0.0.1" --data-urlencode "db_port=3306" \
  --data-urlencode "db_username=reamur" --data-urlencode "db_password=reamur123" --data-urlencode "db_database=reamur_test" \
  --data-urlencode "db_prefix=rms_" --data-urlencode "http_server=" \
  --data-urlencode "username=admin" --data-urlencode "firstname=David" --data-urlencode "lastname=Almeida" \
  --data-urlencode "email=david@example.com" --data-urlencode "password=SenhaForte!2026" --data-urlencode "confirm=SenhaForte!2026" \
  -D /tmp/f215_i.hdr -o /dev/null
L=$(loc /tmp/f215_i.hdr); RC=0; [[ "$L" == *step_4* ]] || RC=1
G=$(q "SELECT JSON_SEARCH(permission, 'one', 'communication/contact_message', NULL, '\$.access') IS NOT NULL FROM reamur_test.rms_user_group WHERE name='Administrator'")
[ "$G" = "1" ] || RC=1
ck 1 $RC "wizard-seed-grant"

echo '══ 2 — login ══'
curl -s -c $JA -b $JA "$B/admin/login" -o /tmp/f215_login.html -w "%{http_code}" | grep -q 200
TOKEN=$(grep -o 'name="user_token" value="[^"]*"' /tmp/f215_login.html | sed 's/.*value="//;s/"//')
curl -s -c $JA -b $JA -X POST "$B/admin/index.php?route=common/login" \
  --data-urlencode "username=admin" --data-urlencode "password=SenhaForte!2026" --data-urlencode "user_token=$TOKEN" \
  -D /tmp/f215_al.hdr -o /dev/null
L=$(loc /tmp/f215_al.hdr); [[ "$L" == *dashboard* ]]
ck 2 $? "admin-login"

echo '══ 3 — lista vazia honesta ══'
curl -s -c $JA -b $JA "$B/admin/communication/contact_message" -o /tmp/f215_list0.html -D /tmp/f215_l0.hdr
H=$(hdr /tmp/f215_l0.hdr)
OK=0; [ "$H" = "200" ] || OK=1
grep -q "Contact Messages" /tmp/f215_list0.html || OK=1
grep -qi "No messages yet" /tmp/f215_list0.html || OK=1
grep -c "row-status" /tmp/f215_list0.html | grep -q "^0$" || OK=1
ck 3 $OK "lista-vazia"

echo '══ 4 — dashboard cartão Messages ══'
curl -s -c $JA -b $JA "$B/admin/common/dashboard" -o /tmp/f215_dash.html
grep -q "Messages" /tmp/f215_dash.html && grep -q "communication/contact_message" /tmp/f215_dash.html
ck 4 $? "dashboard-card"

echo '══ 5 — fixtures (o site envia; admin não cria) + grade assimétrica ══'
sudo mysql reamur_test -e "INSERT INTO rms_contact_message (name,email,telephone,subject,message,ip,status,date_added) VALUES
 ('Ana Souza','ana@test.dev','+55 19 99999-0001','Orçamento landing','Quero um orçamento para a landing page do produto X.','203.0.113.10',0,NOW()),
 ('Bruno Lima','bruno@test.dev','+55 11 98888-0002','Parceria','Proposta de parceria para Q4.','198.51.100.7',0,NOW() - INTERVAL 1 DAY);"
curl -s -c $JA -b $JA "$B/admin/communication/contact_message" -o /tmp/f215_list1.html
OK=0
grep -q "Ana Souza" /tmp/f215_list1.html && grep -q "Bruno Lima" /tmp/f215_list1.html || OK=1
[ "$(grep -o 'row-status-0' /tmp/f215_list1.html | wc -l)" = "2" ] || OK=1
grep -q "contato_add\|contact_message.add" /tmp/f215_list1.html && OK=1   # assimétrico: NÃO pode existir rota .add
ck 5 $OK "fixtures-grade-sem-add"

echo '══ 6 — filtro por status: lido ainda vazio (texto próprio) ══'
curl -s -c $JA -b $JA "$B/admin/index.php?route=communication/contact_message&filter_status=1&language=en-us" -o /tmp/f215_f1.html
OK=0
grep -q "No messages with this status" /tmp/f215_f1.html || OK=1
[ "$(grep -o 'row-status' /tmp/f215_f1.html | wc -l)" = "0" ] || OK=1
ck 6 $OK "filtro-vazio"

echo '══ 7 — leitura da msg 1: render + AUTO-MARCAR lido (0→1) ══'
ID1=$(q "SELECT contact_message_id FROM reamur_test.rms_contact_message WHERE email='ana@test.dev'")
curl -s -c $JA -b $JA "$B/admin/index.php?route=communication/contact_message.read|$ID1&language=en-us" -o /tmp/f215_read1.html -D /tmp/f215_r1.hdr
H=$(hdr /tmp/f215_r1.hdr)
OK=0; [ "$H" = "200" ] || OK=1
grep -q "landing page do produto X" /tmp/f215_read1.html || OK=1
grep -q "203.0.113.10" /tmp/f215_read1.html || OK=1
grep -q "mailto:ana@test.dev" /tmp/f215_read1.html || OK=1
S=$(q "SELECT status FROM reamur_test.rms_contact_message WHERE contact_message_id=$ID1"); [ "$S" = "1" ] || OK=1
ck 7 $OK "read-auto-mark"

echo '══ 8 — filtros refletem: 1 lida, 1 nova, 2 todas ══'
curl -s -c $JA -b $JA "$B/admin/index.php?route=communication/contact_message&filter_status=1&language=en-us" -o /tmp/f215_f1b.html
curl -s -c $JA -b $JA "$B/admin/index.php?route=communication/contact_message&filter_status=0&language=en-us" -o /tmp/f215_f0b.html
curl -s -c $JA -b $JA "$B/admin/index.php?route=communication/contact_message&language=en-us" -o /tmp/f215_fallb.html
OK=0
[ "$(grep -o 'row-status' /tmp/f215_f1b.html | wc -l)" = "1" ] || OK=1
[ "$(grep -o 'row-status' /tmp/f215_f0b.html | wc -l)" = "1" ] || OK=1
[ "$(grep -o 'row-status' /tmp/f215_fallb.html | wc -l)" = "2" ] || OK=1
ck 8 $OK "filtros-contagem"

echo '══ 9 — POST status → respondido (1→2), flash confirma ══'
curl -s -c $JA -b $JA -X POST "$B/admin/index.php?route=communication/contact_message.status|$ID1" \
  --data-urlencode "status=2" --data-urlencode "from=read" -D /tmp/f215_s9.hdr -o /dev/null
S=$(q "SELECT status FROM reamur_test.rms_contact_message WHERE contact_message_id=$ID1")
OK=0; [ "$S" = "2" ] || OK=1
curl -s -c $JA -b $JA "$B/admin/index.php?route=communication/contact_message.read|$ID1&language=en-us" -o /tmp/f215_s9h.html
grep -q "Message status updated" /tmp/f215_s9h.html || OK=1
grep -q "Mark as Replied\" disabled\|disabled style" /tmp/f215_s9h.html >/dev/null 2>&1 || true
ck 9 $OK "status-respondido"

echo '══ 10 — POST adulterado status=9 → rejeitado, sem mutação ══'
curl -s -c $JA -b $JA -X POST "$B/admin/index.php?route=communication/contact_message.status|$ID1" \
  --data-urlencode "status=9" --data-urlencode "from=read" -D /tmp/f215_s10.hdr -o /dev/null
S=$(q "SELECT status FROM reamur_test.rms_contact_message WHERE contact_message_id=$ID1")
OK=0; [ "$S" = "2" ] || OK=1
curl -s -c $JA -b $JA "$B/admin/index.php?route=communication/contact_message.read|$ID1&language=en-us" -o /tmp/f215_s10h.html
grep -q "Invalid status" /tmp/f215_s10h.html || OK=1
ck 10 $OK "status-adulterado"

echo '══ 11 — GET em .status jamais muta ══'
ID2=$(q "SELECT contact_message_id FROM reamur_test.rms_contact_message WHERE email='bruno@test.dev'")
curl -s -c $JA -b $JA "$B/admin/index.php?route=communication/contact_message.status|$ID2&status=1" -D /tmp/f215_s11.hdr -o /dev/null
L=$(loc /tmp/f215_s11.hdr); OK=0
[[ "$L" == *error/permission* ]] || OK=1
S=$(q "SELECT status FROM reamur_test.rms_contact_message WHERE contact_message_id=$ID2"); [ "$S" = "0" ] || OK=1
ck 11 $OK "get-status-nao-muta"

echo '══ 12 — delete POST some; GET jamais exclui ══'
curl -s -c $JA -b $JA "$B/admin/index.php?route=communication/contact_message.delete|$ID2" -D /tmp/f215_dg.hdr -o /dev/null
L=$(loc /tmp/f215_dg.hdr); OK=0
[[ "$L" == *error/permission* ]] || OK=1
N=$(q "SELECT COUNT(*) FROM reamur_test.rms_contact_message WHERE contact_message_id=$ID2"); [ "$N" = "1" ] || OK=1
curl -s -c $JA -b $JA -X POST "$B/admin/index.php?route=communication/contact_message.delete|$ID2" -D /tmp/f215_dp.hdr -o /dev/null
N=$(q "SELECT COUNT(*) FROM reamur_test.rms_contact_message WHERE contact_message_id=$ID2"); [ "$N" = "0" ] || OK=1
L=$(loc /tmp/f215_dp.hdr); [[ "$L" == *communication/contact_message* ]] || OK=1
curl -s -c $JA -b $JA "$B/admin/index.php?route=communication/contact_message&language=en-us" -o /tmp/f215_lpost.html
grep -q "Message deleted" /tmp/f215_lpost.html || OK=1
ck 12 $OK "delete-post-only"

echo '══ 13 — idiomas pt-br / es-eu (depois reseta p/ en-us) ══'
curl -s -c $JA -b $JA "$B/admin/index.php?route=communication/contact_message&language=pt-br" -o /tmp/f215_pt.html
grep -q "Mensagens de Contato" /tmp/f215_pt.html
A=$?
curl -s -c $JA -b $JA "$B/admin/index.php?route=communication/contact_message&language=es-eu" -o /tmp/f215_es.html
grep -q "Mensajes de Contacto" /tmp/f215_es.html
BBB=$?
curl -s -c $JA -b $JA "$B/admin/index.php?route=communication/contact_message&language=en-us" -o /dev/null
ck 13 $(( A + BBB )) "idiomas"

echo '══ 14 — ACL deny + migração idempotente (2x) ══'
sudo mysql reamur_test -e "UPDATE rms_user_group SET permission=JSON_REMOVE(permission, JSON_UNQUOTE(JSON_SEARCH(permission,'one','communication/contact_message',NULL,'\$.access')), JSON_UNQUOTE(JSON_SEARCH(permission,'one','communication/contact_message',NULL,'\$.modify'))) WHERE name='Administrator'"
curl -s -c $JA -b $JA "$B/admin/communication/contact_message" -D /tmp/f215_acl.hdr -o /dev/null
L=$(loc /tmp/f215_acl.hdr); OK=0; [[ "$L" == *error/permission* ]] || OK=1
sudo mysql reamur_test < "$MIRROR/install/sql/upgrade_f2_15_contact_message_grant.sql"
sudo mysql reamur_test < "$MIRROR/install/sql/upgrade_f2_15_contact_message_grant.sql"
C=$(q "SELECT (LENGTH(permission) - LENGTH(REPLACE(permission,'communication/contact_message',''))) DIV 27 FROM reamur_test.rms_user_group WHERE name='Administrator'")
[ "$C" = "2" ] || OK=1
curl -s -c $JA -b $JA "$B/admin/communication/contact_message" -D /tmp/f215_acl2.hdr -o /dev/null
H=$(hdr /tmp/f215_acl2.hdr); [ "$H" = "200" ] || OK=1
ck 14 $OK "acl-deny-migracao"

echo '══ 15 — leitura de id inexistente → 404 route, sem quebrar ══'
curl -s -c $JA -b $JA "$B/admin/index.php?route=communication/contact_message.read|999&language=en-us" -D /tmp/f215_nf.hdr -o /dev/null
L=$(loc /tmp/f215_nf.hdr); OK=0; [[ "$L" == *error/not_found* ]] || OK=1
N=$(q "SELECT COUNT(*) FROM reamur_test.rms_contact_message"); [ "$N" = "1" ] || OK=1
ck 15 $OK "read-inexistente"

echo
echo "═══ RESUMO: $PASS pass, $FAIL fail "
exit $([ $FAIL -eq 0 ] && echo 0 || echo 1)
