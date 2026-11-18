# ÍNDICE — ReamurCMS-Frente-KZ41-Dracaunos-2FA-Obrigatorio.zip

| Caminho no ZIP | O que é |
| --- | --- |
| `dev/admin/model/security/dracaunos.php` | `is2faRequired()` / `save2faRequired()` / `userHas2fa()` |
| `dev/admin/controller/startup/dracaunos.php` | Bloqueio de todas as rotas do admin sem 2FA (quando obrigatório) |
| `dev/admin/controller/common/login.php` | Redirect pós-login para ativar 2FA |
| `dev/admin/controller/security/dracaunos.php` | Flag `force_2fa` + action `2fa_required` |
| `dev/admin/view/template/security/dracaunos.twig` | Banner de bloqueio + checkbox da política |
| `dev/admin/language/{pt-br,en-us,es-eu}/security/dracaunos.php` | Novas chaves de idioma |
| `dev/admin/model/tool/internal-tools.json` | Dracaunos-Security 1.3.0 |
| `tests/check_dracaunos_security.js` | Checker a 64 verificações |
| `tools/decision-matrix.json` | Entrada KZ-41 DECIDED + hashes atualizados |
| `docs/CHANGELOG.md` / `CHECKSUMS.txt` | KZ-41 + hash recalculado |
| `RELATORIO-KZ41.md` | Relatório completo |
