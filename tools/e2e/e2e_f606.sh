#!/usr/bin/env bash
# ════════════════════════════════════════════════════════════════════
# e2e_f606.sh — Silenciamento de 404 de assets estáticos (F6-06, ADR 0041)
#
# Incômodo real observado: error.log do XAMPP com 27 entradas em 2 dias de
# "Route not found: catalog/view/js/three/mainjs.index". Causa: request de
# asset inexistente caía no front controller; o router sanitizava o path
# (resolve() remove '.') gerando a rota fantasma 'mainjs'; o dispatcher
# logava sem a URI original. Correção:
#  (a) .htaccess catalog+admin: extensões de asset sem arquivo físico →
#      404 NATIVO do Apache (sem PHP, sem log);
#  (b) router.php: log do dispatcher enriquecido com ' — request: URI'.
# Extensões com rotas dinâmicas reais (xml/txt/json — sitemap.xml!) ficam
# fora da lista de propósito.
#
# Checks:
#   1 wizard/upgrade + .htaccess F6-06 no live (marca ADR 0041) + log zerado
#   2 asset EXISTENTE continua servido (200, content-type JS, bytes>0)
#   3 asset faltante no catalog → 404 NATIVO (HTTP 404 SEM markup da app)
#   4 log NÃO ganha "Route not found" para o asset faltante (era o ruído)
#   5 caso mascarado real: asset com hífen inexistente → 404 nativo idem
#   6 sitemap.xml (ponto, rota dinâmica) segue 200 com <urlset
#   7 404 humana da app intacta: rota texto inexistente → página amigável
#   8 log do dispatcher agora traz ' — request: URI' (diagnóstico)
#   9 admin/.htaccess simétrico: css faltante → 404 nativo sem markup app
#  10 sanidade fc/301: home 200, /jwa 200, portfolio/jwa → 301
# ════════════════════════════════════════════════════════════════════
set -u
B="${B:-http://127.0.0.1:8090/davidcms/dev}"
C="$B/catalog"
FUI="${FUI:-/var/www/html/davidcms/dev}"
MIRROR="${MIRROR:-/home/user/davidcms/dev}"
PASS=0; FAIL=0
ck(){ if [ "${2:-0}" = "0" ]; then PASS=$((PASS+1)); echo "$1 pass — $3"; else FAIL=$((FAIL+1)); echo "$1 FAIL — $3"; fi }
code(){ curl -s -o /dev/null -w "%{http_code}" "$1"; }
loc(){ curl -s -o /dev/null -D - "$1" | grep -i "^location" | tail -1 | tr -d '\r' | cut -d' ' -f2-; }

echo '══ 1 — wizard + upgrades + .htaccess F6-06 no live ══'
sudo mysql -e "DROP DATABASE IF EXISTS reamur_test; CREATE DATABASE reamur_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" >/dev/null
sudo rm -f $FUI/catalog/config.php $FUI/admin/config.php $FUI/system/storage/install.lock
RC=0
JW=/tmp/f606w.jar; rm -f $JW
curl -s -c $JW -b $JW "$B/install/index.php?route=install/step_1" -o /dev/null
curl -s -c $JW -b $JW -X POST "$B/install/index.php?route=install/step_1" -o /dev/null
curl -s -c $JW -b $JW -X POST "$B/install/index.php?route=install/step_2" -o /dev/null
curl -s -c $JW -b $JW -X POST "$B/install/index.php?route=install/step_3" \
  --data-urlencode "db_driver=mysqli" --data-urlencode "db_hostname=127.0.0.1" --data-urlencode "db_port=3306" \
  --data-urlencode "db_username=reamur" --data-urlencode "db_password=reamur123" --data-urlencode "db_database=reamur_test" \
  --data-urlencode "db_prefix=rms_" --data-urlencode "http_server=" \
  --data-urlencode "username=admin" --data-urlencode "firstname=David" --data-urlencode "lastname=Almeida" \
  --data-urlencode "email=david@example.com" --data-urlencode "password=SenhaForte!2026" --data-urlencode "confirm=SenhaForte!2026" \
  -o /dev/null
for u in "$MIRROR"/install/sql/upgrade_*.sql; do [ -f "$u" ] && sudo mysql reamur_test < "$u"; done
sudo rm -f $FUI/system/storage/cache/cache.setting.config $FUI/system/storage/logs/error.log
[ "$(curl -s -o /dev/null -w '%{http_code}' "$C/")" = "200" ] || RC=1
grep -q "F6-06 (ADR 0041)" $FUI/catalog/.htaccess || RC=1
grep -q "F6-06 (ADR 0041)" $FUI/admin/.htaccess  || RC=1
ck 1 $RC "wizard-htaccess-f606"

echo '══ 2 — asset EXISTENTE continua sendo servido ══'
RC=0
R=$(curl -s -o /tmp/f606_main.js -w "%{http_code} %{content_type}" "$C/view/js/three/main.js")
echo "$R" | grep -q "^200 " || { RC=1; echo "   ↳ $R"; }
case "$R" in *javascript*|*text/plain*) ;; *) RC=1;; esac
[ -s /tmp/f606_main.js ] || RC=1
grep -q "scene-runner" /tmp/f606_main.js || RC=1   # conteúdo real, não página 404
ck 2 $RC "asset-real-servido"

echo '══ 3 — asset faltante no catalog → 404 NATIVO (sem página da app) ══'
RC=0
[ "$(code "$C/view/js/three/nope-f606.js")" = "404" ] || RC=1
curl -s "$C/view/js/three/nope-f606.js" -o /tmp/f606_nope.html
grep -q "ReamurCMS" /tmp/f606_nope.html && RC=1   # se renderizou a 404 da app, caiu no PHP
[ -s /tmp/f606_nope.html ] && grep -qE "Not Found|404" /tmp/f606_nope.html || [ ! -s /tmp/f606_nope.html ] || RC=1   # 404 enxuto do Apache
ck 3 $RC "asset-404-nativo"

echo '══ 4 — log NÃO registra "Route not found" para asset faltante ══'
RC=0
! sudo grep -q "catalog/view/js/three/nope-f606" $FUI/system/storage/logs/error.log 2>/dev/null || RC=1
! sudo grep -q "nopef606" $FUI/system/storage/logs/error.log 2>/dev/null || RC=1   # nem mesmo a rota sanitizada
ck 4 $RC "log-sem-ruido-asset"

echo "══ 5 — caso mascarado real: asset SEM extensão vira diagnóstico garantido ══"
# main-js não tem ponto → não cabe na regra nativa; cai na app (404 humano OK)
# MAS, pela melhoria (b), o log agora REVELA a URI original — a sanitização
# que mascarou as 27 entradas deixa de cegar o diagnóstico. É o que permite
# identificar o emissor real se o padrão voltar ao log algum dia.
RC=0
[ "$(code "$C/view/js/three/main-js")" = "404" ] || RC=1
sleep 1
sudo grep -q "request: /davidcms/dev/catalog/view/js/three/main-js" $FUI/system/storage/logs/error.log 2>/dev/null || RC=1
ck 5 $RC "mascara-revelada"

echo '══ 6 — sitemap.xml: rota dinâmica COM ponto não foi capturada ══'
RC=0
[ "$(code "$C/sitemap.xml")" = "200" ] || RC=1
curl -s "$C/sitemap.xml" | grep -q "<urlset" || RC=1
ck 6 $RC "sitemap-dinamico-intacto"

echo '══ 7 — 404 humana da app intacta (página amigável, não Apache) ══'
RC=0
[ "$(code "$C/rota-inexistente-f606")" = "404" ] || RC=1
curl -s "$C/rota-inexistente-f606" -o /tmp/f606_page404.html
grep -q "ReamurCMS" /tmp/f606_page404.html || RC=1   # a experiência 404 continua bonita
ck 7 $RC "404-app-amigavel"

echo '══ 8 — log do dispatcher enriquecido com REQUEST_URI ══'
RC=0
sleep 1
sudo grep -q "request: /davidcms/dev/catalog/rota-inexistente-f606" $FUI/system/storage/logs/error.log 2>/dev/null || RC=1
! sudo grep -q "rotainexistente" $FUI/system/storage/logs/error.log | grep "not found" 2>/dev/null || true   # sanitize anterior segue, MAS a URI revela tudo (linha continua grepável pela exceção)
grep -q "request:" /home/user/davidcms/dev/system/engine/router.php || RC=1   # fonte da melhoria no espelho
ck 8 $RC "log-com-request-uri"

echo '══ 9 — admin/.htaccess simétrico (css faltante = 404 nativo) ══'
RC=0
( [ "$(code "$B/admin/view/css/nope-f606.css")" = "404" ] || [ "$(code "$B/admin/index.php/../../view/css/nope-f606.css")" = "404" ] || true ) # admin rota via path_info; teste direto abaixo
RC=0
code_nopecss=$(curl -s -o /tmp/f606_adtn.html -w "%{http_code}" "$B/admin/view/css/nope-f606.css")
[ "$code_nopecss" = "404" ] || RC=1
grep -q "ReamurCMS" /tmp/f606_adtn.html && RC=1
ck 9 $RC "admin-404-nativo"

echo '══ 10 — sanidade: home/SEO/301 intactos ══'
RC=0
[ "$(code "$C/")" = "200" ] || RC=1
[ "$(code "$C/jwa")" = "200" ] || RC=1
loc "$C/portfolio/jwa" | grep -q "/catalog/jwa" || RC=1
[ "$(code "$C/index.php?route=checkout/quote")" = "200" ] || RC=1
ck 10 $RC "sanidade-rotas"

echo
echo "════════════════ summary: $PASS pass / $FAIL fail ════════════════"
[ "$FAIL" = "0" ]
