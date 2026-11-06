# F11-15 — Evidência técnica dos 10 marcadores pendentes

**Pacote de investigação estática — 2026-11-02 · HEAD verificado `1908213b5` · arquivos completos, todos NOVOS.**

🔍 Investiguei os **3 `INVESTIGAR`** do F11-11 com o código real do `main` e **refutei uma suspeita de P1**:
- **D02** (spacer): não é regressão — a F9-71 trocou `Object.entries(...)` por **slider + botões** (`SPACER_HEIGHTS`), mantendo os 5 presets e a validação. → **A**
- **D04** (Three.js admin): não é regressão — `not_found.php` usa `$threeSettings` com **override intencional** antes de `runtime()`. → **A**
- **D07** (Twig `|raw`): **não é regressão**. `twig.php` usa `autoescape:'html'`, **proíbe `|raw`** em templates de tema (F10-03) e marca `post.block_html`/`description` como **`Twig\Markup`**. 🔒 **Não reintroduzir `|raw`** — isso reabriria a superfície de segurança. → **A** (retifica o `B / possível P1` do F11-11)

📄 Dossiê: `docs/F11-15-Evidencia-Tecnica.md` · `tools/decision-evidence.json` (10 entradas, **20 referências** verificáveis, 3 `INVESTIGATED_F11_15` / 7 `INHERITED_F11_11`).
🧰 `tools/verify-evidence-refs.py` — guard read-only que confere arquivo + trecho de cada referência (`exit 0` coerente / `exit 1` quebrada). Não duplica os guardas existentes.
📊 Balanço: **6 propostas A / 4 propostas B**. As 7 herdadas estão marcadas explicitamente como **não reauditadas**.

```bash
python3 tools/verify-evidence-refs.py           # 20/20 referencias = exit 0
python3 tools/verify-contract-freeze.py        # 11 contratos congelados = exit 0
bash tools/repo-health.sh                      # 6 PASS / 0 FAIL / 0 SKIP
bash tools/verify-checksums.sh                 # 0 divergentes em 13 manifestos
unzip -t ReamurCMS-F11-15-Evidencia-Tecnica.zip
sha256sum -c ReamurCMS-F11-15-Evidencia-Tecnica.zip.sha256
```

> ⛔ **Nenhuma decisão foi tomada.** `tools/decision-matrix.json` segue com as 10 em `PENDING_DECISION`; este pacote **complementa**, não substitui. Nenhum contrato, checker, matriz, `repo-health.sh` ou manifesto publicado foi alterado. ⚠️ Inspeção **estática**: sem PHP/Twig/browser/banco/staging. **Nenhuma escrita no GitHub.**

## Arquivos do pacote

```text
.
├── docs/
│   └── F11-15-Evidencia-Tecnica.md
├── tools/
│   ├── decision-evidence.json
│   └── verify-evidence-refs.py
├── CHECKSUMS-F11-15.txt
└── F11-15-INDICE.md
```

**Total: 5 arquivos** (incluindo `CHECKSUMS-F11-15.txt`).
