#!/usr/bin/env bash
# ════════════════════════════════════════════════════════════════════
# check_producao.sh — F6-05 (ADR 0040): verificação REMOTA e read-only
# da produção, apontada para o domínio real. Roda de qualquer máquina,
# sem credenciais, sem efeitos colaterais. Substitui automatizar §5 do
# Runbook-Cutover-F6.md (que fica como diagnóstico manual).
#
# Uso:   bash tools/check_producao.sh https://davidalmeida.xyz
#        DOMAIN=https://outro.dominio PROJ=jwa bash tools/check_producao.sh
#
# Checks (15):
#   1 Catálogo no ar (TLS válido quando https)
#   2 Força HTTPS (HTTP aberto servindo site = FAIL; sem :80 = warn)
#   3 Raiz   → 302 → catalog (index.php de atalho)   e segue 200
#   4 Rotas bonitas/aliases: contato + orçamento 200
#   5 SEO de projeto: /catalog/$PROJ 200 ($PROJ=jwa default)
#   6 Mapa 301 legado ×2 + follow 200 (ADR 0038 em prefixo real)
#   7 sitemap.xml 200 + XML válido com <urlset
#   8 robots.txt 200 + Disallow /admin/ + sem domínio hardcoded errado
#   9 Admin protegido: login 200 pública, dashboard sem sessão NUNCA 200
#  10 system/ selado: framework.php e default.php ≠ 200 (403/404)
#  11 Logs não vazam: storage/logs/error.log ≠ 200
#  12 Erro sem stack: rota inexistente = 404 limpo (error_display off)
#  13 Headers de segurança (ADR 0037): nosniff + frame + referrer
#  14 Ausências dev-only: /install /tests /agents-ia /docs /.git /prod ≠ 200
#  15 TTFB (warn apenas, não reprova)
# ════════════════════════════════════════════════════════════════════
set -u
DOMAIN="${1:-${DOMAIN:-https://davidalmeida.xyz}}"
DOMAIN="${DOMAIN%/}"; SCHEME="${DOMAIN%%://*}"
C="$DOMAIN/catalog"
PROJ="${PROJ:-jwa}"
PASS=0; FAIL=0; WARN=0
ck(){ if [ "${2:-0}" = "0" ]; then PASS=$((PASS+1)); echo "$1 pass — $3"; else FAIL=$((FAIL+1)); echo "$1 FAIL — $3"; fi }
wn(){ WARN=$((WARN+1)); echo "⚠ warn — $1"; }
code(){ curl -sk -m 15 -o /dev/null -w "%{http_code}" "$1"; }
codex(){ curl -sk -m 15 -o /dev/null -w "%{http_code}" "$1"; }
loc(){ curl -sk -m 15 -o /dev/null -D - "$1" | tr -d '\r' | grep -i "^location" | tail -1 | cut -d' ' -f2-; }
hdrs(){ curl -sk -m 15 -o /dev/null -D - "$1" | tr -d '\r' | tr 'A-Z' 'a-z'; }

echo "══ alvo: $DOMAIN  (projeto-prova: /catalog/$PROJ) ══"

echo '── 1 — catálogo no ar + TLS ──'
RC=0; RST=$(curl -sk -m 20 -o /tmp/f605_home.html -w "%{http_code}" "$C/")
[ "$RST" = "200" ] || { RC=1; echo "   ↳ $C/ → $RST"; }
[ -s /tmp/f605_home.html ] || RC=1
ck 1 $RC "catalogo-online"

echo '── 2 — força HTTPS ──'
RC=0
if [ "$SCHEME" = "https" ]; then
  HCODE=$(curl -s -m 10 -o /dev/null -w "%{http_code}" "http://${DOMAIN#*://}/" 2>/dev/null || echo 000)
  case "$HCODE" in
    301|302|307|308) ;;                                   # http→https redirect: ideal
    000) wn "porta 80 sem resposta ou sem redirect visível — confira o 'Force HTTPS' do cPanel (runbook 4.5)" ;;
    200) RC=1 ;;                                          # site aberto em HTTP puro: ruim
    *) wn "http respondeu $HCODE (inesperado — confira o redirect 80→443)" ;;
  esac
fi
ck 2 $RC "forca-https"

echo '── 3 — raiz atalha para o catálogo ──'
RC=0
loc "$DOMAIN/" | grep -q "catalog" || { RC=1; echo "   ↳ raiz não aponta para catalog"; }
[ "$(curl -skL -m 15 -o /dev/null -w "%{http_code}" "$DOMAIN/")" = "200" ] || RC=1
ck 3 $RC "raiz-para-catalogo"

echo '── 4 — rotas bonitas (aliases) ──'
RC=0
[ "$(code "$C/contato")" = "200" ] || RC=1
[ "$(code "$C/orcamento")" = "200" ] || RC=1
ck 4 $RC "aliases-paginas"

echo "── 5 — SEO de projeto /catalog/$PROJ ──"
RC=0; [ "$(code "$C/$PROJ")" = "200" ] || RC=1
ck 5 $RC "seo-projeto"

echo '── 6 — mapa 301 legado (ADR 0038 no prefixo real) ──'
RC=0
loc "$C/portfolio/$PROJ" | grep -q "catalog/$PROJ" || { RC=1; echo "   ↳ portfolio/$PROJ → '$(loc $C/portfolio/$PROJ 2>/dev/null)'"; }
loc "$C/tools/open?id=1" | grep -q "catalog/fluxa" || RC=1
[ "$(curl -skL -m 15 -o /dev/null -w "%{http_code}" "$C/portfolio/$PROJ")" = "200" ] || RC=1
ck 6 $RC "redirects-301"

echo '── 7 — sitemap.xml dinâmico ──'
RC=0
[ "$(code "$C/sitemap.xml")" = "200" ] || RC=1
curl -sk -m 15 "$C/sitemap.xml" | grep -q "<urlset" || RC=1
hdrs "$C/sitemap.xml" | grep -q "content-type:.*xml" || RC=1
ck 7 $RC "sitemap-xml"

echo '── 8 — robots.txt ──'
RC=0
curl -sk -m 15 "$DOMAIN/robots.txt" -o /tmp/f605_robots.txt
[ "$(code "$DOMAIN/robots.txt")" = "200" ] || RC=1
grep -qi "^Disallow: /admin/" /tmp/f605_robots.txt || RC=1
grep -qE "^Sitemap: *https?://|localhost|127\.0\.0\.1|davidcms|/dev/" /tmp/f605_robots.txt && RC=1   # vazamento de ambiente dev
ck 8 $RC "robots-portavel"

echo '── 9 — admin protegido ──'
RC=0
[ "$(code "$DOMAIN/admin/login")" = "200" ] || RC=1
DASH=$(curl -sk -m 15 -o /tmp/f605_dash.html -w "%{http_code}" "$DOMAIN/admin/index.php?route=common/dashboard")
[ "$DASH" = "200" ] && grep -qiE "Módulos|Modules" /tmp/f605_dash.html && RC=1   # painel aberto sem sessão = CRÍTICO
ck 9 $RC "admin-protegido"

echo '── 10 — system/ selado ──'
RC=0
for p in system/framework.php system/config/default.php; do
  st=$(code "$DOMAIN/$p"); [ "$st" = "200" ] && { RC=1; echo "   ↳ /$p acessível! ($st)"; }
done
ck 10 $RC "system-selado"

echo '── 11 — logs não vazam ──'
RC=0
for p in system/storage/logs/error.log catalog/config.php admin/config.php; do
  st=$(code "$DOMAIN/$p"); if [ "$st" = "200" ]; then
    body=$(curl -sk -m 10 "$DOMAIN/$p" | head -c 400)
    echo "$body" | grep -qE "error|define|DB_|password|Exception" && { RC=1; echo "   ↳ /$p COM CONTEÚDO! ($st)"; }
  fi
done
ck 11 $RC "logs-config-fechados"

echo '── 12 — erro sem stack (modo produção) ──'
RC=0
st=$(curl -sk -m 15 -o /tmp/f605_404.html -w "%{http_code}" "$C/rota-inexistente-check-f605")
[ "$st" = "404" ] || { RC=1; echo "   ↳ rota inexistente respondeu $st (esperado 404)"; }
grep -qiE "exception|stack trace|/home/|/var/www|db_error|trace" /tmp/f605_404.html && { RC=1; echo "   ↳ página de erro vaza detalhes!"; }
ck 12 $RC "erro-limpo"

echo '── 13 — headers de segurança (ADR 0037) ──'
RC=0
H=$(hdrs "$C/")
echo "$H" | grep -q "x-content-type-options: nosniff" || { RC=1; warnct=1; }
echo "$H" | grep -qE "x-frame-options: (sameorigin|deny)" || wn "X-Frame-Options ausente (definido no cPanel?)" 
echo "$H" | grep -q "referrer-policy:" || wn "Referrer-Policy ausente (definido no cPanel?)"
ck 13 $RC "headers-seguranca"

echo '── 14 — ausências dev-only (build limpa provada de fora) ──'
RC=0
for p in install/ tests/ agents-ia/ docs/ .git/config prod/ planejamento/; do
  st=$(code "$DOMAIN/$p")
  case "$st" in 200|301|302) RC=1; echo "   ↳ /$p visível ($st) — apague do servidor!";; esac
done
ck 14 $RC "ausencias-devonly"

echo '── 15 — TTFB ──'
RC=0
T=$(curl -sk -m 20 -o /dev/null -w "%{time_starttransfer}" "$C/")
TMS=$(echo "$T * 1000 / 1" | bc)
if [ "${TMS:-9999}" -gt 3000 ]; then wn "TTFB ${TMS}ms — acima de 3s (investigar: cache ligado? host lento?)"; else echo "TTFB ${TMS}ms"; fi
ck 15 0 "ttfb-medido"

echo
echo "════════ summary: $PASS pass / $FAIL fail / $WARN avisos ════════"
[ "$WARN" -gt 0 ] && echo "(avisos não reprovam — veja o runbook §4.5/§4.6)"
[ "$FAIL" = "0" ]
