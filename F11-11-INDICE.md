# F11-11 — Dossiê de decisão dos 10 marcadores pendentes

**Pacote de governança — 2026-11-02 · HEAD verificado `749888e76` (retificado em F11-14) · arquivos completos, todos NOVOS.**

📋 Os **10 `MARKER_OR_FINDING`** do F11-09 viraram uma matriz auditável: cada linha tem evidência no `main`, natureza e duas saídas explícitas — **A** (atualizar contrato da fase) ou **B** (registrar achado P1/P2). **Todas `PENDING_DECISION`**; nenhuma foi tomada por inferência.
📄 Dossiê: `docs/F11-11-Decisoes-Contratos.md` · `tools/decision-matrix.json` (10 decisões, 11 artefatos congelados com SHA-256).
🧰 `tools/verify-contract-freeze.py` — guard read-only: **reprova qualquer alteração de contrato enquanto a decisão estiver pendente** (`exit 1`), fail-closed se artefato ausente.

```bash
python3 tools/verify-contract-freeze.py   # COERENTE = exit 0
bash tools/verify-checksums.sh            # 0 divergentes
bash tools/verify-ci-workflow.sh          # 28 PASS / 0 FAIL
unzip -t ReamurCMS-F11-11-Decisoes-Contratos.zip
sha256sum -c ReamurCMS-F11-11-Decisoes-Contratos.zip.sha256
```

> ⛔ Nenhum contrato `F9-*`, checker, arquivo PHP/JS/Twig, `schema.sql` ou `release_approved` foi alterado. Sem PHP/browser/banco/staging. **Nenhuma escrita no GitHub.** Os 7 manifestos publicados permanecem válidos (só adicionamos arquivos novos).

## Arquivos do pacote

```text
.
├── docs/
│   └── F11-11-Decisoes-Contratos.md
├── tools/
│   ├── decision-matrix.json
│   └── verify-contract-freeze.py
├── CHECKSUMS-F11-11.txt
└── F11-11-INDICE.md
```

**Total: 5 arquivos** (incluindo `CHECKSUMS-F11-11.txt`).
