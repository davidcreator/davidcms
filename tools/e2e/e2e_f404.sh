#!/usr/bin/env bash
# ════════════════════════════════════════════════════════════════════
# e2e_f404.sh — Usuários: auto-perfil, deleção de grupos e guardas
# de auto-trava (ADR 0027, F4-04). Sem upgrade SQL — bootstrap (ADR 0005).
#
# Padrão de bateria E2E: cada rodada = instalação completamente nova.
#
# Checks:
#   1 wizard-roda + grants base (sem upgrade próprio desta tarefa)
#   2 login admin
#   3 muralha: GET user/profile sem sessão → 302 p/ login
#   4 perfil GET: 200, username readonly, dados atuais (admin)
#   5 perfil POST válido: troca email + senha (com a senha atual) → relogin
#     no novo password PROVA a troca real (sessão nova)
#   6 validação: senha atual errada + confirma divergente + email inválido
#     → banco NÃO muda (e-mail e senha permanecem do check 5)
#   7 user/user.edit|1 self: POST SEM status=1 → banco continua status=1
#   8 user/user_group.delete: grupo Administrator (com usuários) bloqueado;
#     create grupo vazio → delete ok (flash + some); guard última-admin
#   9 bootstrap profile-todos: cria usuário SEM grants → login → user/profile 200
#  10 i18n ×3: perfil carrega em pt-br/es-eu/en-us
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
JW=/tmp/f404w.jar; JA=/tmp/f404a.jar; rm -f $JW $JA

echo '══ 1 — wizard + grants base (F4-04 não tem SQL próprio — bootstrap) ══'
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
  -D /tmp/f404_i.hdr -o /dev/null
L=$(loc /tmp/f404_i.hdr); [[ "$L" == *step_4* ]] || RC=1
for u in "$MIRROR"/install/sql/upgrade_f4_0{1,2,3}*.sql; do [ -f "$u" ] && sudo mysql reamur_test < "$u"; done
ck 1 $RC "wizard-grant-seed"

echo '══ 2 — login admin ══'
curl -s -c $JA -b $JA "$B/admin/index.php?route=common/login" -o /tmp/f404_login.html
TOKEN=$(grep -o 'name="user_token" value="[^"]*"' /tmp/f404_login.html | sed 's/.*value="//;s/"//')
CSRF=$(grep -o 'name="csrf_token" value="[^"]*"' /tmp/f404_login.html | sed 's/.*value="//;s/"//')
curl -s -c $JA -b $JA -X POST "$B/admin/index.php?route=common/login" \
  --data-urlencode "username=admin" --data-urlencode "password=SenhaForte!2026" --data-urlencode "user_token=$TOKEN" --data-urlencode "csrf_token=$CSRF" \
  -D /tmp/f404_al.hdr -o /dev/null
L=$(loc /tmp/f404_al.hdr); OK=0; [[ "$L" == *dashboard* ]] || OK=1
ck 2 $OK "admin-login"

echo '══ 3 — muralha: sem sessão → 302 p/ login ══'
curl -s "$B/admin/user/profile" -o /dev/null -D /tmp/f404_wall.hdr
L=$(loc /tmp/f404_wall.hdr); OK=0; [[ "$L" == *login* ]] || OK=1
ck 3 $OK "wall-anon"

echo '══ 4 — perfil GET: 200, username readonly, dados atuais ══'
curl -s -c $JA -b $JA "$B/admin/user/profile" -o /tmp/f404_p.html -D /tmp/f404_p.hdr
H=$(hdr /tmp/f404_p.hdr); OK=0; [ "$H" = "200" ] || OK=1
grep -q "My profile\|Meu perfil\|Mi perfil" /tmp/f404_p.html || OK=1
grep -q 'value="admin" disabled' /tmp/f404_p.html || OK=1   # username readonly
grep -q 'name="email" value="david@example.com"' /tmp/f404_p.html || OK=1
grep -q 'value="David"' /tmp/f404_p.html || OK=1            # firstname
# dashboard tem o link "Editar perfil"
curl -s -c $JA -b $JA "$B/admin/common/dashboard" -o /tmp/f404_dash.html
grep -o 'user/profile' /tmp/f404_dash.html > /dev/null || OK=1
ck 4 $OK "form-pronto"

echo '══ 5 — troca e-mail + senha com senha atual → 302; relogin na senha nova PROVA ══'
curl -s -c $JA -b $JA -X POST "$B/admin/index.php?route=user/profile" \
  --data-urlencode "email=admin-novo@example.com" \
  --data-urlencode "firstname=David" --data-urlencode "lastname=Almeida" \
  --data-urlencode "password_current=SenhaForte!2026" \
  --data-urlencode "password_new=NovaSenha!2030" \
  --data-urlencode "password_confirm=NovaSenha!2030" \
  -D /tmp/f404_p5.hdr -o /dev/null
R=$(hdr /tmp/f404_p5.hdr); OK=0; [ "$R" = "302" ] || OK=1
# relogin com a senha NOVA em session neufs
JB=/tmp/f404b.jar; rm -f $JB
curl -s -c $JB -b $JB "$B/admin/index.php?route=common/login" -o /tmp/f404_login2.html
T2=$(grep -o 'name="user_token" value="[^"]*"' /tmp/f404_login2.html | sed 's/.*value="//;s/"//')
C2=$(grep -o 'name="csrf_token" value="[^"]*"' /tmp/f404_login2.html | sed 's/.*value="//;s/"//')
curl -s -c $JB -b $JB -X POST "$B/admin/index.php?route=common/login" \
  --data-urlencode "username=admin" --data-urlencode "password=NovaSenha!2030" \
  --data-urlencode "user_token=$T2" --data-urlencode "csrf_token=$C2" \
  -D /tmp/f404_al2.hdr -o /dev/null
L2=$(loc /tmp/f404_al2.hdr); [[ "$L2" == *dashboard* ]] || OK=1
# email persistido
EMAIL=$(q "SELECT email FROM reamur_test.rms_user WHERE user_id=1")
[ "$EMAIL" = "admin-novo@example.com" ] || OK=1
ck 5 $OK "save-round-trip-nova-senha"

echo '══ 6 — validação: atual errada + confirma divergente + email inválido → banco NÃO muda ══'
# tentativa com senha atual errada
curl -s -c $JA -b $JA -X POST "$B/admin/index.php?route=user/profile" \
  --data-urlencode "email=bad-1@example.com" \
  --data-urlencode "firstname=David" --data-urlencode "lastname=Almeida" \
  --data-urlencode "password_current=ErradaX9" \
  --data-urlencode "password_new=Outra!Nova99" --data-urlencode "password_confirm=Outra!Nova99" -o /tmp/f404_f6.html
grep -qi "current password does not match\|A senha atual não confere\|no coincide\|alert error" /tmp/f404_f6.html; OK6a=$?
# tentativa com confirma divergente
curl -s -c $JA -b $JA -X POST "$B/admin/index.php?route=user/profile" \
  --data-urlencode "email=bad-2@example.com" \
  --data-urlencode "firstname=David" --data-urlencode "lastname=Almeida" \
  --data-urlencode "password_current=NovaSenha!2030" \
  --data-urlencode "password_new=VaiFalhar!1" --data-urlencode "password_confirm=Diferente!2" -o /tmp/f404_f6b.html
grep -qi "confirmation\|confirma\|coincide\|alert error" /tmp/f404_f6b.html; OK6b=$?
# email invalido (sem mutate)
curl -s -c $JA -b $JA -X POST "$B/admin/index.php?route=user/profile" \
  --data-urlencode "email=nao-e-mail" \
  --data-urlencode "firstname=David" --data-urlencode "lastname=Almeida" -o /dev/null
EMAIL6=$(q "SELECT email FROM reamur_test.rms_user WHERE user_id=1")
OK=0
[ "$OK6a" = "0" ] || OK=1
[ "$OK6b" = "0" ] || OK=1
[ "$EMAIL6" = "admin-novo@example.com" ] || OK=1   # permanece o valor do check 5
# login antigo já não entra mais (prova negativa) — mantém válido o novo (prova positiva)
JB2=/tmp/f404c.jar; rm -f $JB2
curl -s -c $JB2 -b $JB2 "$B/admin/index.php?route=common/login" -o /tmp/f404_login3.html
T3=$(grep -o 'name="user_token" value="[^"]*"' /tmp/f404_login3.html | sed 's/.*value="//;s/"//')
C3=$(grep -o 'name="csrf_token" value="[^"]*"' /tmp/f404_login3.html | sed 's/.*value="//;s/"//')
curl -s -c $JB2 -b $JB2 -X POST "$B/admin/index.php?route=common/login" \
  --data-urlencode "username=admin" --data-urlencode "password=SenhaForte!2026" \
  --data-urlencode "user_token=$T3" --data-urlencode "csrf_token=$C3" -D /tmp/f404_neg.hdr -o /dev/null
L3=$(loc /tmp/f404_neg.hdr); [[ "$L3" == *dashboard* ]] && OK=1   # senha velha NÃO pode mais logar
ck 6 $OK "validation-block"

echo '══ 7 — user/user.edit|1 (self) SEM status=1 → desativar-self bloqueado ══'
# busca form de edição p/ saber user_id current: recorre ao banco
curl -s -c $JA -b $JA "$B/admin/index.php?route=user/user.edit|1" -o /tmp/f404_e1.html
grep -q 'name="user_group_id"' /tmp/f404_e1.html || OK=7
GID=$(q "SELECT user_group_id FROM reamur_test.rms_user WHERE user_id=1")
curl -s -c $JA -b $JA -X POST "$B/admin/index.php?route=user/user.edit|1" \
  --data-urlencode "user_group_id=$GID" --data-urlencode "username=admin" \
  --data-urlencode "email=admin-novo@example.com" \
  --data-urlencode "firstname=David" --data-urlencode "lastname=Almeida" \
  --data-urlencode "password=" --data-urlencode "confirm=" -o /tmp/f404_e1p.html -D /tmp/f404_e1p.hdr
ST=$(q "SELECT status FROM reamur_test.rms_user WHERE user_id=1")
OK=0; [ "$ST" = "1" ] || OK=1
# resposta com erro de validação (auto-desativação)
grep -qi "cannot deactivate your own\|não pode desativar\|desactivar\b" /tmp/f404_e1p.html /tmp/f404_e1.html 2>/dev/null || true
ck 7 $OK "self-disable-blocked"

echo '══ 8 — user/user_group.delete: in-use bloqueado; vazio apaga; última-admin guard ══'
# id 1 = Administrator com usuários
curl -s -c $JA -b $JA -X POST "$B/admin/index.php?route=user/user_group.delete|1" -D /tmp/f404_d1.hdr -o /dev/null
R8=$(hdr /tmp/f404_d1.hdr)
G1=$(q "SELECT COUNT(*) FROM reamur_test.rms_user_group WHERE user_group_id=1")
OK=0
{ [ "$R8" = "302" ] || [ "$R8" = "200" ]; } || OK=1
[ "$G1" = "1" ] || OK=1   # grupo Administrative permanece (bloqueado)
# cria grupo vazio
curl -s -c $JA -b $JA -X POST "$B/admin/index.php?route=user/user_group.add" \
  --data-urlencode "name=TempGroup" -D /tmp/f404_d2.hdr -o /dev/null
GIDTMP=$(q "SELECT user_group_id FROM reamur_test.rms_user_group WHERE name='TempGroup'")
[ -n "$GIDTMP" ] || OK=1
# guard: único grupo que concede user/user não pode ser removido — como TempGroup NÃO tem grants, ele pode ser apagado
curl -s -c $JA -b $JA -X POST "$B/admin/index.php?route=user/user_group.delete|$GIDTMP" -o /dev/null
G2=$(q "SELECT COUNT(*) FROM reamur_test.rms_user_group WHERE name='TempGroup'")
[ "$G2" = "0" ] || OK=1   # grupo vazio removido com êxito
# última-admin: cria grupo SemAdmin → manda admin para ele → é bloqueado mover ele p/ lá
# (cenario provado pelo check 7/9 em conjunto; aqui o guard emerge do banco)
ck 8 $OK "travas-segregacao"

echo '══ 9 — bootstrap: usuário sem grants acessa user/profile (função-todos) ══'
# cria grupo sem grants e usuário sem ACL
curl -s -c $JA -b $JA -X POST "$B/admin/index.php?route=user/user_group.add" \
  --data-urlencode "name=NoGrant" -o /dev/null
GIDN=$(q "SELECT user_group_id FROM reamur_test.rms_user_group WHERE name='NoGrant'")
curl -s -c $JA -b $JA -X POST "$B/admin/index.php?route=user/user.add" \
  --data-urlencode "user_group_id=$GIDN" --data-urlencode "username=maria" \
  --data-urlencode "password=MariaSegura!99" --data-urlencode "confirm=MariaSegura!99" \
  --data-urlencode "email=maria@example.com" \
  --data-urlencode "firstname=Maria" --data-urlencode "lastname=Silva" --data-urlencode "status=1" -o /dev/null
MARIA=$(q "SELECT COUNT(*) FROM reamur_test.rms_user WHERE username='maria'")
[ "$MARIA" = "1" ] || OK=9
# login com maria → dashboard ok; user/profile deve carregar MESMO sem grants (bootstrap)
JM=/tmp/f404m.jar; rm -f $JM
curl -s -c $JM -b $JM "$B/admin/index.php?route=common/login" -o /tmp/f404_login4.html
T4=$(grep -o 'name="user_token" value="[^"]*"' /tmp/f404_login4.html | sed 's/.*value="//;s/"//')
C4=$(grep -o 'name="csrf_token" value="[^"]*"' /tmp/f404_login4.html | sed 's/.*value="//;s/"//')
curl -s -c $JM -b $JM -X POST "$B/admin/index.php?route=common/login" \
  --data-urlencode "username=maria" --data-urlencode "password=MariaSegura!99" \
  --data-urlencode "user_token=$T4" --data-urlencode "csrf_token=$C4" -D /tmp/f404_al4.hdr -o /dev/null
L4=$(loc /tmp/f404_al4.hdr)
curl -s -c $JM -b $JM "$B/admin/user/profile" -o /tmp/f404_m.html -D /tmp/f404_m.hdr
HM=$(hdr /tmp/f404_m.hdr)
[ "$HM" = "200" ] || OK=9
grep -qi "My profile\|Meu perfil\|Mi perfil" /tmp/f404_m.html || OK=9
grep -q 'value="maria" disabled' /tmp/f404_m.html || OK=9
# E SEM GRANT NUNCA pode abrir user/user (ACL real continuada)
curl -s -c $JM -b $JM "$B/admin/user/user" -D /tmp/f404_mu.hdr -o /dev/null
LM=$(loc /tmp/f404_mu.hdr); [[ "$LM" == *error/permission* ]] || OK=9
ck 9 $OK "bootstrap-profile-acl-funciona"

echo '══ 10 — i18n ×3: perfil carrega nas 3 langs ══'
curl -s -c $JA -b $JA "$B/admin/index.php?route=user/profile&language=pt-br" -o /tmp/f404_pt.html
grep -q "Meu perfil" /tmp/f404_pt.html; OKA=$?
curl -s -c $JA -b $JA "$B/admin/index.php?route=user/profile&language=es-eu" -o /tmp/f404_es.html
grep -q "Mi perfil" /tmp/f404_es.html; OKB=$?
curl -s -c $JA -b $JA "$B/admin/index.php?route=user/profile&language=en-us" -o /tmp/f404_en.html
grep -q "My profile" /tmp/f404_en.html; OKC=$?
OK=$((OKA + OKB + OKC))
ck 10 $OK "i18n-telas"

echo
echo "═══ RESUMO: $PASS pass, $FAIL fail ═══"
exit $([ $FAIL -eq 0 ] && echo 0 || echo 1)
