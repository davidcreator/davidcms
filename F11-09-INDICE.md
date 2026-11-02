# F11-09 — Triagem dos 14 marcadores (frente A, etapa 2)

**Pacote de correção cirúrgica — 2026-11-02 · HEAD verificado `440419b12` · arquivos completos.**

🎯 **4 falsos negativos corrigidos** com equivalência literal comprovada, **10 mantidos intactos** (segurança, contrato ou governança). Conjunto de não-zero: **36 → 32** de 61 checkers.

| Corrigido | Equivalente comprovado |
| --- | --- |
| `check_threejs_sandbox.cjs` | `threejs-5` → `threejs-49` (versão igual nos 2 controllers) |
| `e2e_f922_static.cjs` | `contentEditable='true'` (regex tolerante a espaçamento) |
| `e2e_f923_static.cjs` | `renderInserter(forceOpen)` + `while (stack.length > 50` |
| `e2e_f926_static.cjs` | `role=list` restrito ao bloco do inserter (0 ocorrências lá) |

⛔ **Revertido:** `e2e_bdre_hierarchical_reordering_static.cjs` — cadeia de marcadores exigiria reescrever o checker.
📄 Dossiê com os 14 vereditos: `docs/F11-09-Triagem-Marcadores.md`
🧰 `tools/checkers-inventory.json` reemitido com 32 entradas + `f11_09_resolutions`/`f11_09_unresolved`; guard `verify-checkers-inventory.py` intacto → **ESTÁVEL**.
🔐 `CHECKSUMS-F11-07.txt` reemitido · novo `CHECKSUMS-F11-09.txt` · demais manifestos intactos.

```bash
python3 tools/verify-checkers-inventory.py   # ESTAVEL = exit 0
bash tools/verify-checksums.sh                # 0 divergentes
bash tools/verify-ci-workflow.sh              # 26 PASS / 0 FAIL
unzip -t ReamurCMS-F11-09-Triagem-Marcadores.zip
sha256sum -c ReamurCMS-F11-09-Triagem-Marcadores.zip.sha256
```

> ⚠️ Os 10 não corrigidos **continuam falhando** e não estão aprovados; vários são checks de segurança ou achados reais (ex.: `schema.sql` presente). Sem PHP/browser/banco/staging. Nenhuma escrita no GitHub.

## Arquivos do pacote

```text
.
├── docs/
│   └── F11-09-Triagem-Marcadores.md
├── tools/
│   ├── e2e/
│   │   ├── check_threejs_sandbox.cjs
│   │   ├── e2e_f922_static.cjs
│   │   ├── e2e_f923_static.cjs
│   │   └── e2e_f926_static.cjs
│   └── checkers-inventory.json
├── CHECKSUMS-F11-07.txt
├── CHECKSUMS-F11-09.txt
└── F11-09-INDICE.md
```

**Total: 9 arquivos** (incluindo `CHECKSUMS-F11-09.txt`).
