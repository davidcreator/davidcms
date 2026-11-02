# F11-04 — Modernização e endurecimento do CI (ADR 0082)

**Pacote de aplicação — 2026-11-02 · base `main` @ `510640c68` · arquivos completos, não patches.**

| | |
| --- | --- |
| Antes | 5 jobs, `ubuntu-latest`, actions `@v4`/`@v1`, 8 avisos de Node 20/Ubuntu 26 por run, guards manuais |
| Depois | 6 jobs (`repo-guards`), `ubuntu-24.04` pinado, actions Node 24 (`checkout@v7`, `setup-node@v7`, `cache@v6`, `setup-php@v2`, `lychee@v2`), matrix Node 20+22, `concurrency`, `timeout-minutes` |

📄 Decisão: `agents-ia/decisoes/0082-modernizacao-e-guards-do-ci.md` · 📄 Relatório e limitações: `docs/F11-04-Modernizacao-CI.md`
🔐 `CHECKSUMS-F11-04.txt` (CRC-32 + SHA-256 por arquivo) · `ReamurCMS-F11-04-CI-Modernizado.zip.sha256` fora do ZIP.

```bash
bash tools/verify-ci-workflow.sh     # contrato do CI (read-only)
bash tools/verify-checksums.sh       # integridade dos CHECKSUMS*.txt
bash tools/verify-deps-layout.sh     # indice do Git
unzip -t ReamurCMS-F11-04-CI-Modernizado.zip
sha256sum -c ReamurCMS-F11-04-CI-Modernizado.zip.sha256
```

⚠️ **Acompanhar o primeiro run no GitHub**: `lychee-action@v2` (ação externa, não executável localmente) e a perna Node 22 da matrix (sandbox dispõe apenas de Node 20). PHP/Composer/PHPStan/browser não foram executados localmente.

## Arquivos do pacote

```text
.
├── .github/
│   ├── workflows/
│   │   └── php.yml
│   └── pull_request_template.md
├── agents-ia/
│   └── decisoes/
│       ├── 0082-modernizacao-e-guards-do-ci.md
│       └── README.md
├── docs/
│   ├── CHANGELOG.md
│   ├── F11-04-Modernizacao-CI.md
│   └── Guia-Composer.md
├── tools/
│   └── verify-ci-workflow.sh
├── CHECKSUMS-F11-04.txt
├── CONTRIBUTING.md
├── F11-04-INDICE.md
└── README.md
```

**Total: 12 arquivos** (incluindo `CHECKSUMS-F11-04.txt`).

> Nenhuma escrita no GitHub foi feita por esta entrega. Após 19/11/2026, testar `ubuntu-26.04` com run verde antes de promover o pin.
