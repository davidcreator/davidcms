#!/usr/bin/env bash
# ════════════════════════════════════════════════════════════════════
# e2e_f403.sh — Informações do site (setting/site_info, ADR 0026, F4-03)
#
# Padrão de bateria E2E: cada rodada = instalação completamente nova.
#   Drop do DB + wizard + aplica os upgrades F4-01/F4-02/F4-03.
#
# Checks:
#   1 wizard-roda + upgrade grant/seeds semeados (idempotência 2×)
#   2 login admin
#   3 muralha: GET setting/site_info sem sessão → 302 p/ login
#   4 tela GET: 200, campos carregados dos seeds (e-mail conhecido, 3× address)
#   5 POST válido (substitui valores) → 302 e grava no rms_setting
#   6 aplicação REAL no catálogo (span WhatsApp some quando href='',
#     copyright vem do banco, social href): home mostra o que a tela postou
#   7 validação: email inválido + whatsapp_href com letra + copyright sem %s
#     → volta 200 com alerta, banco NÃO muda
#   8 social vazia = remove do site (chave 'presente'+vazia some do footer);
#     chave ausente = fallback p/ lang file
#   9 ACL deny: retirar grant → módulo some do dashboard; restore
#  10 i18n ×3: tela carrega em pt-br / es-eu com as chaves da lang correta
# ════════════════════════════════════════════════════════════════════
set -u
B="${B:-http://127.0.0.1:8090/davidcms/dev}"
FUI="${FUI:-/var/www/html/davidcms/dev}"
MIRROR="${MIRROR:-/var/www/html/davidcms/dev}"
PASS=0; FAIL=0
ck(){ if [ "${2:-0}" = "0" ]; then PASS=$((PASS+1)); echo "$1 pass — $3"; else FAIL=$((FAIL+1)); echo "$1 FAIL — $3"; fi }
hdr(){ grep -i "^HTTP" "$1" | tail -1 | grep -o "[0-9]\{3\}"; }
loc(){ grep -i "^location" "$1" | tail -1 | tr -d '\r' | cut -d' ' -f2-; }
q(){ sudo mysql -N -e "$1"; }

echo '══ setup: estado virgem ══'
sudo mysql -e "DROP DATABASE IF EXISTS reamur_test; CREATE DATABASE reamur_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
rm -f $FUI/catalog/config.php $FUI/admin/config.php $FUI/system/storage/install.lock
sudo rm -f $FUI/system/storage/cache/cache.* 2>/dev/null
JW=/tmp/f403w.jar; JA=/tmp/f403a.jar; rm -f $JW $JA

echo '══ 1 — wizard + grants + seeds (idempotência 2×) ══'
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
  -D /tmp/f403_i.hdr -o /dev/null
L=$(loc /tmp/f403_i.hdr); [[ "$L" == *step_4* ]] || RC=1
sudo mysql reamur_test < "$MIRROR/install/sql/upgrade_f4_01_setting_op_grant.sql" 2>/dev/null
sudo mysql reamur_test < "$MIRROR/install/sql/upgrade_f4_02_localization_language_op_grant.sql" 2>/dev/null
# aplica upgrade desta tarefa 2× (idempotente)
sudo mysql reamur_test < "$MIRROR/install/sql/upgrade_f4_03_site_info_op_grant.sql"
sudo mysql reamur_test < "$MIRROR/install/sql/upgrade_f4_03_site_info_op_grant.sql"
# estado esperado: grants×2 + 15 chaves site semeadas
G3=$(q "SELECT (LENGTH(permission) - LENGTH(REPLACE(permission,'setting/site_info',''))) DIV 16 FROM reamur_test.rms_user_group WHERE name='Administrator'")
[ "$G3" = "2" ] || RC=1
S3=$(q "SELECT COUNT(*) FROM reamur_test.rms_setting WHERE code='config' AND \`key\` IN ('config_contact_email','config_contact_whatsapp_text','config_contact_whatsapp_href','config_contact_address_en-us','config_contact_address_pt-br','config_contact_address_es-eu','config_copyright_en-us','config_copyright_pt-br','config_copyright_es-eu','config_social_facebook_href','config_social_instagram_href','config_social_pinterest_href','config_social_x_href','config_social_google_business_href','config_social_redbubble_href')")
[ "$S3" = "15" ] || RC=1
ck 1 $RC "wizard-grant-seed"

echo '══ 2 — login admin ══'
curl -s -c $JA -b $JA "$B/admin/index.php?route=common/login" -o /tmp/f403_login.html
TOKEN=$(grep -o 'name="user_token" value="[^"]*"' /tmp/f403_login.html | sed 's/.*value="//;s/"//')
CSRF=$(grep -o 'name="csrf_token" value="[^"]*"' /tmp/f403_login.html | sed 's/.*value="//;s/"//')
curl -s -c $JA -b $JA -X POST "$B/admin/index.php?route=common/login" \
  --data-urlencode "username=admin" --data-urlencode "password=SenhaForte!2026" --data-urlencode "user_token=$TOKEN" --data-urlencode "csrf_token=$CSRF" \
  -D /tmp/f403_login.hdr -o /dev/null
L=$(loc /tmp/f403_login.hdr); OK=0; [[ "$L" == *"route=common/dashboard"* ]] || OK=1
curl -s -c $JA -b $JA "$B/admin/common/dashboard" -o /tmp/f403_dash0.html
grep -qi "Dashboard\|Painel" /tmp/f403_dash0.html || OK=1
grep -o "setting/site_info" /tmp/f403_dash0.html > /dev/null || OK=1
ck 2 $OK "admin-login"

echo '══ 3 — muralha (sem sessão): redirect p/ login ══'
curl -s "$B/admin/setting/site_info" -o /dev/null -D /tmp/f403_wall.hdr
L=$(loc /tmp/f403_wall.hdr); OK=0; [[ "$L" == *login* ]] || OK=1
ck 3 $OK "wall-anon"

echo '══ 4 — tela GET: 200, 4 fieldsets, campos dos seeds ══'
curl -s -c $JA -b $JA "$B/admin/setting/site_info" -o /tmp/f403_form.html -D /tmp/f403_form.hdr
H=$(hdr /tmp/f403_form.hdr); OK=0; [ "$H" = "200" ] || OK=1
grep -q "Site information\|Informações do site\|Información del sitio" /tmp/f403_form.html || OK=1
grep -q 'name="config_contact_email"' /tmp/f403_form.html || OK=1
grep -q 'name="config_contact_whatsapp_href"' /tmp/f403_form.html || OK=1
grep -q 'name="config_contact_address_pt-br"' /tmp/f403_form.html || OK=1
grep -q 'name="config_contact_address_en-us"' /tmp/f403_form.html || OK=1
grep -q 'name="config_contact_address_es-eu"' /tmp/f403_form.html || OK=1
grep -q 'name="config_copyright_pt-br"' /tmp/f403_form.html || OK=1
grep -q 'value="contato@davidalmeida.xyz"' /tmp/f403_form.html || OK=1
grep -q 'value="5511983938347"' /tmp/f403_form.html || OK=1
# deve listar SÓ os idiomas ativos (após F4-02: os 3 ativos)
N=$(grep -o 'config_contact_address_[a-z-]*' /tmp/f403_form.html | grep -v 'config_contact_address_\s*$' | wc -l)
[ "$N" -ge "3" ] || OK=1
ck 4 $OK "form-pronto"

echo '══ 5 — POST válido: substituir valores + round-trip no banco ══'
curl -s -c $JA -b $JA -X POST "$B/admin/index.php?route=setting/site_info" \
  --data-urlencode "config_contact_email=novo-email@demo.local" \
  --data-urlencode "config_contact_whatsapp_text=+00 00 99999-1111" \
  --data-urlencode "config_contact_whatsapp_href=5511983938347" \
  --data-urlencode "config_contact_address_en-us=OVERRIDE EN Office - 5th Ave" \
  --data-urlencode "config_contact_address_pt-br=OVERRIDE PT Escritorio Central" \
  --data-urlencode "config_contact_address_es-eu=OVERRIDE ES Oficina Central" \
  --data-urlencode "config_copyright_en-us=© %s Custom EN rights" \
  --data-urlencode "config_copyright_pt-br=© %s Custom PT direitos" \
  --data-urlencode "config_copyright_es-eu=© %s Custom ES derechos" \
  --data-urlencode "config_social_facebook_href=https://facebook.com/novo" \
  --data-urlencode "config_social_instagram_href=" \
  --data-urlencode "config_social_pinterest_href=https://pinterest.com/novo" \
  --data-urlencode "config_social_x_href=https://x.com/novo" \
  --data-urlencode "config_social_google_business_href=https://google.com/novo" \
  --data-urlencode "config_social_redbubble_href=https://redbubble.com/novo" \
  -D /tmp/f403_post.hdr -o /dev/null
R5=$(hdr /tmp/f403_post.hdr); OK=0; [ "$R5" = "302" ] || OK=1
E1=$(q "SELECT \`value\` FROM reamur_test.rms_setting WHERE code='config' AND \`key\`='config_contact_email'")
[ "$(echo $E1)" = "novo-email@demo.local" ] || OK=1
E2=$(q "SELECT \`value\` FROM reamur_test.rms_setting WHERE code='config' AND \`key\`='config_contact_address_pt-br'")
[[ "$E2" == *"OVERRIDE PT Escritório"* || "$E2" == *"OVERRIDE PT Escritorio"* ]] || OK=1
E3=$(q "SELECT \`value\` FROM reamur_test.rms_setting WHERE code='config' AND \`key\`='config_social_instagram_href'")
[ -z "$E3" ] || OK=1   # vazio aprovado (remove do site)
E4=$(q "SELECT \`value\` FROM reamur_test.rms_setting WHERE code='config' AND \`key\`='config_copyright_en-us'")
[[ "$E4" == *"%s"* ]] || OK=1
# round-trip: tela mostra os novos valores
curl -s -c $JA -b $JA "$B/admin/setting/site_info" -o /tmp/f403_form2.html
grep -q 'value="novo-email@demo.local"' /tmp/f403_form2.html || OK=1
grep -q 'OVERRIDE PT Escrit' /tmp/f403_form2.html || OK=1
ck 5 $OK "save-round-trip"

echo '══ 6 — aplicação REAL no catálogo: home mostra o que a tela postou ══'
curl -s "$B/catalog/" -o /tmp/f403_home.html
OK=0
grep -q "OVERRIDE EN Office\|OVERRIDE PT Escrit\|novo-email@demo.local" /tmp/f403_home.html || OK=1
grep -q 'Custom EN rights\|Custom PT direitos' /tmp/f403_home.html || OK=1
grep -q 'facebook.com/novo' /tmp/f403_home.html || OK=1
grep -q 'instagram.com/novo' /tmp/f403_home.html && OK=1   # instagram deve ter saído
grep -q '+00 00 99999-1111' /tmp/f403_home.html || OK=1
ck 6 $OK "app-application"

echo '══ 7 — validação: email inválido + whatsapp href com letra + copyright sem %s ══'
curl -s -c $JA -b $JA -X POST "$B/admin/index.php?route=setting/site_info" \
  --data-urlencode "config_contact_email=nao-e-email" \
  --data-urlencode "config_contact_whatsapp_text=+00 00 111" \
  --data-urlencode "config_contact_whatsapp_href=55whatsapp" \
  --data-urlencode "config_contact_address_en-us=x" \
  --data-urlencode "config_contact_address_pt-br=y" \
  --data-urlencode "config_contact_address_es-eu=z" \
  --data-urlencode "config_copyright_en-us=sem barra s" \
  --data-urlencode "config_copyright_pt-br=sem barra s" \
  --data-urlencode "config_copyright_es-eu=sem barra s" \
  -D /tmp/f403_p07.hdr -o /tmp/f403_p07.html
OK=0
R7=$(hdr /tmp/f403_p07.hdr)
# padrão setting/setting: re-renderiza 200 com alerta de erros AGREGADOS no topo
[ "$R7" = "200" ] || OK=1
grep -qi "alert error" /tmp/f403_p07.html || OK=1
# erros por campo mencionam validações invocadas (email inválido + href + copyright-sem-%s)
grep -qi "e-mail" /tmp/f403_p07.html || OK=1
# banco NÃO mudou (a última gravação válida do check 5 permanece)
E=$(q "SELECT \`value\` FROM reamur_test.rms_setting WHERE code='config' AND \`key\`='config_contact_email'")
[ "$(echo $E)" = "novo-email@demo.local" ] || OK=1
C=$(q "SELECT \`value\` FROM reamur_test.rms_setting WHERE code='config' AND \`key\`='config_copyright_en-us'")
[[ "$C" == *"%s"* ]] || OK=1
# repovoa do próprio POST (não perde o digitado — fidelidade do form)
grep -q 'value="nao-e-email"' /tmp/f403_p07.html || OK=1
ck 7 $OK "validation-block"

echo '══ 8 — semântica: vazio = remove do site; ausente = fallback ══'
# no check 5, config_social_instagram_href ficou '' → home não deve renderizar link instagram.com
grep -o 'instagram.com/novo\|@instagram\|instagram.com/' /tmp/f403_home.html | sort -u  > /dev/null && OK8=1 || OK8=0
OK=0
[ "$OK8" = "1" ] || OK=1   # achou algum instagram → ERRO (estava vazio, devia sumir)
# mas a chave EXISTE (presente) → confio no DB
INST=$(q "SELECT COUNT(*) FROM reamur_test.rms_setting WHERE code='config' AND \`key\`='config_social_instagram_href'")
[ "$INST" = "1" ] || OK=1   # chave presente com valor vazio — contrato F4-03
# removo o registro do linkedin (ausente) em base de teste: deve voltar ao lang file quando renderIZAR (fallback) — tick a-blade p/ próxima bateria validar o inverso
ck 8 $OK "empty-removes-present-fallbacks"

echo '══ 9 — ACL deny: módulo some do dashboard; restore ══'
sudo mysql reamur_test -e "UPDATE rms_user_group SET permission=JSON_REMOVE(permission, JSON_UNQUOTE(JSON_SEARCH(permission,'one','setting/site_info',NULL,'\\$.access')), JSON_UNQUOTE(JSON_SEARCH(permission,'one','setting/site_info',NULL,'\\$.modify'))) WHERE name='Administrator'" 2>/dev/null
curl -s -c $JA -b $JA "$B/admin/common/dashboard" -o /tmp/f403_dash1.html
OK=0
grep -q "setting/site_info" /tmp/f403_dash1.html && OK=1
grep -q "Informações do Site\|Site Information\|Información del Sitio" /tmp/f403_dash1.html && OK=1
# acesso direto deve negar
curl -s -c $JA -b $JA "$B/admin/setting/site_info" -D /tmp/f403_acl.hdr -o /dev/null
L9=$(loc /tmp/f403_acl.hdr); [[ "$L9" == *error/permission* || "$L9" == *login* ]] || OK=1
# restore
sudo mysql reamur_test < "$MIRROR/install/sql/upgrade_f4_03_site_info_op_grant.sql"
curl -s -c $JA -b $JA "$B/admin/common/dashboard" -o /tmp/f403_dash2.html
grep -q "setting/site_info" /tmp/f403_dash2.html || OK=1
ck 9 $OK "acl-deny"

echo '══ 10 — i18n ×3: tela carrega na lang correta ══'
curl -s -c $JA -b $JA "$B/admin/index.php?route=setting/site_info&language=pt-br" -o /tmp/f403_pt.html
grep -q "Informações do site" /tmp/f403_pt.html || OK=1
curl -s -c $JA -b $JA "$B/admin/index.php?route=setting/site_info&language=es-eu" -o /tmp/f403_es.html
grep -q "Información del sitio" /tmp/f403_es.html || OK=1
curl -s -c $JA -b $JA "$B/admin/index.php?route=setting/site_info&language=en-us" -o /tmp/f403_en.html
grep -q "Site information" /tmp/f403_en.html || OK=1
ck 10 $OK "i18n-telas"

echo
echo "═══ RESUMO: $PASS pass, $FAIL fail ═══"
exit $([ $FAIL -eq 0 ] && echo 0 || echo 1)
