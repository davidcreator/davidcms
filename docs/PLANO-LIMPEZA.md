# Plano de Limpeza — repositório e workspace

**Data:** 2026-11-01 · **Natureza:** inventário e recomendação. **Eu não removi nada, não alterei nada e não escrevi no GitHub.**
**Regra:** toda sugestão é reversível. No workspace, o script **move para quarentena** em vez de apagar.

> Leia `ESTADO-GOVERNANCA.md` antes: contratos congelados, manifestos e `release_approved:false` não são "lixo".

---

## 0. Raio-X real (medido agora)

| Métrica | Valor |
|---|---|
| Arquivos no workspace | **2.675** (1.484 fora das dependências) |
| Bytes totais | **1.227.754.513** |
| Dependências / cache de browser | **1.092.419.150** (`phptest/node_modules`, `browser/node_modules`, `libs`, `.pw-browsers`) |
| Conteúdo útil versionável | **135.335.363** |
| `entregas/` | **90 ZIPs + 90 `.sha256`** · 35.921.898 bytes · **90/90 `sha256sum -c` OK** |
| `.md` na raiz | **73 arquivos / 340.018 bytes** |
| `docs/` (nova) | relatório, índice, governança, este plano, CSV, script |

A poluição **não é código**: é réplica de validação, duplicata de evidência e snapshot de referência.

---

# PARTE A — Repositório GitHub

## A.0 O que JÁ foi feito (não repetir)

Em **2026-11-05**, a limpeza de **Nível 1** foi aplicada por você:
`5593de…` → **`892fbd…`**, **−96 arquivos / −3.848.525 bytes (−8,7%)**.
Sairam `evidencia/` (50), 18 `docs/KAZTheme-Editor*.md`, 10 relatórios/índices AH–AM e 18 PROBEs `tools/kaz-theme-*`.
**`tests/` foi mantido** porque `php.yml:198` roda `find tools tests …` com `set -euo pipefail`.

Pós-limpeza verificado: 14 manifestos → 90 paths **0 ausentes / 0 divergentes**; 11 congelados intactos; só 3 blobs modificados; CI `docs-links` sem links para os 96 removidos.

## A.1 ⭐ Prioridade 1 — documentação desatualizada **dentro do repo** (baixo risco)

O `docs/Auditoria-Limpeza-GitHub-2026-11-05.md` **que está no repositório** é a versão *anterior* às correções, enquanto o `limpar-github.sh` no mesmo commit já é o corrigido. Inconsistência interna em 3 trechos:

| Linha | No repo (antigo) | Correto |
|---|---|---|
| 54 | `NÍVEL 2 … 79 arquivos / 5.125.218 bytes` | **46 arquivos / 5.045.211 bytes** |
| 65 | `tests/` listado como removível | **`tests/` MANTIDO** |
| 113 | `2519 blobs, −9,0 MB` | **2552 blobs, −8,9 MB** · `96 + 46 = 142 arquivos` |

**Ação:** substitua o arquivo no repo pela cópia local corrigida
`/home/user/Auditoria-Limpeza-GitHub-2026-11-05.md` (**9.068 bytes**).
O `git hash-object` do script já confere (`e39f82140d63…`), `bash -n` OK.

**Opcional (não quebra CI):** sobraram menções em *backticks* a PROBEs removidos —
`INDICE.md` linhas 9–10, `RELATORIO.md` 68 e 77, `docs/RELATORIO-ADERENCIA-main.md` 40–41 e 65.
Como não são links Markdown, o `lychee --offline` não reclama, mas o texto cita arquivos que não existem mais.

## A.2 Nível 2 — remoção opcional, **decisão sua** (46 arquivos / 5.045.211 bytes)

Lista completa em **`lista-remocao-opcional.txt`** (um path por linha).
**0** em manifestos, **0** entre congelados. Blocos:

| Bloco | Arquivos | Bytes | Observação |
|---|---|---|---|
| `**/*.map` (Bootstrap/jQuery vendorizados) | **16** | **4.383.046** | **Maior ganho.** Não quebra runtime nem `verify-deps-layout.sh`, mas o DevTools passa a dar 404 ao depurar essas libs. É decisão de **build**, não de limpeza de frente |
| `tree/` | 19 | 396.228 | Snapshot de código-fonte; **3 idênticos** aos paths reais em `dev/` |
| `reference/` | 7 | 85.477 | Outro snapshot; **3 idênticos** aos paths reais |
| `preview/` | 3 | 160.490 | Prévia estática (`index.html` + 2 assets) |
| `entregas/` no repo | 1 | 19.970 | `BDREditor-UI-Interpretation-Preview.html` |

> ⚠️ `tree/`, `reference/` e `preview/` são citados por ADRs em `agents-ia/` e docs F8/F9.
> **Antes de remover, rode `lychee --offline './**/*.md'` e ajuste as citações**, senão o job `docs-links` do CI falha.
> O `limpar-github.sh` **já** trata as 6 linhas de link do Nível 1; as citações do Nível 2 são responsabilidade de quem aplica.

```bash
# 1) conferir (nenhuma escrita)
bash limpar-github.sh --help
wc -l lista-remocao-opcional.txt            # 46

# 2) dry-run + guards locais
bash limpar-github.sh                       # so lista
bash tools/verify-checksums.sh
bash tools/repo-health.sh
bash tools/verify-ci-workflow.sh
python3 tools/verify-contract-freeze.py

# 3) SE decidir: aplica Nivel 1 + Nivel 2, mantendo tests/
bash limpar-github.sh --aplicar --nivel2

# 4) repetir os 4 guards + lychee --offline './**/*.md' antes do push
# Voce executa. O script NUNCA faz push.
```

**Ganho estimado:** 2694 → **2552 blobs**, **−8,9 MB (~20% do peso do repo)**, sem tocar runtime, contratos ou CI.

## A.3 ❌ NÃO REMOVER (contratos, runtime, CI)

| Item | Motivo |
|---|---|
| `dev/admin/controller/design/theme.php` | Registra os assets `-aj`, `-aj-al`, `-aj-am`; sem ele o editor não carrega |
| `dev/admin/view/css/kaz-theme-editor{,-ergonomia,-ajuste}.css` + 3 `.js` | **Runtime** das frentes AJ/AL/AM |
| `theme_form.twig`, `model/design/theme.php`, 3 `language/*/design/theme.php` | Contrato congelado do formulário |
| `dev/install/sql/schema.sql` | 1 dos 11 congelados |
| **14** `CHECKSUMS*.txt` + seus **90 paths** | `verify-checksums.sh` falha se qualquer um sumir; inclui os 15 `F11-XX-INDICE.md` |
| **11** `frozen_artifacts_sha256` | `verify-contract-freeze.py` é fail-closed |
| `tools/decision-matrix.json`, `verify-contract-freeze.py` | 10 `PENDING_DECISION` |
| `verify-deps-layout.sh`, `verify-checksums.sh`, `verify-ci-workflow.sh`, `verify-f11-01.sh`, `release-readiness-report.sh`, `install-deps.sh`, `lib/python-runtime.sh` | Guardas exigidos por `repo-health.sh` e pelo workflow |
| `docs/README.md`, `.github/workflows/php.yml` | Exigido por `verify-ci-workflow.sh` / CI |
| **`tests/`** | `php.yml:198` usa `find tools tests …`; 80 KB não justificam o risco |
| `dev/system/storage/node_modules/index.html` | Intencional (decisão 0081): mantém o diretório existindo |
| `docs/F9-27-Status.json` (`BLOCKED_NOT_RUN`), `release_approved:false` | Não apagar **nem alterar** — é o registro de que não há release |
| `agents-ia/` (103 arquivos) | 4 estão em `CHECKSUMS.txt` → manter |
| `docs/` em geral (242 outros `.md`) | Documentação de projeto (BDREditor, F8, F9, F11), não lixo das frentes |

---

# PARTE B — Workspace local (`/home/user`)

## B.1 Quarentena recomendada — **15 itens / 20.580.668 bytes (19,6 MB)**

| Item | Bytes | Por que é candidato |
|---|---|---|
| `bo_scan/` | ~15,3 MB | 536 arquivos **achatados**, réplica stale de `ad7643de…`. Duplica `bo_src/` e **não contém `common/header.twig`** (veio de curl separado) |
| `bn_previews/` | ~4,3 MB | Duplicatas **byte a byte** de `bn_pkg/` e das pranchas da raiz |
| 12 logs + `.sudo_as_admin_successful` | ~35 KB | Rastro de validação; as evidências já estão em `bo_out/`, `bo_pkg_final/evidencia/` e nos ZIPs |
| `.sudo_as_admin_successful` | 0 B | Artefato vazio do sandbox |

Outras duplicatas detectadas por SHA-256 (dentro de pacotes — **deixe onde estão**, são parte integrante do ZIP):
`david-universe.js` em 5 cópias, `three.module.js` / `three.core.js` em 4, capturas `shot-bo-*.png` em 2, `bo_config.json` em 2.

```bash
python3 docs/limpar-workspace.py            # DRY-RUN: so lista (padrao)
python3 docs/limpar-workspace.py --apply    # MOVE para _quarentena/<timestamp>/
python3 docs/limpar-workspace.py --restore  # desfaz
```

O script **nunca chama `os.remove`**. Grava `MANIFESTO.json` com origem, destino, bytes e SHA-256.
**Ciclo `--apply` → `--restore` testado: 15/15 itens voltaram intactos**, e `entregas/`, `uploads/image-1.png` e `RELATORIO-FINAL-AP.md` foram **recusados** como protegidos.
A exclusão definitiva fica com você, depois de conferir a quarentena.

## B.2 Fora do snapshot — onde está o peso de verdade

| Item | Tamanho | Recomendação |
|---|---|---|
| `.pw-browsers/` | **~924 MB** | Chromium headless para as capturas. **Não é lixo do projeto**, é cache de teste. Se precisar de disco, mova para fora de `/home/user`; pode reinstalar depois. Não apagar sem decidir |
| `phptest/node_modules/` | ~91 MB | PHP WASM — dependência, reinstalável |
| `libs/` | ~14 MB | `.deb` extraídos para o Chromium |
| `browser/node_modules/` | ~13 MB | Playwright |

Estes diretórios **não fazem parte do conteúdo versionável** e não justificam excluir documentação ou pacotes.

## B.3 ✅ MANTER (fonte de verdade)

`entregas/` (90 ZIPs + 90 `.sha256`, 90/90 OK) · `uploads/` (12 imagens originais suas, **insubstituíveis**) ·
`validacao/` · `docs/` · `bo_docs/` · `bo_pkg/` · `bo_pkg_final/` · `bo_ref/` · `bo_src/` · `bo_out/` ·
`bo_preview/` · `bo_lib/` · `bn_out/` · `bn_pkg/` · `bn_src/` · todos os `*.png`, `*.reamurtheme.json`,
`*.patch`, `*.sql`, `RELATORIO-FINAL-*`, `INDICE-*`, `GUIA-*` da raiz.

> `bn_pkg/` e `bn_out/` guardam o BN **contestado** e sua config corrompida. São evidência do erro de escopo — **não regerei nem apagarei** sem instrução.
> `BL-Diagnostico-Tema.sql` e os 3 `.patch` ficam: são sensíveis e não autorizam migration.

## B.4 Documentação organizada — o que fiz e o que **não** fiz

Criei `docs/` com 6 itens canônicos (`README.md`, `RELATORIO-GERAL.md`, `ESTADO-GOVERNANCA.md`,
`INDICE-DOCUMENTACAO.md`, `PLANO-LIMPEZA.md`, `inventario-limpeza.csv` + `limpar-workspace.py`).

**Não movi, renomeei, apaguei ou dupliquei nenhum `.md` da raiz.** Motivo: mover quebraria os links
relativos citados por `INDICE.md`, `RELATORIO.md` e ADRs `agents-ia/`, além de exigir reemissão dos
14 manifestos — precisamente o que os contratos congelados impedem. Organizar aqui = **indexar sem tocar**.
O `INDICE-DOCUMENTACAO.md` cataloga os 73 `.md` por categoria e situação (CANÔNICO / HISTÓRICO /
OBSOLETO / REPROVADO / CONTESTADO).

---

## 3. Ordem recomendada (segura)

1. **A.1** — substituir o relatório de limpeza desatualizado dentro do repo (3 trechos). Custo: 1 arquivo.
2. **`verificar-bo.html` no seu servidor** — antes de qualquer limpeza maior, confirme o 3D; é a única pendência funcional aberta.
3. **B.1** — `python3 docs/limpar-workspace.py` (dry-run), conferir, e só então `--apply`. Reversível.
4. **A.2** — se quiser os 5 MB: `lychee --offline` → `--nivel2` → 4 guards → `lychee` de novo → push seu.
5. **B.2** — só se o disco apertar; mover `.pw-browsers/` para fora de `/home/user`.

**Ganho combinado:** ~19,6 MB no workspace (quarentena) + ~5,0 MB no repo (Nível 2),
**sem tocar em runtime, contrato, manifesto, CI, aceite ou release**.

---

## 4. Limites

Auditoria de arquivos, não aprovação. Última consulta remota fresca registrada: **2026-11-05** (`892fbd…`);
o HEAD `ad7643de…` não foi reconsultado agora. Não rodei `lychee`, os 4 guards, CI remoto
(403 `Must have admin rights`), health autenticado, MySQL, sessão Admin, migration, deploy nem runtime.
**Nenhuma escrita do agente foi feita no GitHub.** `release_approved` segue `false`, F9-27 `BLOCKED_NOT_RUN`,
10 `PENDING_DECISION`, D01–D10 intactas. **CI verde e Chromium headless não significam release aprovada.**

*Companheiros: `RELATORIO-GERAL.md` · `ESTADO-GOVERNANCA.md` · `INDICE-DOCUMENTACAO.md` · `inventario-limpeza.csv`.*
