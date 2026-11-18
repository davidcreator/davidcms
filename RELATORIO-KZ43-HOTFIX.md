# RELATÓRIO — Hotfix KZ-43: `load->model()` retorna void (fatal no login do admin)

## Erro reportado (WAMP local)
`Call to a member function ipAllowed() on null` em `dev/admin/controller/common/login.php:62`, request `/davidcms/dev/admin/login`.

## Causa raiz
`Loader::model()` é `void`: ele registra um **Proxy** no registry sob a chave `model_<rota>` (ex.: `model_security_dracaunos`). O código fazia `$drac = $this->load->model('...')` → `$drac = null` → fatal na primeira chamada de método. O mesmo padrão errado estava nos 7 controllers `account/*` do catálogo (KZ-38), que quebrariam na conta do cliente.

## Correção (8 arquivos)
Padrão corrigido para `$this->load->model('x/y');` + `$this->model_x_y`:
- `dev/admin/controller/common/login.php` (`model_security_dracaunos`)
- `dev/catalog/controller/account/{login,register,verify,forgotten,reset,account}.php` (`model_account_dracaunos`; `logout.php` não usa model)

## Regressão no checker
`tests/check_dracaunos_security.js` agora varre `dev/` inteiro e falha se achar `= $this->load->model(` em qualquer `.php` → **75 verificações PASS**.

## Governança
- `tools/decision-matrix.json`: entrada **KZ-43** (DECIDED, 2026-11-18) + hashes dos 7 artefatos congelados corrigidos atualizados.
- `docs/CHANGELOG.md` + `CHECKSUMS.txt` recalculado (`verify-checksums.sh` 3 PASS / 0 FAIL).
- Este ZIP contém também os arquivos do KZ-42 (entregue antes e ainda não commitado no main) — aplicar este ZIP cobre os dois.

## Validações
- `node tests/check_dracaunos_security.js` → PASS (75); `node --check` OK.
- Suite `tests/check_*.js`: só os 2 pré-existentes de UI falham (iguais no main limpo).
