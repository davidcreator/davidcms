# ÍNDICE — ReamurCMS-Frente-Hotfix-KZ43-Model-Load.zip

| Caminho no ZIP | O que é |
| --- | --- |
| `dev/admin/controller/common/login.php` | model via `model_security_dracaunos` (era null → fatal) |
| `dev/catalog/controller/account/*.php` (7 arquivos) | model via `model_account_dracaunos` |
| `tests/check_dracaunos_security.js` | + verificação de regressao (`= $this->load->model(`) — 75 checks |
| `tools/decision-matrix.json` | entrada KZ-43 DECIDED + hashes atualizados |
| `docs/CHANGELOG.md` / `CHECKSUMS.txt` | hotfix + hash recalculado |
| arquivos do KZ-42 | export CSV / prune (presentes porque ainda não estavam no main) |
| `RELATORIO-KZ43-HOTFIX.md` | este relatório |
