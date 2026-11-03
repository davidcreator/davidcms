# Frente I — Mídia Vetorial Declarativa e Modelos de Página

Série A→J: **A** (builder de condições), **B** (design tokens), **C** (prévia
rascunho × publicado), **D** (editor de cena), **E** (pacotes/child-theme),
**F** (section builder), **G** (biblioteca de componentes), o **hotfix
`ScenePresetRegistry`** e **H** (agendamento/rollout) já estão aplicados no
`main` 92d0782c0; **esta é a I**. Próxima: **J — multisite**. Um pacote
instalável por frente.

## Recorte

Até a H, o tema só compunha cores, seções, componentes e regras de exibição.
A I acrescenta **12 mídias vetoriais determinísticas** e **6 modelos de página**
constantes — sem upload, sem arquivos novos no disco, sem URL externa, sem
GLB, sem cena Three.js/WebGL, sem `custom_js`, sem `<script>` inline, sem
`|raw` novo, sem `fetch`/`eval`/`innerHTML` no JavaScript, sem tabela, sem
coluna e sem migration. **Não é** a biblioteca de mídia do Blog (ADR 0049 /
F8-05), que continua intacta e fora deste escopo.

- **`ThemeMedia`** (novo): catálogo allowlistado de 12 IDs (`hero-aurora`,
  `hero-grid`, `cover-desk`, `cover-code`, `portrait-a`, `portrait-b`,
  `icon-spark`, `icon-shield`, `pattern-dots`, `pattern-waves`, `logo-mark`,
  `thumb-project`), variantes e tons por ID (enum), proporção, grupo e um
  **snippet Twig fixo** por mídia. `normalize()` é **fail-closed** (ID
  desconhecido, variante/tom fora do enum, aninhamento, chave não escalar ou
  mais de 12 entradas ⇒ `null`). `svg()` gera SVG determinístico a partir de
  constantes; `dataUri()` devolve `data:image/svg+xml;base64,...`.
- **Onde a mídia aparece**: `Twig::setThemeGlobals()` descarta strings com
  mais de **256 bytes**, então **nenhuma data URI vira global Twig**. Os
  globais são 4 mapas **planos e curtos** (`theme_media`, `theme_media_tones`,
  `theme_media_ratios`, `theme_media_enabled`); o CSS `--rms-media-*` é
  concatenado ao `theme_token_css` no atributo `style` do `<body>` por
  `startup/theme.php` → `catalog_theme_media_css` → `common/header.php`,
  reaproveitando B, G e H. O rollout H continua decidindo publicado ×
  rascunho; a mídia segue o config efetivo escolhido.
- **Pacotes E**: `media` entra em `CONFIG_KEYS`, `spec()`, `validateConfig()`
  (**`media_invalid`**), `sanitizeConfig()`, checksum SHA-256 e `decode()`. Na
  herança é **mesclado por ID** (como `components`), preservando as entradas
  do pai que o filho não citar.
- **`ThemeSnapshot`**: `media`, `media_enabled`, `media_css`, `media_bars` e
  os 4 mapas planos; `diff()` inclui `media.<id>.variant` etc.
  **`ThemeCompareView::mediaStrip()`** desenha a fita de mídias nos dois
  painéis da prévia C, com ID, variante e proporção.
- **6 modelos** (`landing`, `portfolio`, `article`, `catalog`, `contact`,
  `blank`): apenas constantes allowlistadas — seções F, componentes G, mídias I
  e o `textarea` de `templates[common/home]`. **Não entram no config, não
  gravam sozinhos e não têm endpoint no servidor**: o card do Admin exige um
  **checkbox de confirmação**, e `theme-media.js` preenche o formulário em
  DOM puro; só o botão **Salvar rascunho** persiste.
- **Admin**: galeria com 12 miniaturas SVG, checkbox, variante, tom, seletor
  de template e botão **Inserir trecho**; `mediaThumb()` (GET, ACL, CSP
  `default-src 'none'`, `no-store`, fail-closed). **66 novas chaves de UI ×3
  idiomas** (316 → 382). Guia `docs/GUIA-MIDIA-MODELOS.md`.

## Compatibilidade

Config sem `media` ⇒ comportamento **idêntico à H**: nenhuma variável
`--rms-media-*`, mapas planos vazios, globais nos defaults, 12 cards
desabilitados e **zero gravação**. Os 6 presets D, as 12 seções F, os 10
componentes G, 5 campos/7 motivos de H, o hotfix e os 14 manifestos seguem
intactos. Validação com o **`Autoloader` real** confirma `ThemeMedia` →
`theme_media.php` e as assinaturas de 6/7 argumentos de `ThemePackage` e
`ThemeSnapshot`. Nenhum `Deprecated:`/`Warning:` no PHP 8.5 WASM.

## Validação executada

| Verificação | Resultado |
|---|---|
| `php -l` PHP 8.5 real nos 11 arquivos PHP | 11 OK / 0 FAIL |
| `node --check` (7 JS: A, D, E, F, G, H e o novo) | 7 OK |
| **Autoloader real** (todas as classes do `framework.php`) | 71 PASS / 0 FAIL |
| `ThemeMedia` + modelos + pacote/checksum + snapshot + prévia | 211 PASS / 0 FAIL |
| Startup Catalog: mídia + rollout + zero escrita | 81 PASS / 0 FAIL |
| Controller público `common/home` (regressão F/G/H) | 23 PASS / 0 FAIL |
| Controller Admin: edit/save/export/import/compare/thumbs | 215 PASS / 0 FAIL |
| Render Twig 3.14 + view (galeria, modelos, escape, fita) | 168 PASS / 0 FAIL |
| **jsdom 29**: inserção, variantes, confirmação, degradação sem JS | 138 PASS / 0 FAIL |
| `verify-checksums` (A e B) | 14 manifestos / 142 arquivos / 0 divergentes |
| `repo-health` (A e B) | 6 PASS / 0 FAIL / 0 SKIP |
| `verify-ci-workflow` | 31 PASS / 0 FAIL |
| `verify-contract-freeze` | exit 0 |

**Total: 907 asserções, 0 falhas.** 18 arquivos congelados
conferidos por hash antes/depois (inclui `schema.sql`, `home.twig`,
`twig.php`, `autoloader.php`, `component_registry.php`, `theme_schedule.php`,
`theme-schedule.js`, `scene_preset_registry.php`, `PageLayout`,
`cms/slot.php`, `ConditionEngine`, `TokenRegistry`). Os 14
`CHECKSUMS*.txt` permanecem **idênticos** e não foram reemitidos. Nenhum
contrato congelado, `release_approved`, workflow, checker, `custom_js` do
BDREditor ou runtime WebGL foi modificado. Nenhuma escrita remota. F9-27
segue `BLOCKED_NOT_RUN`; BDREditor pausado; P2 do F9-18 não reaberto.

## Limitações

PHP 8.5 via WASM + Twig 3.14 + jsdom, com mocks de DB/sessão/response. **Não**
houve CMS completo, MySQL real, sessão Admin autenticada, browser com rede,
WebGL, E2E, migration, staging, deploy ou escrita no GitHub. As miniaturas
são SVG esquemáticos determinísticos, **não** frames WebGL nem fotos; a I não
cria cena, GLB ou JS de cena. **CI verde e health verde ≠ release aprovada.**

Aplicar: substituir pelos arquivos completos (os 3 novos entram nos
respectivos diretórios) e rodar
`bash tools/verify-checksums.sh && bash tools/repo-health.sh`.
