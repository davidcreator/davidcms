# Roteiro dos próximos passos — pós-AP

**Data:** 2026-11-06 (atualizado pela Frente AT) · **HEAD atual:**
`bfe9eec7aae82dc72c7054bcb46dbd901307d7c5`
(`docs(aq-acceptance): add full planning package and health tool`, árvore
3.224 entradas / 2.641 blobs, `truncated: false`). A Frente **AQ** foi
aplicada sobre `179269fe…` (`fix(bdre-editor): limit dark theme to editor`):
+6 / −0 / ~0, com **10/10 arquivos do pacote idênticos** no remoto.

**Natureza deste documento:** planejamento e leitura de contratos. **Não** é
uma frente de código, **não** executa runtime, **não** altera contrato,
**não** escreve no GitHub e **não** declara release. Aplicação da AP: +9 / −0 /
~6 em relação à AO, com os **6 arquivos substituídos byte a byte idênticos**
ao pacote entregue.

---

## 0. Ponto de partida (confirmado, não inferido)

| Item | Estado no HEAD |
|---|---|
| Aplicação técnica da AP | ✅ fiel (6/6 idênticos, 9 adições, 0 remoções) |
| **Veredito visual da AP** | ✅ **APROVADO pelo usuário em 2026-11-06** — `image-9.png` é registro histórico superado; `image-10.png` referência atendida |
| `decision-matrix.json` | `PENDING_DECISION: 10`, `DECIDED: 0` |
| F9-27 | `BLOCKED_NOT_RUN`, `release_approved: false`, `runtime_tests_executed: false` |
| F9-18 | `release_ready: false`, 4 achados abertos (2 × P1, 2 × P2) |
| F9-25 | `patterns_persistence: PROPOSAL_PENDING`, `catalog_context_preview: DESIGN_GATE_PENDING` |
| F9-26 | `wcag_conformance_claimed: false`, runtime/axe/performance `false`; foco `#174ea6`/3 px/2 px intacto |
| F9-14 | `standalone_in_memory_component_prepared_not_integrated` |
| CI remoto | ✅ **verificado** (Frente AT): run `42217429359` `completed/success`, **7/7 check-runs success** no HEAD `bfe9eec7…`; leitura pública da API, sem token |
| Health do Admin real | ⏳ pendente: `tools/health-admin-ap.js` já está no GitHub, falta colar no console do Admin autenticado |
| Aplicação da AQ | ✅ +6 / −0 / ~0; 10/10 arquivos idênticos (6 novos + 4 evidências já existentes da AP) |

> Regra que segue valendo: **aplicar ≠ aprovar**. AA e AO também foram
> aplicadas e depois reprovadas.

---

## 1. Passo 1 — Veredito visual ✅ **CONCLUÍDO: APROVADO (2026-11-06)**

> **Atualização AU.** O usuário respondeu explicitamente **`aprovado`** após
> avaliar o Admin real. Os quatro critérios abaixo foram considerados
> satisfeitos. `image-9.png` deixa de ser a reprovação vigente; o escopo
> "editar somente o BDREditor" fica **fechado e congelado**. Registro
> completo em `Registro-Veredito-Visual-AP.md`. **Isto não é release**:
> `release_approved: false` e todos os contratos permanecem como estavam.

Critérios avaliados (mantidos para rastreabilidade):

Abrir **Pages › Edit** e **Blog › Edit post** no Admin autenticado e conferir:

1. Item ativo da sidebar **azul** (`#0d6efd`), sem borda/linha verde.
2. Topbar, breadcrumb, título, cards, campos, abas de idioma, botões, alertas
   e badges **como antes da AO**.
3. BDREditor escuro, accent verde, **canvas branco**, documento íntegro.
4. **Nada mais** alterado fora do BDREditor (overlays do próprio editor podem
   e devem ser escuros).

**Como foi registrado:** resposta explícita **`aprovado`** do usuário em
2026-11-06, após avaliação no Admin real. `Protocolo-Aceitacao-Visual-AP.md`
e `Registro-Veredito-Visual-AP.md` foram atualizados; `image-9.png` passou a
registro histórico superado.

| Desfecho | Ação |
|---|---|
| ✅ **Aprovado (ocorrido)** | Veredito registrado; escopo congelado; passos 2–4 liberados para as ações do usuário |
| Reprovado (não ocorreu) | Frente de ajuste **pontual**, sem reabrir o escopo da página |

**Critério de saída: SATISFEITO** pelo veredito explícito. PROBE 53/0,
Chromium 41/0, 117 asserções finais e CI 7/7 foram apoio técnico — **não**
substituíram a decisão e **não significam release aprovada**.

---

## 2. Passo 2 — Saúde do Admin real e CI remoto

### 2.1 Correção necessária: `__kazHealth()` não existe

Varrendo **464 JS/PHP/Twig** e **460 `.md`/`.json`/`.txt`** do HEAD:
`kazHealth` aparece em **11 documentos** (relatórios meus) e em **nenhum
arquivo de código**. Ou seja, a recomendação repetida de *"rodar
`copy(__kazHealth())` em produção"* era uma formulação de relatório, não uma
função instalada. **Não tente executá-la.** O equivalente concreto é um
health **observacional, somente leitura**, pelo console do DevTools.

### 2.2 Health observacional (copiar e colar no console do Admin real)

```js
copy(JSON.stringify((() => {
  const q = s => document.querySelector(s);
  const cs = e => e ? getComputedStyle(e) : null;
  const host = q('[data-blog-block-editor]');
  const menu = q('#menu > li.active > a');
  const styles = [...document.querySelectorAll('link[rel="stylesheet"]')]
    .map(l => l.getAttribute('href') || '')
    .filter(h => h.includes('blog-block-editor-kaz'));
  return {
    path: location.pathname,
    marker_ap: styles.some(h => h.includes('-kaz-bdre-ap')),
    styles,
    bs_primary: getComputedStyle(document.body).getPropertyValue('--bs-primary').trim(),
    rms_accent: getComputedStyle(document.documentElement).getPropertyValue('--rms-accent').trim(),
    menu_active_bg: menu ? cs(menu).backgroundColor : null,
    page_bg: getComputedStyle(document.body).backgroundColor,
    host_present: !!host,
    host_bg: host ? cs(host).backgroundColor : null,
    editor_mounted: !!q('.rb-editor'),
    canvas_bg: q('.rb-canvas') ? cs(q('.rb-canvas')).backgroundColor : null
  };
})(), null, 2))
```

Somente leitura; não expõe sessão, token nem conteúdo de post. **Esperado**
(conforme o A/B/C medido): `marker_ap: true`, `bs_primary: "#0d6efd"`,
`rms_accent: "#0d6efd"`, `menu_active_bg: "rgb(13, 110, 253)"`,
`page_bg: "rgb(244, 246, 249)"`, `host_bg: "rgb(24, 24, 27)"`,
`editor_mounted: true`, `canvas_bg: "rgb(255, 255, 255)"`.
Qualquer divergência é **achado a investigar**, não erro automático — o
Admin real pode ter skin/modo diferente da prévia.

Complemento local (sem rede, sem PHP, sem banco): `bash tools/repo-health.sh`
— orquestrador *read-only* de 6 guardas; `SKIP` não conta como `PASS`.

### 2.3 CI remoto

O workflow `.github/workflows/php.yml` tem **6 jobs**: `composer`,
`php-lint`, `docs-links`, `static-analysis`, `node-deps`, `repo-guards`
(este último chama `tools/repo-health.sh`).

**Resultado verificado em 2026-11-06 (Frente AT).** A leitura pública da
API funcionou sem token — o `403 Must have admin rights` registrado antes
aplicava-se a outro endpoint, não a `/actions/runs` nem a
`/commits/<sha>/check-runs`:

| Commit | Run | Status | Check-runs |
|---|---|---|---|
| **`bfe9eec7…` (AQ, HEAD atual)** | `42217429359` | `completed / success` | **7/7 success** |
| `179269fe…` (AP) | `42212306218` | `completed / success` | **7/7 success** |

Os 7 checks: `php-lint`, `node-deps (22)`, `static-analysis`, `repo-guards`,
`node-deps (20)`, `docs-links`, `composer`. Atenção: o
`ci_status: "7/7 success (runs 41483802295 / 41504039830)"` gravado em
`decision-matrix.json` continua sendo de **runs antigos**; a evidência
acima é a do commit atual, registrada em `evidencia/ci-remoto-bfe9eec7.json`.

**Critério de saída do passo 2:** ✅ CI do commit atual verde (concluído) +
⏳ health colado do Admin real (pendente, depende do usuário). Isso **não**
converte `NOT_RUN`/`BLOCKED_NOT_RUN` em `PASS` e **não** autoriza release.

---

## 3. Passo 3 — Governança dos contratos

### 3.1 Regra de ouro (literal do repositório)

> `policy`: *"Nenhuma decisao tomada por inferencia. Contratos congelados ate
> registro explicito de status=DECIDED."*

Ou seja: as 10 decisões são **do responsável**, não do agente. Minha parte é
preparar a pauta, não decidir.

### 3.2 Pauta das 10 `PENDING_DECISION`

| ID | Natureza | Checker | Recomendação registrada | Decisão que falta |
|---|---|---|---|---|
| **D03** | POSSÍVEL_MUDANÇA_ACL | `check_audio_catalog.cjs` | **B** — segurança/ACL: registrar achado, não relaxar checker | A ou B (B recomendado) |
| **D07** | SEGURANÇA_OU_REGRESSÃO | `e2e_f915.cjs` | **B** — se não for `Twig\Markup`, é regressão funcional P1 | investigar + A ou B |
| **D08** | DIVERGÊNCIA_REAL_CONTRATO | `e2e_f925_static.cjs` | **B** — exige confirmação de aprovação das features antes de mexer no contrato | aprovar features? depois A ou B |
| **D10** | DRIFT_METADADOS | `e2e_f927_static.cjs` | **B** — **não** atualizar o SHA sem autorização: equivaleria a declarar prontidão do gate | A ou B (B recomendado) |
| D05 | CONTRATO_HISTÓRICO | `e2e_bdre_unsaved_changes_static.cjs` | **A** — reconhecer histórico de revisões, mantendo a proibição de autosave | A ou B |
| D06 | CONTRATO_HISTÓRICO | `e2e_f914.cjs` | **A** — atualizar F9-14 pela evolução, **sem** alterar o checker de segurança | A ou B |
| D09 | ACHADO_REAL_GOVERNANÇA | `e2e_f905.cjs` | **B** — `schema.sql` é legítimo de outra fase? aposentar o checker histórico? | A ou B |
| D01 | CADEIA_MARCADORES | `e2e_bdre_hierarchical_reordering_static.cjs` | **A** — revisar marcadores um a um com evidência de equivalência | A ou B |
| D02 | EQUIVALENTE_NÃO_COMPROVADO | `e2e_bdre_spacer_static.cjs` | **INVESTIGAR** — pode ser regressão real do inspector; ler o código antes | investigar → A ou B |
| D04 | EQUIVALENTE_NÃO_COMPROVADO | `check_threejs_native_admin.cjs` | **INVESTIGAR** — confirmar equivalência da assinatura antes de atualizar marcador | investigar → A ou B |

**Ordem sugerida de pauta:** (1) segurança/ACL D03, D07, D08, D10 →
(2) contratos históricos D05, D06, D09 → (3) marcadores/equivalência D01,
D02, D04. Para cada uma registrar `status: DECIDED`, `decided_by` e
`decision_date` — **nunca** por inferência nem reescrevendo hashes.

### 3.3 Contratos bloqueantes e o que realmente falta

| Contrato | Estado | Pré-requisito real |
|---|---|---|
| **F9-27** | `BLOCKED_NOT_RUN`; matriz com 12 casos (10 `NOT_RUN` + 2 `BLOCKED_F9_12_UNKNOWN`); `f9_12_status: UNKNOWN_NOT_CONFIRMED` | 7 itens em `required_before_execution`: autorização escrita, staging descartável identificado, prova de isolamento, **confirmação de F9-12 pelo responsável**, rollback/dados sintéticos, escopo explícito das famílias runtime, contas de teste para pattern library |
| **F9-18** | `release_ready: false` | **F918-01 P1** (`dev/catalog/model/blog/blog.php:110-124`): exceção de lookup cacheada como "tabela ausente" → fallback legado `description|raw` *fail-open*; exige detecção tri-state e *fail closed*. **F918-02 P1** (`block_renderer.php:62-66`): URLs relativas em rotas SEO aninhadas. P2: F918-03 (idioma do sidecar), F918-04 (atomicidade do delete) |
| **F9-25** | `patterns_persistence: PROPOSAL_PENDING`; `catalog_context_preview: DESIGN_GATE_PENDING` | ACL dedicada (não inferir do ACL de Blog), revisão do install-agent e aprovação de schema/migration antes de qualquer persistência; nenhum endpoint de preview público |
| **F9-26** | `wcag_conformance_claimed: false`; `php_sql_browser_app_e2e_runtime: false`; `screen_reader_or_axe: false`; `measured_browser_performance: false` | runtime e medição em navegador real; **não** declarar conformidade WCAG a partir do headless |
| **F9-14** | `standalone_in_memory_component_prepared_not_integrated` | contrato histórico do MVP; tratado por D06/D09 — atualizar descrição, não reimplementar |

### 3.4 Sequência e critérios de saída

1. **Decidir** as 10 `PENDING_DECISION` (responsável) → `DECIDED` explícito.
2. **Fechar F918-01 e F918-02** com correção + evidência (dependente de
   ambiente com PHP/DB, portanto do passo 4).
3. **Só então** abrir F9-27 com as 7 condições do Release Gate satisfeitas.
4. **Nunca:** marcar `release_ready`/`release_approved` por inferência,
   reescrever `base_head`/hashes, aposentar checker sem decisão, ou converter
   `PENDING`/`SKIP`/`NOT_RUN`/`STATIC_ONLY`/`BLOCKED_NOT_RUN` em `PASS`.

---

## 4. Dependências em uma linha

`✅ 1 veredito visual (APROVADO)` → `2 health + ✅ CI` → `3 decisões + P1` →
`4 backend/ciclo salvar–Publicar` → `5 (opcional) identidade extra`.

**Após o veredito, todos os passos restantes dependem de uma ação do
usuário**: colar o health no console (2), decidir D01–D10 (3), autorizar
staging/runtime (4) ou revisar o escopo (5). Não há próxima frente de código
a ser entregue pelo agente sem uma dessas ações.

O passo 4 é o maior risco funcional remanescente e **exige** sessão
autenticada, MySQL e staging — tudo fora deste ambiente. Deve ser uma frente
separada e explicitamente autorizada, não uma continuação visual.

O passo 5 (screenbar / 3 colunas / grid do canvas do KAZTheme-Editor, ou
levar a identidade ao catálogo público) segue **vetado** pelas decisões
`onde=admin` e "não clonar layout KAZ"; só com nova decisão explícita.

---

## 5. O que não fazer agora

Declarar release; rodar migration ou SQL; alterar DOM, o JS de ~600 KB,
Twigs, renderer, `schema.sql`, traduções ou manifestos; reentregar A–AO;
tratar CI verde, health local, PHP WASM ou Chromium headless como aceitação;
escrever no GitHub pelo agente.

`release_approved: false` · F9-27 `BLOCKED_NOT_RUN` ·
F9-18 `release_ready: false`.

---

## 6. O que este roteiro fez e não fez

**Fez:** reauditou o HEAD pós-AP, confirmou 6/6 arquivos idênticos, leu 13
arquivos de contrato/CI do remoto, varreu 924 arquivos em busca de
`kazHealth`, corrigiu a inconsistência e montou a pauta de decisões.
**Atualização AT:** confirmou a aplicação da AQ (10/10 idênticos), verificou
o CI remoto do HEAD atual (7/7 success) e registrou a evidência em JSON.

**Não fez:** executar runtime, acessar banco/staging, aplicar correção,
alterar qualquer contrato, escrever no GitHub, deploy ou declaração de
release.

Se quiser, posso — ainda sem tocar no GitHub — transformar isto num pacote
em `entregas/`, preparar um modelo de registro para D01–D10, ou detalhar o
plano de correção de F918-01/F918-02 para o passo 4.
