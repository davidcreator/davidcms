#!/usr/bin/env bash
# ══════════════════════════════════════════════════════════════════
# e2e_f304.sh — Páginas públicas das ferramentas (Fluxa, Quotia,
#                Vertex, Solis) + keywords SEO tool_id (ADR 0022)
#
#   PRÉ-CONDIÇÃO: install já concluído (rode e2e_f301.sh antes).
#   A bateria não aplica seed — assume o banco semeado (keywords
#   tool_id=1..4 são parte do upgrade_f3_01 desde a F3-04).
#
# Checks:
#   1 /tools (alias) → 200, grade pública com 4 ferramentas reais
#   2 keywords reais: /fluxa /quotia /vertex /solis todas 200
#   3 /fluxa: nome do seed + description na meta description
#   4 /fluxa: title tem nome + vendor do seed
#   5 info é página DEDICADA: URL externa ausente (url='') → hint presente
#   6 tool sem URL: nenhum target=_blank no fragmento principal
#   7 keyword inexistente → 404 honesto
#   8 link da home p/ a página dedicada (hidratação de href na partial)
#   9 rewrite sai limpo (sem index.php?route=tool/tool.info no href da home)
#  10 i18n pt/es: landing /tools traduzida, keywords continuam amigáveis
# ══════════════════════════════════════════════════════════════════
set -u
B="${BASE:-http://127.0.0.1:8090/davidcms/dev}"
C="$B/catalog"
PASS=0; FAIL=0
ck() { if [ "$2" -eq 0 ]; then PASS=$((PASS+1)); echo "$1 pass — $3"; else FAIL=$((FAIL+1)); echo "$1 FAIL — $3"; fi; }
hdr() { grep -i "^HTTP" "$1" | tail -1 | grep -o "[0-9][0-9][0-9]"; }

echo '══ 1 — /tools (alias SEO): grade pública com 4 ferramentas reais ══'
curl -s -D /tmp/f304_tools.hdr -o /tmp/f304_tools.html "$C/tools"
OK=0
[ "$(hdr /tmp/f304_tools.hdr)" = "200" ] || OK=1
for t in Fluxa Quotia Vertex Solis DavidCreator; do grep -q "$t" /tmp/f304_tools.html || OK=1; done
grep -q "tool-card" /tmp/f304_tools.html || OK=1
ck 1 $OK "tools-grade"

echo '══ 2 — keywords reais das 4 ferramentas: todas 200 ══'
OK=0
for k in fluxa quotia vertex solis; do
  curl -s -D /tmp/f304_$k.hdr -o /tmp/f304_$k.html "$C/$k"
  [ "$(hdr /tmp/f304_$k.hdr)" = "200" ] || OK=1
done
ck 2 $OK "keywords-tools-200"

echo '══ 3 — /fluxa: description da ferramenta sai na <meta name="description"> ══'
OK=0
grep -q 'Financial management and indicator tracking' /tmp/f304_fluxa.html || OK=1
grep -q '<meta name="description" content="Financial management and indicator tracking tool\.">' /tmp/f304_fluxa.html || OK=1
ck 3 $OK "fluxa-meta"

echo '══ 4 — /fluxa: <title> com nome + vendor do seed ══'
OK=0
grep -q '<title>Fluxa — DavidCreator' /tmp/f304_fluxa.html || OK=1
grep -q 'DavidCreator' /tmp/f304_fluxa.html || OK=1
ck 4 $OK "fluxa-title"

echo '══ 5 — página DEDICADA: url='"'""'"' → hint de acesso (sem CTA externo) ══'
OK=0
grep -q 'public link for this tool is being prepared\|early access' /tmp/f304_fluxa.html || OK=1
grep -q '<h1>Fluxa</h1>' /tmp/f304_fluxa.html || OK=1
# sem link externo _blank pra a URL (que está vazia)
grep -q 'href="" target="_blank"' /tmp/f304_fluxa.html && OK=1
ck 5 $OK "fluxa-sem-cta-externo"

echo '══ 6 — /fluxa: layout do tema (page-head, fact-table) sem inline ══'
OK=0
grep -q 'page-head' /tmp/f304_fluxa.html || OK=1
grep -q 'fact-table' /tmp/f304_fluxa.html || OK=1
grep -q 'style=' /tmp/f304_fluxa.html && OK=1
ck 6 $OK "fluxa-sem-inline"

echo '══ 7 — keyword inexistente (/ferramenta-que-nao-existe) → 404 honesto ══'
curl -s -D /tmp/f304_404.hdr -o /tmp/f304_404.html "$C/ferramenta-que-nao-existe"
OK=0
[ "$(hdr /tmp/f304_404.hdr)" = "404" ] || OK=1
grep -q 'data-scene="not-found"' /tmp/f304_404.html || OK=1
ck 7 $OK "keyword-inexistente-404"

echo '══ 8 — home: tool-card linka p/ a página dedicada (/fluxa) ══'
curl -s "$C/" -o /tmp/f304_home.html
OK=0
grep -q 'class="tool-link" href="' /tmp/f304_home.html || OK=1
grep -q 'href="[^"]*fluxa"' /tmp/f304_home.html || OK=1
ck 8 $OK "home-linka-tool"

echo '══ 9 — rewrite limpo: sem index.php?route=tool/tool.info ao linkar da home ══'
OK=0
grep -q 'route=tool/tool.info' /tmp/f304_home.html && OK=1
grep -q 'href="[^"]*\/fluxa"' /tmp/f304_home.html || OK=1
grep -q 'href="[^"]*\/solis"' /tmp/f304_home.html || OK=1
ck 9 $OK "rewrite-limpo"

echo '══ 10 — i18n: /tools pt/es traduzida; keywords continuam amigáveis ══'
curl -s "$C/tools?language=pt-br" -o /tmp/f304_pt.html
curl -s "$C/tools?language=es-eu" -o /tmp/f304_es.html
curl -s "$C/?language=en-us" -o /dev/null
OK=0
grep -qi "Ferramentas conectadas" /tmp/f304_pt.html || OK=1
grep -qi "Herramientas conectadas" /tmp/f304_es.html || OK=1
curl -s "$C/fluxa?language=pt-br" -o /tmp/f304_fpt.html
grep -q "acesso antecipado" /tmp/f304_fpt.html || OK=1   # hint text_open_hint traduzido (url vazia)
curl -s -D /tmp/f304_ptkw.hdr -o /tmp/f304_ptkw.html "$C/fluxa?language=pt-br"
[ "$(hdr /tmp/f304_ptkw.hdr)" = "200" ] || OK=1
grep -q 'Fluxa' /tmp/f304_ptkw.html || OK=1
ck 10 $OK "i18n-tools"

echo "═══ RESUMO: $PASS pass, $FAIL fail ═══"
[ $FAIL -eq 0 ]
