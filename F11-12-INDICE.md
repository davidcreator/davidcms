# F11-12 — `docs-links` determinístico com `--offline`

**Pacote de hotfix — 2026-11-02 · HEAD verificado `9ce71a662` · arquivos completos.**

🔴 O `docs-links` seguiu `failure` (`exit 2`) em **3 runs** mesmo após F11-10, embora a correção desse `exit 0` localmente com o binário idêntico. Descartei `--mode task`, `--format/--output` e réplica incompleta; a causa exata exige logs de admin (403). A única diferença restante é a **rede do runner**.
✅ **Decisão A aplicada:** `--offline` — verifica apenas links internos/relativos, **determinístico**, mantendo **`fail: true`** e `failIfEmpty: true`. Removidos `--accept` e as 3 exclusões de domínios mortos (inócuos neste modo); mantidos o pin `v0.15.0`, `timeout-minutes: 20` e as 5 exclusões originais.

> ⚠️ **Trade-off explícito:** links externos deixam de ser verificados pelo CI. Links locais quebrados continuam derrubando o job.

🧰 `verify-ci-workflow.sh` atualizado (**28 PASS / 0 FAIL**): exige `--offline`, proíbe `fail: false` e `--accept`, mantém 5 exclusões e `docs/README.md` no disco.
🔐 5 manifestos reemitidos com hashes do estado final; `F11-07`/`F11-09`/`F11-11` intactos; novo `CHECKSUMS-F11-12.txt`.
📄 Relatório: `docs/F11-12-Docs-Links-Offline.md` · CHANGELOG atualizado.

```bash
/tmp/lychee-bin/lychee --no-progress --offline './**/*.md'   # exit 0 / 0 erros
bash tools/verify-ci-workflow.sh                              # 28 PASS / 0 FAIL
bash tools/verify-checksums.sh                               # 0 divergentes
unzip -t ReamurCMS-F11-12-Docs-Links-Offline.zip
sha256sum -c ReamurCMS-F11-12-Docs-Links-Offline.zip.sha256
```

> ✅ Validação local com o binário do CI: **exit 0 / 0 erros / 449 OK**. ⚠️ Ainda **não rodou no CI**. Nenhuma escrita no GitHub.

## Arquivos do pacote

```text
.
├── .github/
│   └── workflows/
│       └── php.yml
├── docs/
│   ├── CHANGELOG.md
│   └── F11-12-Docs-Links-Offline.md
├── tools/
│   └── verify-ci-workflow.sh
├── CHECKSUMS-F11-04.txt
├── CHECKSUMS-F11-05.txt
├── CHECKSUMS-F11-08.txt
├── CHECKSUMS-F11-10.txt
├── CHECKSUMS-F11-12.txt
├── CHECKSUMS.txt
└── F11-12-INDICE.md
```

**Total: 11 arquivos** (incluindo `CHECKSUMS-F11-12.txt`).
