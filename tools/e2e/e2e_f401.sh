#!/usr/bin/env bash
# ════════════════════════════════════════════════════════════════════
# e2e_f401.sh — Configurações operacionais do painel (ADR 0024)
#
# Padrão de bateria E2E: cada rodada = instalação completamente nova.
#   Drop do DB + wizard + aplica o upgrade F4-01 2× (idempotência).
#
# Checks:
#   1 wizard-roda + upgrade grant setting/setting semeado
#   2 login admin
#   3 muralha: GET setting/setting sem sessão → 302 p/ login
#   4 tela GET autenticada: 200, 3 fieldsets (Store/Mail/Cache), form
#     preenchido com os seeds do upgrade (DavidCreator, 3600)
#   5 POST válido: 302 e grava 5 chaves no rms_setting
#   6 round-trip + aplicação real no catálogo (fallback meta na 404 do
#     CMS; home NÃO é pisada)
#   7 validação: smtp sem hostname + ttl curto → volta 200 com alerta,
#     banco NÃO muda
#   8 clearCache: POST dedicado (302) esvazia system/storage/cache
#   9 ACL: retirar grant → módulo some do dashboard; restore
#  10 i18n ×3: heading + fieldsets (pt-br/es-eu/en-us)
# ════════════════════════════════════════════════════════════════════
set -u
B="${B:-http://127.0.0.1:8090/davidcms/dev}"
FUI="${FUI:-/var/www/html/davidcms/dev}"
MIRROR="${MIRROR:-/home/user/davidcms/dev}"
A="$B/admin"
CACHE_DIR="$FUI/system/storage/cache"
PASS=0; FAIL=0
ck() { if [ "${2:-0}" = "0" ]; then PASS=$((PASS+1)); echo "$1 pass — $3"; else FAIL=$((FAIL+1)); echo "$1 FAIL — $3"; fi; }
hdr() { grep -i "^HTTP" "$1" | tail -1 | grep -o "[0-9]\{3\}"; }
loc() { grep -i "^location" "$1" | tail -1 | tr -d '\r' | cut -d' ' -f2-; }
q() { sudo mysql -N -e "$1"; }

echo '══ setup: estado virgem ══'
sudo mysql -e "DROP DATABASE IF EXISTS reamur_test; CREATE DATABASE reamur_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
rm -f $FUI/catalog/config.php $FUI/admin/config.php $FUI/system/storage/install.lock
# fresh install = cache de arquivo completamente vazio (padrão das outras
# baterias que apagam uploads gerados); senão a leitura cai no cache velho
sudo rm -f $FUI/system/storage/cache/* 2>/dev/null
JW=/tmp/f401w.jar; JA=/tmp/f401a.jar; rm -f $JW $JA

echo '══ 1 — wizard + upgrade F4-01 (2× = idempotente) ══'
curl -s -c $JW -b $JW "$B/install/index.php?route=install/step_1" -o /dev/null
curl -s -c $JW -b $JW -X POST "$B/install/index.php?route=install/step_1" -o /dev/null
curl -s -c $JW -b $JW -X POST "$B/install/index.php?route=install/step_2" -o /dev/null
curl -s -c $JW -b $JW -X POST "$B/install/index.php?route=install/step_3" \
  --data-urlencode "db_driver=mysqli" --data-urlencode "db_hostname=127.0.0.1" --data-urlencode "db_port=3306" \
  --data-urlencode "db_username=reamur" --data-urlencode "db_password=reamur123" --data-urlencode "db_database=reamur_test" \
  --data-urlencode "db_prefix=rms_" --data-urlencode "http_server=" \
  --data-urlencode "username=admin" --data-urlencode "firstname=David" --data-urlencode "lastname=Almeida" \
  --data-urlencode "email=david@example.com" --data-urlencode "password=SenhaForte!2026" --data-urlencode "confirm=SenhaForte!2026" \
  -D /tmp/f401_i.hdr -o /dev/null
L=$(loc /tmp/f401_i.hdr); RC=0; [[ "$L" == *step_4* ]] || RC=1
sudo mysql reamur_test < "$MIRROR/install/sql/upgrade_f4_01_setting_op_grant.sql"
sudo mysql reamur_test < "$MIRROR/install/sql/upgrade_f4_01_setting_op_grant.sql"
for u in "$MIRROR"/install/sql/upgrade_f4_02*.sql; do
  [ -f "$u" ] && sudo mysql reamur_test < "$u"
done
G=$(q "SELECT JSON_SEARCH(permission,'one','setting/setting',NULL,'\$.access') IS NOT NULL FROM reamur_test.rms_user_group WHERE name='Administrator'")
[ "$G" = "1" ] || RC=1
S=$(q "SELECT COUNT(*) FROM reamur_test.rms_setting WHERE code='config'")
[ "$S" -ge "6" ] || RC=1   # 6 seeds base (+ seeds aditivas de upgrades desta linha — F4-02 etc.); tolerante p/ não quebrar baterias futuras
ck 1 $RC "wizard-upgrade-idem"

echo '══ 2 — login admin ══'
curl -s -c $JA -b $JA "$A/login" -o /tmp/f401_login.html -w "%{http_code}" | grep -q 200
RT=$?
TOKEN=$(grep -o 'name="user_token" value="[^"]*"' /tmp/f401_login.html | sed 's/.*value="//;s/"//')
CSRF=$(grep -o 'name="csrf_token" value="[^"]*"' /tmp/f401_login.html | sed 's/.*value="//;s/"//')
curl -s -c $JA -b $JA -X POST "$A/index.php?route=common/login" \
  --data-urlencode "username=admin" --data-urlencode "password=SenhaForte!2026" \
  --data-urlencode "user_token=$TOKEN" --data-urlencode "csrf_token=$CSRF" \
  -D /tmp/f401_al.hdr -o /dev/null
L=$(loc /tmp/f401_al.hdr); [[ "$L" == *dashboard* ]]
ck 2 $((RT + $?)) "admin-login"

echo '══ 3 — muralha: sem sessão → 302 p/ login ══'
curl -s -D /tmp/f401_anon.hdr -o /dev/null "$A/index.php?route=setting/setting"
OK=0
[ "$(hdr /tmp/f401_anon.hdr)" = "302" ] || OK=1
L=$(loc /tmp/f401_anon.hdr); [[ "$L" == *login* ]] || OK=1
ck 3 $OK "anon-302"

echo '══ 4 — GET autenticado: 200, fieldsets, seeds preenchidos ══'
curl -s -c $JA -b $JA -D /tmp/f401_get.hdr "$A/index.php?route=setting/setting" -o /tmp/f401_get.html
OK=0
[ "$(hdr /tmp/f401_get.hdr)" = "200" ] || OK=1
grep -q "Settings" /tmp/f401_get.html || OK=1
grep -q "Store\b" /tmp/f401_get.html || OK=1
grep -q "Mail\b" /tmp/f401_get.html || OK=1
grep -q "Cache\b" /tmp/f401_get.html || OK=1
grep -q 'value="DavidCreator' /tmp/f401_get.html || OK=1
grep -q 'value="3600"' /tmp/f401_get.html || OK=1
grep -q 'name="mail_smtp_hostname"' /tmp/f401_get.html || OK=1
grep -q 'name="cache_expire"' /tmp/f401_get.html || OK=1
# módulo aparece no dashboard (grant existente)
curl -s -c $JA -b $JA "$A/common/dashboard" -o /tmp/f401_dash0.html
grep -q "Settings" /tmp/f401_dash0.html || OK=1
ck 4 $OK "get-200-seeds"

echo '══ 5 — POST válido: grava 5 chaves no rms_setting ══'
curl -s -c $JA -b $JA -X POST "$A/index.php?route=setting/setting" \
  --data-urlencode "csrf_token=$CSRF" \
  --data-urlencode "config_name=DavidCreatorX" \
  --data-urlencode "config_meta_title=Meta X F4-01" \
  --data-urlencode "config_meta_description=Desc custom F4-01 test" \
  --data-urlencode "mail_engine=mail" \
  --data-urlencode "mail_smtp_hostname=" --data-urlencode "mail_smtp_username=" \
  --data-urlencode "mail_smtp_password=" --data-urlencode "mail_smtp_port=" \
  --data-urlencode "mail_smtp_timeout=" \
  --data-urlencode "mail_reply_to=noreply@x.com" \
  --data-urlencode "cache_engine=file" --data-urlencode "cache_expire=7200" \
  -D /tmp/f401_save.hdr -o /dev/null
OK=0
[ "$(hdr /tmp/f401_save.hdr)" = "302" ] || OK=1
for kv in "config_name|DavidCreatorX" "config_meta_title|Meta X F4-01" "config_meta_description|Desc custom F4-01 test" "mail_reply_to|noreply@x.com" "cache_expire|7200"; do
  k="${kv%%|*}"; v="${kv##*|}"
  RT=$(q "SELECT \`value\` FROM reamur_test.rms_setting WHERE code='config' AND \`key\`='$k'")
  [ "$RT" = "$v" ] || OK=1
done
ck 5 $OK "save-grava"

echo '══ 6 — round-trip + fallback no catálogo (aplicação real) ══'
curl -s -c $JA -b $JA "$A/index.php?route=setting/setting" -o /tmp/f401_get2.html
OK=0
grep -q 'value="DavidCreatorX"' /tmp/f401_get2.html || OK=1
grep -q 'value="Meta X F4-01"' /tmp/f401_get2.html || OK=1
grep -q 'value="7200"' /tmp/f401_get2.html || OK=1
# home tem title/description próprios (language) → NÃO deve ser pisada
curl -s "$B/catalog/" -o /tmp/f401_home.html
grep -q "Meta X F4-01" /tmp/f401_home.html && OK=1
# error/not_found do CMS: title do lang (no pisa) + description vazia → cai o fallback
curl -s "$B/catalog/index.php?route=error/not_found" -o /tmp/f401_404.html
grep -q 'content="Desc custom F4-01 test"' /tmp/f401_404.html || OK=1
! grep -q "<title>Meta X F4-01" /tmp/f401_404.html || OK=1
ck 6 $OK "fallback-real"

echo '══ 7 — validação: smtp sem hostname + ttl baixo → alert e NÃO grava ══'
VAL0=$(q "SELECT \`value\` FROM reamur_test.rms_setting WHERE code='config' AND \`key\`='mail_smtp_hostname'")
curl -s -c $JA -b $JA -X POST "$A/index.php?route=setting/setting" \
  --data-urlencode "config_name=X" --data-urlencode "mail_engine=smtp" \
  --data-urlencode "mail_smtp_hostname=" --data-urlencode "mail_smtp_port=587" \
  --data-urlencode "mail_smtp_timeout=10" --data-urlencode "mail_reply_to=" \
  --data-urlencode "cache_engine=file" --data-urlencode "cache_expire=10" \
  -D /tmp/f401_bad.hdr -o /tmp/f401_bad.html
OK=0
[ "$(hdr /tmp/f401_bad.hdr)" = "200" ] || OK=1   # volta pra form (sem redirect)
grep -q "alert error" /tmp/f401_bad.html || OK=1
# mensagens das regras
grep -qi "hostname\|SMTP" /tmp/f401_bad.html || OK=1
VAL1=$(q "SELECT \`value\` FROM reamur_test.rms_setting WHERE code='config' AND \`key\`='mail_smtp_hostname'")
[ "$VAL1" = "$VAL0" ] || OK=1
ck 7 $OK "validation"

echo '══ 8 — Clear cache: POST dedicado esvazia o diretório ══'
curl -s "$B/catalog/" -o /dev/null   # semeia cache real
mkdir -p "$CACHE_DIR"
FILES_PRE=$(sudo ls "$CACHE_DIR" 2>/dev/null | wc -l)
curl -s -c $JA -b $JA -X POST "$A/index.php?route=setting/setting.clearCache" \
  -D /tmp/f401_clear.hdr -o /dev/null
FILES_POST=$(sudo ls "$CACHE_DIR" 2>/dev/null | wc -l)
OK=0
[ "$(hdr /tmp/f401_clear.hdr)" = "302" ] || OK=1
L=$(loc /tmp/f401_clear.hdr); [[ "$L" == *setting/setting* ]] || OK=1
[ "$FILES_POST" -lt "$FILES_PRE" ] || OK=1
# flash aparece na tela ao voltar
curl -s -c $JA -b $JA "$A/index.php?route=setting/setting" -o /tmp/f401_after_clear.html
grep -q "alert success" /tmp/f401_after_clear.html || OK=1
ck 8 $OK "clear-via-post ($FILES_PRE → $FILES_POST)"

echo '══ 9 — ACL: retirar grant → some do dashboard + redirect permission ══'
sudo mysql reamur_test -e "
  UPDATE rms_user_group
     SET permission = JSON_REMOVE(permission,
        REPLACE(JSON_UNQUOTE(JSON_SEARCH(permission,'one','setting/setting',NULL,'\$.access')),'\"',''),
        REPLACE(JSON_UNQUOTE(JSON_SEARCH(permission,'one','setting/setting',NULL,'\$.modify')),'\"',''))
   WHERE name='Administrator'"
NOG=$(q "SELECT COUNT(*) FROM reamur_test.rms_user_group WHERE name='Administrator' AND JSON_SEARCH(permission,'one','setting/setting') IS NULL")
curl -s -c $JA -b $JA "$A/common/dashboard" -o /tmp/f401_dash9.html
curl -s -c $JA -b $JA -D /tmp/f401_nog.hdr -o /dev/null "$A/index.php?route=setting/setting"
OK=0
[ "$NOG" = "1" ] || OK=1
grep -q "Settings" /tmp/f401_dash9.html && OK=1     # módulo some
[ "$(hdr /tmp/f401_nog.hdr)" = "302" ] || OK=1
L=$(loc /tmp/f401_nog.hdr); [[ "$L" == *permission* ]] || OK=1
# restore (idempotente)
sudo mysql reamur_test < "$MIRROR/install/sql/upgrade_f4_01_setting_op_grant.sql"
ck 9 $OK "acl-parede"

echo '══ 10 — i18n ×3: heading + fieldsets traduzidos ══'
curl -s -c $JA -b $JA "$A/index.php?route=setting/setting&language=pt-br" -o /tmp/f401_pt.html
curl -s -c $JA -b $JA "$A/index.php?route=setting/setting&language=es-eu" -o /tmp/f401_es.html
curl -s -c $JA -b $JA "$A/index.php?route=setting/setting&language=en-us" -o /tmp/f401_en.html
OK=0
grep -q "Configurações" /tmp/f401_pt.html || OK=1
grep -q "Loja" /tmp/f401_pt.html || OK=1
grep -qi "e-mail\|Email" /tmp/f401_pt.html || OK=1
grep -q "Configuraci" /tmp/f401_es.html || OK=1
grep -q "Settings" /tmp/f401_en.html || OK=1
grep -q "Store" /tmp/f401_en.html || OK=1
ck 10 $OK "i18n-setting"

echo "═══ RESUMO: $PASS pass, $FAIL fail ═══"
[ $FAIL -eq 0 ]
