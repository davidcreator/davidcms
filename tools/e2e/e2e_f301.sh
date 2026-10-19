#!/usr/bin/env bash
# ═══ Bateria E2E — F3 Tarefa 01: Tema base (partials+CSS) + seed conteúdo real ═══
set -u
B="${B:-http://127.0.0.1:8090/davidcms/dev}"
FUI="${FUI:-/tmp/fui/dev}"
C="$B/catalog"
PASS=0; FAIL=0
ck(){ if [ "${2:-0}" = "0" ]; then PASS=$((PASS+1)); echo "$1 pass — $3"; else FAIL=$((FAIL+1)); echo "$1 FAIL — $3"; fi }
hdr(){ grep -i "^HTTP" "$1" | tail -1 | grep -o "[0-9]\{3\}"; }
q(){ sudo mysql -N -e "$1"; }

echo '══ setup: estado virgem ══'
sudo mysql -e "DROP DATABASE IF EXISTS reamur_test; CREATE DATABASE reamur_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
rm -f $FUI/catalog/config.php $FUI/admin/config.php $FUI/system/storage/install.lock
JW=/tmp/f301w.jar; JC=/tmp/f301c.jar; rm -f $JW $JC

echo '══ 1 — wizard ══'
curl -s -c $JW -b $JW "$B/install/index.php?route=install/step_1" -o /dev/null
curl -s -c $JW -b $JW -X POST "$B/install/index.php?route=install/step_1" -o /dev/null
curl -s -c $JW -b $JW -X POST "$B/install/index.php?route=install/step_2" -o /dev/null
curl -s -c $JW -b $JW -X POST "$B/install/index.php?route=install/step_3" \
  --data-urlencode "db_driver=mysqli" --data-urlencode "db_hostname=127.0.0.1" --data-urlencode "db_port=3306" \
  --data-urlencode "db_username=reamur" --data-urlencode "db_password=reamur123" --data-urlencode "db_database=reamur_test" \
  --data-urlencode "db_prefix=rms_" --data-urlencode "http_server=" \
  --data-urlencode "username=admin" --data-urlencode "firstname=David" --data-urlencode "lastname=Almeida" \
  --data-urlencode "email=david@example.com" --data-urlencode "password=SenhaForte!2026" --data-urlencode "confirm=SenhaForte!2026" \
  -D /tmp/f301_i.hdr -o /dev/null
L=$(grep -i "^location" /tmp/f301_i.hdr | tail -1 | tr -d '\r' | cut -d' ' -f2-); RC=0; [[ "$L" == *step_4* ]] || RC=1
ck 1 $RC "wizard"

echo '══ 2 — SEED do conteúdo real aplicado (idempotente: roda 2×) ══'
sudo mysql reamur_test < $FUI/install/sql/upgrade_f3_01_seed_conteudo_davidalmeida.sql
sudo mysql reamur_test < $FUI/install/sql/upgrade_f3_01_seed_conteudo_davidalmeida.sql
N=$(q "SELECT COUNT(*) FROM reamur_test.rms_project"); OK=0; [ "$N" = "10" ] || OK=1
K=$(q "SELECT COUNT(*) FROM reamur_test.rms_seo_url"); [ "$K" = "12" ] || OK=1
T=$(q "SELECT COUNT(*) FROM reamur_test.rms_testimonial"); [ "$T" = "3" ] || OK=1
cl=$(q "SELECT COUNT(*) FROM reamur_test.rms_client_logo"); [ "$cl" = "5" ] || OK=1
tl=$(q "SELECT COUNT(*) FROM reamur_test.rms_tool"); [ "$tl" = "4" ] || OK=1
CG=$(q "SELECT COUNT(*) FROM reamur_test.rms_category_description WHERE language_id=1"); [ "$CG" = "3" ] || OK=1
PG=$(q "SELECT COUNT(*) FROM reamur_test.rms_page_description"); [ "$PG" = "2" ] || OK=1
ck 2 $OK "seed-idempotente"

echo '══ 3 — home 200 composta: partials + dados reais ══'
curl -s -c $JC -b $JC "$C/" -o /tmp/f301_home.html -D /tmp/f301_h.hdr
H=$(hdr /tmp/f301_h.hdr); OK=0; [ "$H" = "200" ] || OK=1
grep -q "theme.css" /tmp/f301_home.html || OK=1
for t in "Who" "transform ideas into reality" "Knowing the Client" "Flexibility" "best SEO practices"; do grep -qi "$t" /tmp/f301_home.html || OK=1; done
for t in "JWA" "Steel of Death" "Farmer Life Simulation" "Selva Dev" "Visual identity you can measure"; do grep -qi "$t" /tmp/f301_home.html || OK=1; done
grep -qi "Silvio Robson" /tmp/f301_home.html || OK=1
grep -qi "SEVEN A" /tmp/f301_home.html || OK=1
grep -qi "Horror na Veia" /tmp/f301_home.html || OK=1
grep -qi "Fluxa\|Quotia\|Vertex\|Solis" /tmp/f301_home.html || OK=1
ck 3 $OK "home-composta-real"

echo '══ 4 — hero: marca DA + 6 redes sociais reais ══'
grep -q 'class="brand-chip">DA<' /tmp/f301_home.html
A=$?
grep -q "davidalmeidadesigner" /tmp/f301_home.html || A=1
grep -q "davidalmeidades" /tmp/f301_home.html || A=1
grep -q "DavidAlmeidaDes" /tmp/f301_home.html || A=1
grep -q "redbubble.com/people/DavidCreator" /tmp/f301_home.html || A=1
grep -q "search?q=David+Creator" /tmp/f301_home.html || A=1
grep -q "pinterest.com/davidalmeidades" /tmp/f301_home.html || A=1
ck 4 $A "hero-social-real"

echo '══ 5 — metodologia: 6 etapas reais + 3 pilares de identidade ══'
OK=0
for t in "Knowing the Client" "Search" "Concept" "Execution" "Flexibility" "Delivery"; do grep -q "$t" /tmp/f301_home.html || OK=1; done
grep -qi "Unity" /tmp/f301_home.html || OK=1
grep -qi "Credibility" /tmp/f301_home.html || OK=1
grep -qi "Attraction" /tmp/f301_home.html || OK=1
ck 5 $OK "metodo-identidade"

echo '══ 6 — skills: chips+grupos (tech/platform/programs/scm) ══'
OK=0
for t in "Developer" "Brand Action" "VS Code" "CorelDRAW" "Wordpress" "jQuery\|JavaScript" "GitHub"; do grep -q "$t" /tmp/f301_home.html || OK=1; done
ck 6 $OK "skills"

echo '══ 7 — serviços: 3 grupos × 5 itens (taxonomia F2-17) ══'
OK=0
for t in "Graphic Design" "Web Design" "Brand Design" "Digital Installations" "Keyword Analysis" "SEO Audit" "Landing Page"; do grep -q "$t" /tmp/f301_home.html || OK=1; done
ck 7 $OK "servicos"

echo '══ 8 — grade projetos: filtros pelas 3 categorias reais ══'
OK=0
grep -q "Visual Identity" /tmp/f301_home.html || OK=1
grep -q "Illustration" /tmp/f301_home.html || OK=1
grep -q "Featured" /tmp/f301_home.html || OK=1
curl -s -c $JC -b $JC "$C/catalog/project?category_id=3" -o /tmp/f301_grid.html
grep -q "Chameleon Digital Art\|Cat Fe\|Classic Car" /tmp/f301_grid.html || OK=1
grep -q "JWA" /tmp/f301_grid.html && OK=1
ck 8 $OK "grade-filtros"

echo '══ 9 — SEO por keyword (produção preservada + legado "pitbul-color") ══'
OK=0
for k in jwa farmer rc classic-car; do
  curl -s -c $JC -b $JC "$C/$k" -D /tmp/f301_kw.hdr -o /dev/null; H=$(hdr /tmp/f301_kw.hdr); [ "$H" = "200" ] || OK=1
done
curl -s -c $JC -b $JC "$C/pitbul-color" -D /tmp/f301_kw.hdr -o /dev/null; H=$(hdr /tmp/f301_kw.hdr); [ "$H" = "200" ] || OK=1
curl -s -c $JC -b $JC "$C/cookies" -D /tmp/f301_kw.hdr -o /tmp/f301_cok.html; H=$(hdr /tmp/f301_kw.hdr); [ "$H" = "200" ] || OK=1
grep -qi "What cookies are" /tmp/f301_cok.html || OK=1
ck 9 $OK "keyword-real"

echo '══ 10 — projeto informa cliente + galeria placeholder (sem image) ══'
curl -s -c $JC -b $JC "$C/jwa" -o /tmp/f301_jwa.html
OK=0
grep -q "<title>JWA - Juvenile World Academy" /tmp/f301_jwa.html || OK=1
grep -q "Juvenile World Academy" /tmp/f301_jwa.html || OK=1
grep -q "logo,brand-manual\|visual-identity" /tmp/f301_jwa.html || OK=1
ck 10 $OK "projeto-detalle"

echo '══ 11 — footer: legais dinâmicas + sociais + copyright ══'
OK=0
grep -q "Cookie Policy" /tmp/f301_home.html || OK=1
grep -q "Internal Policies" /tmp/f301_home.html || OK=1
grep -q "Creative Developer" /tmp/f301_home.html || OK=1
grep -q "davidalmeidadesigner" /tmp/f301_home.html || OK=1
# footer dinâmico — quando o seed NÃO foi aplicado (banco limpo), nenhum 404
q "TRUNCATE reamur_test.rms_seo_url"
curl -s -c $JC -b $JC "$C/" -o /tmp/f301_home_noseed.html -D /tmp/f301_ns.hdr
[ "$(hdr /tmp/f301_ns.hdr)" = "200" ] || OK=1
grep -q "Cookie Policy" /tmp/f301_home_noseed.html && OK=1
sudo mysql reamur_test < $FUI/install/sql/upgrade_f3_01_seed_conteudo_davidalmeida.sql
ck 11 $OK "footer-dinamico"

echo '══ 12 — idioma: home pt respeita EN-only do seed (coalesce) ══'
curl -s -c $JC -b $JC "$C/?language=pt-br" -o /tmp/f301_pt.html
grep -q "transform ideas into reality\|Quem sou eu?" /tmp/f301_pt.html
A=$?
grep -qi "JWA" /tmp/f301_pt.html || A=1
curl -s -c $JC -b $JC "$C/?language=en-us" -o /dev/null
ck 12 $A "idioma-coalesce"

echo '══ 13 — contato/orçamento + login admin continuam saudáveis ══'
CT=$(curl -s -c $JC -b $JC "$C/index.php?route=common/contact" | grep -o 'name="csrf_token" value="[^"]*"' | sed 's/.*value="//;s/"//')
curl -s -c $JC -b $JC -X POST "$C/index.php?route=common/contact" \
  --data-urlencode "csrf_token=$CT" --data-urlencode "name=F3 Test" --data-urlencode "email=f3@test.dev" \
  --data-urlencode "subject=Smoke" --data-urlencode "message=F3 smoke contact." \
  -D /tmp/f301_ct.hdr -o /dev/null
L=$(grep -i "^location" /tmp/f301_ct.hdr | tail -1 | tr -d '\r' | cut -d' ' -f2-); OK=0
[[ "$L" == *contact* || "$L" == *contato* ]] || OK=1
N=$(q "SELECT COUNT(*) FROM reamur_test.rms_contact_message WHERE email='f3@test.dev'"); [ "$N" = "1" ] || OK=1
ck 13 $OK "formularios-smoke"

echo
echo "═══ RESUMO: $PASS pass, $FAIL fail "
exit $([ $FAIL -eq 0 ] && echo 0 || echo 1)
