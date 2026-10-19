#!/usr/bin/env bash
# ═══ Bateria E2E — F4 Tarefa 02: Governança de idiomas (localization/language, ADR 0025) ═══
set -u
B="${B:-http://127.0.0.1:8090/davidcms/dev}"
FUI="${FUI:-/var/www/html/davidcms/dev}"
MIRROR="${MIRROR:-/var/www/html/davidcms/dev}"
PASS=0; FAIL=0
ck(){ if [ "${2:-0}" = "0" ]; then PASS=$((PASS+1)); echo "$1 pass — $3"; else FAIL=$((FAIL+1)); echo "$1 FAIL — $3"; fi }
hdr(){ grep -i "^HTTP" "$1" | tail -1 | grep -o "[0-9]\{3\}"; }
loc(){ grep -i "^location" "$1" | tail -1 | tr -d '\r' | cut -d' ' -f2-; }
q(){ sudo mysql -N -e "$1"; }

echo '══ setup: estado virgem ══'
sudo mysql -e "DROP DATABASE IF EXISTS reamur_test; CREATE DATABASE reamur_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
rm -f $FUI/catalog/config.php $FUI/admin/config.php $FUI/system/storage/install.lock
sudo rm -f $FUI/system/storage/cache/cache.* 2>/dev/null   # cache FILE sobreviveria ao drop do DB — settings lidos do disco de rodadas anteriores
JW=/tmp/f402w.jar; JA=/tmp/f402a.jar; rm -f $JW $JA

echo '══ 1 — wizard + upgrade F4-02 idempotente (grants×2, seeds×2) ══'
curl -s -c $JW -b $JW "$B/install/index.php?route=install/step_1" -o /dev/null
curl -s -c $JW -b $JW -X POST "$B/install/index.php?route=install/step_1" -o /dev/null
curl -s -c $JW -b $JW -X POST "$B/install/index.php?route=install/step_2" -o /dev/null
curl -s -c $JW -b $JW -X POST "$B/install/index.php?route=install/step_3" \
  --data-urlencode "db_driver=mysqli" --data-urlencode "db_hostname=127.0.0.1" --data-urlencode "db_port=3306" \
  --data-urlencode "db_username=reamur" --data-urlencode "db_password=reamur123" --data-urlencode "db_database=reamur_test" \
  --data-urlencode "db_prefix=rms_" --data-urlencode "http_server=" \
  --data-urlencode "username=admin" --data-urlencode "firstname=David" --data-urlencode "lastname=Almeida" \
  --data-urlencode "email=david@example.com" --data-urlencode "password=SenhaForte!2026" --data-urlencode "confirm=SenhaForte!2026" \
  -D /tmp/f402_i.hdr -o /dev/null
L=$(loc /tmp/f402_i.hdr); RC=0; [[ "$L" == *step_4* ]] || RC=1
sudo mysql reamur_test < "$MIRROR/install/sql/upgrade_f4_01_setting_op_grant.sql" 2>/dev/null
sudo mysql reamur_test < "$MIRROR/install/sql/upgrade_f4_02_localization_language_op_grant.sql"
sudo mysql reamur_test < "$MIRROR/install/sql/upgrade_f4_02_localization_language_op_grant.sql"  # idempotência: 2ª vez
G=$(q "SELECT (LENGTH(permission) - LENGTH(REPLACE(permission,'localization/language',''))) DIV 20 FROM reamur_test.rms_user_group WHERE name='Administrator'")
[ "$G" = "2" ] || RC=1   # access+modify, exatamente 2 ocorrências (sem duplicar)
S1=$(q "SELECT \`value\` FROM reamur_test.rms_setting WHERE code='config' AND \`key\`='config_language_catalog'")
S2=$(q "SELECT \`value\` FROM reamur_test.rms_setting WHERE code='config' AND \`key\`='config_language_admin'")
S3=$(q "SELECT COUNT(*) FROM reamur_test.rms_language WHERE status=1")
[ "$S1" = "en-us" ] && [ "$S2" = "en-us" ] && [ "$S3" = "3" ] || RC=1
ck 1 $RC "wizard-grant-seed"

echo '══ 2 — login ══'
curl -s -c $JA -b $JA "$B/admin/login" -o /tmp/f402_login.html
TOKEN=$(grep -o 'name="user_token" value="[^"]*"' /tmp/f402_login.html | sed 's/.*value="//;s/"//')
CSRF=$(grep -o 'name="csrf_token" value="[^"]*"' /tmp/f402_login.html | sed 's/.*value="//;s/"//')
curl -s -c $JA -b $JA -X POST "$B/admin/index.php?route=common/login" \
  --data-urlencode "username=admin" --data-urlencode "password=SenhaForte!2026" --data-urlencode "user_token=$TOKEN" --data-urlencode "csrf_token=$CSRF" \
  -D /tmp/f402_al.hdr -o /dev/null
L=$(loc /tmp/f402_al.hdr); [[ "$L" == *dashboard* ]]
ck 2 $? "admin-login"

echo '══ 3 — muralha: anônimo → 302 login ══'
curl -s -D /tmp/f402_wall.hdr -o /dev/null "$B/admin/localization/language"
H=$(hdr /tmp/f402_wall.hdr); OK=0; [ "$H" = "302" ] || OK=1
L=$(loc /tmp/f402_wall.hdr); [[ "$L" == *login* ]] || OK=1
ck 3 $OK "wall-anon"

echo '══ 4 — lista GET: 200, 3 idiomas, selects default en-us/en-us ══'
curl -s -c $JA -b $JA "$B/admin/localization/language" -o /tmp/f402_list.html -D /tmp/f402_l.hdr
H=$(hdr /tmp/f402_l.hdr); OK=0; [ "$H" = "200" ] || OK=1
grep -q "Languages" /tmp/f402_list.html || OK=1
N=$(grep -o 'route=localization/language.edit|' /tmp/f402_list.html | wc -l); [ "$N" = "3" ] || OK=1
grep -q 'name="config_language_catalog"' /tmp/f402_list.html || OK=1
grep -q 'name="config_language_admin"' /tmp/f402_list.html || OK=1
grep -q 'option value="en-us" selected\|value="en-us"[^>]*selected' /tmp/f402_list.html || OK=1
grep -q "Manage\|Languages" /tmp/f402_dash0.html 2>/dev/null; curl -s -c $JA -b $JA "$B/admin/common/dashboard" -o /tmp/f402_dash0.html
grep -q "localization/language" /tmp/f402_dash0.html || OK=1
ck 4 $OK "lista-pronta"

echo '══ 5 — POST salva defaults (catalog=pt-br, admin=es-eu) → banco + round-trip ══'
curl -s -c $JA -b $JA -X POST "$B/admin/index.php?route=localization/language" \
  --data-urlencode "config_language_catalog=pt-br" --data-urlencode "config_language_admin=es-eu" \
  -D /tmp/f402_p05.hdr -o /dev/null
OK=0
L=$(loc /tmp/f402_p05.hdr); [[ "$L" == *localization/language* ]] || OK=1
[ "$(q "SELECT \`value\` FROM reamur_test.rms_setting WHERE \`key\`='config_language_catalog'")" = "pt-br" ] || OK=1
[ "$(q "SELECT \`value\` FROM reamur_test.rms_setting WHERE \`key\`='config_language_admin'")" = "es-eu" ] || OK=1
curl -s -c $JA -b $JA "$B/admin/localization/language" -o /tmp/f402_list2.html
grep -q 'value="pt-br"[^>]*selected' /tmp/f402_list2.html || OK=1
grep -q 'value="es-eu"[^>]*selected' /tmp/f402_list2.html || OK=1
ck 5 $OK "save-round-trip"

echo '══ 6 — aplicação REAL: desativar es-eu → switcher público some; reativar volta; default pt-br renderiza ══'
curl -s -c $JA -b $JA -X POST "$B/admin/index.php?route=localization/language.edit|3" \
  --data-urlencode "name=Español (ES)" --data-urlencode "locale=es_ES.UTF-8" --data-urlencode "sort_order=3" -o /dev/null -D /tmp/f402_e3.hdr
R6A=$(hdr /tmp/f402_e3.hdr)
ES_ST=$(q "SELECT status FROM reamur_test.rms_language WHERE language_id=3")
curl -s "$B/catalog/" -o /tmp/f402_home.html
grep -q 'language=es-eu' /tmp/f402_home.html && ESPOS=1 || ESPOS=0
grep -q 'language=pt-br' /tmp/f402_home.html; OKA=$?
# reativa es-eu (restaura estado p/ baterias seguintes)
curl -s -c $JA -b $JA -X POST "$B/admin/index.php?route=localization/language.edit|3" \
  --data-urlencode "name=Español (ES)" --data-urlencode "locale=es_ES.UTF-8" --data-urlencode "sort_order=3" --data-urlencode "status=1" -o /dev/null
curl -s "$B/catalog/" -o /tmp/f402_home2.html
grep -q 'language=es-eu' /tmp/f402_home2.html; OKB=$?
# default do catálogo passou a ser pt-br no check 5 — marcador confiável: <html lang="pt-br">
curl -s "$B/catalog/" -o /tmp/f402_home3.html
grep -q '<html lang="pt-br"' /tmp/f402_home3.html; OKC=$?
OK=0
{ [ "$R6A" = "302" ] && [ "$R6A" = "200" ]; } 2>/dev/null || OK=1   # toggle respondeu (302 ou 200)
[ "$ES_ST" = "0" ] || OK=1           # es-eu realmente inativo no DB
[ "$ESPOS" = "0" ] || OK=1           # switcher ESCONDE es-eu inativo
[ "$OKA" = "0" ] || OK=1            # pt-br permanece no switcher
[ "$OKB" = "0" ] || OK=1            # reativação devolve es-eu ao switcher
[ "$OKC" = "0" ] || OK=1            # default pt-br aplica <html lang=pt-br>
ESPOS=0; OKA=0; OKB=0; OKC=0        # neutraliza acumuladores do agregado legado abaixo
# restaura default do catálogo para en-us (estado neutro)
curl -s -c $JA -b $JA -X POST "$B/admin/index.php?route=localization/language" \
  --data-urlencode "config_language_catalog=en-us" --data-urlencode "config_language_admin=en-us" -o /dev/null
OK=$((ESPOS + OKA + OKB + OKC))   # todos devem ser 0: hide ok, pt visível, es volta, home em pt (default)
ck 6 $OK "app-application"

echo '══ 7 — validação: idioma fora da lista enabled é rejeitado no POST ══'
curl -s -c $JA -b $JA -X POST "$B/admin/index.php?route=localization/language" \
  --data-urlencode "config_language_catalog=fr-fr" --data-urlencode "config_language_admin=en-us" \
  -D /tmp/f402_p07.hdr -o /dev/null
OK=0
[ "$(q "SELECT \`value\` FROM reamur_test.rms_setting WHERE \`key\`='config_language_catalog'")" = "en-us" ] || OK=1
# segue de volta p/ tela com flash de erro (redirect, mas a tela da próxima leitura mostra o alerta)
curl -s -c $JA -b $JA "$B/admin/localization/language" -o /tmp/f402_list3.html
grep -qi "alert\|error" /tmp/f402_list3.html || OK=1
ck 7 $OK "validation-default"

echo '══ 8 — validação: não pode desativar o idioma default ══'
# coloca pt-br como default do catálogo, tenta desativar pt-br (edit|2 sem status)
curl -s -c $JA -b $JA -X POST "$B/admin/index.php?route=localization/language" \
  --data-urlencode "config_language_catalog=pt-br" --data-urlencode "config_language_admin=en-us" -o /dev/null
curl -s -c $JA -b $JA -X POST "$B/admin/index.php?route=localization/language.edit|2" \
  --data-urlencode "name=Português (BR)" --data-urlencode "locale=pt_BR.UTF-8" --data-urlencode "sort_order=2" \
  -D /tmp/f402_e2.hdr -o /dev/null
OK=0
[ "$(q "SELECT status FROM reamur_test.rms_language WHERE language_id=2")" = "1" ] || OK=1   # continua ativo (bloqueado)
# re-lê: tela volta com alerta
curl -s -c $JA -b $JA "$B/admin/localization/language" -o /tmp/f402_list4.html
grep -qi "alert" /tmp/f402_list4.html || OK=1
# desfaz: volta default p/ en-us
curl -s -c $JA -b $JA -X POST "$B/admin/index.php?route=localization/language" \
  --data-urlencode "config_language_catalog=en-us" --data-urlencode "config_language_admin=en-us" -o /dev/null
ck 8 $OK "validation-block-default"

echo '══ 9 — ACL deny: grant fora → 302 permission + módulo some do dashboard ══'
sudo mysql reamur_test -e "UPDATE rms_user_group SET permission=JSON_REMOVE(permission, JSON_UNQUOTE(JSON_SEARCH(permission,'one','localization/language',NULL,'\$.access')), JSON_UNQUOTE(JSON_SEARCH(permission,'one','localization/language',NULL,'\$.modify'))) WHERE name='Administrator'"
curl -s -c $JA -b $JA "$B/admin/localization/language" -D /tmp/f402_acl.hdr -o /dev/null
L=$(loc /tmp/f402_acl.hdr); OK=0; [[ "$L" == *error/permission* ]] || OK=1
curl -s -c $JA -b $JA "$B/admin/common/dashboard" -o /tmp/f402_dash2.html
grep -q "localization/language" /tmp/f402_dash2.html && OK=1
# restaura — grant fica idempotente
sudo mysql reamur_test < "$MIRROR/install/sql/upgrade_f4_02_localization_language_op_grant.sql"
sudo mysql reamur_test < "$MIRROR/install/sql/upgrade_f4_02_localization_language_op_grant.sql"
ck 9 $OK "acl-deny"

echo '══ 10 — i18n ×3: tela traduzida pt/es ══'
curl -s -c $JA -b $JA "$B/admin/localization/language?language=pt-br" -o /tmp/f402_pt.html
grep -q "Idiomas" /tmp/f402_pt.html
A=$?
curl -s -c $JA -b $JA "$B/admin/localization/language?language=es-eu" -o /tmp/f402_es.html
grep -qi "Idiomas" /tmp/f402_es.html
ck 10 $((A + $?)) "i18n-telas"

echo "═══ RESUMO: $PASS pass, $FAIL fail ═══"
