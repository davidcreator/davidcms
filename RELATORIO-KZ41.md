# RELATÓRIO — KZ-41: 2FA obrigatório no admin (Dracaunos-Security)

## O que muda
O setting `dracaunos_2fa_required` (criado no KZ-34, até então sem efeito) passa a ser **enforceado**:
- `startup/dracaunos` (pré-ação que já cobre todas as rotas do admin): com `dracaunos_2fa_required=1`, usuário logado **sem 2FA ativo** é redirecionado para `security/dracaunos` em qualquer rota — exceto `security/dracaunos` (para ativar) e `common/logout`. Fail-closed, mesmo padrão da expiração de senha (KZ-39).
- Login: conta sem 2FA + política ativa → redireciona direto para o painel Dracaunos após a senha (flag `dracaunos_2fa_required_pending`).
- Ativar o 2FA limpa o flag e desbloqueia o painel.

## Arquivos
- `dev/admin/model/security/dracaunos.php` — `is2faRequired()`, `save2faRequired()`, `userHas2fa()`
- `dev/admin/controller/startup/dracaunos.php` — bloqueio 2FA no pré-ação
- `dev/admin/controller/common/login.php` — redirect pós-login quando 2FA obrigatório e inativo
- `dev/admin/controller/security/dracaunos.php` — flag `force_2fa`, action `2fa_required`, limpa flag no `twofa_enable`
- `dev/admin/view/template/security/dracaunos.twig` — banner de bloqueio + checkbox da política
- Idiomas pt-br/en-us/es-eu (`security/dracaunos.php`)
- `dev/admin/model/tool/internal-tools.json` — Dracaunos-Security **1.3.0**
- `tests/check_dracaunos_security.js` — 64 verificações (novas: model/startup/login/controller/twig/idiomas do 2FA obrigatório)
- `tools/decision-matrix.json` — entrada **KZ-41** (DECIDED) + hashes atualizados dos 6 artefatos congelados alterados
- `docs/CHANGELOG.md` + `CHECKSUMS.txt` (recalculado)

## Validações locais (@ `69a9d65d` + esta frente)
- `node tests/check_dracaunos_security.js` → PASS (64 verificações); `node --check` OK.
- Suite `tests/check_*.js`: só falham os 2 pré-existentes (`elementor_workbench`, `reopen_and_immersive`), iguais no main limpo.
- `bash tools/verify-checksums.sh` → 3 PASS / 0 FAIL.
- CI no main @ `69a9d65d`: run 44315041093 7/7 success.

## Como ativar em produção
No painel Dracaunos → seção 2FA → marcar "Exigir 2FA para todos os usuários do admin" (grava `dracaunos_2fa_required=1` em `rms_setting`). Contas sem 2FA passam a cair na tela do Dracaunos até ativarem.
