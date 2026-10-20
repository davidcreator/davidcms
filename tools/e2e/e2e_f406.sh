#!/usr/bin/env bash
# ════════════════════════════════════════════════════════════════════
# e2e_f406.sh — Cache efetivo: boot lê rms_settings, fallback File,
# guarda de clearCache e indicador no dashboard (ADR 0030, F4-06).
# Sem upgrade SQL — chaves cache_* já persistem desde F4-01.
#
# Filosofia da prova: sandbox SEM ext-redis/apcu — então a bateria prova
#  (a) TTL efetivo: arquivo de warm cache.setting.config nasce com
#      expiração ≈ agora+TTL DO PAINEL (não o 3600 do default.php);
#  (b) RESILIÊNCIA: engine=redis (sem extensão) → site inteiro 200,
#      linha de fallback no log, dashboard sinaliza fallback.
#
# Padrão de bateria E2E: cada rodada = instalação completamente nova.
#
# Checks:
#   1 wizard-roda + upgrades F4-01..03
#   2 login admin
#   3 dashboard mostra indicador "Cache: file · TTL 3600s" (defaults)
#   4 save: cache_expire=1800 (engine=file) → persiste em rms_setting
#   5 DISCRIMINATÓRIO: warm seguinte grava cache.setting.config com
#     expire ≈ agora+1800 (pré-fix seria sempre 3600 do default.php)
#   6 clearCache (File) apaga o artefato cache.setting.config
#   7 engine=redis SEM extensão → admin e catalog continuam 200 + log
#   8 dashboard sinaliza fallback ("fallback active", configurada redis)
#   9 volta file → indicador normal, sem aviso de fallback
#  10 i18n ×3 do indicador (pt/es/en) + muralha anônima são íntegras
# ════════════════════════════════════════════════════════════════════
set -u
B="${B:-http://127.0.0.1:8090/davidcms/dev}"
C="$B/catalog"
FUI="${FUI:-/var/www/html/davidcms/dev}"
MIRROR="${MIRROR:-/var/www/html/davidcms/dev}"
PASS=0; FAIL=0
ck(){ if [ "${2:-0}" = "0" ]; then PASS=$((PASS+1)); echo "$1 pass — $3"; else FAIL=$((FAIL+1)); echo "$1 FAIL — $3"; fi }
hdr(){ grep -i "^HTTP" "$1" | tail -1 | grep -o "[0-9]\{3\}"; }
loc(){ grep -i "^location" "$1" | tail -1 | tr -d '\r' | cut -d' ' -f2-; }
q(){ sudo mysql -N -e "$1"; }
CACHEF="$FUI/system/storage/cache/cache.setting.config"
LOGF="$FUI/system/storage/logs/error.log"

save_settings(){ # uso: save_settings <engine> <expire>
  curl -s -c $JA -b $JA -X POST "$B/admin/index.php?route=setting/setting" \
    --data-urlencode "config_name=Reamur F406" \
    --data-urlencode "config_meta_title=ReamurCMS" \
    --data-urlencode "config_meta_description=Suite F406" \
    --data-urlencode "mail_engine=mail" --data-urlencode "mail_reply_to=" \
    --data-urlencode "mail_smtp_hostname=" --data-urlencode "mail_smtp_username=" \
    --data-urlencode "mail_smtp_password=" --data-urlencode "mail_smtp_port=587" \
    --data-urlencode "mail_smtp_timeout=5" \
    --data-urlencode "cache_engine=$1" --data-urlencode "cache_expire=$2" -o /dev/null
}

echo '══ setup: estado virgem ══'
sudo mysql -e "DROP DATABASE IF EXISTS reamur_test; CREATE DATABASE reamur_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
rm -f $FUI/catalog/config.php $FUI/admin/config.php $FUI/system/storage/install.lock $CACHEF
sudo rm -f $FUI/system/storage/cache/cache.* 2>/dev/null
JW=/tmp/f406w.jar; JA=/tmp/f406a.jar; rm -f $JW $JA

echo '══ 1 — wizard + grants base ══'
RC=0
curl -s -c $JW -b $JW "$B/install/index.php?route=install/step_1" -o /dev/null
curl -s -c $JW -b $JW -X POST "$B/install/index.php?route=install/step_1" -o /dev/null
curl -s -c $JW -b $JW -X POST "$B/install/index.php?route=install/step_2" -o /dev/null
curl -s -c $JW -b $JW -X POST "$B/install/index.php?route=install/step_3" \
  --data-urlencode "db_driver=mysqli" --data-urlencode "db_hostname=127.0.0.1" --data-urlencode "db_port=3306" \
  --data-urlencode "db_username=reamur" --data-urlencode "db_password=reamur123" --data-urlencode "db_database=reamur_test" \
  --data-urlencode "db_prefix=rms_" --data-urlencode "http_server=" \
  --data-urlencode "username=admin" --data-urlencode "firstname=David" --data-urlencode "lastname=Almeida" \
  --data-urlencode "email=david@example.com" --data-urlencode "password=SenhaForte!2026" --data-urlencode "confirm=SenhaForte!2026" \
  -D /tmp/f406_i.hdr -o /dev/null
L=$(loc /tmp/f406_i.hdr); [[ "$L" == *step_4* ]] || RC=1
for u in "$MIRROR"/install/sql/upgrade_f4_0{1,2,3}*.sql; do [ -f "$u" ] && sudo mysql reamur_test < "$u"; done
ck 1 $RC "wizard-grant-seed"

echo '══ 2 — login admin ══'
curl -s -c $JA -b $JA "$B/admin/index.php?route=common/login" -o /tmp/f406_login.html
TOKEN=$(grep -o 'name="user_token" value="[^"]*"' /tmp/f406_login.html | sed 's/.*value="//;s/"//')
CSRF=$(grep -o 'name="csrf_token" value="[^"]*"' /tmp/f406_login.html | sed 's/.*value="//;s/"//')
curl -s -c $JA -b $JA -X POST "$B/admin/index.php?route=common/login" \
  --data-urlencode "username=admin" --data-urlencode "password=SenhaForte!2026" --data-urlencode "user_token=$TOKEN" --data-urlencode "csrf_token=$CSRF" \
  -D /tmp/f406_al.hdr -o /dev/null
L=$(loc /tmp/f406_al.hdr); OK=0; [[ "$L" == *dashboard* ]] || OK=1
ck 2 $OK "admin-login"

echo '══ 3 — dashboard mostra indicador (defaults file/3600) ══'
curl -s -c $JA -b $JA "$B/admin/index.php?route=common/dashboard" -o /tmp/f406_d.html
OK=0; grep -q "Cache: file · TTL 3600s" /tmp/f406_d.html || OK=1
grep -q "fallback" /tmp/f406_d.html && OK=1   # não pode haver aviso agora
ck 3 $OK "dashboard-indicador-defaults"

echo '══ 4 — save cache_expire=1800 persiste ══'
save_settings file 1800
R4=$(q "SELECT value FROM reamur_test.rms_setting WHERE code='config' AND \`key\`='cache_expire'")
OK=0; [ "$R4" = "1800" ] || OK=1
ck 4 $OK "save-expire-1800"

echo '══ 5 — DISCRIMINATÓRIO: warm usa o TTL do painel ══'
rm -f "$CACHEF"
T0=$(date +%s)
curl -s -c $JA -b $JA "$B/admin/index.php?route=common/dashboard" -o /dev/null   # reaquece
EXP=$(python3 -c "import json,sys; print(json.load(open('$CACHEF'))['expire'])" 2>/dev/null)
OK=0
[ -n "$EXP" ] || OK=1
DIFF=$((EXP - T0)); [ "$DIFF" -ge 1500 ] && [ "$DIFF" -le 2100 ] || OK=1   # ~1800, nunca 3600
ck 5 $OK "warm-ttl-do-painel"

echo '══ 6 — clearCache (File) apaga o artefato ══'
curl -s -c $JA -b $JA -X POST "$B/admin/index.php?route=setting/setting.clearCache" -o /dev/null
OK=0; [ -f "$CACHEF" ] && OK=1
ck 6 $OK "clearcache-apaga-warm"

echo '══ 7 — engine=redis SEM ext: admin e catalog seguem 200 + log ══'
save_settings redis 3600
H1=$(curl -s -o /dev/null -w "%{http_code}" -c $JA -b $JA "$B/admin/index.php?route=common/dashboard")
H2=$(curl -s -o /dev/null -w "%{http_code}" "$C/index.php")
OK=0
[ "$H1" = "200" ] || OK=1
[ "$H2" = "200" ] || [ "$H2" = "301" ] || [ "$H2" = "302" ] || OK=1
grep -q "engine redis indisponível" "$LOGF" 2>/dev/null || OK=1
ck 7 $OK "redis-sem-ext-fallback-200"

echo '══ 8 — dashboard sinaliza o fallback ══'
curl -s -c $JA -b $JA "$B/admin/index.php?route=common/dashboard" -o /tmp/f406_d8.html
OK=0
grep -q "Cache: file · TTL" /tmp/f406_d8.html || OK=1
grep -q "configured &quot;redis&quot; — fallback active" /tmp/f406_d8.html || OK=1   # twig autoescape: aspas viram &quot;
ck 8 $OK "dashboard-avisa-fallback"

echo '══ 9 — volta file: indicador normal ══'
save_settings file 3600
curl -s -c $JA -b $JA "$B/admin/index.php?route=common/dashboard" -o /tmp/f406_d9.html
OK=0
grep -q "Cache: file · TTL 3600s" /tmp/f406_d9.html || OK=1
grep -q "fallback active" /tmp/f406_d9.html && OK=1
ck 9 $OK "restore-file-indicador-normal"

echo '══ 10 — i18n ×3 do indicador + muralha anônima intacta ══'
OK=0
curl -s -c $JA -b $JA "$B/admin/index.php?route=common/dashboard&language=pt-br" -o /tmp/f406_pt.html
grep -q "Cache: file · TTL" /tmp/f406_pt.html || OK=1
curl -s -c $JA -b $JA "$B/admin/index.php?route=common/dashboard&language=es-eu" -o /tmp/f406_es.html
grep -q "Caché: file · TTL" /tmp/f406_es.html || OK=1
curl -s -c $JA -b $JA "$B/admin/index.php?route=common/dashboard&language=en-us" -o /tmp/f406_en.html
grep -q "Cache: file · TTL" /tmp/f406_en.html || OK=1
curl -s "$B/admin/index.php?route=common/dashboard" -o /dev/null -D /tmp/f406_wall.hdr
L=$(loc /tmp/f406_wall.hdr); [[ "$L" == *login* ]] || OK=1
ck 10 $OK "i18n-indicador-e-muralha"

echo
echo "═══ RESUMO: $PASS pass, $FAIL fail ═══"
exit $([ $FAIL -eq 0 ] && echo 0 || echo 1)
