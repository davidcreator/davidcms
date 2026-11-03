# Frente G — Biblioteca de Componentes Declarativos

Série A→J: **A** (builder de condições), **B** (design tokens), **C** (prévia
rascunho × publicado), **D** (editor de cena), **E** (pacotes/child-theme),
**F** (section builder) e o **hotfix `ScenePresetRegistry`** já estão aplicados
no `main` 83cbd4fa5; **esta é a G**. Próxima: **H — agendamento/rollout**.
Um pacote instalável por frente.

## Recorte

Os 25 templates Twig do tema aceitam HTML autorizado, mas o Admin não oferecia
nenhum bloco reutilizável: cada chamada, cartão ou aviso exigia escrever markup
à mão. A G cria uma **biblioteca de 10 componentes declarativos**, allowlistados
no servidor, com variantes e tons por enum, miniatura SVG determinística e
snippet Twig fixo — insertável com um clique, versionável no pacote E e visível
na prévia C. **Sem migration, sem coluna nova, sem upload e sem HTML livre.**

## Entregue (17 arquivos, 3 novos)

- **`ComponentRegistry`** (novo): 10 IDs — `button`, `badge`, `card`, `callout`,
  `stat`, `quote`, `divider`, `feature`, `cta`, `notice` — com grupos (ação,
  exibição, feedback), 2–3 variantes e tons permitidos por componente.
  `normalize()` é **fail-closed** (ID/enum desconhecido, `enabled` de tipo
  errado, aninhado, lista maior que 10 → `null`). `css()` emite apenas
  variáveis `--rms-cmp-*`; `globals()` publica três mapas **planos** aceitos
  pelo `twig.php` (`theme_components`, `theme_component_tones`,
  `theme_component_enabled`); `svg()`/`dataUri()` geram miniaturas
  determinísticas, sem texto do usuário.
- **Snippets Twig fixos** (10): blocos `{% if theme_component_enabled.X %}` com
  texto de exemplo e classes `rms-cmp-*`. Nenhum `|raw`, `<script>`, handler
  `on*`, `javascript:`, `srcdoc`, `formaction`, `autoescape` ou tag perigosa.
  Inseridos via `textarea.value` — o escape padrão do Twig continua valendo.
- **Chave `components` no config do tema** (JSON, sem coluna nova), integrada ao
  `ThemePackage` (`CONFIG_KEYS`, `MAP_KEYS`, `sanitizeConfig`,
  `validateConfig` → **`components_invalid`**, `spec`, **checksum SHA-256**,
  `decode()` e `resolveChain`). Herança E é **por ID**: o filho sobrescreve só
  o componente que declarar e mantém os demais do pai; `[]` desliga todos.
- **`ThemeSnapshot`**: `components`, `component_css`, `component_enabled` e os
  globais; `ThemeCompareView` mostra a **fita de componentes** nos dois painéis
  (ou "Nenhum componente ativo") e o diff (`components.button.variant`).
- **Catalog**: `startup/theme` publica `catalog_theme_components`;
  `common/header.php` concatena `component_css` a `theme_token_css` no `style`
  do `<body>` (**nenhum `<style>`/`|raw` novo**); `twig.php` recebe os 3 mapas
  planos via `setThemeGlobals()`. **`twig.php`, `header.twig`, `home.twig`,
  `PageLayout`, `cms/slot.php`, models, `schema.sql` e o runtime WebGL ficam
  intactos.**
- **Admin**: card "Biblioteca de componentes" com galeria de 10 miniaturas,
  checkbox de habilitação, `<select>` de variante/tom, botão "Inserir" e
  seletor do template de destino. Endpoint **`componentThumb`** (GET, `access`,
  `no-store`, CSP, fail-closed para ID/enum inválido). `edit()` publica as
  linhas e os snippets; `buildDraftConfig()` valida e grava. **48 novas chaves
  de UI ×3 idiomas** (235 → 283); estilos no CSS existente.
- **`theme-component-library.js`** (novo): DOM puro (`getAttribute`,
  `setAttribute`, `textContent`, `addEventListener`), sem
  `fetch`/`eval`/`innerHTML`/`document.write`/`insertAdjacentHTML`. Sem
  JavaScript, as miniaturas já vêm renderizadas e o formulário envia tudo.
- Guia `docs/GUIA-BIBLIOTECA-COMPONENTES.md`.

## Compatibilidade

Formulários e pacotes sem `components` preservam **exatamente** o
comportamento da F (a chave não é gravada); `component_css` fica vazio, os 3
globais são mapas vazios e o CSS dos 22 tokens B continua idêntico. Os 10
componentes abrem **desabilitados** num tema legado. Validação com o
**`Autoloader` real** confirma que `ComponentRegistry` resolve para
`component_registry.php` — a causa do fatal do hotfix não se repete. Os 6
presets D e as 12 seções F seguem intactos.

## Validação executada

| Verificação | Resultado |
|---|---|
| `php -l` PHP 8.5 real nos 11 arquivos PHP | 11 OK / 0 FAIL |
| `node --check` (5 JS: A, D, E, F e o novo) | 5 OK |
| **Autoloader real** (todas as classes do `framework.php`) | 43 PASS / 0 FAIL |
| `ComponentRegistry` + pacote/checksum + snapshot + prévia | 118 PASS / 0 FAIL |
| Núcleo: startup, paridade, globais, mapas dos models | 48 PASS / 0 FAIL |
| Controller público `common/home` (regressão F) | 23 PASS / 0 FAIL |
| Controller Admin: edit/save/export/import/compare/thumb | 139 PASS / 0 FAIL |
| Render Twig 3.14 + view (card, galeria, escape, fita) | 95 PASS / 0 FAIL |
| **jsdom 29**: insert, thumb, enums, fail-closed, sem JS | 82 PASS / 0 FAIL |
| `verify-checksums` (A e B) | 14 manifestos / 142 arquivos / 0 divergentes |
| `repo-health` (A e B) | 6 PASS / 0 FAIL / 0 SKIP |
| `verify-ci-workflow` | 31 PASS / 0 FAIL |
| `verify-contract-freeze` | exit 0 |

**Total: 548 asserções, 0 falhas.** 14 arquivos congelados
conferidos por hash antes/depois (inclui `schema.sql`, `home.twig`, `twig.php`,
`autoloader.php`, `scene_preset_registry.php`, `PageLayout`, `cms/slot.php`,
`ConditionEngine`, `TokenRegistry`). Os 14 `CHECKSUMS*.txt` permanecem
**idênticos** e não foram reemitidos. Nenhum contrato congelado,
`release_approved`, workflow, checker, `custom_js` do BDREditor ou runtime
WebGL foi modificado. Nenhuma escrita remota. F9-27 segue `BLOCKED_NOT_RUN`;
BDREditor pausado; P2 do F9-18 não reaberto.

## Limitações

PHP 8.5 via WASM + Twig 3.14 + jsdom, com mocks de DB/sessão/response. **Não**
houve CMS completo, MySQL real, sessão Admin autenticada, browser com rede,
WebGL, E2E, migration, staging, deploy ou escrita no GitHub. As miniaturas são
SVG esquemático determinístico, não frames renderizados. **CI verde e health
verde ≠ release aprovada.**

Aplicar: substituir pelos arquivos completos (os 3 novos entram nos
respectivos diretórios) e rodar
`bash tools/verify-checksums.sh && bash tools/repo-health.sh`.
