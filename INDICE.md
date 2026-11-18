# ÍNDICE — ReamurCMS-Frente-KZ39-Dracaunos-FaseB.zip

| Caminho no ZIP | O que é |
| --- | --- |
| `dev/admin/controller/startup/dracaunos.php` | **Novo** pré-ação: IP em todas as rotas do admin + rota real aplicada + auditoria + expiração de senha |
| `dev/system/config/admin.php` | `startup/dracaunos` no `action_pre_action` (antes de `startup/login`) |
| `dev/admin/controller/common/login.php` | Redirect para troca de senha quando expirada + hotfix CI (1 leitura de IP) |
| `dev/admin/controller/security/dracaunos.php` | Carrega a seção de auditoria |
| `dev/admin/controller/user/profile.php` | Aviso/flag de senha expirada |
| `dev/admin/model/security/dracaunos.php` | `passwordExpired()`, `addAudit()`, `getAuditLogs()` |
| `dev/admin/model/user/user.php` | `password_changed_at = NOW()` ao trocar senha |
| `dev/admin/view/template/security/dracaunos.twig` | Tabela de auditoria |
| `dev/admin/view/template/user/profile.twig` | Aviso de senha expirada |
| `dev/admin/language/{pt-br,en-us,es-eu}/security/dracaunos.php` | Chaves da auditoria |
| `dev/admin/language/{pt-br,en-us,es-eu}/user/profile.php` | Aviso de expiração |
| `dev/admin/model/tool/internal-tools.json` | Dracaunos-Security 1.2.0 |
| `tools/upgrade_kz39_dracaunos_fase_b.sql` | `dracaunos_audit` + `rms_user.password_changed_at` + `dracaunos_audit_days` |
| `docs/CHANGELOG.md` | Entrada KZ-39 |
| `CHECKSUMS.txt` | Hash do CHANGELOG recalculado |
| `RELATORIO-KZ39.md` | Relatório completo |
