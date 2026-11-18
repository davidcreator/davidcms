# RELATÓRIO — KZ-44: recuperação de senha do próprio admin (Dracaunos-Security)

## Fluxo
1. Tela de login do admin ganha o link **"Esqueci minha senha"** → rota pública `common/forgotten` (liberada na muralha `startup/login` junto com login/logout).
2. Usuário informa o **username** → grava token `type='admin_password'` em `dracaunos_recovery` (rate limit 6/15min por IP, CSRF, resposta genérica que não revela se o usuário existe).
3. No painel **Dracaunos-Security → Recuperação**: aprovar um pedido `admin_password` mostra o **link de reset uma única vez** (flash) — `common/reset&token=...`.
4. `common/reset` (pública, token-gated): valida token pendente `admin_password`, aplica a **política de senhas** (`passwordMeetsPolicy`), grava a nova senha com `password_changed_at = NOW()` (renova expiração), consome o token (`used`) e registra no log de logins. Rate limit por IP.

## Arquivos
- **Novos**: `dev/admin/controller/common/forgotten.php`, `dev/admin/controller/common/reset.php`, `dev/admin/view/template/common/forgotten.twig`, `dev/admin/view/template/common/reset.twig`, `dev/admin/language/{pt-br,en-us,es-eu}/common/forgotten.php`
- **Modificados**: `startup/login.php` (rotas públicas), `common/login.php` (link forgotten) + `common/login.twig`, `model/security/dracaunos.php` (`getRecoveryById`, `getRecoveryByToken`, `setUserPassword`), `controller/security/dracaunos.php` (link de reset no approve), `internal-tools.json` (**1.5.0**), checker (89 verificações), decision-matrix (KZ-44 DECIDED + hashes), CHANGELOG + CHECKSUMS
- **Sem SQL novo** — reusa `dracaunos_recovery` (type `admin_password` não colide com `password`/`verification` do catálogo).

## Validações locais (@ `340eb12f` + esta frente)
- `node tests/check_dracaunos_security.js` → PASS (89 verificações); `node --check` OK.
- `bash tools/verify-checksums.sh` → 3 PASS / 0 FAIL.
- Suite `tests/check_*.js`: só os 2 pré-existentes de UI falham.
- CI no main @ `340eb12f`: run 44334050281 success.

## Segurança
- Rotas de recuperação passam pelo `startup/dracaunos` (regras de IP escopo admin valem nelas) e têm rate limit próprio.
- Token uso único (`used`), expiração por retenção já existente; resposta do forgotten é idêntica com usuário existente ou não.
