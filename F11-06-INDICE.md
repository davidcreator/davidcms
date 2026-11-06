# F11-06 — Fechamento da frente de infraestrutura + radar de prontidão

**Pacote de consolidação — 2026-11-02 · HEAD original `fb7b56055` · RETIFICADO em F11-14 contra `749888e76` · arquivos completos.**

✅ **CI fechado (estado atual, F11-14):** **7/7 jobs verdes**, 0 annotations de falha, matrix Node 20+22 verde. ⚠️ **Retificação:** a afirmação original de que `docs-links` estava "em andamento dentro do tempo normal (~6 min)" foi **superada** — ele falhou com `exit 2` em 3 runs e só fechou em **0,13 min** com `--offline` no F11-12. Ver nota no dossiê.

📄 Relatório: `docs/F11-06-Fechamento-e-Radar.md` — linha do tempo F11-01→05, invariantes e **radar das 5 frentes seguintes com seus bloqueios**.
🧰 `tools/release-readiness-report.sh` — painel read-only que agrega F9-27/F9-18/handoff. **Não é gate, não aprova release.**
🔐 `CHECKSUMS-F11-06.txt` · `ReamurCMS-F11-06-Fechamento-e-Radar.zip.sha256` fora do ZIP.

| Frente | Pode executar agora? |
| --- | --- |
| A. Higienizar os 36 checkers `tools/e2e` | ✅ única frente executável sem nova autorização |
| B. F9-27 release gate | ❌ `BLOCKED_NOT_RUN`; exige staging, F9-12 e autorização por escrito |
| C. BDREditor / F9-103 | ❌ pausado pelo handoff, só com reabertura explícita |
| D. F9-18 (2 P1 + 2 P2) | ⚠️ depende de decisão do responsável |
| E. `ubuntu-26.04` | 📅 após 19/11/2026, com run verde |

```bash
bash tools/release-readiness-report.sh
bash tools/verify-checksums.sh
unzip -t ReamurCMS-F11-06-Fechamento-e-Radar.zip
sha256sum -c ReamurCMS-F11-06-Fechamento-e-Radar.zip.sha256
```

> Nenhum arquivo existente foi alterado neste pacote. Nenhuma escrita no GitHub foi feita por esta entrega. O CI verde **não** significa release aprovada.

## Arquivos do pacote

```text
.
├── docs/
│   └── F11-06-Fechamento-e-Radar.md
├── tools/
│   └── release-readiness-report.sh
├── CHECKSUMS-F11-06.txt
└── F11-06-INDICE.md
```

**Total: 4 arquivos** (incluindo `CHECKSUMS-F11-06.txt`).
