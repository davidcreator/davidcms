# RELATÓRIO — KZ-39: Dracaunos-Security Fase B (2026-11-18)

## Escopo (Fase B completa, conforme combinado)
1. **Regras de IP em TODAS as rotas do admin** — novo pré-ação `startup/dracaunos` roda antes de `startup/login` e aplica `dracaunos_ip_rule` (escopo `admin`) em qualquer rota do painel: 403 + log em `dracaunos_login_log`.
2. **Auditoria do admin** — tabela nova `rms_dracaunos_audit`; toda requisição autenticada do painel é registrada (user_id, username, rota, método, IP, user agent) pelo mesmo `startup/dracaunos`, com poda por `dracaunos_audit_days` (30). Seção nova no painel `security/dracaunos`.
3. **Expiração de senha** — `rms_user.password_changed_at` (SQL com backfill = `date_added`); `dracaunos_password_policy.max_age_days` passa a valer: login com senha vencida → redirect `user/profile`; `startup/dracaunos` bloqueia as demais rotas até a troca (fail-closed; `user/profile` e `common/logout` liberadas). Troca de senha em `user/profile` e `user/user` grava `password_changed_at = NOW()`; perfil mostra aviso quando o flag `dracaunos_password_expired` está setado.
4. **Rota real do admin aplicada** — com `dracaunos_admin_route` custom, o `startup/dracaunos` recusa com 403 o acesso pela pasta física `/admin/`; snippet de rewrite documentado no SQL (`RewriteRule ^rota/(.*)$ admin/$1 [L,QSA]` + ajustar HTTP_SERVER).

## Arquivos alterados/criados
- `dev/admin/controller/startup/dracaunos.php` — **novo** pré-ação (IP todas as rotas + rota aplicada + auditoria + expiração)
- `dev/system/config/admin.php` — `startup/dracaunos` adicionado antes de `startup/login` (extension continua último — respeita `ShippedAndWiringTest`)
- `dev/admin/controller/common/login.php` — redirect pós-login para `user/profile` quando a senha venceu (**inclui também o hotfix do CI**: 1 única chamada a `$this->request->ip()`, que corrige o `ProxyWiringTest` do run 44299525501)
- `dev/admin/controller/security/dracaunos.php` — seção de auditoria
- `dev/admin/controller/user/profile.php` — flag de expiração + limpa flag ao trocar senha
- `dev/admin/model/security/dracaunos.php` — `passwordExpired()`, `addAudit()`, `getAuditLogs()`
- `dev/admin/model/user/user.php` — `password_changed_at = NOW()` em `addUser`/`editUser`/`editProfile` quando a senha muda
- `dev/admin/view/template/security/dracaunos.twig` — tabela de auditoria
- `dev/admin/view/template/user/profile.twig` — aviso de senha expirada
- `dev/admin/language/{pt-br,en-us,es-eu}/security/dracaunos.php` — chaves da auditoria
- `dev/admin/language/{pt-br,en-us,es-eu}/user/profile.php` — aviso de expiração
- `dev/admin/model/tool/internal-tools.json` — Dracaunos-Security **1.2.0**
- `tools/upgrade_kz39_dracaunos_fase_b.sql` — tabela `dracaunos_audit`, coluna `rms_user.password_changed_at`, setting `dracaunos_audit_days`
- `docs/CHANGELOG.md` + `CHECKSUMS.txt` (hash do CHANGELOG recalculado — é o único arquivo coberto pelo manifesto)

## Validações locais (@ `81a445d` + hotfix)
- Todos os `tests/check_*.js` passam, **exceto** `check_elementor_workbench.js` e `check_reopen_and_immersive.js`, que falham **identicamente antes** das minhas mudanças (pré-existente, sem relação com KZ-39).
- Ordem do `action_pre_action` do admin respeita as restrições do `ShippedAndWiringTest` (extension último; login/permission antes dele).
- PHP sem binary local — revisão manual de sintaxe dos arquivos alterados.

## Observação
Este ZIP **já inclui o hotfix do CI** no `login.php` (1 leitura de IP). Se você já aplicou o `ReamurCMS-Frente-Hotfix-CI-Composer-KZ38.zip`, basta aplicar este por cima.
