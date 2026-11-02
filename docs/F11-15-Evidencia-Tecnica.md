# F11-15 — Evidência técnica dos 10 marcadores pendentes

**Data:** 2026-11-02 · **HEAD verificado:** `1908213b5` (F11-14 aplicado, run `41506254830` `success`) · **Nenhuma escrita no GitHub.**

---

## 1. Objetivo e método

O F11-11 deixou 3 dos 10 `MARKER_OR_FINDING` como `INVESTIGAR` e classificou D07 como possível regressão P1. Esta frente **investiga os 3 com o código real do `main`** para que as decisões A/B não dependam de suposição.

Método: leitura de 24 arquivos do `main` via `raw.githubusercontent.com` (editores, controllers, renderer, Twig, contratos F9, checkers e docs de fase), inspeção estática com `grep`/`sed`. **Não** houve execução de PHP, Composer, PHPStan, Twig, browser, banco, staging ou deploy. Não houve alteração de código, contrato, checker ou matriz de decisão.

> ⛔ **Nenhuma das 10 decisões foi tomada.** `tools/decision-matrix.json` segue com as 10 em `PENDING_DECISION`. Este pacote **complementa** a matriz com `tools/decision-evidence.json`, sem substituí-la.

## 2. D02 — `e2e_bdre_spacer_static.cjs`: **não é regressão**, é marcador obsoleto

O checker exige, no mesmo `ok(...)`:

```js
/this\.strings\.spacer\.heightLabel/.test(js) && /Object\.entries\(this\.strings\.spacer\.heights\)/.test(js)
```

**Evidência no `main`:**

| O que existe | Onde |
| --- | --- |
| `spacer:{heightLabel:'Altura do espaçador',heights:{16,32,48,64,96}}` | `dev/admin/view/js/blog-block-editor.js:1790` (defaults) |
| `spacerInspector:spacerInspectorCopy()` com rótulos próprios | mesmo arquivo, `:1790` e `:47` |
| Slider `[data-spacer-height-range]` → `SPACER_HEIGHTS[...]` | `:1895` |
| Botões `spacer-decrease` / `spacer-increase` com `this.strings.spacerInspector.*` | `:4823` |
| Validação client/server restrita aos 5 valores | `:182`, `:1077`; `block_document.php`; `block_renderer.php` |
| Especificação da UI como **range rotulado + botões** | `docs/F9-71-Vertical-Spacer.md` |

**Conclusão:** a UI foi implementada pela F9-71 de forma **diferente** da que o marcador pressupõe (slider + botões em vez de um `<select>` derivado de `Object.entries(...)`), mas **funcionalmente equivalente**: mesmos 5 presets, mesma allowlist, mesma validação client/server, mesmo renderer/CSS. `Object.entries` simplesmente não é mais usado.

**Recomendação: A** — atualizar o marcador do checker para a implementação F9-71. **Não envolve nenhum dos 11 contratos congelados**; é um ajuste de checker de QA, a executar somente após decisão registrada.

## 3. D04 — `check_threejs_native_admin.cjs`: **não é regressão**, é assinatura intermediária

O teste `home and 404 consume only normalized system scene settings` exige:

```js
/\$this->three_scene_config->runtime\(\$this->model_setting_setting->getSettings\(\)\)/.test(notFound)
```

**Evidência no `main`:**

| O checker espera | O código real (`dev/catalog/controller/error/not_found.php:34-37`) |
| --- | --- |
| chamada inline a `getSettings()` dentro de `runtime(...)` | `$threeSettings = $this->model_setting_setting->getSettings();` → **override intencional** `if (tema === 'not-found') $threeSettings['three_not_found_enabled'] = '1';` → `runtime($threeSettings)` |

`home.php:60` mantém exatamente `runtime($siteInfo)`, como o checker exige. Os demais marcadores do teste (`publicBasePath()`, `three_runtime_base`, ausência de `new \Reamur`) também estão presentes.

**Conclusão:** a variável intermediária é **equivalente e acrescenta comportamento deliberado** (forçar a cena 404 conforme o tema). O marcador é de uma implementação anterior, não um defeito. Nota: este checker lê ~20 arquivos e faz 10 testes; **apenas este marcador** foi reauditado aqui — não há certificação de que os outros 9 passam.

**Recomendação: A** — atualizar o marcador para aceitar a variável intermediária, preservando a exigência de que `runtime()` receba settings normalizados.

## 4. D07 — `e2e_f915.cjs`: **NÃO é regressão P1**. E **não adicionar `|raw`**

Esta é a correção mais importante desta frente. O F11-11 suspeitava: *"Twig sem `|raw`; se não for `Twig\Markup`, é regressão funcional (P1)"*. A suspeita foi **refutada**:

| Camada | Evidência |
| --- | --- |
| Autoescape ativo | `dev/system/library/template/twig.php:47` → `'autoescape'=>'html'` |
| **`|raw` é proibido** em templates de tema | `twig.php:62`, `:100`, `:129` — regex rejeita `\|\s*raw\b` em `THEME_TEMPLATE_KEYS`, que **inclui `blog/blog_info`** (`:30`) |
| Mecanismo substituto | `twig.php:165-171` `markTrustedHtml()` → `new \Twig\Markup($html, 'UTF-8')`; docblock: *"raw filter stays unavailable to theme source"* |
| Aplicação exata à rota do blog | `twig.php:179-186` `render()`: para `blog/blog_info`, marca **`post.block_html` e `post.description`** como `Twig\Markup` |
| Template coerente | `dev/catalog/view/template/blog/blog_info.twig` usa `{{ post.block_html }}` / `{{ post.description }}` **sem** `|raw` — correto, pois `Markup` não é reescapado |
| HTML já saneado na origem | `block_renderer.php` escapa com `htmlspecialchars(ENT_QUOTES\|ENT_SUBSTITUTE\|ENT_HTML5)`; contrato `F9-15` → `row_valid: "pass trusted renderer HTML to template"`, `raw_html_block: false` |

**Conclusão:** o HTML do renderer chega ao template como objeto `Twig\Markup` e é emitido intacto, com `autoescape` ligado e sem depender de `|raw`. A remoção de `|raw` foi uma **decisão de segurança do F10-03** (templates de tema editáveis não podem usar `raw`), não uma perda funcional. O checker `e2e_f915.cjs` exige um marcador da arquitetura anterior.

> 🔒 **Alerta de segurança:** a correção **não** é "voltar a usar `|raw`". Fazê-lo reintroduziria a superfície que o F10-03 fechou e faria o template ser rejeitado pelo `setThemeTemplates()`/`renderSandboxPreview()`.

**Recomendação: A com ressalva** — atualizar o marcador do checker para verificar `markTrustedHtml()`/`Twig\Markup` em `twig.php`, **mantendo a proibição de `|raw`**. Retifica a recomendação `B / possível P1` do F11-11.

## 5. Matriz consolidada de recomendações

| ID | Checker | Recomendação | Base da evidência |
| --- | --- | --- | --- |
| D01 | `e2e_bdre_hierarchical_reordering_static.cjs` | **A** (revisão caso a caso) | herdada F11-09/F11-11 |
| D02 | `e2e_bdre_spacer_static.cjs` | **A** | **investigada nesta frente** |
| D03 | `check_audio_catalog.cjs` | **B** (ACL/segurança) | herdada — não reauditada |
| D04 | `check_threejs_native_admin.cjs` | **A** | **investigada nesta frente** |
| D05 | `e2e_bdre_unsaved_changes_static.cjs` | **A** | herdada — não reauditada |
| D06 | `e2e_f914.cjs` | **A** | herdada — não reauditada |
| D07 | `e2e_f915.cjs` | **A** (sem `|raw`) | **investigada; retifica suspeita de P1** |
| D08 | `e2e_f925_static.cjs` | **B** (mais sensível) | herdada — não reauditada |
| D09 | `e2e_f905.cjs` | **B** | herdada — não reauditada |
| D10 | `e2e_f927_static.cjs` | **B** | herdada — não reauditada |

**Balanço:** 6 propostas **A** / 4 propostas **B**. As 7 marcações "herdada — não reauditada" são explicitamente **propostas, não provas**; continuam exigindo a decisão do responsável e, se ele quiser, auditoria dedicada.

## 6. Artefatos

- `tools/decision-evidence.json` — 10 entradas com `conclusion`, `proposed_choice`, `evidence_refs` (arquivo + trecho) e `evidence_level` (`INVESTIGATED_F11_15` ou `INHERITED_F11_11`).
- `tools/verify-evidence-refs.py` — guard **read-only** que confere, para cada referência, que o arquivo existe e contém o trecho citado. Não duplica os guardas existentes (checa integridade de evidência, não contratos/checkers/workflow). `exit 0` = coerente; `exit 1` = referência quebrada.
- `docs/F11-15-Evidencia-Tecnica.md` (este) · `F11-15-INDICE.md` · `CHECKSUMS-F11-15.txt`.

**Todos os arquivos são novos.** `tools/decision-matrix.json`, `tools/verify-contract-freeze.py`, `tools/repo-health.sh` e os **12 manifestos publicados permanecem intactos**.

## 7. Validações executadas

| Verificação | Resultado |
| --- | --- |
| 24 arquivos do `main` inspecionados | OK (sem execução de runtime) |
| `python3 -m json.tool tools/decision-evidence.json` | 10 entradas, 3 `INVESTIGATED_F11_15` |
| `python3 -m py_compile tools/verify-evidence-refs.py` | OK |
| `python3 tools/verify-evidence-refs.py` | **`exit 0` — 20/20 referências coerentes** |
| A/B: trecho adulterado | **`exit 1` — referência quebrada detectada** |
| `python3 tools/verify-contract-freeze.py` | `exit 0` — 11 contratos ainda congelados |
| `bash tools/repo-health.sh` | **6 PASS / 0 FAIL / 0 SKIP** |
| `bash tools/verify-checksums.sh` | **0 divergentes em 13 manifestos** |
| `unzip -t` / CRC / `sha256sum -c` (15 ZIPs) | OK |

## 8. Limitações e próximo passo

- Inspeção **estática**: `Twig\Markup` e `autoescape` foram lidos no código, mas a renderização real não foi executada (sem PHP/browser). A conclusão de D07 é de arquitetura, não de teste funcional.
- D04 reauditou **1 dos 10 testes** do checker; D02 reauditou **1 dos ~17 `ok(...)`**. Não há certificação de que os demais passam.
- Verificação remota **somente leitura**; nenhum commit, push, PR ou alteração de contrato.
- **Nenhuma decisão A/B foi registrada.** Para decidir, editar `tools/decision-matrix.json` (`status: DECIDED`, `choice`, `decided_by`, `date`); o `verify-contract-freeze.py` só aceitará reemissão de contrato depois disso.

Recomenda-se começar por **D08** (divergência real entre contrato `PROPOSAL_PENDING` e storage/rede implementados) e **D03** (ACL do catálogo de áudio) — as duas propostas **B** que podem ser achados de segurança. As 6 propostas **A** são ajustes de marcadores de QA e devem ser aplicadas **uma a uma**, sem transformar `STATIC_ONLY`/`NOT_RUN` em `PASS`.
