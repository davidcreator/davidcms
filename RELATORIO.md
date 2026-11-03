# Frente H — Agendamento e Rollout Declarativo

Série A→J: **A** (builder de condições), **B** (design tokens), **C** (prévia
rascunho × publicado), **D** (editor de cena), **E** (pacotes/child-theme),
**F** (section builder), **G** (biblioteca de componentes) e o **hotfix
`ScenePresetRegistry`** já estão aplicados no `main` deccff813; **esta é a H**.
Próxima: **I — mídia/modelos**. Um pacote instalável por frente.

## Recorte

Até a G, só havia dois estados: o tema **publicado** (o Catalog servia) e o
**rascunho** (só o Admin via). A H permite que o rascunho passe a valer para
uma **janela de tempo** e uma **parcela dos visitantes** — sem cron, sem
migration, sem coluna nova, sem escrita no banco, sem cookie novo e sem
promoção automática. **Ativar** continua um ato explícito.

## Entregue (18 arquivos, 3 novos)

- **`ThemeSchedule`** (novo): 5 campos allowlistados (`enabled`, `from`, `until`,
  `percent`, `scope`) e 7 motivos estáveis. `normalize()` é **fail-closed**
  (chave extra, aninhamento, data fora de `YYYY-MM-DDTHH:MM` UTC, `until`
  anterior a `from`, percentual fora de 0–100, público desconhecido ⇒ `null`).
  Datas são validadas por `DateTimeImmutable` + round-trip, **nunca por regex
  do usuário**. `bucket()` = `hexdec(substr(hash('sha256', seed), 0, 8)) % 100`:
  estável por visitante, determinístico, **nunca renderizado nem persistido**.
- **Decisão no Catalog**: `getEffectiveTheme()` (model, **1 SELECT** somente
  leitura) traz publicado + rascunho do tema ativo; `startup/theme.php` decide
  em cada requisição e resolve o `ThemeSnapshot` sobre o config escolhido,
  usando `getThemeMap('published')` ou `getThemeMap('draft')` conforme o caso.
  O publicado **permanece intacto**. Público `all` / `logged_in` / `anonymous`
  usa o `logged_in` do contexto allowlistado já existente (A).
- **`ThemeSnapshot`**: `schedule`, `schedule_effective`, `schedule_reason`,
  `schedule_percent`, `schedule_bucket`, `schedule_bars`, `schedule_css` e 5
  globais Twig **planos** (`theme_schedule_*`). `diff()` passa a incluir
  `schedule.percent`, `schedule.from`, etc. **`ThemeCompareView`** mostra a fita
  de agendamento nos dois painéis, com barra de rollout.
- **Catalog**: `common/header.php` acrescenta a classe discreta
  `rms-theme-rollout` e concatena `--rms-sched-*` ao `theme_token_css` já
  existente no `style` do `<body>` (B + G). **Nenhum `<style>`, `<script>` ou
  `|raw` novo**; `header.twig`, `twig.php`, `home.twig`, `PageLayout`,
  `cms/slot.php`, engines e runtime WebGL intactos.
- **Pacotes E**: `schedule` em `CONFIG_KEYS`, `spec`, `validateConfig`
  (**`schedule_invalid`**), checksum SHA-256 e `decode()`. Na herança é
  **substituído integralmente** (como `conditions`): filho sem `schedule`
  herda a regra do pai por inteiro; filho com `schedule` substitui por inteiro.
- **Admin**: card com checkbox, início/fim (`datetime-local`), percentual,
  público e uma **sonda determinística** (data/hora, identificador de teste,
  visitante autenticado). O botão **Simular** reusa a prévia C via
  `formaction` + `formtarget="theme-preview"` — **sem JavaScript e sem
  `fetch`**. `buildDraftConfig()` valida; `import` recusa JSON inválido.
  **33 novas chaves de UI ×3 idiomas** (283 → 316).
- **`theme-schedule.js`** (novo): DOM puro (habilita campos, mostra o
  percentual), sem `fetch`/`eval`/`innerHTML`/`document.write`. Guia
  `docs/GUIA-AGENDAMENTO-ROLLOUT.md`.

## Compatibilidade

Config sem `schedule` ⇒ comportamento **idêntico à G**: `effective=published`,
variáveis `--rms-sched-*` neutras, globais planos nos defaults e **nenhuma
consulta extra** relevante (o model já fazia 1 SELECT; continua 1 SELECT).
Model sem `getEffectiveTheme` cai para `getPublishedTheme()`. Os 6 presets D,
as 12 seções F e os 10 componentes G seguem intactos. Validação com o
**`Autoloader` real** confirma `ThemeSchedule` → `theme_schedule.php`.

## Validação executada

| Verificação | Resultado |
|---|---|
| `php -l` PHP 8.5 real nos 12 arquivos PHP | 12 OK / 0 FAIL |
| `node --check` (6 JS: A, D, E, F, G e o novo) | 6 OK |
| **Autoloader real** (todas as classes do `framework.php`) | 53 PASS / 0 FAIL |
| `ThemeSchedule` + pacote/checksum + snapshot + prévia | 135 PASS / 0 FAIL |
| Startup Catalog: publicado × rascunho, escopo, janela, zero escrita | 69 PASS / 0 FAIL |
| Controller público `common/home` (regressão F/G) | 23 PASS / 0 FAIL |
| Controller Admin: edit/save/export/import/compare/sonda | 173 PASS / 0 FAIL |
| Render Twig 3.14 + view (card, sonda, escape, fita) | 123 PASS / 0 FAIL |
| **jsdom 29**: toggle, percentual, degradação sem JS, regressão A–G | 100 PASS / 0 FAIL |
| `verify-checksums` (A e B) | 14 manifestos / 142 arquivos / 0 divergentes |
| `repo-health` (A e B) | 6 PASS / 0 FAIL / 0 SKIP |
| `verify-ci-workflow` | 31 PASS / 0 FAIL |
| `verify-contract-freeze` | exit 0 |

**Total: 676 asserções, 0 falhas.** 16 arquivos congelados
conferidos por hash antes/depois (inclui `schema.sql`, `home.twig`, `twig.php`,
`autoloader.php`, `component_registry.php`, `theme-component-library.js`,
`scene_preset_registry.php`, `PageLayout`, `cms/slot.php`, `ConditionEngine`,
`TokenRegistry`). Os 14 `CHECKSUMS*.txt` permanecem **idênticos** e não
foram reemitidos. Nenhum contrato congelado, `release_approved`, workflow,
checker, `custom_js` do BDREditor ou runtime WebGL foi modificado. Nenhuma
escrita remota. F9-27 segue `BLOCKED_NOT_RUN`; BDREditor pausado; P2 do F9-18
não reaberto.

## Limitações

PHP 8.5 via WASM + Twig 3.14 + jsdom, com mocks de DB/sessão/response. **Não**
houve CMS completo, MySQL real, sessão Admin autenticada, browser com rede,
cron, WebGL, E2E, migration, staging, deploy ou escrita no GitHub. A decisão é
**por requisição**: não existe tarefa agendada que promova o rascunho; se o
servidor reiniciar, a regra continua a mesma enquanto o JSON não mudar.
**CI verde e health verde ≠ release aprovada.**

Aplicar: substituir pelos arquivos completos (os 3 novos entram nos
respectivos diretórios) e rodar
`bash tools/verify-checksums.sh && bash tools/repo-health.sh`.
