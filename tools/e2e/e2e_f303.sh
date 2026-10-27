#!/usr/bin/env bash
# ══════════════════════════════════════════════════════════════════
# e2e_f303.sh — Portfólio w/ SEO real + tilt CSS (Cena 3.1) + 404 fog (Cena 4)
# ADR 0021
#
#   PRÉ-CONDIÇÃO: install já concluído (rode e2e_f301.sh antes).
#   Bateria leve e NÃO destrutiva (sem wizard/drop).
#
# Checks:
#   1 /portfolio usa grade do tema (.pgrid/.pcard) + filtros-chips com ativo
#   2 tilt CSS: theme.css tem perspective+rotateX+rotateY e guard reduced-motion
#   3 SEO real: header emite <meta name="description"> na home e em /jwa
#   4 /jwa: title/meta do projeto (meta_title do seed) + chips de categoria
#   5 /jwa: classes do tema (page-head, fact-table) — sem style= inline de layout
#   6 404: status 404 + data-scene="not-found" + data-count/drift + fallback
#   7 scenes/not-found.js servido como factory da cena (drift+wrap)
#   8 main.js importa not-found.js (cena registrada no entrypoint)
#   9 404 em pt/es: textos traduzidos + cena presente
#  10 escopo: /portfolio 200 sem wrapper de erro; jwa mantém sem import map
#     (só home e 404 têm has_3d — header compartilhado)
# ══════════════════════════════════════════════════════════════════
set -u
B="${BASE:-http://127.0.0.1:8090/davidcms/dev}"
C="$B/catalog"
PASS=0; FAIL=0
ck() { if [ "$2" -eq 0 ]; then PASS=$((PASS+1)); echo "$1 pass — $3"; else FAIL=$((FAIL+1)); echo "$1 FAIL — $3"; fi; }
hdr() { grep -i "^HTTP" "$1" | tail -1 | grep -o "[0-9][0-9][0-9]"; }

echo '══ 1 — /portfolio renderiza com grade do tema + chips ativos ══'
curl -s -D /tmp/f303_pf.hdr -o /tmp/f303_pf.html "$C/portfolio"
OK=0
[ "$(hdr /tmp/f303_pf.hdr)" = "200" ] || OK=1
grep -q 'class="pgrid"' /tmp/f303_pf.html || OK=1
grep -q 'class="pcard"' /tmp/f303_pf.html || OK=1
grep -q 'chip active\|chip"' /tmp/f303_pf.html || OK=1
grep -q 'chip active' /tmp/f303_pf.html || OK=1   # "All" marcado como ativo sem category_id
ck 1 $OK "portfolio-grid-tema"

echo '══ 2 — tilt CSS (Cena 3 etapa 1): perspective/rotate + reduced-motion ══'
curl -s -D /tmp/f303_css.hdr -o /tmp/f303_theme.css "$C/view/css/theme.css"
OK=0
[ "$(hdr /tmp/f303_css.hdr)" = "200" ] || OK=1
for t in "preserve-3d" "perspective(800px)" "rotateX(4deg)" "rotateY(-4deg)" "prefers-reduced-motion: reduce"; do
  grep -q "$t" /tmp/f303_theme.css || OK=1
done
ck 2 $OK "tilt-css"

echo '══ 3 — SEO real: meta description no head (home + projeto) ══'
curl -s "$C/" -o /tmp/f303_home.html
curl -s "$C/jwa" -o /tmp/f303_jwa.html
OK=0
grep -q '<meta name="description"' /tmp/f303_home.html || OK=1
grep -q '<meta name="description"' /tmp/f303_jwa.html || OK=1
grep -q 'Visual identity for Juvenile World Academy' /tmp/f303_jwa.html || OK=1
ck 3 $OK "meta-description-real"

echo '══ 4 — /jwa: meta_title do seed + chips de categoria ══'
OK=0
grep -q '<title>JWA - Juvenile World Academy' /tmp/f303_jwa.html || OK=1
grep -q 'chip-row\|chip' /tmp/f303_jwa.html || OK=1
grep -q 'Juvenile World Academy' /tmp/f303_jwa.html || OK=1
ck 4 $OK "jwa-meta-e-cats"

echo '══ 5 — /jwa usa classes do tema (sem estilo inline de layout) ══'
OK=0
grep -q 'page-head' /tmp/f303_jwa.html || OK=1
grep -q 'fact-table' /tmp/f303_jwa.html || OK=1
grep -q 'style=' /tmp/f303_jwa.html && OK=1      # política zero-inline nas páginas do tema
ck 5 $OK "jwa-sem-inline"

echo '══ 6 — 404: status + data-scene not-found + fallback ══'
curl -s -D /tmp/f303_404.hdr -o /tmp/f303_404.html "$C/pagina-que-nao-existe"
OK=0
[ "$(hdr /tmp/f303_404.hdr)" = "404" ] || OK=1
grep -q 'data-scene="not-found"' /tmp/f303_404.html || OK=1
grep -q 'data-count="120"' /tmp/f303_404.html || OK=1
grep -q 'data-drift="0.4"' /tmp/f303_404.html || OK=1
grep -q 'nf-3d__fallback' /tmp/f303_404.html || OK=1
grep -q '"three"' /tmp/f303_404.html || OK=1     # has_3d liga o import map
ck 6 $OK "404-fog"

echo '══ 7 — scenes/not-found.js: factory do SceneModule + drift + wrap ══'
curl -s -D /tmp/f303_nf.hdr -o /tmp/f303_nf.js "$C/three-runtime/three/scenes/not-found.js"
OK=0
[ "$(hdr /tmp/f303_nf.hdr)" = "200" ] || OK=1
for t in "registerScene('not-found'" "animated: true" "update(" "dispose()" "velocities\[" "needsUpdate" "drift"; do
  grep -q "$t" /tmp/f303_nf.js || OK=1
done
ck 7 $OK "nf-scene-module"

echo '══ 8 — main.js importa not-found.js ══'
curl -s "$C/three-runtime/three/main.js" -o /tmp/f303_main.js
OK=0
grep -q "hero-particles.js" /tmp/f303_main.js || OK=1
grep -q "not-found.js" /tmp/f303_main.js || OK=1
grep -q "boot()" /tmp/f303_main.js || OK=1
ck 8 $OK "main-imports-nf"

echo '══ 9 — 404 pt/es: textos traduzidos + cena ══'
curl -s "$C/pagina-que-nao-existe?language=pt-br" -o /tmp/f303_404pt.html
curl -s "$C/pagina-que-nao-existe?language=es-eu" -o /tmp/f303_404es.html
curl -s "$C/?language=en-us" -o /dev/null
OK=0
grep -qi "não foi encontrada" /tmp/f303_404pt.html || OK=1
grep -qi "no se ha encontrado" /tmp/f303_404es.html || OK=1
grep -q 'data-scene="not-found"' /tmp/f303_404pt.html || OK=1
ck 9 $OK "404-i18n"

echo '══ 10 — escopo cirúrgico: /jwa SEM import map (Cena 3 é CSS-only) ══'
OK=0
grep -q '"three"' /tmp/f303_jwa.html && OK=1
grep -q 'data-scene=' /tmp/f303_jwa.html && OK=1
curl -s -D /tmp/f303_sm.hdr -o /tmp/f303_sm.html "$C/portfolio"
[ "$(hdr /tmp/f303_sm.hdr)" = "200" ] || OK=1
grep -q "ReamurCMS error" /tmp/f303_sm.html && OK=1
ck 10 $OK "escopo-portfolio"

echo "═══ RESUMO: $PASS pass, $FAIL fail ═══"
[ $FAIL -eq 0 ]
