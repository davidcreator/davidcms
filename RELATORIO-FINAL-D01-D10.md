# RELATORIO-FINAL-D01-D10 — Registro das 10 decisões de governança (F11-11)

**Data:** 2026-11-17 · **Decidido por:** David L. Almeida (responsável) · **Base:** HEAD `5a6cb33` (pós-KZ-24)
**Pacote:** `ReamurCMS-Frente-D01-D10-Decisoes-Registradas.zip` · **Não é release**

## Resultado: 10/10 DECIDED (0 pendentes)

| ID | Decisão | Ação executada |
| --- | --- | --- |
| D01 | **A** | Checker `e2e_bdre_hierarchical_reordering_static.cjs` atualizado: `moveBlockAcrossHierarchy` e `validateDocument` aceitam `allowPageMedia` opcional (default preserva comportamento antigo). **27 PASS** |
| D02 | **A** | Checker `e2e_bdre_spacer_static.cjs` atualizado: inspector usa `spacerInspectorCopy()` (pt/en/es) + escala fixa `[16,32,48,64,96]` — equivalente nos 3 idiomas oficiais. **36 PASS** |
| D03 | **B** | Achado **F918-05 (P2)** no F9-18-Risk-Register.json: `can_use_audio_catalog` ausente no Twig (só `can_modify`) — possível relaxamento de ACL; checker não relaxado |
| D04 | **A** | Checker `check_threejs_native_admin.cjs` atualizado: `not_found.php` usa `$threeSettings = getSettings()` + override intencional → `runtime($threeSettings)`; assinatura `runtime(array)` idêntica nos 2 controllers |
| D05 | **A** | Contrato `F9-25` (unsaved_changes) reconhece fetch real de histórico de revisões (por clique, **não autosave**; proibição mantida) |
| D06 | **A** | Contrato `F9-14` atualizado: `no_network_or_database` agora false, com nota de evolução; **checker de segurança intacto** |
| D07 | **B** | Achado **F918-06 (P1)**: `blog_info.twig` imprime `post.block_html`/`post.description` sem `\|raw` — se não forem `Twig\Markup`, conteúdo sai escapado no blog público |
| D08 | **B** | Achado **F918-07 (P2)**: localStorage/fetch/XHR reais vs F9-25 `PROPOSAL_PENDING` — contrato segue pendente até aprovação explícita |
| D09 | **B** | Achado **F918-08 (P2)**: `dev/install/sql/schema.sql` (22.542 bytes) existe e o checker histórico proíbe |
| D10 | **B** | Achado **F918-09 (P2)**: drift de metadados (`base_head 3d481ecf` ≠ HEAD `5a6cb33`); **SHA não atualizado** sem autorização |

## Arquivos neste pacote
`tools/decision-matrix.json` (10 DECIDED, com decided_by/data/evidence_ref) · `docs/F9-18-Risk-Register.json` (+5 findings, total 9) · 3 checkers atualizados (D01/D02/D04) · 2 contratos atualizados (D05/D06) · `Pauta-Decisoes-D01-D10.md` (tabela de resultado)

## Validação
- `e2e_bdre_hierarchical_reordering_static.cjs` → 27 PASS · `e2e_bdre_spacer_static.cjs` → 36 PASS
- JSONs validados (parse OK); política respeitada: nenhuma decisão por inferência; nenhum hash reescrito; checkers de segurança intactos.

## Limites
- D07 (P1) e D03 (ACL) permanecem **abertos** no risk register até correção/verificação pelo responsável.
- F9-27 segue `BLOCKED_NOT_RUN`; este pacote não declara release.
