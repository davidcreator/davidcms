# Extrato de contratos — HEAD `179269fe…`

Leitura direta de `raw.githubusercontent.com/davidcreator/davidcms/179269fe…`,
sem cache. Nenhum arquivo foi alterado. Os trechos abaixo são **transcrições**,
não interpretações.

## HEAD e árvore

| Campo | Valor |
|---|---|
| commit | `179269fea36584076f9ef41b95ae94638dd08f45` |
| mensagem | `fix(bdre-editor): limit dark theme to editor` |
| pai | `9c809050ff908b66b6f3bf97c1fc5a1aed9b3160` (AO) |
| tree | 3.218 entradas / 2.635 blobs / `truncated: false` |
| diff vs AO | +9 / −0 / ~6 (6/6 substituídos byte a byte idênticos ao pacote AP) |

## `tools/decision-matrix.json`

```
status_counts : {"PENDING_DECISION": 10, "DECIDED": 0}
policy        : "Nenhuma decisao tomada por inferencia. Contratos congelados
                ate registro explicito de status=DECIDED."
ci_status     : "7/7 success (runs 41483802295 / 41504039830); docs-links
                0.13 min com --offline desde F11-12"
```

**Ressalva:** os runs citados são **anteriores** ao commit `179269fe…`. O
`ci_status` é um registro estático, não o resultado do CI do HEAD atual.

Decisões (todas `PENDING_DECISION`): D01…D10, com `checker`, `nature`,
`evidence`, `options` (A: atualizar contrato da fase / B: registrar achado
P1/P2 no F9-18), `recommendation`, `recommended_action`,
`frozen_artifacts`, `decided_by: null`, `decision_date: null`.

## `docs/F9-27-Status.json`

```
status                    : BLOCKED_NOT_RUN
reason                    : runtime blocked — explicit staging authorization
                            absent and F9-12 status unknown
f9_12_status              : UNKNOWN_NOT_CONFIRMED
database_accessed         : false
migration_applied         : false
runtime_tests_executed    : false
release_approved          : false
github_write_or_deploy    : false
matrix                    : 12 casos → 10 NOT_RUN + 2 BLOCKED_F9_12_UNKNOWN
required_before_execution  : 7 itens (autorizacao, staging descartavel,
                            isolamento, confirmacao F9-12, rollback/dados
                            sinteticos, escopo das familias runtime, contas
                            de teste para pattern library)
```

## `docs/F9-18-Risk-Register.json`

```
head           : 72a8b927c0d2b96b7d3df41114969aa620bb9602  (ANTIGO)
release_ready  : false
execution      : php/sql/browser/application/database/migration/github = false
findings       : F918-01 P1 open · F918-02 P1 open
                 F918-03 P2 open · F918-04 P2 open
```

Os paths/linhas citados nos achados referem-se ao head antigo. Ver o pacote
**AS** para a leitura do código atual.

## `docs/F9-25-Contrato-Editor-Editorial.json`

```
editor_document_version             : 2
patterns_persistence.status         : PROPOSAL_PENDING
patterns_persistence.authorization  : ACL dedicada; nao inferir do ACL de Blog
patterns_persistence.schema         : nao incluido; exige aprovacao de migration
catalog_context_preview.status      : DESIGN_GATE_PENDING
```

## `docs/F9-26-Contrato-A11y-Performance.json`

```
focus                       : #174ea6 / 3px / offset 2px
localization.locales        : pt-br, en-us, es-eu
verification = true         : node_syntax_checks, static_checker, json_parse
verification = false        : php_sql_browser_app_e2e_runtime,
                              screen_reader_or_axe,
                              measured_browser_performance,
                              wcag_conformance_claimed
not_in_scope                : virtualization, autosave, revisions,
                              database changes, runtime integration tests
```

## `docs/F9-14-Contrato-Editor-MVP.json`

```
status              : standalone_in_memory_component_prepared_not_integrated
component.api       : ReamurBlogBlockEditor.mount(root, {document, onChange})
no_network_or_database : true
form_submission_binding : false
persistence / html_rendering : false
```

Contrato histórico do MVP; tratado pelas decisões D06 e D09.

## `.github/workflows/php.yml`

6 jobs: `composer`, `php-lint`, `docs-links`, `static-analysis`, `node-deps`,
`repo-guards`. O `repo-guards` invoca `tools/repo-health.sh`, orquestrador
*read-only* de 6 guardas (`verify-deps-layout`, `verify-checksums`,
`verify-ci-workflow`, `verify-f11-01`, `verify-contract-freeze`,
`release-readiness-report`); `SKIP` não conta como `PASS`.

## Onde `__kazHealth` existe

Varredura de **464 JS/PHP/Twig** + **460 `.md`/`.json`/`.txt`** do HEAD:
**0 ocorrências em código**; 11 ocorrências em documentos (relatórios das
frentes e a análise pós-limpeza). Conclusão: `copy(__kazHealth())` é uma
formulação de relatório, não uma função instalada. O pacote **AQ** entrega
`tools/health-admin-ap.js` como substituto observacional.

## Limites

Leitura de arquivos públicos via `raw`, sem autenticação, sem PHP nativo,
sem MySQL, sem sessão Admin, sem staging e sem CI remoto verificado. Nada
aqui é `PASS` de runtime nem **significa release aprovada**.
