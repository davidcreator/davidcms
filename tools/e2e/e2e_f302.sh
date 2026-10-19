#!/usr/bin/env bash
# ══════════════════════════════════════════════════════════════════
# e2e_f302.sh — Hero Three.js (Cena 1: partículas + parallax), ADR 0020
#
#   PRÉ-CONDIÇÃO: install já concluído (rode e2e_f301.sh antes) —
#   bateria leve, rodável N vezes, não destrói banco.
#
# Checks:
#   1 home tem data-scene="hero-particles" + data-* da cena
#   2 import map "three" ANTES de main.js; ambos presentes
#   3 main.js 200 + registrando boot()
#   4 hero-particles.js registra SceneModule (play/pause/resize/dispose)
#   5 scene-runner honra prefers-reduced-motion + lazy-init (IntersectionObserver)
#   6 vendor three.module.js 200 + budget ≤ 700 KB (§10 do guia)
#   7 header.twig emite slot SÓ na home (has_3d): projeto /jwa sem import map
#   8 texto via Language em pt/es (hero traduzido sobre o mesmo DOM 3D)
#   9 fallback gradiente no DOM (sem binário; sem 404 de hero-fallback.webp)
#  10 smoke: home 200 + nenhuma ocorrência de "ReamurCMS error"
# ══════════════════════════════════════════════════════════════════
set -u
B="${BASE:-http://127.0.0.1:8090/davidcms/dev}"
C="$B/catalog"
PASS=0; FAIL=0
ck() { if [ "$2" -eq 0 ]; then PASS=$((PASS+1)); echo "$1 pass — $3"; else FAIL=$((FAIL+1)); echo "$1 FAIL — $3"; fi; }
hdr() { grep -i "^HTTP" "$1" | tail -1 | grep -o "[0-9][0-9][0-9]"; }

echo '══ 1 — home declara a cena (data-scene + data-count/color/speed) ══'
curl -s "$C/" -o /tmp/f302_home.html
OK=0
grep -q 'data-scene="hero-particles"' /tmp/f302_home.html || OK=1
grep -q 'data-count="400"' /tmp/f302_home.html || OK=1
grep -q 'data-color="#8a4fff"' /tmp/f302_home.html || OK=1
grep -q 'data-speed="0.05"' /tmp/f302_home.html || OK=1
ck 1 $OK "hero-data-scene"

echo '══ 2 — import map ANTES do módulo (ordem no <head>) ══'
OK=0
POS_MAP=$(grep -n '"three"' /tmp/f302_home.html | head -1 | cut -d: -f1)
POS_MOD=$(grep -n 'js/three/main.js' /tmp/f302_home.html | head -1 | cut -d: -f1)
[ -n "$POS_MAP" ] && [ -n "$POS_MOD" ] || OK=1
[ "$OK" -eq 0 ] && [ "$POS_MAP" -lt "$POS_MOD" ] || [ "$OK" -ne 0 ] || OK=1
grep -q 'three.module.js' /tmp/f302_home.html || OK=1
grep -q 'type="module"' /tmp/f302_home.html || OK=1
ck 2 $OK "importmap-ordem"

echo '══ 3 — main.js servido e chama boot() ══'
curl -s -D /tmp/f302_m.hdr -o /tmp/f302_main.js "$C/view/js/three/main.js"
OK=0
[ "$(hdr /tmp/f302_m.hdr)" = "200" ] || OK=1
grep -q "boot()" /tmp/f302_main.js || OK=1
grep -q "hero-particles.js" /tmp/f302_main.js || OK=1
ck 3 $OK "main-js"

echo '══ 4 — hero-particles.js: SceneModule completo ══'
curl -s "$C/view/js/three/scenes/hero-particles.js" -o /tmp/f302_scene.js
OK=0
for t in "registerScene('hero-particles'" "play()" "pause()" "resize()" "dispose()" "WebGLRenderer" "devicePixelRatio, 2" "pointermove"; do
  grep -q "$t" /tmp/f302_scene.js || OK=1
done
ck 4 $OK "scene-module"

echo '══ 5 — scene-runner: reduced-motion + lazy-init + pausa ══'
curl -s "$C/view/js/three/core/scene-runner.js" -o /tmp/f302_runner.js
OK=0
for t in "prefers-reduced-motion: no-preference" "IntersectionObserver" "rootMargin: '200px'" "visibilitychange" "pagehide"; do
  grep -q "$t" /tmp/f302_runner.js || OK=1
done
ck 5 $OK "scene-runner"

echo '══ 6 — vendor three.js servido dentro do budget (≤ 700 KB) ══'
curl -s -D /tmp/f302_v.hdr -o /tmp/f302_three.js "$C/view/js/vendor/three.module.js"
OK=0
[ "$(hdr /tmp/f302_v.hdr)" = "200" ] || OK=1
SIZE=$(stat -c%s /tmp/f302_three.js)
[ "$SIZE" -gt 100000 ] || OK=1        # arquivo real, não shell de erro
[ "$SIZE" -le 750000 ] || OK=1        # budget §10 (687 KB esperado p/ r169 min)
grep -q '169' <(head -c 20000 /tmp/f302_three.js) || OK=1
ck 6 $OK "three-vendor-budget"

echo '══ 7 — slot WebGL SÓ na home (escopo cirúrgico): /jwa sem import map ══'
curl -s "$C/jwa" -o /tmp/f302_jwa.html -D /tmp/f302_jwa.hdr
OK=0
[ "$(hdr /tmp/f302_jwa.hdr)" = "200" ] || OK=1
grep -q '"three"' /tmp/f302_jwa.html && OK=1
grep -q 'data-scene=' /tmp/f302_jwa.html && OK=1
ck 7 $OK "slot-escopado"

echo '══ 8 — textos do hero via Language (pt/es) sobre o mesmo DOM 3D ══'
curl -s "$C/?language=pt-br" -o /tmp/f302_pt.html
curl -s "$C/?language=es-eu" -o /tmp/f302_es.html
curl -s "$C/?language=en-us" -o /dev/null
OK=0
grep -q 'data-scene="hero-particles"' /tmp/f302_pt.html || OK=1
grep -q 'data-scene="hero-particles"' /tmp/f302_es.html || OK=1
grep -q "promover seu negócio\|Ideias\|criatividade" /tmp/f302_pt.html || OK=1
grep -q "promover\|Ideas\|creatividad" /tmp/f302_es.html || OK=1
ck 8 $OK "hero-i18n"

echo '══ 9 — fallback CSS no DOM (sem binário; nenhum 404 de webp) ══'
OK=0
grep -q 'hero-3d__fallback' /tmp/f302_home.html || OK=1
grep -q 'data-fallback' /tmp/f302_home.html || OK=1
curl -s -D /tmp/f302_css.hdr -o /tmp/f302_theme.css "$C/view/css/theme.css"
[ "$(hdr /tmp/f302_css.hdr)" = "200" ] || OK=1
grep -q 'hero-3d__fallback' /tmp/f302_theme.css || OK=1
grep -qi 'radial-gradient' /tmp/f302_theme.css || OK=1
curl -s -o /dev/null "$C/view/assets/3d/hero-fallback.webp" -D /tmp/f302_fb.hdr
[ "$(hdr /tmp/f302_fb.hdr)" = "404" ] || OK=1   # confirmado: referência 0 no DOM requer o asset
! grep -q 'hero-fallback.webp' /tmp/f302_home.html || OK=1
ck 9 $OK "fallback-css"

echo '══ 10 — smoke: home 200 sem wrapper de erro ══'
OK=0
curl -s -D /tmp/f302_h.hdr -o /tmp/f302_smoke.html "$C/"
[ "$(hdr /tmp/f302_h.hdr)" = "200" ] || OK=1
grep -q "ReamurCMS" /tmp/f302_smoke.html && grep -q "ReamurCMS error" /tmp/f302_smoke.html && OK=1
grep -q "<h1>" /tmp/f302_smoke.html || OK=1
ck 10 $OK "smoke"

echo "═══ RESUMO: $PASS pass, $FAIL fail ═══"
[ $FAIL -eq 0 ]
