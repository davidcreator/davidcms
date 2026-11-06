# F11-14 — Aplicação consolidada dos pacotes pendentes

**Pacote de aplicação única — 2026-11-02 · HEAD verificado `749888e76` · CI 7/7 · arquivos completos.**

📦 Junta, **num único ZIP**, os 2 pacotes entregues e nunca aplicados: **F11-06** (radar de prontidão) + **F11-11** (matriz de decisão + congelamento de contratos). Árvore atual confirma os 9 arquivos como AUSENTES.
🔧 **Retificação necessária encontrada:** o F11-06 dizia que `docs-links` era "normal ~6 min, não regressão" e o F11-11 dizia que o CI estava vermelho. Ambos corrigidos contra `749888e76` (**7/7 success**, `docs-links` 0,13 min com `--offline`). Registro histórico preservado como nota de retificação.
🔐 11 contratos congelados **recalculados do `main`: 11/11 idênticos**, guard segue válido. `head` da matriz atualizado para `749888e76`.
🧰 **`repo-health.sh` não foi alterado** — ele já promove os 2 guardas de `SKIP` para `PASS`: **4/0/2 → 6 PASS / 0 FAIL / 0 SKIP**.
🔏 `CHECKSUMS-F11-06.txt` e `CHECKSUMS-F11-11.txt` reemitidos (versões retificadas); novo `CHECKSUMS-F11-14.txt` (11 entradas). Os 9 manifestos publicados permanecem válidos.

```bash
bash tools/repo-health.sh                    # 6 PASS / 0 FAIL / 0 SKIP
python3 tools/verify-contract-freeze.py      # COERENTE = exit 0
bash tools/release-readiness-report.sh       # informativo, nao e gate
bash tools/verify-checksums.sh               # 0 divergentes em 12 manifestos
unzip -t ReamurCMS-F11-14-Aplicacao-Pendentes.zip
sha256sum -c ReamurCMS-F11-14-Aplicacao-Pendentes.zip.sha256
```

> ⚠️ **Aplique ESTE pacote em vez dos ZIPs F11-06/F11-11 originais** (documentação desatualizada e manifestos divergentes). ⚠️ **CI 7/7 + health 6/6 ≠ release aprovada**: F9-27 `BLOCKED_NOT_RUN`, F9-18 com 2 P1 + 2 P2, BDREditor pausado. As **10 decisões A/B** seguem `PENDING_DECISION`. **Nenhuma escrita no GitHub.**

## Arquivos do pacote

```text
.
├── docs/
│   ├── F11-06-Fechamento-e-Radar.md
│   ├── F11-11-Decisoes-Contratos.md
│   └── F11-14-Aplicacao-Pendentes.md
├── tools/
│   ├── decision-matrix.json
│   ├── release-readiness-report.sh
│   └── verify-contract-freeze.py
├── CHECKSUMS-F11-06.txt
├── CHECKSUMS-F11-11.txt
├── CHECKSUMS-F11-14.txt
├── F11-06-INDICE.md
├── F11-11-INDICE.md
└── F11-14-INDICE.md
```

**Total: 12 arquivos** (incluindo `CHECKSUMS-F11-14.txt`).
