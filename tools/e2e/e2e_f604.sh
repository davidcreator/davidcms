#!/usr/bin/env bash
# ════════════════════════════════════════════════════════════════════
# e2e_f604.sh — Build de produção (F6-04, ADR 0039): prova a build prod/
# SERVINDO de verdade, instalada "modo produção" (SQL + configs manuais,
# SEM wizard, SEM install/), fora da árvore dev.
#
# Checks:
#   1 build_prod.sh OK: runtime presente, dev-only ausente (install/,
#     tests/, phpstan*, composer.*, logs/error.log, configs reais)
#   2 EXEMPLOs de config: presentes, {{APP_DIR}} resolvido, caminhos sem
#     prefixo 'prod/' (portáteis), instrução REAMUR_ERROR_DISPLAY presente
#   3 deploy simulado (/prodtest): schema+upgrades por SQL (jeito produção)
#     + configs geradas dos EXEMPLOs → home pública 200
#   4 admin sem wizard: user criado por SQL (bcrypt) → login → Painel 200
#   5 jornadas públicas: contato 200 + orçamento 200 na build
#   6 SEO de projeto na build: /prodtest/catalog/jwa 200
#   7 mapa 301 PORTÁTIL (CMSBASE): portfolio/jwa → 301 → /prodtest/catalog/jwa
#     (prova que o .htaccess serve qualquer prefixo) + tools/open?id=1 → fluxa
#   8 POST notify MP não redirecionado na build (sem 301) + resposta honesta
#   9 default.php da build = modo produção seguro (simulação CLI do handler,
#     sem vazar stack) — mesmo mecanismo cobrindo a cópia entregue
#  10 pacote de upload: zip da build íntegro (unzip -t), sem e2e/mock/zip
#     dentro, vendor/autoload presente
# ════════════════════════════════════════════════════════════════════
set -u
MIRROR="${MIRROR:-/home/user/davidcms}"
LIVETEST="/var/www/html/prodtest"
B="${B:-http://127.0.0.1:8090/prodtest}"
C="$B/catalog"
PASS=0; FAIL=0
ck(){ if [ "${2:-0}" = "0" ]; then PASS=$((PASS+1)); echo "$1 pass — $3"; else FAIL=$((FAIL+1)); echo "$1 FAIL — $3"; fi }
code(){ curl -s -o /dev/null -w "%{http_code}" "$1"; }
loc(){ curl -s -o /dev/null -D - "$1" | grep -i "^location" | tail -1 | tr -d '\r' | cut -d' ' -f2-; }
cleanup(){ sudo rm -rf "$LIVETEST" /tmp/prodbuild.zip; }
trap cleanup EXIT

echo '══ 1 — build_prod.sh: runtime presente, dev-only ausente ══'
RC=0
OUT=$(cd "$MIRROR" && bash tools/build_prod.sh 2>&1 | tail -1)
echo "$OUT" | grep -q "build pronta" || { RC=1; echo "$OUT"; }
P="$MIRROR/prod"
for f in catalog/index.php admin/index.php system/framework.php system/storage/vendor/autoload.php .htaccess catalog/.htaccess; do
  [ -f "$P/$f" ] || RC=1
done
for f in install tests system/storage/phpstan.neon system/storage/phpstan-baseline.neon \
         system/storage/phpstan-bootstrap.php system/storage/composer.json system/storage/composer.lock \
         system/storage/logs/error.log catalog/config.php admin/config.php; do
  [ -e "$P/$f" ] && RC=1
done
[ -d "$P/system/storage/session" ] && [ -f "$P/system/storage/session/index.html" ] || RC=1  # guards preservados
ck 1 $RC "build-runtime-e-ausencias"

echo '══ 2 — EXEMPLOs de config portáteis ══'
RC=0
[ -f "$P/catalog/config.php.EXEMPLO" ] && [ -f "$P/admin/config.php.EXEMPLO" ] || RC=1
! grep -q "{{APP_DIR}}" "$P/catalog/config.php.EXEMPLO" || RC=1
grep -q "'catalog/'" "$P/catalog/config.php.EXEMPLO" || RC=1          # prod/catalog/ NÃO — portátil
! grep -q "'prod/" "$P/catalog/config.php.EXEMPLO" || RC=1
grep -q "REAMUR_ERROR_DISPLAY" "$P/catalog/config.php.EXEMPLO" || RC=1
grep -q "Salve este arquivo como config.php" "$P/catalog/config.php.EXEMPLO" || RC=1
[ "$(grep -c 'HTTP_SERVER: admin →' "$P/admin/config.php.EXEMPLO")" = "1" ] || RC=1
ck 2 $RC "config-exemplos"

echo '══ 3 — deploy simulado: SQL + configs manuais (jeito produção) ══'
RC=0
sudo rm -rf "$LIVETEST"; sudo mkdir -p "$LIVETEST"
sudo rsync -a "$P/" "$LIVETEST/"
sudo mysql -e "DROP DATABASE IF EXISTS reamur_test; CREATE DATABASE reamur_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
sudo mysql reamur_test < "$MIRROR/dev/install/sql/schema.sql"
# Seed "wizard-less" idêntico ao step_3/createAdministrator: grupo ANTES dos upgrades,
# senão os *_grant (UPDATE … WHERE name='Administrator') caem no vazio.
SEED_ROUTES='["user/user","user/user_group","catalog/project","catalog/category","cms/page","marketing/testimonial","marketing/client_logo","tool/tool","sale/order","communication/contact_message","setting/webhook"]'
sudo mysql reamur_test -e "INSERT INTO rms_user_group (name,permission) VALUES ('Administrator','{\"access\":$SEED_ROUTES,\"modify\":$SEED_ROUTES}')"
HASH=$(php -r 'echo password_hash("SenhaForte!2026", PASSWORD_BCRYPT);')
sudo mysql reamur_test -e "INSERT INTO rms_user (user_group_id,username,password,email,firstname,lastname,image,status,date_added)
  VALUES (1,'admin','$HASH','david@example.com','David','Almeida','',1,NOW())"
for u in "$MIRROR"/dev/install/sql/upgrade_*.sql; do [ -f "$u" ] && sudo mysql reamur_test < "$u"; done
PERMS=$(sudo mysql -N -e "SELECT JSON_LENGTH(JSON_EXTRACT(permission,'$.access')) FROM reamur_test.rms_user_group WHERE name='Administrator'")
[ "$PERMS" -ge 15 ] || { RC=1; echo "   ↳ grants insuficientes: $PERMS (esperado ≥15: 11 seed + f4_01/02/03 + f5_02)"; }
for app in catalog admin; do
  [ "$app" = admin ] && URL="$B/admin/" || URL="$B/catalog/"   # HTTP_SERVER SEMPRE termina em /${app}/
  sudo sed -e "s#{{HTTP_SERVER}}#$URL#g" -e "s#{{HTTPS_SERVER}}#$URL#g" \
           -e "s#{{DIR_REAMUR}}#$LIVETEST/#g" \
           -e "s#{{DB_DRIVER}}#mysqli#g" -e "s#{{DB_HOSTNAME}}#127.0.0.1#g" \
           -e "s#{{DB_USERNAME}}#reamur#g" -e "s#{{DB_PASSWORD}}#reamur123#g" \
           -e "s#{{DB_DATABASE}}#reamur_test#g" -e "s#{{DB_PORT}}#3306#g" \
           -e "s#{{DB_PREFIX}}#rms_#g" \
           "$LIVETEST/$app/config.php.EXEMPLO" | sudo tee "$LIVETEST/$app/config.php" >/dev/null
  grep -qE "define\([^)]*\{\{" "$LIVETEST/$app/config.php" && RC=1   # placeholder sem resolver (só conta dentro de define; comentários do tpl são doc)
done
sudo chown -R www-data:www-data "$LIVETEST"
[ "$(code "$C/")" = "200" ] || RC=1
ck 3 $RC "deploy-sql-configs"

echo '══ 4 — admin sem wizard: usuário/grupo por SQL (check 3) → login → Painel ══'
RC=0
JA=/tmp/f604a.jar; rm -f $JA
curl -s -c $JA -b $JA "$B/admin/login" -o /tmp/f604_login.html
[ "$(code "$B/admin/login")" = "200" ] || RC=1
TOKEN=$(grep -o 'name="user_token" value="[^"]*"' /tmp/f604_login.html | sed 's/.*value="//;s/"//')
CSRF=$(grep -o 'name="csrf_token" value="[^"]*"' /tmp/f604_login.html | sed 's/.*value="//;s/"//')
curl -s -c $JA -b $JA -X POST "$B/admin/index.php?route=common/login" \
  --data-urlencode "username=admin" --data-urlencode "password=SenhaForte!2026" \
  --data-urlencode "user_token=$TOKEN" --data-urlencode "csrf_token=$CSRF" -o /dev/null -D /tmp/f604_post.hdr
POSTLOC=$(grep -i '^location' /tmp/f604_post.hdr | tail -1 | tr -d '\r')
curl -s -c $JA -b $JA "$B/admin/index.php?route=common/dashboard" -o /tmp/f604_dash.html
grep -qiE "Módulos|Modules" /tmp/f604_dash.html || { RC=1; echo "   ↳ dash sem Modules (login=$(curl -s -o /dev/null -w '%{http_code}' -c $JA -b $JA "$B/admin/login"), post→$POSTLOC, title=$(grep -o '<title>[^<]*' /tmp/f604_dash.html))"; }
grep -qi "Mercado Pago Marketplace" /tmp/f604_dash.html || RC=1   # módulos F5 no ar na build
ck 4 $RC "admin-sem-wizard"

echo '══ 5 — jornadas públicas na build ══'
RC=0
[ "$(code "$C/index.php?route=common/contact")" = "200" ] || RC=1
[ "$(code "$C/index.php?route=checkout/quote")" = "200" ] || RC=1
ck 5 $RC "jornadas-publicas"

echo '══ 6 — SEO de projeto idêntico ao dev (/catalog/jwa) ══'
RC=0
[ "$(code "$C/jwa")" = "200" ] || RC=1
ck 6 $RC "seo-projeto"

echo '══ 7 — mapa 301 portátil (CMSBASE em /prodtest/) ══'
RC=0
loc "$C/portfolio/jwa" | grep -q "/prodtest/catalog/jwa$" || RC=1
loc "$C/tools/open?id=1" | grep -q "/prodtest/catalog/fluxa$" || RC=1
[ "$(curl -sL -o /dev/null -w "%{http_code}" "$C/portfolio/jwa")" = "200" ] || RC=1
ck 7 $RC "redirects-301-na-build"

echo '══ 8 — POST notify não redirecionado na build ══'
RC=0
H=$(curl -s -o /tmp/f604_8.txt -w "%{http_code}" -X POST "$C/index.php?route=checkout/payment.notify&type=payment&data.id=1" \
  -H 'Content-Type: application/json' -d '{"type":"payment","data":{"id":"1"}}')
[ "$H" = "301" ] && RC=1; [ "$H" = "302" ] && RC=1
grep -qiE "ok:|ignor|not" /tmp/f604_8.txt || RC=1
ck 8 $RC "notify-sem-301"

echo '══ 9 — default.php da build: erro NUNCA na tela (modo produção) ══'
RC=0
OUTPHP=$(php -r '$_=[]; require "'"$LIVETEST"'/system/config/default.php";
  if (!$_["error_display"]) { echo "Ocorreu um erro. Detalhes no log do sistema."; }')
echo "$OUTPHP" | grep -q "Ocorreu um erro" || RC=1
echo "$OUTPHP" | grep -qE "Exception|stack|/var/www" && RC=1
ck 9 $RC "erro-seguro-producao"

echo '══ 10 — pacote de upload íntegro ══'
RC=0
( cd "$P" && sudo zip -qr /tmp/prodbuild.zip . )
unzip -tq /tmp/prodbuild.zip | grep -q "No errors detected" || RC=1
unzip -l /tmp/prodbuild.zip | grep -qE "e2e_|mpmock|hookmock" && RC=1
unzip -l /tmp/prodbuild.zip | grep -q "system/storage/vendor/autoload.php" || RC=1
! unzip -l /tmp/prodbuild.zip | awk '{print $4}' | grep -qE "\.zip$" || RC=1   # campo nome apenas (cabeçalho 'Archive: …zip' não conta)
ck 10 $RC "pacote-upload"

echo
echo "════════════════ summary: $PASS pass / $FAIL fail ════════════════"
[ "$FAIL" = "0" ]
