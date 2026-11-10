# F11-09 — Triagem caso a caso dos 14 `MARKER_OR_FINDING` (frente A, etapa 2)

**Data:** 2026-11-02 · **HEAD verificado:** `440419b12` · **Tipo:** correção cirúrgica + dossiê. **Nenhuma escrita no GitHub.**

---

## 1. Princípio aplicado

A frente A autorizou "corrigir **só os falsos negativos**". Corrigir, aqui, significa **atualizar um marcador literal quando existe equivalente comprovado no código atual**, mantendo o mesmo nível de rigor. Não significa:

- relaxar um check de segurança porque o código hoje usa `fetch`, `localStorage` ou `innerHTML`;
- trocar `process.exitCode = 1` por `0` (transformaria `STATIC_ONLY`/`NOT_RUN` em `PASS`);
- atualizar o SHA do release gate sem autorização do responsável;
- reescrever um checker com vários marcadores desatualizados em cadeia.

Resultado: **4 corrigidos**, **10 mantidos como estão**. O conjunto de checkers com saída não-zero caiu de **36 → 32** (de 61 executados).

## 2. As 4 correções (equivalência comprovada)

| Checker | Marcador antigo | Equivalente real no `main` | Correção |
| --- | --- | --- | --- |
| `check_threejs_sandbox.cjs` | `/threejs-5/` nos 2 controllers | `threejs-49` (linha: cache-bust incrementado) | Exigir `threejs-\d+` **e que a versão seja idêntica nos dois controllers** — mais rigoroso que o literal |
| `e2e_f922_static.cjs` | `editor.includes("contentEditable = 'true'")` | `contentEditable='true'` e `contentEditable = …?'false':'true'` (4 ocorrências) | `/contentEditable\s*=\s*'true'/`, mantendo `normalizeDomInline` + `buildInlineDom` |
| `e2e_f923_static.cjs` | `renderInserter()` e `this.undoStack.length > 50` | `renderInserter(forceOpen) {` (L3113) e `while (stack.length > 50 \|\| …)` (L3405, dentro de `pushHistory`) | Dois marcadores literais **atualizados**, sem trocar `includes()` por regex permissiva |
| `e2e_f926_static.cjs` | `!role=list` no arquivo inteiro | **0** ocorrências dentro de `renderInserter`; **24** em painéis de achados de acessibilidade (`role="list"/"listitem"` semanticamente corretos) | Verificação **restrita ao bloco do inserter**, com fail-closed se o bloco não existir |

Nenhuma das quatro altera `--accept`, exclusões, `fail`/`failIfEmpty`, contratos de segurança ou o `exit code` de qualquer checker. `node --check` passou nos 4.

> **Revertido:** `e2e_bdre_hierarchical_reordering_static.cjs` teve o 1º marcador corrigido (`moveBlockAcrossHierarchy` ganhou `allowPageMedia`), mas a execução seguinte revelou uma **cadeia** de outros marcadores desatualizados (`!validateDocument(document).valid`, `indent.disabled = …`, etc.). Corrigi-lo exigiria reescrever o checker — fora do escopo. O patch foi **revertido** e o arquivo volta intacto ao inventário.

## 3. Os 10 mantidos (e por quê)

| Checker | Evidência no código-alvo | Veredito |
| --- | --- | --- |
| `e2e_bdre_hierarchical_reordering_static.cjs` | Cadeia de marcadores antigos | Requer revisão do responsável |
| `e2e_bdre_spacer_static.cjs` | `spacer:{heights:…}` existe nos defaults, mas **não** `Object.entries(this.strings.spacer.heights)` | Sem equivalente comprovado |
| `check_audio_catalog.cjs` | Rotas/IDs presentes; `can_modify and can_use_audio_catalog` **ausente** no Twig | Possível mudança real de ACL |
| `check_threejs_native_admin.cjs` | `three_scene_config->runtime(` presente, assinatura divergente | Não comprovado |
| `e2e_bdre_unsaved_changes_static.cjs` | `fetch(...)` real no init, para **histórico de revisões** disparado por clique (L233) — não é autosave | Evolução intencional; check de rede |
| `e2e_f914.cjs` | `await fetch(url, options)` real no editor (L3243) | Contrato **F9-14 MVP** proibia rede; check de segurança |
| `e2e_f915.cjs` | Twig usa `{{ post.block_html }}` / `{{ post.description }}` **sem `\|raw`** | Pode ser endurecimento anti-XSS **ou** regressão de render; exige decisão |
| `e2e_f925_static.cjs` | `localStorage`, `sessionStorage`, `fetch(`, `XMLHttpRequest` **reais** (notas, presets, rascunho, upload); contrato diz `PROPOSAL_PENDING` | Divergência real de contrato |
| `e2e_f905.cjs` | `dev/install/sql/schema.sql` **existe** (HTTP 200) e o checker o proíbe | Achado real de governança |
| `e2e_f927_static.cjs` | `hashes.head = 448dd61f…` ≠ `status.base_head = 3d481ecf…` | Drift de metadados do gate; atualizar exigiria declarar prontidão |

**Nenhum desses 10 foi editado.** Registrá-los como `PASS` seria mascarar divergências possivelmente genuínas — exatamente o que o F11-07 se propôs a evitar.

## 4. Inventário e guard

`tools/checkers-inventory.json` foi reemitido com **32 entradas** (4 removidas), `head: 440419b12`, e os campos `f11_09_resolutions` (4) e `f11_09_unresolved` (10). `tools/verify-checkers-inventory.py` **não foi alterado**: ele continua comparando o conjunto observado com o inventário e agora reporta `ESTÁVEL` com 32.

`CHECKSUMS-F11-07.txt` foi **reemitido** (o JSON mudou); os demais manifestos (`CHECKSUMS.txt` 33, `F11-04` 11, `F11-05` 8, `F11-07` 4, `F11-08` 8) permanecem válidos porque nenhum de seus arquivos foi tocado. Novo `CHECKSUMS-F11-09.txt`.

## 5. Validações executadas

| Verificação | Resultado |
| --- | --- |
| 61 checkers na réplica de 2.353 arquivos do `main` | **29 OK / 32 não-zero** (antes: 25/36) |
| `node --check` nos 4 checkers corrigidos | OK |
| `python3 tools/verify-checkers-inventory.py` | **ESTÁVEL — conjunto idêntico ao inventário**, `exit 0` |
| Guard com regressão injetada | **REGRESSÃO detectada**, `exit 1` |
| `bash tools/verify-checksums.sh` | **0 divergentes** em 6 manifestos |
| `bash tools/verify-ci-workflow.sh` | **26 PASS / 0 FAIL** |
| `bash -n` / `python3 -m json.tool` / `unzip -t` / CRC / `sha256sum -c` | OK |

### ⚠️ Limitações
- Os 10 checkers não corrigidos **permanecem falhando** e não devem ser tratados como aprovados. Este pacote reduz o ruído de marcadores obsoletos; **não** certifica o BDREditor nem o release.
- PHP, Composer, PHPStan, browser real, banco, staging, F9-12, migrations e deploy **não** foram executados. As correções são todas estáticas (marcadores de texto).
- **Nenhuma escrita no GitHub**: sem commit, push, PR ou aplicação remota.
- F9-27 segue `BLOCKED_NOT_RUN` / `release_approved: false`; BDREditor segue pausado em F9-103 pelo handoff.

## 6. Próximo passo sugerido

Levar a tabela da seção 3 ao responsável: cada uma das 10 linhas precisa de uma decisão explícita — **atualizar o contrato da fase** (se a mudança foi intencional) ou **registrar como achado P1/P2** no `F9-18-Risk-Register.json` (se não foi). Sem essa decisão, não há base segura para editar checks de segurança.
