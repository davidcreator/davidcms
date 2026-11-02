# F11-16 — Auditoria das 7 decisões herdadas: contratos congelados no tempo

**Data:** 2026-11-02 · **HEAD verificado:** `04ac4c579` (F11-15 aplicado) · **Nenhuma escrita no GitHub.**

---

## 1. Conclusão central

O F11-15 auditou 3 dos 10 `MARKER_OR_FINDING`. Esta frente audita as **7 restantes** (D01, D03, D05, D06, D08, D09, D10) e encontra um padrão que o F11-11 não tinha visto:

> **5 dos 10 marcadores (D01, D05, D06, D08, D09) não são defeitos do produto — são contratos ou gates de *incremento* congelados no tempo.** O código evoluiu por fases posteriores, documentadas no próprio repositório; os contratos `F9-*` permaneceram com o texto da fase original. O problema é de **versionamento de contrato**, não de implementação.

A evidência mais forte é o commit **`3d481ecf6`** ("*feat(bdre-editor): add personal saved block pattern library*"), que se autodeclara **F9-25**, adiciona `rms_cms_block_pattern` ao `dev/install/sql/schema.sql`, e é **exatamente o `base_head` registrado em `docs/F9-27-Status.json`**. Na sequência, as fases **F9-28 / F9-30 / F9-31 / F9-33** documentam explicitamente `sessionStorage`, histórico local, histórico persistente e comparação de revisões.

Método: leitura de ~40 arquivos do `main` (7 checkers, editores, controllers, templates, contratos F9, docs de fase) + API de commits do GitHub. **Inspeção estática**: sem PHP, Composer, PHPStan, Twig, browser, banco, staging ou deploy.

## 2. As 7 auditorias

### D01 — `e2e_bdre_hierarchical_reordering_static.cjs` → **A** (confirmado)

Dos 13 marcadores, **10 presentes**; os 3 ausentes são evolução de assinatura:

| Marcador exigido | Real |
| --- | --- |
| `function moveBlockAcrossHierarchy(document, nodeId, direction)` | `...(document, nodeId, direction, allowPageMedia)` (4º arg) |
| `!validateDocument(document).valid` | `const validation=validateDocument(document,!!allowPageMedia);if(!validation.valid)...` (`:237`, `:246`) |
| `!validateDocument(next).valid` | `const result=validateDocument(next,!!allowPageMedia); return result.valid?...` (`:264`, `:730`, `:884`) |

A validação **existe e continua fail-closed**, agora parametrizada por `allowPageMedia`. Não é perda de segurança. **A** — atualizar os 3 marcadores com revisão caso a caso.

### D03 — `check_audio_catalog.cjs` → **B** (mantido, natureza refinada)

O teste exige `/can_modify and can_use_audio_catalog/` em `dev/admin/view/template/cms/page_form.twig` **e** `/hasPermission\('modify', 'blog\/blog'\)/` em `dev/admin/controller/cms/page.php`. Evidência:

- **`can_use_audio_catalog` não ocorre em nenhum arquivo do repositório** — apenas no próprio checker. O template usa somente `{% if can_modify %}`.
- `cms/page.php:41` usa **`hasPermission('modify','cms/page')`**, não `'blog/blog'`. A ACL `'blog/blog'` está em `dev/admin/controller/blog/blog.php:46`.
- Essa separação é **coerente com a restrição explícita do `PROJECT-HANDOFF.md`**: "*keep Pages and Blog independent*". O checker parece ter sido escrito quando as rotas de mídia de Pages delegavam ao Blog.

**Não há evidência de falha de segurança** (a ACL `modify` permanece em ambos os contexts). Trata-se de um **marcador de contrato de fase desatualizado** (ACL dupla nunca implementada + rota de mídia posteriormente autonomizada). **B** — registrar a divergência e confirmar com o responsável; se a independência Pages/Blog for confirmada como definitiva, a decisão tende a **A** (atualizar os marcadores).

### D05 — `e2e_bdre_unsaved_changes_static.cjs` → **A** (confirmado)

O checker proíbe `fetch\s*\(|XMLHttpRequest|sendBeacon|setInterval` no init. Evidência:

- `blog-block-editor-init.js:233` → `fetch(url, {method:'POST', credentials:'same-origin', ...})` dentro de `post()` do painel de **histórico de revisões**, disparado por clique em `[data-bdre-history-load|restore|compare]` (`:220-222`).
- `'X-Requested-With': 'XMLHttpRequest'` é **apenas o valor de um header**, não `new XMLHttpRequest()` — **falso positivo por substring**.
- `sendBeacon` e `setInterval` **ausentes**; não há autosave. As fases **F9-31** e **F9-33** documentam o endpoint de listagem/restauração de revisões.

**A** — atualizar o marcador para reconhecer o endpoint de revisões e parar de casar a string do header.

### D06 — `e2e_f914.cjs` → **A** (mantido, evidência reforçada)

O contrato `F9-14-Contrato-Editor-MVP.json` declara `"no_network_or_database": true`, `"persistence": false`, `"form_submission_binding": false`; o editor contém `await fetch(url, options)`. O contrato descreve o **MVP (F9-14)**; as fases posteriores (F9-24 mídia, F9-25 padrões, F9-31 revisões) introduziram rede de forma documentada. **A** — atualizar o contrato de incremento com o registro das fases posteriores, **sem alterar o checker de segurança**.

### D08 — `e2e_f925_static.cjs` → **A condicionada** ⚠️ **retifica B do F11-11**

O contrato `F9-25` diz `patterns_persistence.status: "PROPOSAL_PENDING"`, `"user_choice": "Prepare design proposal; do not implement persistent storage in this increment"`, `"authorization": "…Request a dedicated ACL decision or explicit mapping from the owner before endpoints/storage"`, `"schema": "Not included; obtain install-agent review and migration/schema approval"`.

O código implementa `localStorage`/`sessionStorage`/`fetch`, e o `schema.sql:451` contém:

```sql
-- F9-25 — Biblioteca pessoal de padrões BDRE. Sem FKs: User::deleteUser faz a cascata manual.
CREATE TABLE IF NOT EXISTS `rms_cms_block_pattern` (...)
```

As fases **F9-28** (`sessionStorage` de rascunho), **F9-30** (histórico local), **F9-31** (histórico persistente) e **F9-33** (comparação de revisões) estão documentadas no repositório. Portanto **não é uma implementação clandestina**: é a evolução prevista, executada em incrementos posteriores, com o contrato F9-25 congelado no texto original.

> ⚠️ **Condição honesta:** encontrei evidência de **implementação**, mas **não encontrei o registro da autorização explícita do proprietário** que o próprio contrato F9-25 exige ("*explicit mapping from the owner*", "*migration/schema approval*"). Cabe ao responsável confirmar se as fases F9-28…F9-33 constituem essa autorização. Sem essa confirmação, a decisão correta permanece **B** (registrar achado de governança).

**Recomendação: A, condicionada** — atualizar o contrato **somente após** o responsável registrar que as fases posteriores são a autorização; caso contrário, **B**.

### D09 — `e2e_f905.cjs` → **A** ⚠️ **retifica B do F11-11**

O gate `assert(!fs.existsSync('dev/install/sql/schema.sql'), 'F9-05 não deve entregar schema.sql')` pertence à **F9-05**, fase *backend-only* cujo contrato (`F9-05-Contrato-Definicoes-Campos.json`, `status: implementation_partial_backend_model_acl`) **não menciona `schema.sql`**.

A API de commits mostra que o arquivo foi **adicionado em `3d481ecf6` (2026-10-29)**, pela biblioteca de padrões F9-25 — **fase muito posterior**. Não é violação do contrato F9-05: é um **gate histórico que passou a falhar quando outra fase adicionou um arquivo legítimo**. Há 27 referências a `schema.sql` em docs/tools, incluindo `schema-complete.sql` e runbooks de instalação.

**A** — aposentar/escopar o gate da F9-05 ao seu próprio incremento. Nenhum achado de segurança.

### D10 — `e2e_f927_static.cjs` → **B** (confirmado)

```
F9-27-Base-Hashes.json : head      = 448dd61f467e8ce2b4a0d5bce481345dace4ca77
F9-27-Status.json      : base_head = 3d481ecf65c124c17d61e1c5fa7ec6c031f1027b
```

`Base-Hashes.json` declara no campo `method` que "*identified F9-26*", enquanto `Status.json` aponta para o commit da biblioteca de padrões. É um **drift interno ao runbook F9-27**, com `status: BLOCKED_NOT_RUN` e `release_approved: false`. **B** — registrar; **não** atualizar o SHA sem autorização, pois alteraria os metadados do release gate.

## 3. Matriz final de recomendações (10/10 auditadas)

| ID | Recomendação | Natureza | Fonte |
| --- | --- | --- | --- |
| D01 | **A** | Assinatura `allowPageMedia` + validação equivalente | F11-16 |
| D02 | **A** | Slider/botões F9-71 | F11-15 |
| D03 | **B** | ACL dupla nunca implementada; sem falha de segurança comprovada | F11-16 |
| D04 | **A** | Variável `$threeSettings` com override intencional | F11-15 |
| D05 | **A** | Endpoint de revisões F9-31/33 + falso positivo de substring | F11-16 |
| D06 | **A** | Contrato MVP F9-14 congelado | F11-16 |
| D07 | **A** | `Twig\Markup` (F10-03); 🔒 **não reintroduzir `\|raw`** | F11-15 |
| D08 | **A condicionada** | Fases F9-28…33; exige confirmação da autorização | F11-16 |
| D09 | **A** | Gate F9-05 sobreposto por fase posterior | F11-16 |
| D10 | **B** | Drift de metadados do release gate | F11-16 |

**Balanço: 8 propostas A (1 condicionada) / 2 B.** Retificações em relação ao F11-11: **D08** (B → A condicionada), **D09** (B → A), **D03** (natureza refinada), **D06** (A reforçado com evidência de fases posteriores).

## 4. O que este pacote **não** faz

- **Nenhuma decisão foi tomada.** `tools/decision-matrix.json` segue com as 10 em `PENDING_DECISION`; os 11 contratos continuam congelados e `verify-contract-freeze.py` continua `exit 0`.
- **Nenhuma ferramenta foi duplicada.** A validação das referências usa o `tools/verify-evidence-refs.py` **já publicado no F11-15**, aplicado a uma cópia consolidada do JSON em `/tmp`. Não foi criado novo guard nem alterado qualquer arquivo publicado.
- Nenhum contrato, checker, arquivo PHP/JS/Twig, `schema.sql`, `release_approved` ou manifesto publicado foi alterado.
- Sem PHP/Twig/browser/banco/staging/deploy; **nenhuma escrita no GitHub**.

## 5. Artefatos e validações

`tools/contract-drift-audit.json` consolida as **10 decisões** com `evidence_refs`, `conclusion`, `proposed_choice`, `evidence_level: INVESTIGATED` e as `retifications` aplicadas. `docs/F11-16-Auditoria-Contratos-Congelados.md` (este), `F11-16-INDICE.md`, `CHECKSUMS-F11-16.txt` (16 entradas = 3 novos + 13 manifestos publicados).

| Verificação | Resultado |
| --- | --- |
| ~40 arquivos do `main` inspecionados + API de commits | OK |
| `python3 -m json.tool tools/contract-drift-audit.json` | 10 entradas, 7 auditadas nesta frente |
| `verify-evidence-refs.py` (guard **existente**) sobre JSON consolidado | **50/50 referências coerentes, `exit 0`** |
| A/B: trecho adulterado | **`exit 1`** |
| `python3 tools/verify-contract-freeze.py` | `exit 0` — 11 contratos congelados |
| `bash tools/repo-health.sh` | **6 PASS / 0 FAIL / 0 SKIP** |
| `bash tools/verify-checksums.sh` | **0 divergentes em 14 manifestos** |
| `unzip -t` / CRC / `sha256sum -c` (16 ZIPs) | OK |

## 6. Próximo passo (depende do responsável)

A infraestrutura está fechada (CI 7/7, health 6/6) e **todas as 10 decisões agora têm evidência**. Resta exclusivamente a decisão:

1. **Confirmar a autorização das fases F9-28…F9-33** — destrava **D08**; sem confirmação, D08 vira **B** e deve entrar no `F9-18-Risk-Register.json`.
2. Registrar, uma a uma, `status: DECIDED` + `choice` + `decided_by` + `date` em `tools/decision-matrix.json`.
3. Só então reemitir contratos: o `verify-contract-freeze.py` bloqueará qualquer alteração sem esse registro.

As 2 propostas **B** (D03, D10) e a **D08 não confirmada** são as que merecem atenção primeiro. Nada disso autoriza release, BDREditor (pausado em F9-103) ou F9-27 (`BLOCKED_NOT_RUN`).
