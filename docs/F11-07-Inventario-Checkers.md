# F11-07 — Inventário auditado dos checkers `tools/e2e` (frente A)

**Data:** 2026-11-02 · **HEAD:** `fb7b56055` · **Tipo:** classificação + guard de estabilidade. **Nenhum checker foi alterado.**

## 1. Metodologia

- Réplica local de **777 arquivos** do `main` (ferramentas, testes, docs e alvos `dev/` referenciados).
- **61** arquivos `tools/e2e/*.cjs` executados com captura de `stdout`/`stderr`/exit code.
- **36** com saída não-zero; **0** alvos de arquivo ausentes na réplica — portanto as falhas não são artefato de montagem.
- Os 36 foram **reproduzidos identicamente** em árvore-base nas frentes F11-01/F11-02: são pré-existentes, não regressão do CI.

## 2. Classificação

| Categoria | Qtd | Descrição |
| --- | ---: | --- |
| `REQUIRES_RUNTIME` | 4 | Runners Playwright — recusam sem browser/env; a recusa é a proteção. |
| `REQUIRES_WRAPPER` | 7 | Runners HTTP — exigem o wrapper `.sh` correspondente. |
| `REQUIRES_ARGS` | 5 | Checkers que exigem argumentos de CLI (`Uso:`/`Usage:`). |
| `BLOCKED_CONTROL` | 2 | Controles de consolidação que emitem `status: blocked` por design. |
| `STATIC_ONLY_FAIL_CLOSED` | 4 | Checks estáticos com `process.exitCode = 1` fail-closed, mesmo com `PASS`. |
| `MARKER_OR_FINDING` | 14 | Marcadores literais desatualizados ou achados reais de código/segurança. |

**Total: 36.** Os 25 checkers restantes passam (`25 OK`).

## 3. Por que **nenhum** marcador foi relaxado nesta frente

A escolha da frente foi "corrigir só os falsos negativos". A auditoria concluiu que **não há falso negativo seguro de corrigir sem evidência de runtime**:

- `e2e_f914.cjs` exige ausência de `.innerHTML =`, `eval(`, `fetch(`, `XMLHttpRequest` e `$this->db` no editor — é um **check de segurança**; a falha pode ser um achado real.
- `e2e_f915.cjs` exige `htmlspecialchars`/`ENT_QUOTES` e branches explícitas de trusted-render/legacy no Twig — idem.
- `e2e_f925_static.cjs` sinaliza marcador de persistência/rede (`XMLHttpRequest`); `e2e_bdre_unsaved_changes_static.cjs` exige "no autosave or network request". Relaxá-los sem ler cada ocorrência seria **mascarar**.
- `e2e_f905.cjs` falha com *"F9-05 não deve entregar schema.sql"* — governança de schema, não marcador cosmeticamente antigo.
- `e2e_f927_static.cjs` exige `hashes.head === status.base_head` e >=5 fontes — é o **gate de release**; atualizar o SHA sem autorização equivaleria a declarar prontidão.
- Os 4 `e2e_bdre_*_static.cjs` (áudio, block-copy, arquivo, tabela) definem `process.exitCode = 1` **por design**, mesmo quando todos os checks internos são `PASS`. Trocar para `0` transformaria `STATIC_ONLY`/`NOT_RUN` em `PASS` — exatamente o que a frente proibiu.

Consequência: a correção caso a caso fica para uma frente posterior, com leitura do código-alvo e evidência de equivalência/segurança. **Neste pacote, zero linhas de checker foram editadas.**

## 4. Guard de estabilidade (entregue)

`tools/verify-checkers-inventory.py` executa os 61 checkers e compara o conjunto de saídas não-zero com `tools/checkers-inventory.json`:

- **REGRESSÃO** (nova falha não auditada) → `exit 1`;
- **RESOLVIDO** (falha inventoryada que passou) → aviso para atualizar o inventário, `exit 0`;
- **ESTÁVEL** (conjunto idêntico) → `exit 0`.

Ele **não** exige que os 36 passem, **não** marca `NOT_RUN` como `PASS` e **não** aprova release. É uma cerca contra regressões, não um gate de qualidade.

```bash
python3 tools/verify-checkers-inventory.py
```

## 5. Inventário completo (36)

| Checker | rc | Categoria | Evidência |
| --- | ---: | --- | --- |
| `threejs_admin_preview_sandbox_isolated.cjs` | 1 | `REQUIRES_RUNTIME` | FAIL isolated Three.js sandbox browser test: Playwright is missing; install it only in the local QA environment. |
| `threejs_cms_playwright.cjs` | 1 | `REQUIRES_RUNTIME` | FAIL E2E: Playwright is missing. Install it locally with npm ci --prefix dev/system/storage, then install Chromium. |
| `threejs_native_admin_playwright.cjs` | 1 | `REQUIRES_RUNTIME` | FAIL native Three.js E2E: Playwright is missing; install it only in the local test environment. |
| `threejs_native_readonly_playwright.cjs` | 1 | `REQUIRES_RUNTIME` | FAIL read-only native Three.js E2E: Playwright is missing; install it only in the local test environment. |
| `e2e_f805.cjs` | 2 | `REQUIRES_WRAPPER` | BLOQUEADO/ERRO: Use o wrapper e2e_f805.sh --help. |
| `e2e_f806_http.cjs` | 2 | `REQUIRES_WRAPPER` | BLOQUEADO/ERRO: Use e2e_f806.sh --help. |
| `e2e_f807_http.cjs` | 2 | `REQUIRES_WRAPPER` | BLOQUEADO/ERRO: Use e2e_f807.sh --help. |
| `e2e_f808_http.cjs` | 2 | `REQUIRES_WRAPPER` | BLOQUEADO/ERRO: Use e2e_f808.sh --help. |
| `e2e_f809_http.cjs` | 2 | `REQUIRES_WRAPPER` | BLOQUEADO/ERRO: Use e2e_f809.sh --help. |
| `e2e_f810_http.cjs` | 2 | `REQUIRES_WRAPPER` | BLOQUEADO/ERRO: requisito de execução não atendido; consulte --help. Detalhes omitidos por privacidade. |
| `e2e_f811_http.cjs` | 2 | `REQUIRES_WRAPPER` | BLOQUEADO/ERRO: Use e2e_f811.sh --help. |
| `e2e_f903.cjs` | 2 | `REQUIRES_ARGS` | Uso: node e2e_f903.cjs --check [--sql <arquivo>] [--inventory <json>] [--fixture <sql>] |
| `e2e_f904.cjs` | 2 | `REQUIRES_ARGS` | Uso: node e2e_f904.cjs --check [--inventory docs/F9-03-Inventario-Banco-DavidAlmeida.json] [--map docs/F9-04-Mapa-Migracao-DavidAlmeida.json] [--sql / |
| `e2e_f917.cjs` | 2 | `REQUIRES_ARGS` | Usage: node e2e_f917.cjs <path-to-DavidCMS-checkout> |
| `e2e_f918_preflight.cjs` | 2 | `REQUIRES_ARGS` | Usage: node e2e_f918_preflight.cjs <path-to-DavidCMS-checkout> |
| `e2e_f919_static.cjs` | 2 | `REQUIRES_ARGS` | Usage: node e2e_f919_static.cjs <checkout-or-F9-19-package-root> |
| `e2e_f812.cjs` | 2 | `BLOCKED_CONTROL` | "status": "blocked", |
| `e2e_f901.cjs` | 2 | `BLOCKED_CONTROL` | "status": "blocked", |
| `e2e_bdre_audio_static.cjs` | 1 | `STATIC_ONLY_FAIL_CLOSED` | FAIL: modal de áudio é separado e não inclui upload/exclusão / FAIL: não há endpoint/modelo de upload ou exclusão de áudio / STATIC_ONLY: fixture, exe |
| `e2e_bdre_block_copy_static.cjs` | 1 | `STATIC_ONLY_FAIL_CLOSED` | FAIL: ações copiar/colar são explícitas e acessíveis na interface / FAIL: colar valida estrutura/contexto e limite JSON antes de alterar estado / STAT |
| `e2e_bdre_file_static.cjs` | 1 | `STATIC_ONLY_FAIL_CLOSED` | FAIL: catálogo passa raízes físicas confiáveis ao renderer / STATIC_ONLY: JS/fixtures e varredura de fontes; sem PHP, SQL, browser/app, staging, deplo |
| `e2e_bdre_table_static.cjs` | 1 | `STATIC_ONLY_FAIL_CLOSED` | STATIC_ONLY: sem PHP, SQL, browser, app, staging, deploy, migration ou banco. / FAIL: schema server-side contém v2 e estrutura de linhas/células / FAI |
| `check_audio_catalog.cjs` | 1 | `MARKER_OR_FINDING` | FAIL audio/video list and upload use the existing Blog controller and ACL / FAIL both media modals provide upload controls and existing-item selection |
| `check_threejs_native_admin.cjs` | 1 | `MARKER_OR_FINDING` | FAIL home and 404 consume only normalized system scene settings |
| `check_threejs_sandbox.cjs` | 1 | `MARKER_OR_FINDING` | FAIL cache-busted admin editor and public runtime URLs prevent stale script UI/runtime |
| `e2e_bdre_hierarchical_reordering_static.cjs` | 1 | `MARKER_OR_FINDING` | Error: FAIL: pure hierarchy move helper is present |
| `e2e_bdre_spacer_static.cjs` | 1 | `MARKER_OR_FINDING` | Error: FAIL: inspector exposes localized fixed-height choices |
| `e2e_bdre_unsaved_changes_static.cjs` | 1 | `MARKER_OR_FINDING` | Error: FAIL: no autosave or network request was introduced |
| `e2e_f905.cjs` | 1 | `MARKER_OR_FINDING` | FAIL F9-05: F9-05 não deve entregar schema.sql |
| `e2e_f914.cjs` | 1 | `MARKER_OR_FINDING` | - no HTML injection, network, or DB code allowed |
| `e2e_f915.cjs` | 1 | `MARKER_OR_FINDING` | - template needs explicit trusted-render and legacy fallback branches |
| `e2e_f922_static.cjs` | 1 | `MARKER_OR_FINDING` | - inline editor/DOM reconstruction missing |
| `e2e_f923_static.cjs` | 1 | `MARKER_OR_FINDING` | - missing source marker: renderInserter() / - missing source marker: this.undoStack.length > 50 |
| `e2e_f925_static.cjs` | 1 | `MARKER_OR_FINDING` | - unexpected persistence/network marker sessionStorage / - unexpected persistence/network marker fetch( / - unexpected persistence/network marker XMLH |
| `e2e_f926_static.cjs` | 1 | `MARKER_OR_FINDING` | - native inserter buttons must not have overridden list roles |
| `e2e_f927_static.cjs` | 1 | `MARKER_OR_FINDING` | - base SHA manifest incomplete |

## 6. Validações executadas

| Verificação | Resultado |
| --- | --- |
| 61 checkers executados na réplica | 25 OK / 36 não-zero, idênticos ao `main` |
| `python3 -m py_compile tools/verify-checkers-inventory.py` | OK |
| `python3 -m json.tool tools/checkers-inventory.json` | OK, 36 entradas |
| Guard na réplica | **ESTÁVEL — conjunto idêntico ao inventário**, `exit 0` |
| Guard com regressão injetada (checker temporário com `exit 3`) | **REGRESSÃO detectada**, `exit 1` |
| `bash tools/verify-checksums.sh` (estado publicado) | 52 conferidos / 0 divergentes |

### ⚠️ Limitações

- Nenhum checker foi corrigido; os 14 `MARKER_OR_FINDING` permanecem como estão e **não** devem ser tratados como aprovados.
- PHP, Composer, PHPStan, browser real, banco, staging, F9-12 e deploy não foram executados.
- `docs-links` do CI (run `41464495804`) estava com **12,3 min** em andamento na última medição (14:55 UTC), contra **358–369 s (~6 min)** nos 4 runs anteriores — **2× o habitual**, ainda dentro do `timeout-minutes: 20`. Causa provável: mais arquivos `.md` adicionados pelas frentes F11-01..07 e/ou diferença de varredura da `lychee-action@v2`. **Acompanhar**: se estourar 20 min, avaliar o timeout ou refinar os `--exclude` sem desligar o job.
- **Nenhuma escrita no GitHub.** Todos os arquivos deste pacote são novos; nenhum manifesto `CHECKSUMS*.txt` existente precisa ser recalculado.
- F9-27 segue `BLOCKED_NOT_RUN` / `release_approved: false`; BDREditor segue pausado em F9-103.

## 7. Próximo passo sugerido

F11-08: abrir os 14 `MARKER_OR_FINDING` um a um, ler o código-alvo e classificar cada marcador como *falso negativo comprovado* (corrigir o checker com evidência) ou *achado real* (registrar como P1/P2 no `F9-18-Risk-Register.json`, sem alterar o checker).
