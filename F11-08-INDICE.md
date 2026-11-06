# F11-08 — Hotfix de performance do `docs-links`

**Pacote de correção de CI — 2026-11-02 · HEAD verificado `a65d06800` · arquivos completos.**

🔎 **Problema:** `@v1` → `@v2` do `lychee-action` trocou o binário `lychee 0.15.0` por `v0.24.2`; o `docs-links` passou de **~6 min (4 runs)** para **14,0 / 15,5 min** em dois runs cancelados por push e **≥8,3 min** no terceiro, ainda sem conclusão. Os defaults de rede são **iguais** nas duas versões e os logs exigem admin (403) — causa interna **não comprovada** (pode haver fator transitório); correção determinística por A/B.

✅ **Correção:** `lycheeVersion: v0.15.0` no `with:`, **mantendo** a action `@v2` (Node 24), `fail`/`failIfEmpty=true`, as 5 exclusões, sem `--accept`, sem desligar o job e sem mexer no `timeout-minutes: 20`.

🧰 `tools/verify-ci-workflow.sh` reforçado: **26 PASS / 0 FAIL** no novo, **25 PASS / 1 FAIL** no antigo (A/B detecta a brecha).
🔐 `CHECKSUMS.txt` (33), `CHECKSUMS-F11-04.txt` (11) e `CHECKSUMS-F11-05.txt` (8) **reemitidos com hashes do estado final**; `CHECKSUMS-F11-07.txt` intacto; novo `CHECKSUMS-F11-08.txt`.
📄 Relatório: `docs/F11-08-Hotfix-Docs-Links.md` · CHANGELOG atualizado.

```bash
bash tools/verify-ci-workflow.sh
bash tools/verify-checksums.sh          # 64 conferidos / 0 divergentes
unzip -t ReamurCMS-F11-08-Hotfix-Docs-Links.zip
sha256sum -c ReamurCMS-F11-08-Hotfix-Docs-Links.zip.sha256
```

> ⚠️ A correção **ainda não rodou no CI** (o run atual usa o YAML sem o pin). Meta de ~6 min é expectativa baseada em 4 runs medidos, não medição deste commit. Nenhuma escrita no GitHub foi feita por esta entrega.

## Arquivos do pacote

```text
.
├── .github/
│   └── workflows/
│       └── php.yml
├── docs/
│   ├── CHANGELOG.md
│   └── F11-08-Hotfix-Docs-Links.md
├── tools/
│   └── verify-ci-workflow.sh
├── CHECKSUMS-F11-04.txt
├── CHECKSUMS-F11-05.txt
├── CHECKSUMS-F11-08.txt
├── CHECKSUMS.txt
└── F11-08-INDICE.md
```

**Total: 9 arquivos** (incluindo `CHECKSUMS-F11-08.txt`).
