#!/usr/bin/env bash
# ════════════════════════════════════════════════════════════════
# e2e_f8v2_appearance.sh — personalização efetiva do painel admin
# Fase 8 / tarefa V2 (ADR base 0047; critérios revisados em 2026-10-22)
#
# Verifica: upgrade, ACL, CSRF obrigatório, POST/Redirect/GET, skin global
# aplicada em nova requisição, override pessoal, desligamento do override sem
# perder preferências, isolamento entre preferência global e pessoal e ordem
# CSS necessária para a skin vencer os defaults do admin.
#
# Pré-requisitos: executar dev/tests/e2e/setup_sandbox.sh primeiro.
# ════════════════════════════════════════════════════════════════
set -u

B="${B:-http://127.0.0.1:8090/davidcms/dev}"
FUI="${FUI:-/var/www/html/davidcms/dev}"
MIRROR="${MIRROR:-/home/user/davidcms/dev}"
PASS=0; FAIL=0

ck() {
  if [ "${2:-0}" = "0" ]; then PASS=$((PASS + 1)); echo "$1 pass — $3";
  else FAIL=$((FAIL + 1)); echo "$1 FAIL — $3"; fi
}
q() { sudo mysql -N --default-character-set=utf8mb4 -e "$1"; }
loc() { grep -i '^location:' "$1" | tail -1 | tr -d '\r' | cut -d' ' -f2-; }
csrf_from() { grep -o 'name="csrf_token" value="[^"]*"' "$1" | head -1 | sed 's/.*value="//;s/"//'; }

A="$B/admin/index.php?route=setting/appearance"

if ! command -v sudo >/dev/null 2>&1 || ! command -v mysql >/dev/null 2>&1; then
  echo 'ERRO: teste requer sudo + mysql; veja setup_sandbox.sh.' >&2
  exit 2
fi

printf '%s\n' '══ setup: banco limpo + wizard + upgrades ══'
sudo mysql -e "DROP DATABASE IF EXISTS reamur_test; CREATE DATABASE reamur_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
rm -f "$FUI/catalog/config.php" "$FUI/admin/config.php" "$FUI/system/storage/install.lock"
JW=/tmp/f8v2w.jar; JA=/tmp/f8v2a.jar
rm -f "$JW" "$JA"
curl -s -c "$JW" -b "$JW" "$B/install/index.php?route=install/step_1" -o /dev/null
curl -s -c "$JW" -b "$JW" -X POST "$B/install/index.php?route=install/step_1" -o /dev/null
curl -s -c "$JW" -b "$JW" -X POST "$B/install/index.php?route=install/step_2" -o /dev/null
curl -s -c "$JW" -b "$JW" -X POST "$B/install/index.php?route=install/step_3" \
  --data-urlencode 'db_driver=mysqli' --data-urlencode 'db_hostname=127.0.0.1' --data-urlencode 'db_port=3306' \
  --data-urlencode 'db_username=reamur' --data-urlencode 'db_password=reamur123' --data-urlencode 'db_database=reamur_test' \
  --data-urlencode 'db_prefix=rms_' --data-urlencode 'http_server=' \
  --data-urlencode 'username=admin' --data-urlencode 'firstname=Test' --data-urlencode 'lastname=Admin' \
  --data-urlencode 'email=admin@example.com' --data-urlencode 'password=SenhaForte!2026' --data-urlencode 'confirm=SenhaForte!2026' \
  -D /tmp/f8v2_install.hdr -o /dev/null
for upgrade in "$MIRROR"/install/sql/upgrade_*.sql; do
  [ -f "$upgrade" ] && sudo mysql reamur_test < "$upgrade"
done
sudo rm -f "$FUI/system/storage/cache/cache.setting.config"

curl -s -c "$JA" -b "$JA" "$B/admin/login" -o /tmp/f8v2_login.html
TOKEN=$(grep -o 'name="user_token" value="[^"]*"' /tmp/f8v2_login.html | sed 's/.*value="//;s/"//')
LOGIN_CSRF=$(csrf_from /tmp/f8v2_login.html)
curl -s -c "$JA" -b "$JA" -X POST "$B/admin/index.php?route=common/login" \
  --data-urlencode 'username=admin' --data-urlencode 'password=SenhaForte!2026' \
  --data-urlencode "user_token=$TOKEN" --data-urlencode "csrf_token=$LOGIN_CSRF" \
  -D /tmp/f8v2_login.hdr -o /dev/null
L=$(loc /tmp/f8v2_login.hdr)
RC=0; [[ "$L" == *dashboard* ]] || RC=1
[ "$(q "SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='reamur_test' AND TABLE_NAME='rms_user' AND COLUMN_NAME='theme_prefs'")" = "1" ] || RC=1
ck 1 "$RC" 'wizard-upgrade-login-theme-column'

printf '%s\n' '══ 2 — GET: forms protegidos, estado e shell self-hosted ══'
curl -s -c "$JA" -b "$JA" "$A" -o /tmp/f8v2_appearance_get.html
RC=0
[ "$(grep -o 'name="csrf_token" value="[^"]*"' /tmp/f8v2_appearance_get.html | wc -l)" -eq 2 ] || RC=1
grep -q 'id="user-override"' /tmp/f8v2_appearance_get.html || RC=1
grep -q 'view/js/appearance.js' /tmp/f8v2_appearance_get.html || RC=1
grep -q 'id="g-skin-ocean"' /tmp/f8v2_appearance_get.html || RC=1
grep -q 'id="g-acc-blue"' /tmp/f8v2_appearance_get.html || RC=1
grep -q 'Institutional blue' /tmp/f8v2_appearance_get.html || RC=1
grep -q 'data-label-inactive="Use panel default"' /tmp/f8v2_appearance_get.html || RC=1
grep -q 'Save panel default' /tmp/f8v2_appearance_get.html || RC=1
ck 2 "$RC" 'appearance-ui-csrf-preview'

printf '%s\n' '══ 3 — POST sem CSRF é rejeitado e não altera settings ══'
BEFORE=$(q "SELECT value FROM reamur_test.rms_setting WHERE code='config' AND \`key\`='config_admin_skin'")
curl -s -c "$JA" -b "$JA" -X POST "$A" \
  --data-urlencode 'scope=global' --data-urlencode 'skin=plum' \
  --data-urlencode 'sidebar=auto' --data-urlencode 'density=comfortable' \
  --data-urlencode 'font_scale=100' --data-urlencode 'mode=light' \
  -o /tmp/f8v2_no_csrf.html
AFTER=$(q "SELECT value FROM reamur_test.rms_setting WHERE code='config' AND \`key\`='config_admin_skin'")
RC=0
grep -q 'security token is missing or expired' /tmp/f8v2_no_csrf.html || RC=1
[ "$BEFORE" = "$AFTER" ] || RC=1
ck 3 "$RC" 'csrf-deny-no-write'

printf '%s\n' '══ 4 — padrão global: PRG e skin aplicada no admin ══'
curl -s -c "$JA" -b "$JA" "$A" -o /tmp/f8v2_global_form.html
CSRF=$(csrf_from /tmp/f8v2_global_form.html)
curl -s -c "$JA" -b "$JA" -X POST "$A" -D /tmp/f8v2_global_save.hdr -o /dev/null \
  --data-urlencode "csrf_token=$CSRF" --data-urlencode 'scope=global' \
  --data-urlencode 'skin=ocean' --data-urlencode 'accent_choice=' \
  --data-urlencode 'sidebar=light' --data-urlencode 'density=compact' \
  --data-urlencode 'font_scale=105' --data-urlencode 'mode=dark'
L=$(loc /tmp/f8v2_global_save.hdr)
RC=0
[[ "$L" == *setting/appearance* ]] || RC=1
curl -s -c "$JA" -b "$JA" "$B/admin/index.php?route=common/dashboard" -o /tmp/f8v2_global_dashboard.html
ROOT=$(grep -o '<html[^>]*>' /tmp/f8v2_global_dashboard.html | head -1)
[[ "$ROOT" == *'data-rms-skin="ocean"'* && "$ROOT" == *'data-rms-density="compact"'* && "$ROOT" == *'data-bs-theme="dark"'* ]] || RC=1
ADMIN_POS=$(grep -n -m1 'view/css/admin.css' /tmp/f8v2_global_dashboard.html | cut -d: -f1)
SKIN_POS=$(grep -n -m1 'view/css/themes/ocean.css' /tmp/f8v2_global_dashboard.html | cut -d: -f1)
[ -n "$ADMIN_POS" ] && [ -n "$SKIN_POS" ] && [ "$ADMIN_POS" -lt "$SKIN_POS" ] || RC=1
[ "$(q "SELECT value FROM reamur_test.rms_setting WHERE code='config' AND \`key\`='config_admin_skin'")" = 'ocean' ] || RC=1
ck 4 "$RC" 'global-prg-applies-in-admin'

printf '%s\n' '══ 5 — override pessoal: aplica sem alterar padrão global ══'
curl -s -c "$JA" -b "$JA" "$A" -o /tmp/f8v2_user_form.html
CSRF=$(csrf_from /tmp/f8v2_user_form.html)
curl -s -c "$JA" -b "$JA" -X POST "$A" -D /tmp/f8v2_user_save.hdr -o /dev/null \
  --data-urlencode "csrf_token=$CSRF" --data-urlencode 'scope=user' --data-urlencode 'user_override=1' \
  --data-urlencode 'skin=forest' --data-urlencode 'accent_choice=' \
  --data-urlencode 'sidebar=dark' --data-urlencode 'density=comfortable' \
  --data-urlencode 'font_scale=95' --data-urlencode 'mode=light'
L=$(loc /tmp/f8v2_user_save.hdr)
RC=0
[[ "$L" == *setting/appearance* ]] || RC=1
curl -s -c "$JA" -b "$JA" "$B/admin/index.php?route=common/dashboard" -o /tmp/f8v2_user_dashboard.html
ROOT=$(grep -o '<html[^>]*>' /tmp/f8v2_user_dashboard.html | head -1)
[[ "$ROOT" == *'data-rms-skin="forest"'* && "$ROOT" == *'data-rms-sidebar="dark"'* ]] || RC=1
[[ "$ROOT" != *'data-rms-skin="ocean"'* ]] || RC=1
[ "$(q "SELECT value FROM reamur_test.rms_setting WHERE code='config' AND \`key\`='config_admin_skin'")" = 'ocean' ] || RC=1
[ "$(q "SELECT JSON_UNQUOTE(JSON_EXTRACT(theme_prefs,'$.skin')) FROM reamur_test.rms_user WHERE user_id=1")" = 'forest' ] || RC=1
ck 5 "$RC" 'user-override-isolated-and-applied'

printf '%s\n' '══ 6 — desligar override usa global e preserva preferências pessoais ══'
curl -s -c "$JA" -b "$JA" "$A" -o /tmp/f8v2_reset_form.html
CSRF=$(csrf_from /tmp/f8v2_reset_form.html)
curl -s -c "$JA" -b "$JA" -X POST "$A" -D /tmp/f8v2_reset_save.hdr -o /dev/null \
  --data-urlencode "csrf_token=$CSRF" --data-urlencode 'scope=user'
L=$(loc /tmp/f8v2_reset_save.hdr)
RC=0
[[ "$L" == *setting/appearance* ]] || RC=1
curl -s -c "$JA" -b "$JA" "$B/admin/index.php?route=common/dashboard" -o /tmp/f8v2_reset_dashboard.html
ROOT=$(grep -o '<html[^>]*>' /tmp/f8v2_reset_dashboard.html | head -1)
[[ "$ROOT" == *'data-rms-skin="ocean"'* ]] || RC=1
[ "$(q "SELECT JSON_UNQUOTE(JSON_EXTRACT(theme_prefs,'$.skin')) FROM reamur_test.rms_user WHERE user_id=1")" = 'forest' ] || RC=1
[ "$(q "SELECT JSON_UNQUOTE(JSON_EXTRACT(theme_prefs,'$.override')) FROM reamur_test.rms_user WHERE user_id=1")" = 'false' ] || RC=1
curl -s -c "$JA" -b "$JA" "$A" -o /tmp/f8v2_reset_ui.html
grep -q 'data-label-inactive="Use panel default"' /tmp/f8v2_reset_ui.html || RC=1
# Inactive personal controls must be disabled; submit remains available to re-enable/reset.
grep -A1 'id="u-skin-light"' /tmp/f8v2_reset_ui.html | grep -q 'disabled' || RC=1
grep -q 'rms-btn-save-label">Use panel default' /tmp/f8v2_reset_ui.html || RC=1
ck 6 "$RC" 'override-off-falls-back-and-retains-prefs'

printf '%s\n' '══ 7 — fontes e sintaxe dos ativos da personalização ══'
RC=0
php -l "$MIRROR/admin/controller/setting/appearance.php" >/dev/null 2>&1 || RC=1
for lang in en-us pt-br es-eu; do php -l "$MIRROR/admin/language/$lang/setting/appearance.php" >/dev/null 2>&1 || RC=1; done
node --check "$MIRROR/admin/view/js/appearance.js" >/dev/null 2>&1 || RC=1
grep -q 'syncUserControls' "$MIRROR/admin/view/js/appearance.js" || RC=1
grep -q 'data-rms-skin' "$MIRROR/admin/view/template/common/header.twig" || RC=1
ck 7 "$RC" 'php-node-theme-contracts'

echo
echo "════════════════ summary: $PASS pass / $FAIL fail ════════════════"
[ "$FAIL" = "0" ]
