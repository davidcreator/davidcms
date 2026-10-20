#!/usr/bin/env bash
# ════════════════════════════════════════════════════════════════════
# e2e_f405.sh — Envio de e-mail ativo: Mail\Factory, notificação do
# contato público e botão de teste no admin (ADR 0029, F4-05).
# Sem upgrade SQL — rota testMail coberta pelo grant setting/setting;
# consumo via bootstrap (ADR 0005).
#
# Determinismo: checks de envio usam mail_engine='capture' — o payload
# vira JSON em {FUI}/system/storage/logs/mail_capture.json (ADR 0029 §4).
#
# Padrão de bateria E2E: cada rodada = instalação completamente nova.
#
# Checks:
#   1 wizard-roda + grants base (upgrades F4-01..03; F4-04/05 não têm SQL)
#   2 login admin
#   3 muralha: GET setting/setting sem sessão → 302 p/ login
#   4 GET tela: seção E-mail + rota setting/setting.testMail visível;
#     botão de teste traduzido nas 3 langs (pt/es/en)
#   5 POST save válido com mail_engine=capture → rms_setting persiste
#     (prova: SELECT mail_engine) e config_name round-trip
#   6 repopulação: GET devolve <option value="capture" selected>
#   7 POST testMail → 302 + flash sucesso; mail_capture.json tem
#     to=e-mail do admin e subject do template en-us
#   8 contato público válido: grava inbox E notifica (capture regravado
#     com subject "New contact from the site:" e to=contato@…)
#   9 honeypot: finge sucesso, NÃO grava inbox, NÃO regrava capture
#  10 engine smtp inválida → testMail falha COM flash de erro e tela viva
#     (prova do try/catch — degradação silenciosa ADR 0029 §3)
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
CAP="$FUI/system/storage/logs/mail_capture.json"

getpage(){ # $1 jar  $2 route  $3 out.html — GET autenticado simples
  curl -s -c "$1" -b "$1" "$B/admin/index.php?route=$2" -o "$3"
}

echo '══ setup: estado virgem ══'
sudo mysql -e "DROP DATABASE IF EXISTS reamur_test; CREATE DATABASE reamur_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
rm -f $FUI/catalog/config.php $FUI/admin/config.php $FUI/system/storage/install.lock "$CAP"
sudo rm -f $FUI/system/storage/cache/cache.* 2>/dev/null
JW=/tmp/f405w.jar; JA=/tmp/f405a.jar; JC=/tmp/f405c.jar; rm -f $JW $JA $JC

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
  -D /tmp/f405_i.hdr -o /dev/null
L=$(loc /tmp/f405_i.hdr); [[ "$L" == *step_4* ]] || RC=1
for u in "$MIRROR"/install/sql/upgrade_f4_0{1,2,3}*.sql; do [ -f "$u" ] && sudo mysql reamur_test < "$u"; done
ck 1 $RC "wizard-grant-seed"

echo '══ 2 — login admin ══'
getpage $JA "common/login" /tmp/f405_login.html
TOKEN=$(grep -o 'name="user_token" value="[^"]*"' /tmp/f405_login.html | sed 's/.*value="//;s/"//')
CSRF=$(grep -o 'name="csrf_token" value="[^"]*"' /tmp/f405_login.html | sed 's/.*value="//;s/"//')
curl -s -c $JA -b $JA -X POST "$B/admin/index.php?route=common/login" \
  --data-urlencode "username=admin" --data-urlencode "password=SenhaForte!2026" --data-urlencode "user_token=$TOKEN" --data-urlencode "csrf_token=$CSRF" \
  -D /tmp/f405_al.hdr -o /dev/null
L=$(loc /tmp/f405_al.hdr); OK=0; [[ "$L" == *dashboard* ]] || OK=1
ck 2 $OK "admin-login"

echo '══ 3 — muralha: sem sessão → 302 p/ login ══'
curl -s "$B/admin/index.php?route=setting/setting" -o /dev/null -D /tmp/f405_wall.hdr
L=$(loc /tmp/f405_wall.hdr); OK=0; [[ "$L" == *login* ]] || OK=1
ck 3 $OK "wall-anon"

echo '══ 4 — tela tem seção E-mail + botão teste ×3 langs ══'
getpage $JA "setting/setting" /tmp/f405_s.html
OK=0
grep -q "setting/setting.testMail" /tmp/f405_s.html || OK=1
grep -q 'name="mail_engine"' /tmp/f405_s.html || OK=1
getpage $JA "setting/setting&language=pt-br" /tmp/f405_spt.html
grep -q "Enviar e-mail de teste" /tmp/f405_spt.html || OK=1
getpage $JA "setting/setting&language=es-eu" /tmp/f405_ses.html
grep -q "Enviar correo de prueba" /tmp/f405_ses.html || OK=1
getpage $JA "setting/setting&language=en-us" /tmp/f405_sen.html
grep -q "Send test e-mail" /tmp/f405_sen.html || OK=1
ck 4 $OK "form-mail-i18n-botao"

echo '══ 5 — save com mail_engine=capture persiste ══'
curl -s -c $JA -b $JA -X POST "$B/admin/index.php?route=setting/setting" \
  --data-urlencode "config_name=Reamur F405" \
  --data-urlencode "config_meta_title=ReamurCMS" \
  --data-urlencode "config_meta_description=Suite F405" \
  --data-urlencode "mail_engine=capture" \
  --data-urlencode "mail_reply_to=responder@example.com" \
  --data-urlencode "mail_smtp_hostname=" --data-urlencode "mail_smtp_username=" --data-urlencode "mail_smtp_password=" \
  --data-urlencode "mail_smtp_port=587" --data-urlencode "mail_smtp_timeout=5" \
  --data-urlencode "cache_engine=file" --data-urlencode "cache_expire=3600" \
  -D /tmp/f405_sv.hdr -o /dev/null
R5=$(q "SELECT value FROM reamur_test.rms_setting WHERE code='config' AND \`key\`='mail_engine'")
N5=$(q "SELECT value FROM reamur_test.rms_setting WHERE code='config' AND \`key\`='config_name'")
OK=0; [ "$R5" = "capture" ] || OK=1; [ "$N5" = "Reamur F405" ] || OK=1
ck 5 $OK "save-capture-persiste"

echo '══ 6 — repopulação: capture selected ══'
getpage $JA "setting/setting" /tmp/f405_r.html
OK=0; grep -q 'value="capture" selected' /tmp/f405_r.html || OK=1
ck 6 $OK "select-repopula-capture"

echo '══ 7 — testMail: flash sucesso + captura com destinatário ══'
curl -s -c $JA -b $JA -X POST "$B/admin/index.php?route=setting/setting.testMail" -D /tmp/f405_tm.hdr -o /dev/null
L=$(loc /tmp/f405_tm.hdr); OK=0; [[ "$L" == *setting/setting* ]] || OK=1
getpage $JA "setting/setting" /tmp/f405_r7.html
grep -q "Test e-mail sent to david@example.com" /tmp/f405_r7.html || OK=1
[ -f "$CAP" ] || OK=1
grep -q "david@example.com" "$CAP" 2>/dev/null || OK=1
grep -q "ReamurCMS. Send test" "$CAP" 2>/dev/null || OK=1
ck 7 $OK "testmail-captura-destinatario"

echo '══ 8 — contato público grava inbox E notifica ══'
curl -s -c $JC -b $JC "$C/index.php?route=common/contact" -o /tmp/f405_c.html
CT=$(grep -o 'name="csrf_token" value="[^"]*"' /tmp/f405_c.html | sed 's/.*value="//;s/"//')
curl -s -c $JC -b $JC -X POST "$C/index.php?route=common/contact" \
  --data-urlencode "name=Maria Teste" --data-urlencode "email=maria@example.com" \
  --data-urlencode "telephone=19999990000" --data-urlencode "subject=Contato F405" \
  --data-urlencode "message=Mensagem de fumaça da bateria." \
  --data-urlencode "company=" --data-urlencode "csrf_token=$CT" -o /dev/null
I8=$(q "SELECT COUNT(*) FROM reamur_test.rms_contact_message WHERE subject='Contato F405'")
OK=0; [ "$I8" = "1" ] || OK=1
grep -q "New contact from the site: Contato F405" "$CAP" 2>/dev/null || OK=1
grep -q "contato@davidalmeida.xyz" "$CAP" 2>/dev/null || OK=1
ck 8 $OK "contato-notifica"

echo '══ 9 — honeypot: finge sucesso, não grava nem notifica ══'
curl -s -c $JC -b $JC "$C/index.php?route=common/contact" -o /tmp/f405_c2.html
CT2=$(grep -o 'name="csrf_token" value="[^"]*"' /tmp/f405_c2.html | sed 's/.*value="//;s/"//')
curl -s -c $JC -b $JC -X POST "$C/index.php?route=common/contact" \
  --data-urlencode "name=Bot" --data-urlencode "email=bot@example.com" \
  --data-urlencode "subject=BotSpam" --data-urlencode "message=spam" \
  --data-urlencode "company=BotCorp" --data-urlencode "csrf_token=$CT2" \
  -D /tmp/f405_hj.hdr -o /dev/null
I9=$(q "SELECT COUNT(*) FROM reamur_test.rms_contact_message WHERE subject='BotSpam'")
OK=0; [ "$I9" = "0" ] || OK=1
grep -q "New contact from the site: Contato F405" "$CAP" 2>/dev/null || OK=1   # capture continua do check 8
! grep -q "BotSpam" "$CAP" 2>/dev/null || OK=1
ck 9 $OK "honeypot-sem-notificacao"

echo '══ 10 — engine smtp inválida → flash erro, tela viva ══'
curl -s -c $JA -b $JA -X POST "$B/admin/index.php?route=setting/setting" \
  --data-urlencode "config_name=Reamur F405" \
  --data-urlencode "mail_engine=smtp" --data-urlencode "mail_smtp_hostname=smtp.invalid" \
  --data-urlencode "mail_smtp_username=u" --data-urlencode "mail_smtp_password=p" \
  --data-urlencode "mail_smtp_port=587" --data-urlencode "mail_smtp_timeout=5" \
  --data-urlencode "cache_engine=file" --data-urlencode "cache_expire=3600" -o /dev/null
curl -s -c $JA -b $JA -X POST "$B/admin/index.php?route=setting/setting.testMail" -D /tmp/f405_e10.hdr -o /dev/null
getpage $JA "setting/setting" /tmp/f405_r10.html
OK=0
grep -q "Failed to send the test e-mail" /tmp/f405_r10.html || OK=1
grep -q 'name="mail_engine"' /tmp/f405_r10.html || OK=1   # tela íntegra
# volta ao estado bom p/ próximas baterias idempotentes
curl -s -c $JA -b $JA -X POST "$B/admin/index.php?route=setting/setting" \
  --data-urlencode "config_name=ReamurCMS" --data-urlencode "mail_engine=capture" \
  --data-urlencode "cache_engine=file" --data-urlencode "cache_expire=3600" -o /dev/null
ck 10 $OK "smtp-invalido-flash-erro"

echo
echo "═══ RESUMO: $PASS pass, $FAIL fail ═══"
exit $([ $FAIL -eq 0 ] && echo 0 || echo 1)
