# F11-10 — Correção dos links quebrados no `docs-links`

**Pacote de hotfix — 2026-11-02 · HEAD verificado `709d7fa08` · arquivos completos.**

🔴 **Problema:** com o pin do F11-08 o `docs-links` passou a concluir (6,5 min) e expôs `exit code 2` em 2 runs. A `@v2` tem `fail: true` (a `@v1` não) — os links quebrados já existiam.
🔬 **Diagnóstico exato:** executei o **binário oficial `lychee 0.15.0`** (o mesmo do CI) numa réplica de 2.476 arquivos → **16 erros**. A estimativa anterior de "41 links ausentes" era **falsa** (artefato de réplica incompleta); o `--offline` real dá só 2.

✅ **Correção, sem desligar `fail`:**
- `docs/README.md` **criado** com índice real (resolve 2 links locais).
- 3 exclusões de domínios mortos de vendor: `watchersnet.de` (404), `code.google.com` (500), `awio.iljmp.com` (redirect).
- `--accept '100..=103,200..=299,403,429'` para rate limiting/anti-bot (resolve 8), prática documentada no `--help` do lychee.
- `fail: true`, `failIfEmpty: true`, `timeout-minutes: 20` e `lycheeVersion: v0.15.0` **mantidos**.

🧰 `verify-ci-workflow.sh` reforçado (**28 PASS / 0 FAIL**): proíbe `fail: false`, exige `--accept`, as 8 exclusões e o `docs/README.md` no disco.
🔐 4 manifestos reemitidos com hashes do estado final; `F11-07`/`F11-09` intactos; novo `CHECKSUMS-F11-10.txt`.
📄 Relatório: `docs/F11-10-Links-Quebrados.md` · CHANGELOG atualizado.

```bash
bash tools/verify-ci-workflow.sh     # 28 PASS / 0 FAIL
bash tools/verify-checksums.sh       # 0 divergentes
unzip -t ReamurCMS-F11-10-Links-Quebrados.zip
sha256sum -c ReamurCMS-F11-10-Links-Quebrados.zip.sha256
```

> ✅ **Validação local com o binário do CI:** `exit 0 / 0 erros / 522 OK` (controle sem `--accept`: 8 erros). ⚠️ Ainda **não rodou no CI** — depende da aplicação. Nenhuma escrita no GitHub.

## Arquivos do pacote

```text
.
├── .github/
│   └── workflows/
│       └── php.yml
├── docs/
│   ├── CHANGELOG.md
│   ├── F11-10-Links-Quebrados.md
│   └── README.md
├── tools/
│   └── verify-ci-workflow.sh
├── CHECKSUMS-F11-04.txt
├── CHECKSUMS-F11-05.txt
├── CHECKSUMS-F11-08.txt
├── CHECKSUMS-F11-10.txt
├── CHECKSUMS.txt
└── F11-10-INDICE.md
```

**Total: 11 arquivos** (incluindo `CHECKSUMS-F11-10.txt`).
