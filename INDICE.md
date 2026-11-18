# ÍNDICE — ReamurCMS-Frente-KZ58-Dracaunos-Defesa.zip

| Caminho no ZIP | O que é |
| --- | --- |
| `dev/system/library/security/defense.php` | **nova** library de defesa compartilhada |
| `dev/admin/model/security/dracaunos.php` | delega para a Defense |
| `dev/admin/controller/security/dracaunos.php` | aba Defesa + ações defense_save/incident_handle/scan_run |
| `dev/admin/view/template/security/dracaunos.twig` | aba Defesa (stats, incidentes, toggles, scan) |
| `dev/admin/controller/startup/dracaunos.php` |chamadas DDoS/sonda/scanner no admin |
| `dev/catalog/controller/startup/dracaunos.php` + `dev/system/config/catalog.php` | defesa na face pública |
| `dev/admin/controller/common/login.php` | brute force na falha de login |
| `dev/admin/language/{pt-br,en-us,es-eu}/security/dracaunos.php` | chaves da aba Defesa |
| `dev/admin/model/tool/internal-tools.json` | 1.11.0 |
| `tests/check_dracaunos_security.js` | 168 verificações |
| `tools/decision-matrix.json` | KZ-58 DECIDED + hashes |
| `docs/CHANGELOG.md` / `CHECKSUMS.txt` | KZ-58 + hash recalculado |
| `RELATORIO-KZ58.md` | relatório |
