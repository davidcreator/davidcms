# F11-16 — Auditoria dos contratos congelados (7 decisões restantes)

**Pacote de auditoria estática — 2026-11-02 · HEAD verificado `04ac4c579` · arquivos completos, todos NOVOS.**

🔍 Auditei as **7 decisões herdadas** e encontrei o padrão central: **5 dos 10 marcadores (D01, D05, D06, D08, D09) são contratos/gates de *incremento* congelados no tempo**, não defeitos — o código evoluiu por fases posteriores documentadas.
🔗 Evidência-chave: o commit **`3d481ecf6`** ("*add personal saved block pattern library*", autodeclarado **F9-25**) adicionou `rms_cms_block_pattern` ao `schema.sql:451` e é **exatamente o `base_head` do F9-27**. Na sequência, **F9-28/30/31/33** documentam `sessionStorage`, histórico local, histórico persistente e comparação de revisões.

⚠️ **Retificações:** **D08** `B → A condicionada` (não é implementação clandestina, mas **não foi encontrado o registro da autorização explícita** que o contrato F9-25 exige — sem confirmação do responsável, permanece B); **D09** `B → A` (gate F9-05 sobreposto por fase posterior, não violação); **D03** natureza refinada (`cms/page.php` usa `hasPermission('modify','cms/page')`, coerente com Pages/Blog independentes — sem falha de segurança comprovada).
📊 **Balanço final: 8 propostas A (1 condicionada) / 2 B** · 10/10 decisões agora com evidência.

📄 `docs/F11-16-Auditoria-Contratos-Congelados.md` · `tools/contract-drift-audit.json` (10 entradas, **50 referências**, 7 auditadas nesta frente).
🧰 **Nenhuma ferramenta duplicada**: a validação usa o `tools/verify-evidence-refs.py` **já publicado no F11-15** sobre uma cópia consolidada do JSON. Nenhum arquivo publicado foi alterado.

```bash
python3 tools/verify-contract-freeze.py        # 11 contratos congelados = exit 0
bash tools/repo-health.sh                      # 6 PASS / 0 FAIL / 0 SKIP
bash tools/verify-checksums.sh                 # 0 divergentes em 14 manifestos
unzip -t ReamurCMS-F11-16-Contratos-Congelados.zip
sha256sum -c ReamurCMS-F11-16-Contratos-Congelados.zip.sha256
```

> ⛔ **Nenhuma decisão foi tomada**: `decision-matrix.json` segue com 10 `PENDING_DECISION`. ⚠️ Inspeção **estática** — sem PHP/Twig/browser/banco/staging. **Nenhuma escrita no GitHub.** CI 7/7 + health 6/6 **≠ release aprovada** (F9-27 `BLOCKED_NOT_RUN`, BDREditor pausado em F9-103).

## Arquivos do pacote

```text
.
├── docs/
│   └── F11-16-Auditoria-Contratos-Congelados.md
├── tools/
│   └── contract-drift-audit.json
├── CHECKSUMS-F11-16.txt
└── F11-16-INDICE.md
```

**Total: 4 arquivos** (incluindo `CHECKSUMS-F11-16.txt`).
