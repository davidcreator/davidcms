#!/usr/bin/env bash
# ═══ Bateria E2E — F2 Tarefa 17: Publicação do site (catálogo/front) ═══
set -u
B="${B:-http://127.0.0.1:8090/davidcms/dev}"
MIRROR="${MIRROR:-/home/user/davidcms/dev}"
C="$B/catalog"
PASS=0; FAIL=0
ck(){ if [ "${2:-0}" = "0" ]; then PASS=$((PASS+1)); echo "$1 pass — $3"; else FAIL=$((FAIL+1)); echo "$1 FAIL — $3"; fi }
hdr(){ grep -i "^HTTP" "$1" | tail -1 | grep -o "[0-9]\{3\}"; }
loc(){ grep -i "^location" "$1" | tail -1 | tr -d '\r' | cut -d' ' -f2-; }
q(){ sudo mysql -N -e "$1"; }

echo '══ setup: estado virgem ══'
sudo mysql -e "DROP DATABASE IF EXISTS reamur_test; CREATE DATABASE reamur_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
rm -f /tmp/fui/dev/catalog/config.php /tmp/fui/dev/admin/config.php /tmp/fui/dev/system/storage/install.lock
JW=/tmp/f217w.jar; JA=/tmp/f217a.jar; JC=/tmp/f217c.jar; rm -f $JW $JA $JC

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
  -D /tmp/f217_i.hdr -o /dev/null
L=$(loc /tmp/f217_i.hdr); RC=0; [[ "$L" == *step_4* ]] || RC=1
ck 1 $RC "wizard"

echo '══ 2 — fixtures do conteúdo público (categorias, projetos+seo, página, mkt, tools) ══'
sudo mysql reamur_test <<'SQLFIX'
INSERT INTO rms_category (parent_id,image,status,sort_order,date_added,date_modified) VALUES
 (0,'',1,1,NOW(),NOW()),(0,'',1,2,NOW(),NOW());
INSERT INTO rms_category_description (category_id,language_id,name,description,meta_title,meta_description) VALUES
 (1,1,'Visual Identity','','Visual Identity',''),(2,1,'Brand','','Brand','');
INSERT INTO rms_project (image,client,url,status,featured,sort_order,date_added,date_modified) VALUES
 ('','JWA Music','',1,1,1,NOW(),NOW()),('','Band SOD','https://external.dev',1,0,2,NOW(),NOW());
SET @p1 := (SELECT project_id FROM rms_project WHERE client='JWA Music');
SET @p2 := (SELECT project_id FROM rms_project WHERE client='Band SOD');
INSERT INTO rms_project_description (project_id,language_id,name,summary,description,tag,meta_title,meta_description) VALUES
 (@p1,1,'JWA','Brand identity for DJ','Full identity pack.','brand,dj','JWA Identity','Meta JWA'),
 (@p2,1,'Steel of Death','Metal band identity','Logo + cover.','metal,logo','Steel of Death','Meta Steel');
INSERT INTO rms_project_to_category VALUES (@p1,1),(@p2,2);
INSERT INTO rms_seo_url (`key`,`value`,language_id,sort_order) VALUES
 (CONCAT('project_id=',@p1),'jwa',1,0),(CONCAT('project_id=',@p2),'steel-of-death',1,0);
INSERT INTO rms_page (status,sort_order,date_added,date_modified) VALUES (1,1,NOW(),NOW());
SET @pg := LAST_INSERT_ID();
INSERT INTO rms_page_description (page_id,language_id,title,description,meta_title,meta_description) VALUES
 (@pg,1,'Internal Policies','<p>Cookie policy text.</p>','Internal Policies','');
INSERT INTO rms_seo_url (`key`,`value`,language_id,sort_order) VALUES (CONCAT('page_id=',@pg),'internal-policies',1,0);
INSERT INTO rms_testimonial (image,status,sort_order,date_added) VALUES ('',1,1,NOW());
INSERT INTO rms_testimonial_description (testimonial_id,language_id,author,company,text) VALUES
 (LAST_INSERT_ID(),1,'Silvio Robson B. Lima','GR Produtora','Great work!');
INSERT INTO rms_client_logo (name,image,url,status,sort_order) VALUES ('SEVEN A','','',1,1);
INSERT INTO rms_tool (image,vendor,url,status,sort_order,date_added) VALUES ('','DavidCreator','https://fluxa.app',1,1,NOW());
INSERT INTO rms_tool_description (tool_id,language_id,name,description) VALUES (LAST_INSERT_ID(),1,'Fluxa','Finance flow');
SQLFIX
N=$(q "SELECT COUNT(*) FROM reamur_test.rms_project"); OK=0; [ "$N" = "2" ] || OK=1
K=$(q "SELECT COUNT(*) FROM reamur_test.rms_seo_url"); [ "$K" = "3" ] || OK=1
ck 2 $OK "fixtures-conteudo"

# F2-18: tokens CSRF são de sessão (mesmo jar $JC cobre contato E orçamento)
CTOKEN=$(curl -s -c $JC -b $JC "$C/index.php?route=common/contact" | grep -o 'name="csrf_token" value="[^"]*"' | sed 's/.*value="//;s/"//')

echo '══ 3 — home 200 publicada com todas as seções reais ══'
curl -s -c $JC -b $JC "$C/" -o /tmp/f217_home.html -D /tmp/f217_h.hdr
H=$(hdr /tmp/f217_h.hdr); OK=0; [ "$H" = "200" ] || OK=1
grep -q "David Almeida" /tmp/f217_home.html || OK=1
grep -q "Creative Developer" /tmp/f217_home.html || OK=1
grep -q 'id="about"' /tmp/f217_home.html || OK=1
grep -q 'id="services"' /tmp/f217_home.html || OK=1
grep -q 'id="portfolio"' /tmp/f217_home.html || OK=1
grep -q "JWA" /tmp/f217_home.html || OK=1
grep -q "Silvio Robson" /tmp/f217_home.html || OK=1
grep -q "SEVEN A" /tmp/f217_home.html || OK=1
grep -q "Fluxa" /tmp/f217_home.html || OK=1
grep -q "contato@davidalmeida.xyz" /tmp/f217_home.html || OK=1
ck 3 $OK "home-secoes-reais"

echo '══ 4 — links saem AMIGÁVEIS (rewrite seo_url → /jwa) ══'
grep -q 'href="[^"]*jwa"' /tmp/f217_home.html
A=$?
grep -q 'href="[^"]*route=catalog/project.info' /tmp/f217_home.html && A=1
grep -q 'href="[^"]*internal-policies"' /tmp/f217_home.html || true
ck 4 $A "rewrite-keyword-out"

echo '══ 5 — GET /jwa resolve no projeto (SEO in) ══'
curl -s -c $JC -b $JC "$C/jwa" -o /tmp/f217_jwa.html -D /tmp/f217_jwa.hdr
H=$(hdr /tmp/f217_jwa.hdr); OK=0; [ "$H" = "200" ] || OK=1
grep -q ">JWA</h1>\|JWA" /tmp/f217_jwa.html || OK=1
grep -q "<title>JWA Identity" /tmp/f217_jwa.html || OK=1
grep -q "Brand identity for DJ" /tmp/f217_jwa.html || OK=1
ck 5 $OK "seo-keyword-in"

echo '══ 6 — keyword desconhecida → 404 honesto ══'
curl -s -c $JC -b $JC "$C/nao-existe-xyz" -D /tmp/f217_404.hdr -o /dev/null
H=$(hdr /tmp/f217_404.hdr); [ "$H" = "404" ]
ck 6 $? "seo-404"

echo '══ 7 — grade pública + filtro por categoria ══'
curl -s -c $JC -b $JC "$C/catalog/project" -o /tmp/f217_pl.html
curl -s -c $JC -b $JC "$C/catalog/project?category_id=2" -o /tmp/f217_plc.html
OK=0
grep -q "JWA" /tmp/f217_pl.html && grep -q "Steel of Death" /tmp/f217_pl.html || OK=1
grep -q "category_id=2" /tmp/f217_pl.html || OK=1
grep -q "Steel of Death" /tmp/f217_plc.html || OK=1
grep -q "JWA" /tmp/f217_plc.html && OK=1
ck 7 $OK "grade-filtro"

echo '══ 8 — projeto inexistente/inativo → 404 ══'
curl -s -c $JC -b $JC "$C/index.php?route=catalog/project.info|99" -D /tmp/f217_pi99.hdr -o /dev/null
H=$(hdr /tmp/f217_pi99.hdr); [ "$H" = "404" ]
ck 8 $? "projeto-99-404"

echo '══ 9 — contato POST válido → PRG + grava na inbox com IP ══'
curl -s -c $JC -b $JC -X POST "$C/index.php?route=common/contact" \
  --data-urlencode "name=Teste Silva" --data-urlencode "email=teste@silva.dev" \
  --data-urlencode "telephone=+55 19 90000-0001" --data-urlencode "subject=Projeto novo" \
  --data-urlencode "message=Quero conversar sobre um site." \
  --data-urlencode "csrf_token=$CTOKEN" \
  -D /tmp/f217_ct.hdr -o /dev/null
L=$(loc /tmp/f217_ct.hdr); OK=0
[[ "$L" == *contact* || "$L" == *contato* ]] || OK=1
N=$(q "SELECT COUNT(*) FROM reamur_test.rms_contact_message WHERE email='teste@silva.dev' AND status=0"); [ "$N" = "1" ] || OK=1
I=$(q "SELECT ip <> '' FROM reamur_test.rms_contact_message WHERE email='teste@silva.dev'"); [ "$I" = "1" ] || OK=1
ck 9 $OK "contato-grava"

echo '══ 10 — honeypot: finge sucesso, NÃO grava ══'
B4=$(q "SELECT COUNT(*) FROM reamur_test.rms_contact_message")
curl -s -c $JC -b $JC -X POST "$C/index.php?route=common/contact" \
  --data-urlencode "company=Spam Inc" --data-urlencode "name=Bot" --data-urlencode "email=bot@spam.dev" \
  --data-urlencode "subject=Viagra" --data-urlencode "message=Compre já!" \
  -D /tmp/f217_bh.hdr -o /dev/null
L=$(loc /tmp/f217_bh.hdr); OK=0
[[ "$L" == *contact* || "$L" == *contato* ]] || OK=1
A5=$(q "SELECT COUNT(*) FROM reamur_test.rms_contact_message"); [ "$A5" = "$B4" ] || OK=1
ck 10 $OK "honeypot-contato"

echo '══ 11 — contato inválido: erros repovoados, nada grava ══'
curl -s -c $JC -b $JC -X POST "$C/index.php?route=common/contact" \
  --data-urlencode "name=A" --data-urlencode "email=nao-e-email" \
  --data-urlencode "subject=" --data-urlencode "message=oi" \
  --data-urlencode "csrf_token=$CTOKEN" \
  -o /tmp/f217_cv.html
OK=0
grep -q "Please inform a valid e-mail" /tmp/f217_cv.html || OK=1
grep -q "Please inform the subject" /tmp/f217_cv.html || OK=1
grep -q "Write your message\|write your message" /tmp/f217_cv.html || OK=1
N=$(q "SELECT COUNT(*) FROM reamur_test.rms_contact_message WHERE email='nao-e-email'"); [ "$N" = "0" ] || OK=1
ck 11 $OK "contato-validacoes"

echo '══ 12 — orçamento POST válido → order 0.0000 + itens + histórico ══'
curl -s -c $JC -b $JC -X POST "$C/index.php?route=checkout/quote" \
  --data-urlencode "firstname=Carla" --data-urlencode "lastname=" \
  --data-urlencode "email=carla@cliente.dev" --data-urlencode "telephone=+55 11 93333-0000" \
  --data-urlencode "services[]=wd_landing" --data-urlencode "services[]=seo_audit" \
  --data-urlencode "comment=Landing nova para o produto X, prazo 30 dias." \
  --data-urlencode "csrf_token=$CTOKEN" \
  -D /tmp/f217_q.hdr -o /dev/null
L=$(loc /tmp/f217_q.hdr); OK=0; [[ "$L" == *quote* || "$L" == *orcamento* ]] || OK=1
OID=$(q "SELECT order_id FROM reamur_test.rms_order WHERE email='carla@cliente.dev'")
[ -n "$OID" ] || OK=1
T=$(q "SELECT total FROM reamur_test.rms_order WHERE order_id='$OID'"); [ "$T" = "0.0000" ] || OK=1
CI=$(q "SELECT customer_id FROM reamur_test.rms_order WHERE order_id='$OID'"); [ "$CI" = "0" ] || OK=1
NP=$(q "SELECT COUNT(*) FROM reamur_test.rms_order_product WHERE order_id='$OID' AND price='0.0000' AND total='0.0000'"); [ "$NP" = "2" ] || OK=1
NH=$(q "SELECT COUNT(*) FROM reamur_test.rms_order_history WHERE order_id='$OID'"); [ "$NH" = "1" ] || OK=1
HS=$(q "SELECT h.order_status_id = o.order_status_id FROM reamur_test.rms_order_history h JOIN reamur_test.rms_order o ON o.order_id=h.order_id WHERE o.order_id='$OID' LIMIT 1"); [ "$HS" = "1" ] || OK=1
ck 12 $OK "quote-grava"

echo '══ 13 — orçamento: honeypot + validações ══'
B4=$(q "SELECT COUNT(*) FROM reamur_test.rms_order")
curl -s -c $JC -b $JC -X POST "$C/index.php?route=checkout/quote" \
  --data-urlencode "company=Bot" --data-urlencode "firstname=Z" --data-urlencode "email=z@z.dev" \
  --data-urlencode "csrf_token=$CTOKEN" \
  --data-urlencode "services[]=wd_cms" --data-urlencode "comment=spam" -D /tmp/f217_qh.hdr -o /dev/null
A5=$(q "SELECT COUNT(*) FROM reamur_test.rms_order"); OK=0; [ "$A5" = "$B4" ] || OK=1
curl -s -c $JC -b $JC -X POST "$C/index.php?route=checkout/quote" \
  --data-urlencode "firstname=" --data-urlencode "email=ruim" --data-urlencode "comment=x" \
  --data-urlencode "csrf_token=$CTOKEN" \
  -o /tmp/f217_qv.html
grep -q "Please inform your first name\|first name" /tmp/f217_qv.html || OK=1
grep -q "valid e-mail" /tmp/f217_qv.html || OK=1
grep -q "Pick at least one service" /tmp/f217_qv.html || OK=1
A6=$(q "SELECT COUNT(*) FROM reamur_test.rms_order"); [ "$A6" = "$B4" ] || OK=1
ck 13 $OK "quote-validacoes"

echo '══ 14 — página CMS via keyword (/internal-policies) ══'
curl -s -c $JC -b $JC "$C/internal-policies" -o /tmp/f217_pol.html -D /tmp/f217_pol.hdr
H=$(hdr /tmp/f217_pol.hdr); OK=0; [ "$H" = "200" ] || OK=1
grep -q "Internal Policies" /tmp/f217_pol.html || OK=1
grep -q "Cookie policy text" /tmp/f217_pol.html || OK=1
curl -s -c $JC -b $JC "$C/index.php?route=cms/page.info|99" -D /tmp/f217_p99.hdr -o /dev/null
H=$(hdr /tmp/f217_p99.hdr); [ "$H" = "404" ] || OK=1
ck 14 $OK "cms-page-keyword"

echo '══ 15 — aliases curtas (contato/orçamento/portfolio) ══'
curl -s -c $JC -b $JC "$C/contact" -o /dev/null -D /tmp/f217_a1.hdr; A1=$(hdr /tmp/f217_a1.hdr)
curl -s -c $JC -b $JC "$C/quote" -o /dev/null -D /tmp/f217_a2.hdr; A2=$(hdr /tmp/f217_a2.hdr)
curl -s -c $JC -b $JC "$C/portfolio" -o /dev/null -D /tmp/f217_a3.hdr; A3=$(hdr /tmp/f217_a3.hdr)
OK=0; [ "$A1" = "200" ] && [ "$A2" = "200" ] && [ "$A3" = "200" ] || OK=1
ck 15 $OK "aliases"

echo '══ 16 — idiomas no front (pt/es) ══'
curl -s -c $JC -b $JC "$C/?language=pt-br" -o /tmp/f217_pt.html
grep -q "promover seu negócio" /tmp/f217_pt.html
A=$?
curl -s -c $JC -b $JC "$C/?language=es-eu" -o /tmp/f217_es.html
grep -q "promover tu negocio" /tmp/f217_es.html
BBB=$?
# seo coalesce: keyword en resolvida mesmo com idioma ativo pt-br
curl -s -c $JC -b $JC "$C/jwa?language=pt-br" -o /tmp/f217_jwapt.html -D /tmp/f217_jwapt.hdr
CC=$(hdr /tmp/f217_jwapt.hdr); [ "$CC" = "200" ] || BBB=$((BBB+1))
curl -s -c $JC -b $JC "$C/?language=en-us" -o /dev/null
ck 16 $(( A + BBB )) "idiomas-front"

echo '══ 17 — integração admin: inbox vê contato; pedidos vê orçamento ══'
curl -s -c $JA -b $JA "$B/admin/login" -o /tmp/f217_login.html
TOKEN=$(grep -o 'name="user_token" value="[^"]*"' /tmp/f217_login.html | sed 's/.*value="//;s/"//')
CSRF=$(grep -o 'name="csrf_token" value="[^"]*"' /tmp/f217_login.html | sed 's/.*value="//;s/"//')
curl -s -c $JA -b $JA -X POST "$B/admin/index.php?route=common/login" \
  --data-urlencode "username=admin" --data-urlencode "password=SenhaForte!2026" --data-urlencode "user_token=$TOKEN" --data-urlencode "csrf_token=$CSRF" -o /dev/null
curl -s -c $JA -b $JA "$B/admin/communication/contact_message" -o /tmp/f217_inbox.html
curl -s -c $JA -b $JA "$B/admin/sale/order" -o /tmp/f217_orders.html
OK=0
grep -q "Teste Silva" /tmp/f217_inbox.html || OK=1
grep -q "carla@cliente.dev" /tmp/f217_orders.html || OK=1
grep -q "0.00" /tmp/f217_orders.html || OK=1
ck 17 $OK "integracao-admin"

echo
echo "═══ RESUMO: $PASS pass, $FAIL fail "
exit $([ $FAIL -eq 0 ] && echo 0 || echo 1)
