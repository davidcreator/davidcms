# Pauta de decisões D01–D10

**Para:** responsável pelas decisões · **Data:** 2026-11-06 · **Fonte:** `tools/decision-matrix.json` (HEAD `1b7724a1…`)

> `status_counts`: **10 `PENDING_DECISION` / 0 `DECIDED`**.
> `policy` (literal): *"Nenhuma decisao tomada por inferencia. Contratos congelados ate registro explicito de status=DECIDED."*
> **O agente não decide.** Este documento é só a pauta; o formulário preenchível é o pacote **AR**.

## Regras rápidas

- `A` = atualizar o contrato da fase · `B` = registrar achado P1/P2 no `F9-18-Risk-Register.json`.
- Uma decisão `A` **não** autoriza alterar o checker de segurança nem reescrever hashes/`base_head`.
- `INVESTIGAR` = ler o código e comprovar equivalência **antes** de escolher A ou B.
- Preencher `decision`, `decided_by`, `decision_date`, `evidence_ref`; só então `status: DECIDED`.
- Nunca converter `PENDING`/`SKIP`/`NOT_RUN`/`STATIC_ONLY`/`BLOCKED_NOT_RUN` em `PASS`.

## Seguranca / ACL / governanca

### D03 — `check_audio_catalog.cjs` · **recomendação: B**

- **natureza:** `POSSIVEL_MUDANCA_ACL`
- **evidência:** Rotas/IDs presentes; can_modify and can_use_audio_catalog ausente no Twig
- **ação recomendada:** Seguranca/ACL: registrar como achado, nao relaxar o checker

### D07 — `e2e_f915.cjs` · **recomendação: B**

- **natureza:** `SEGURANCA_OU_REGRESSAO`
- **evidência:** Twig usa post.block_html / post.description sem |raw
- **ação recomendada:** Se nao for Twig\Markup, e regressao funcional: registrar como P1

### D08 — `e2e_f925_static.cjs` · **recomendação: B**

- **natureza:** `DIVERGENCIA_REAL_CONTRATO`
- **evidência:** localStorage/sessionStorage/fetch/XMLHttpRequest reais enquanto o contrato diz PROPOSAL_PENDING
- **ação recomendada:** Mais sensivel: exige confirmacao de aprovacao das features antes de qualquer atualizacao de contrato

### D10 — `e2e_f927_static.cjs` · **recomendação: B**

- **natureza:** `DRIFT_METADADOS`
- **evidência:** hashes.head=448dd61f... != status.base_head=3d481ecf...
- **ação recomendada:** NAO atualizar o SHA sem autorizacao: equivaleria a declarar prontidao do gate


## Contratos historicos

### D05 — `e2e_bdre_unsaved_changes_static.cjs` · **recomendação: A**

- **natureza:** `CONTRATO_HISTORICO`
- **evidência:** fetch(...) real no init para historico de revisoes, disparado por clique; nao e autosave
- **ação recomendada:** Atualizar o contrato da fase reconhecendo a feature, mantendo a proibicao de autosave

### D06 — `e2e_f914.cjs` · **recomendação: A**

- **natureza:** `CONTRATO_HISTORICO`
- **evidência:** await fetch(url, options) no editor (L3243); contrato F9-14 (MVP) proibia rede
- **ação recomendada:** Atualizar F9-14 para refletir a evolucao, SEM alterar o checker de seguranca

### D09 — `e2e_f905.cjs` · **recomendação: B**

- **natureza:** `ACHADO_REAL_GOVERNANCA`
- **evidência:** dev/install/sql/schema.sql existe (22.495 bytes) e o checker o proibe
- **ação recomendada:** Decidir se o arquivo e legitimo de outra fase e se o checker historico deve ser aposentado


## Marcadores / equivalencia nao comprovada

### D01 — `e2e_bdre_hierarchical_reordering_static.cjs` · **recomendação: A**

- **natureza:** `CADEIA_MARCADORES`
- **evidência:** moveBlockAcrossHierarchy ganhou allowPageMedia; outros marcadores (validateDocument, indent.disabled) tambem divergem
- **ação recomendada:** Revisar e atualizar os marcadores um a um, com evidencia de equivalencia

### D02 — `e2e_bdre_spacer_static.cjs` · **recomendação: INVESTIGAR**

- **natureza:** `EQUIVALENTE_NAO_COMPROVADO`
- **evidência:** spacer:{heightLabel,heights} existe nos defaults, mas nao Object.entries(this.strings.spacer.heights)
- **ação recomendada:** Pode ser regressao real do inspector; leer o codigo antes de decidir

### D04 — `check_threejs_native_admin.cjs` · **recomendação: INVESTIGAR**

- **natureza:** `EQUIVALENTE_NAO_COMPROVADO`
- **evidência:** three_scene_config->runtime( presente nos 2 controllers, assinatura divergente
- **ação recomendada:** Confirmar se a assinatura atual e equivalente antes de atualizar o marcador


## Ordem de votação sugerida

1. D03 → D07 → D08 → D10 (não relaxar checker, não atualizar SHA sem autorização).
2. D05 → D06 → D09 (atualizar descrição com evidência de equivalência; D06 **sem** mexer no checker de segurança).
3. D01 → D02 → D04 (investigar código; D02/D04 podem ser regressão real).

## Depois de decidir

- Modelo preenchível: `docs/Modelo-Registro-Decisoes-D01-D10.json` (pacote **AR**).
- Verificador: `node tools/verify-modelo-decisoes.cjs <raiz>` — continua válido após o preenchimento.
- A transcrição para `tools/decision-matrix.json` é um **ato separado e explícito** do responsável.
- Decisões vedadas ao agente; nenhuma foi tomada por inferência nesta pauta.

## Limites

Ci verde (7/7), health local, PROBE e Chromium headless **não significam release aprovada**.
`release_approved: false` · F9-27 `BLOCKED_NOT_RUN` · F9-18 `release_ready: false` · F9-25 `PROPOSAL_PENDING`.
Sem escrita do agente no GitHub.
