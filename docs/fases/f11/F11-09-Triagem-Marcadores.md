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

## 3. Os 10 inicialmente mantidos (e por quê)

| Checker | Evidência no código-alvo | Veredito |
| --- | --- | --- |
| `e2e_bdre_hierarchical_reordering_static.cjs` | Cadeia de marcadores antigos | Requer revisão do responsável |
| `e2e_bdre_spacer_static.cjs` | `spacer:{heights:…}` existe nos defaults, mas **não** `Object.entries(this.strings.spacer.heights)` | Sem equivalente comprovado |
| `check_audio_catalog.cjs` | Rotas/IDs presentes; `can_modify and can_use_audio_catalog` **ausente** no Twig | Possível mudança real de ACL |
| `check_threejs_native_admin.cjs` | `three_scene_config->runtime(` presente, assinatura divergente | Não comprovado |
| `e2e_bdre_unsaved_changes_static.cjs` | `fetch(...)` real no init, para **histórico de revisões** disparado por clique (L233) — não é autosave | Evolução intencional; check de rede |
| `e2e_f914.cjs` | `await fetch(url, options)` real no editor (L3243) | Contrato **F9-14 MVP** proibia rede; check de segurança |
| `e2e_f915.cjs` | Twig usa `{{ post.block_html }}` / `{{ post.description }}` **sem `\|raw`** | Pode ser endurecimento anti-XSS **ou** regressão de render; exige decisão |
| `e2e_f925_static.cjs` | `localStorage`/`sessionStorage` e `fetch` reais (notas, presets, recuperação local, APIs); a string `XMLHttpRequest` é somente o valor do header `X-Requested-With`, não uso da API XHR; contrato dizia `PROPOSAL_PENDING` | Divergência real de contrato — **resolvida por KZ-62/D08=A**: contrato e checker distinguem os mecanismos existentes; sem liberar server autosave ou feature nova. |
| `e2e_f905.cjs` | `schema.sql` é artefato compartilhado do repositório, com adição posterior F9-25; F9-05 entrega Model + ACL; o checker antigo exigia ausência do arquivo | **D09=A / resolvido em KZ-63**: contrato esclarece o escopo e o checker valida o limite da fase, sem editar SQL. O checker completo ainda para em outra asserção (`declare(strict_types=1)`), fora de F918-08. |
| `e2e_f927_static.cjs` | `hashes.head = 448dd61f…` ≠ `status.base_head = 3d481ecf…` | Drift de metadados do gate; atualizar exigiria declarar prontidão |

**Na triagem original (2026-11-02), nenhum desses 10 havia sido editado.** Depois, D08 foi resolvido em KZ-62 e D09 em KZ-63, com limites e ressalvas registrados nas linhas correspondentes. As outras oito linhas continuam sem mudanças; as que ainda falham não devem ser classificadas como `PASS`.

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

D08 e D09 receberam autorização explícita e foram alinhados em KZ-62/KZ-63. Para os demais itens, preservam-se as decisões e achados já registrados; nenhuma outra mudança fica autorizada por este adendo. D10 continua B/owner-locked e fora do escopo — não alterar F9-27 nem hashes de release sem autorização separada.


## 7. Atualização KZ-63 — D09=A

A decisão explícita D09=A substitui o B anterior. A investigação F11-16 concluiu que a proibição do `schema.sql` no checker F9-05 era uma sobreposição de escopo: o arquivo é um artefato compartilhado do repositório, recebeu conteúdo de fases posteriores (incluindo F9-25) e não foi entregue pela fase F9-05. O instalador atual importa `schema-complete.sql`.

O contrato F9-05 agora declara essa fronteira; `e2e_f905.cjs` deixou de exigir que o arquivo global esteja ausente e valida o escopo documentado. Nenhum SQL foi alterado. As verificações específicas de D09 passaram. O checker integral ainda falha na asserção preexistente sobre `declare(strict_types=1)` — confirmada também no checker original e não alterada por D09. F918-08 foi resolvido; `release_ready` permanece `false`, e D10 não foi tocado.

## 8. Atualização D10=A — alinhamento estático dos metadados F9-27

O responsável autorizou explicitamente D10=A em 2026-11-17. Os campos `F9-27-Status.base_head`, `F9-27-Base-Hashes.head` e `F9-27-Preflight-Status.base_head` foram alinhados ao snapshot inspecionado `9fcc6e5a78fc246a0f8743ffa37658e365b65027`; os sete Git blob SHA-1s das fontes pontuais foram recalculados. F918-09 foi resolvido como drift de metadados, não como falha funcional.

O gate continua `BLOCKED_NOT_RUN`; F9-12 permanece `UNKNOWN_NOT_CONFIRMED`; runtime, DB, migration, aprovação de release e deploy permanecem falsos. Nenhum teste de staging/runtime, escrita no GitHub, deploy, arquivo F9-26 ou SQL/schema foi alterado/executado.
