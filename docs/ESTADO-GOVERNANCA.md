# Estado de Governança — cartão de consulta rápida

**Atualizado em:** 2026-11-01 · **Fonte:** registros locais da jornada + auditoria remota de 2026-11-05.
**Não é veredito, não é release, não é health autenticado.**

> Use este arquivo antes de responder "está aprovado?", "posso liberar?" ou "posso apagar?".
> O relatório completo está em `RELATORIO-GERAL.md`.

---

## 1. Veredito de release

| Indicador | Valor |
|---|---|
| `release_approved` | **`false`** |
| F9-27 Status | **`BLOCKED_NOT_RUN`** |
| F9-18 | `release_ready:false` |
| F9-25 | `PROPOSAL_PENDING` |
| F9-26 | `wcag_conformance_claimed:false` |
| `tools/decision-matrix.json` | **10 `PENDING_DECISION` / 0 `DECIDED`** |
| D01–D10 | **Intactas — nenhuma decidida** |

**Nunca converter em `PASS`:** `PENDING_DECISION`, `STATIC_ONLY`, `NOT_RUN`, `SKIP`, `BLOCKED_NOT_RUN`.
**CI 7/7, `repo-health`, PHP WASM 8.4.1, jsdom, Chromium headless e guardas não substituem veredito nem release.**

---

## 2. Aceites visuais

| Status | Frentes | Frase que o definiu |
|---|---|---|
| ✅ **APROVADO** | **AP**, **BI**, **BM** | AP: "aprovado" · BI: "Agora sim ficou perfeito e como esperado" · BM: "ficou muito bom" |
| ❌ **REPROVADO** | **AO**, **BK** | AO: "A edição passou dos limites… acabou editando a barra de navegação lateral" · BK: "Ainda está ocorrendo vazamento indesejado" |
| ⚠️ **CONTESTADO** | **BN** | "No tema não veio a nave e as interações do threejs, veio só a parte visual básica" |
| ⬜ **SEM ACEITE** | AY, BB, BC, BE, BF, BG, BH, BJ, BL, **BO / r2 / r3 / r4** | — |

**Aprovação visual ≠ release.** Nenhuma das três aprovadas autoriza deploy.

---

## 3. Repositório

| Item | Valor |
|---|---|
| HEAD mais recente auditado | **`ad7643de6ca2`** (16 arquivos, +925/−22) |
| Histórico | `ab8b765…` → `3219ccae…` → `ad7643de6ca2` |
| Árvore | 3.330 entradas / 0 `astra` / 4 temas |
| `dev/` no HEAD | **0** — não afirmar que patches BL/BO estão vivos no runtime |
| Limpeza 2026-11-05 | `5593de…` → **`892fbd…`**: −96 arquivos / −3.848.525 bytes (−8,7%), Nível 1 |
| Contratos | 14 `CHECKSUMS*.txt` → 90 paths · 11 `frozen_artifacts_sha256` · **0 ausentes / 0 divergentes** no pós-limpeza |
| Ferramentas internas | `BDREditor-Content` e `KAZEditor-Theme`, ambos `version:"1.0.0"`, `updated_at:"2026-11-06"`, `notes:[]`, Admin-only P2 |

`updated_at` e `notes:[]` são **interpretação do agente** — não alterar sem instrução. Não inferir B1/B2/B3.

---

## 4. O que NÃO foi feito (e não deve ser declarado como feito)

- ❌ Escrita do agente no GitHub — **todas as aplicações são suas**.
- ❌ CI remoto autenticado (403 `Must have admin rights`), health real (`copy(__kazHealth())` nunca colado), sessão Admin real, MySQL, staging, migration, deploy.
- ❌ Capturas desktop + 390 px do Admin real.
- ❌ Correção confirmada do vazamento BK/BL no servidor; `3219ccae…` **não** é fix de runtime.
- ❌ Prova de que o editor carrega dados reais dos temas no servidor.
- ❌ Prova de que o 3D renderiza no seu servidor — BO-r4 tem **142/0 local**, sem aceite visual.
- ❌ Aceite visual de BJ/BK/BL/BN/BO/r2/r3/r4.
- ❌ Decisão das D01–D10, B2/B3, AR/AS, F9-103 (pausado), P2/F9-18 (não reaberto).

---

## 5. Restrições permanentes de escopo

**Recorte A (geral):** import por arquivo seguro; Twig/ThreeJS são **dados validados, sem código executável no tema**. Sem `.js` solto no tema, sem `<script>`/JS inline/`|raw` no `.reamurtheme.json`, sem CDN/Google Fonts/WebP remotos obrigatórios, sem upload, sem ACL nova.

**Recorte B (BO, `ask_user caminho=b_catalogo`):** autoriza código executável **apenas no runtime self-hosted allowlistado** (`scenes/david-universe.js`, `main.js`, `asset_manifest.php`, allowlists/presets). Sem React/bundle de 892 KB, **sem migration**, você aplica. Não autoriza release/deploy/escrita no GitHub/runtime autenticado/decisão D01–D10.

**Intocáveis:** `schema.sql`, `schema-complete.sql`, SQL de upgrade, contratos congelados, manifestos, hashes, DOM, JS de ~600 KB, renderer, catálogo público, F9/F11, traduções como identificadores, `docs/F9-27-Status.json`, `release_approved:false`.

**Não reemitir manifestos nem reescrever hashes** para "limpar" os 15 `F11-XX-INDICE.md` — são 35 KB e o `verify-contract-freeze.py` existe exatamente para impedir isso.

---

## 6. Aplicações confirmadas por você

AQ · AT · AU · AV · AX · AY · BA · BB · BC · `Health-Admin-Real-Instrucoes` · BE · BF · BG · BH · BI · BJ (`ab8b765…`) · BK (`3219ccae…`) · BL (`ad7643de6ca2`).

> "Aplicado com sucesso, github atualizado. Podemos prosseguir…" = **autorização para prosseguir**.
> **Não** é veredito visual, release, runtime, migration, deploy, escrita do agente no GitHub nem decisão D01–D10.

---

## 7. Entrega corrente

`entregas/ReamurCMS-Frente-BO-r4-Windows.zip` · 3.203.580 bytes · 80 entradas ·
SHA `e69ed9f306b5f2c6c91533d79c03f4eb0bd2332af42d93d27d5f69287f01f76c` ·
**90/90** `.sha256` OK · verificador **142 OK / 0 FALHAS**.

Próxima evidência que falta: **`verificar-bo.html` verde no seu servidor** e, só então, o aceite visual do 3D.

---

*Companheiros: `RELATORIO-GERAL.md` · `PLANO-LIMPEZA.md` · `INDICE-DOCUMENTACAO.md`.*
