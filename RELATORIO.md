# Frente J — Multisite Declarativo (última da série A→J)

Série A→J: **A** (builder de condições), **B** (design tokens), **C** (prévia
rascunho × publicado), **D** (editor de cena), **E** (pacotes/child-theme),
**F** (section builder), **G** (biblioteca de componentes), o **hotfix
`ScenePresetRegistry`**, **H** (agendamento/rollout) e **I** (mídia/modelos)
já estão aplicados no `main` aa49dd088; **esta é a J**, que fecha o roadmap.

## Recorte

A J permite que **hosts diferentes usem temas diferentes** — sem vhost, DNS,
tabela, coluna, migration, cron, cookie novo, escrita no banco ou **consulta SQL
adicional**. Não é hospedagem multiloja: é uma **política declarativa no JSON do
tema**, resolvida em cada requisição. Não toca em `schema.sql`, na biblioteca de
mídia do Blog (ADR 0049), no BDREditor, no runtime WebGL nem em `custom_js`.

- **`ThemeSites`** (novo): até **8 IDs allowlistados** (`primary`, `shop`,
  `blog`, `docs`, `landing`, `partner`, `legacy`, `staging`), cada um com
  `host`, `slug` (tema), `language` (`pt-br`/`en-us`/`es-eu`) e `enabled`.
  `normalize()` é **fail-closed** (ID/chave extra, aninhamento, host com
  scheme/porta/caminho/`..`, slug fora de `^[a-z0-9_-]{1,48}$`, idioma
  desconhecido ou mais de 8 sites ⇒ `null`). `slug=''` significa **usar o tema
  ativo**. `match()` compara o host por `hash_equals`, normalizado
  (minúsculas, porta removida).
- **Segurança de host**: `HTTP_HOST` é dado do cliente. É **apenas comparado**
  com hosts do config — **nunca** publicado em HTML, global Twig, CSS, log ou
  resposta. Host desconhecido/inválido cai no primeiro site habilitado; nenhum
  site habilitado ⇒ estado vazio (fail-closed). Os globais Twig são 3 mapas
  **planos e curtos** (≤256 bytes); `--rms-site-*` contém **só inteiros**.
- **Zero query nova**: a H já carregava **todos** os temas com
  `getThemeMap($source)` para a herança E. `startup/theme.php` procura o `slug`
  do site nesse mesmo mapa e usa o config dele como raiz da cadeia — continuam
  **exatamente 2 SELECTs somente leitura** (`getEffectiveTheme` + `getThemeMap`).
  O rollout H segue decidindo `published` × `draft`; o site escolhe **qual
  tema**. Slug ausente/inválido volta ao tema ativo, sem erro e sem escrita.
  A política de sites é **global** (tema ativo) e chega ao snapshot por
  `$siteState`, para não se perder na troca de raiz.
- **Pacotes E**: `sites` em `CONFIG_KEYS`, `spec()`, `validateConfig()`
  (**`sites_invalid`**), checksum SHA-256 e `decode()`. Na herança é
  **substituído integralmente** (como `schedule`/`conditions`): um filho nunca
  herda metade do mapa.
- **Snapshot/prévia**: `ThemeSnapshot` publica `sites`, `site`, `site_css`,
  `site_bars`, `site_language` e os globais planos; `diff()` inclui
  `sites.<id>.host`. `ThemeCompareView::siteStrip()` mostra a fita nos dois
  painéis, com o site ativo destacado.
- **Catalog/Admin**: `common/header.php` concatena `--rms-site-*` no `style`
  do `<body>` e usa a classe allowlistada `rms-site-<id>`; o Admin ganha o card
  **Sites e domínios** (8 linhas, seletor de temas existentes, idioma) e uma
  **sonda local** em `theme-sites.js` (DOM puro, sem rede) que simula um host e
  mostra `id · slug · idioma · motivo`. A prévia C aceita `site_probe_host`
  apenas para comparação. **36 novas chaves de UI ×3 idiomas** (382 → 418) e
  guia `docs/GUIA-MULTISITE.md`.

## Compatibilidade

Config sem `sites` ⇒ comportamento **idêntico à I**: nenhuma classe
`rms-site-*`, `site_css` vazio, globais nos defaults, 8 linhas desabilitadas,
**zero gravação** e **zero consulta extra**. Preservados: 6 presets D, 12 seções
F, 10 componentes G, 5 campos/7 motivos H, 12 mídias/6 modelos I, hotfix,
25 templates, 14 manifestos e contratos congelados. `Autoloader` real confirma
`ThemeSites` → `theme_sites.php` e as assinaturas de 7/8 argumentos de
`ThemePackage`/`ThemeSnapshot`. Sem `|raw`, `<script>`, `fetch`/`eval`/
`innerHTML`, upload, URL externa, GLB, cena WebGL, schema/migration ou escrita
remota. Nenhum `Deprecated:`/`Warning:` no PHP 8.5 WASM.

## Validação executada

| Verificação | Resultado |
|---|---|
| `php -l` PHP 8.5 real nos 11 arquivos PHP | 11 OK / 0 FAIL |
| `node --check` (8 JS: A, D, E, F, G, H, I e o novo) | 8 OK |
| **Autoloader real** (todas as classes do `framework.php`) | 82 PASS / 0 FAIL |
| `ThemeSites` + pacote/checksum + snapshot + prévia + startup | 141 PASS / 0 FAIL |
| Controller Admin: edit/save/export/import/compare/sonda | 59 PASS / 0 FAIL |
| Render Twig 3.14 + view (card, sonda, escape, fita) | 55 PASS / 0 FAIL |
| **jsdom 29**: sonda local, checkbox, degradação sem JS | 59 PASS / 0 FAIL |
| `verify-checksums` (A e B) | 14 manifestos / 142 arquivos / 0 divergentes |
| `repo-health` (A e B) | 6 PASS / 0 FAIL / 0 SKIP |
| `verify-ci-workflow` | 31 PASS / 0 FAIL |
| `verify-contract-freeze` | exit 0 |

**Total: 396 asserções, 0 falhas.** 24 arquivos congelados
conferidos por hash antes/depois (inclui `schema.sql`, `home.twig`, `twig.php`,
`autoloader.php`, `component_registry.php`, `theme_schedule.php`,
`theme_media.php`, `theme-schedule.js`, `theme-media.js`, os guias H/I,
`scene_preset_registry.php`, `PageLayout`, `cms/slot.php`, `ConditionEngine`,
`TokenRegistry`). Os 14 `CHECKSUMS*.txt` permanecem **idênticos** e não
foram reemitidos. Nenhum contrato congelado, `release_approved`, workflow,
checker, `custom_js` do BDREditor ou runtime WebGL foi modificado. Nenhuma
escrita remota. F9-27 segue `BLOCKED_NOT_RUN`; BDREditor pausado; P2 do F9-18
não reaberto.

## Limitações

PHP 8.5 via WASM + Twig 3.14 + jsdom, com mocks de DB/sessão/response. **Não**
houve CMS completo, MySQL real, sessão Admin autenticada, browser com rede,
DNS/vhost, WebGL, E2E, migration, staging, deploy ou escrita no GitHub. Cabe ao
servidor web apontar os domínios para a mesma instalação; a J apenas **decide o
tema** de cada host. A duplicata legada `text_preview_error` do arquivo de
idioma já existia no HEAD I e foi preservada (não corrigida nem agravada).
**CI verde e health verde ≠ release aprovada.**

Aplicar: substituir pelos arquivos completos (os 3 novos entram nos
respectivos diretórios) e rodar
`bash tools/verify-checksums.sh && bash tools/repo-health.sh`.
