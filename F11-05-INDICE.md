# F11-05 — Hotfix do `repo-guards` e restauração de integridade

**Pacote de correção — 2026-11-02 · HEAD `706205389` · arquivos completos.**

❌ **Problema:** `repo-guards` falhou com `exit code 127` porque `tools/verify-checksums.sh` não estava no GitHub (F11-03 não aplicado). Além disso, o commit do F11-04 removeu `F11-01-INDICE.md` e `F11-02-INDICE.md`, deixando manifestos sem alvo, e o auditor do CI aprovava o workflow mesmo com um guard ausente.

✅ **Correção:** auditor adicionado e reforçado, `CHECKSUMS.txt` consolidado com 33 entradas, 2 índices restaurados, contrato do CI passa a exigir os arquivos no disco e `CHECKSUMS-F11-04.txt` recalculado. **Nenhum arquivo do workflow foi alterado.**

📄 Relatório e evidências: `docs/F11-05-Hotfix-Repo-Guards.md`
🔐 `CHECKSUMS-F11-05.txt` (CRC-32 + SHA-256 por arquivo) · `ReamurCMS-F11-05-Hotfix-Guards.zip.sha256` fora do ZIP.

```bash
bash tools/verify-checksums.sh      # 52 conferidos / 0 divergentes
bash tools/verify-ci-workflow.sh    # 22 PASS / 0 FAIL
bash tools/verify-deps-layout.sh    # 0 FAIL
unzip -t ReamurCMS-F11-05-Hotfix-Guards.zip
sha256sum -c ReamurCMS-F11-05-Hotfix-Guards.zip.sha256
```

⚠️ `docs-links` (lychee@v2) ainda estava em andamento na última consulta do CI; não foi executado localmente. Nenhuma escrita no GitHub foi feita por esta entrega.

## Arquivos do pacote

```text
.
├── docs/
│   └── F11-05-Hotfix-Repo-Guards.md
├── tools/
│   ├── verify-checksums.sh
│   └── verify-ci-workflow.sh
├── CHECKSUMS-F11-04.txt
├── CHECKSUMS-F11-05.txt
├── CHECKSUMS.txt
├── F11-01-INDICE.md
├── F11-02-INDICE.md
└── F11-05-INDICE.md
```

**Total: 9 arquivos** (incluindo `CHECKSUMS-F11-05.txt`).
