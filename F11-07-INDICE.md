# F11-07 — Inventário auditado dos checkers `tools/e2e` (frente A)

**Pacote de classificação — 2026-11-02 · HEAD `fb7b56055` · arquivos completos, todos NOVOS.**

Classifiquei os **36 checkers com saída não-zero** executando os 61 `tools/e2e/*.cjs` numa réplica de 777 arquivos. **Nenhum checker foi alterado** — a maioria das falhas é proteção intencional ou check de segurança.

| Categoria | Qtd | Ação |
| --- | ---: | --- |
| `REQUIRES_RUNTIME` | 4 | documentar; a recusa sem browser/env é a proteção |
| `REQUIRES_WRAPPER` | 7 | invocar pelo `.sh` correspondente |
| `REQUIRES_ARGS` | 5 | invocar com os argumentos de CLI |
| `BLOCKED_CONTROL` | 2 | emite `status: blocked` por design |
| `STATIC_ONLY_FAIL_CLOSED` | 4 | `process.exitCode = 1` fail-closed; **não** trocar por 0 |
| `MARKER_OR_FINDING` | 14 | **não relaxar** sem evidência — vários são checks de segurança |

📄 Relatório com as 36 linhas, evidências e justificativas: `docs/F11-07-Inventario-Checkers.md`
🧰 `tools/checkers-inventory.json` (36 entradas auditadas) + `tools/verify-checkers-inventory.py` — **guard de estabilidade**: reprova apenas uma *nova* falha não inventariada, sem exigir que os 36 passem e sem marcar `NOT_RUN` como `PASS`.
🔐 `CHECKSUMS-F11-07.txt` · `ReamurCMS-F11-07-Inventario-Checkers.zip.sha256` fora do ZIP.

```bash
python3 tools/verify-checkers-inventory.py   # ESTAVEL = exit 0; REGRESSAO = exit 1
bash tools/verify-checksums.sh
unzip -t ReamurCMS-F11-07-Inventario-Checkers.zip
sha256sum -c ReamurCMS-F11-07-Inventario-Checkers.zip.sha256
```

> **Validações:** 61 checkers → 25 OK / 36 não-zero idênticos ao `main`; guard `ESTÁVEL` e detecta regressão injetada; JSON e `py_compile` válidos; 52 checksums conferidos. Nenhuma escrita no GitHub. F9-27 segue `BLOCKED_NOT_RUN`; BDREditor pausado em F9-103.

## Arquivos do pacote

```text
.
├── docs/
│   └── F11-07-Inventario-Checkers.md
├── tools/
│   ├── checkers-inventory.json
│   └── verify-checkers-inventory.py
├── CHECKSUMS-F11-07.txt
└── F11-07-INDICE.md
```

**Total: 5 arquivos** (incluindo `CHECKSUMS-F11-07.txt`).
