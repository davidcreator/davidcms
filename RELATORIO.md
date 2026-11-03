# Frente F — Section Builder

Série A→J: **A** (builder de condições), **B** (design tokens), **C** (prévia
rascunho × publicado), **D** (editor de cena) e **E** (pacotes/child-theme) já
estão aplicadas no `main` 4835384bf; **esta é a F**. Próxima: **G — biblioteca de
componentes**. Um pacote instalável por frente.

## Recorte

A home pública já era composta por uma sequência de parciais Twig, mas a **ordem** vinha
somente da página CMS (`cms/slot.sections`). A F passa a permitir que o **tema**
declare quais seções aparecem e em que ordem — e faz isso **sem criar uma
segunda allowlist, sem migration e sem tocar no template público**.

## Entregue (17 arquivos)

- **`SectionRegistry`** (novo): delega os 12 identificadores e a validação de
  ordem ao contrato existente `PageLayout` (`options('home')` /
  `normalizeSections`). Guarda só metadados: rótulo, grupo, partial, tipo e
  cor/altura da fita de prévia. `normalize()` é fail-closed (duplicata, id
  desconhecido, lista maior que a allowlist, tipo errado → `null`).
- **Chave `sections` no config do tema** (JSON, sem coluna nova). Três estados:
  **ausente** = layout legado do CMS (idêntico às frentes A–E); **`[]`** =
  override explícito com nenhuma seção; **lista** = exatamente aquela ordem.
  Integrada ao `ThemePackage` (`CONFIG_KEYS`, sanitize, `validateConfig`,
  `spec`, **checksum SHA-256**, `resolveChain`) e ao `ThemeSnapshot`
  (`sections`, `sections_defined`, `section_bars`, globais Twig).
- **Child-theme**: lista ordenada é **substituída** na cadeia pai → filho
  (como `conditions`); filho sem `sections` herda a do pai; `[]` desliga todas.
- **Catalog**: `startup/theme` publica `catalog_theme_sections`;
  `common/home` aplica `SectionRegistry::plan()` quando há override e recalcula
  `has_3d` sobre a lista efetiva — WebGL só liga se `hero`/`portfolio`/`tools`
  estiverem ativos. **`common/home.twig`, `PageLayout`, `cms/slot.php`, models,
  `schema.sql` e runtime Three.js permanecem intactos.**
- **Admin**: card "Seções da home" com 12 linhas (checkbox, rótulo, grupo,
  tipo, partial, ↑/↓) e `sections_order` (CSV). `edit()` publica os dados;
  `buildDraftConfig()` valida e grava; `compare()` mostra a **fita de seções**
  nos dois painéis e o diff (`sections.0`, …); `export`/`import` transportam a
  chave. **32 novas chaves de UI ×3 idiomas**; estilos no CSS existente.
- **`theme-section-builder.js`** (novo): só DOM (`insertBefore`, `classList`,
  `textContent`), sem `fetch`/`eval`/`innerHTML`/`document.write`/upload. Sem
  JavaScript o formulário já envia a ordem renderizada no servidor.
- Guia `docs/GUIA-SECTION-BUILDER.md`.

## Compatibilidade

Formulários e pacotes sem os campos F preservam exatamente o comportamento
anterior (a chave não é gravada). Um tema legado abre o builder com **todas as
12 seções marcadas** e o aviso "Ainda usando a estrutura definida pelo CMS"; ao
salvar, o override passa a valer — mudança nunca silenciosa.

## Validação executada

| Verificação | Resultado |
|---|---|
| `php -l` PHP 8.5 real nos 11 arquivos PHP | 11 OK / 0 FAIL |
| `node --check` (4 JS: A, D, E e o novo) | 4 OK |
| `SectionRegistry` + pacote/checksum + snapshot + prévia | 81 PASS / 0 FAIL |
| Núcleo: startup, paridade, mapas dos models (regressão E) | 41 PASS / 0 FAIL |
| **Controller público `common/home`** com mocks (override × legado × WebGL) | 23 PASS / 0 FAIL |
| Controller Admin: edit/save/export/import/compare + regressão A–E | 100 PASS / 0 FAIL |
| Render Twig 3.14 + view (card, fita, escape) | 64 PASS / 0 FAIL |
| **jsdom 29**: ↑/↓, hidden, contador, submit, degradação sem JS | 58 PASS / 0 FAIL |
| `verify-checksums` (A e B) | 14 manifestos / 142 arquivos / 0 divergentes |
| `repo-health` (A e B) | 6 PASS / 0 FAIL / 0 SKIP |
| `verify-ci-workflow` | 31 PASS / 0 FAIL |
| `verify-contract-freeze` | exit 0 |

**Total: 367 asserções, 0 falhas.** 8 arquivos congelados conferidos por hash
antes/depois (inclui `schema.sql`, `home.twig`, `PageLayout`, `cms/slot.php`,
`ConditionEngine`, `TokenRegistry`, `SceneConfig`). Os 14 `CHECKSUMS*.txt`
permanecem **idênticos** e não foram reemitidos. Nenhum contrato congelado,
`release_approved`, workflow, checker ou runtime WebGL foi modificado. Nenhuma
escrita remota. F9-27 segue `BLOCKED_NOT_RUN`; BDREditor pausado; P2 do F9-18
não reaberto.

## Limitações

PHP 8.5 via WASM + Twig 3.14 + jsdom, com mocks de DB/sessão/response. **Não**
houve CMS completo, MySQL real, sessão Admin autenticada, browser com rede,
WebGL, E2E, migration, staging, deploy ou escrita no GitHub. A fita de seções é
um mapa esquemático, não um frame renderizado da home. **CI verde e health
verde ≠ release aprovada.**

Aplicar: substituir pelos arquivos completos e rodar
`bash tools/verify-checksums.sh && bash tools/repo-health.sh`.
