# KAZTheme-Editor — Interatividade Total (Frente AA)

> **Todos os botões e recursos agora executam uma ação real. O tamanho das
> telas é ajustável, há densidade de colunas, e o editor pode ocupar a tela
> cheia nesta aba ou em outra página.**

## 1. Pedido atendido

1. *"Aplicar as funcionalidades para todos os botões e recursos"* — ver §2.
2. *"Que possa ajustar o tamanho das telas"* — ver §3.
3. *"Tenha uma opção de usar o tamanho tela cheia em outra página"* — ver §4.

Base: `main` @ `08eca35d39c7464d2543e21995807ea9c162d62c` (frente Z aplicada
pelo usuário, 3.176 entradas / 2.594 blobs / 14 manifestos).

## 2. Inventário de controles

O workspace Z tinha 15 `data-kaz-action`, 8 abas, 3 zoom, 2 ferramentas,
5 kinds, 6 blocos, 29 links de módulo, 11 campos do inspector e 6 swatches.
As ações já existentes foram **mantidas**; os elementos decorativos viraram
botões funcionais:

| Antes | Agora | Ação |
| --- | --- | --- |
| `<span>Página 1</span>` | `<button data-kaz-action="pages">` | abre aba Páginas e o módulo Design |
| `<span class="kaz-avatar">1</span>` | `<button data-kaz-action="users">` | informa "1 editor local · sessão Admin" (sem rede) |
| `<span data-kaz-dirty-badge>` | `<button data-kaz-action="revert">` | `revertScene()` volta ao `baseline` |
| `<span data-kaz-file-status>` | `<button type="submit" form="kaz-theme-form">` | salva o tema **sem depender de JS** |
| 6 `<span class="kaz-swatch">` | `<button data-kaz-swatch="#cor">` | `applyColor()` na camada selecionada |
| 2 `<div class="kaz-token">` | `<button data-kaz-token-apply>` | aplica `primary`/`surface` à camada |
| `<div class="kaz-type-sample">Aa</div>` | `<button data-kaz-action="add-text">` | `add('text')` |
| Painel Páginas (só texto) | + `Ir para o board` | Design + Camadas + `fitZoom()` |
| Painel Protótipo (3 notas) | + 3 botões | prévia do tema, ajustar zoom, tela cheia |
| `Exportar cena` | + download | `Blob` + `createObjectURL` quando disponível; fallback = textarea |

Os botões dos módulos legados continuam com seus scripts:
`theme-section-builder.js`, `theme-media.js`, `theme-component-library.js`,
`theme-sites.js`, `theme-schedule.js`, `theme-scene-editor.js`,
`theme-condition-builder.js`, `theme-package.js` e `theme-sites.js`.
**Nenhum foi reimplementado ou duplicado.**

Atalhos acrescentados (sem conflito com `V/H/R/T/B/C/S/+/-/0`, `Ctrl+Z`,
`Ctrl+D`, `Delete`, setas): `F` fullscreen local, `[` compacto, `]` amplo,
`Shift+1` Desktop, `Shift+2` Laptop, `Shift+3` Tablet, `Shift+4` Mobile,
`Shift+5` Widescreen, `Shift+6` Tema.

## 3. Tamanhos de tela

`.kaz-screenbar` (centro, acima das réguas):

| Preset | Dimensões |
| --- | --- |
| Tema (cena) | `scene.canvas.w × scene.canvas.h` (padrão `960 × 540`) |
| Desktop | `1440 × 900` |
| Laptop | `1280 × 800` |
| Tablet | `768 × 1024` |
| Mobile | `390 × 844` |
| Widescreen | `1920 × 1080` |
| Personalizado | largura `320–3840`, altura `240–2160` (clamp no JS) |

O board da cena é envolvido por `.kaz-screen`, que recebe as dimensões em px.
`data-kaz-screen-size`, `[data-kaz-screen-frame-label]` e
`[data-kaz-status-screen]` espelham a escolha. `Ajustar à tela` e `fitZoom()`
passam a dividir pelo **tamanho da tela**; como o preset inicial é `Tema`, o
comportamento de boot é idêntico ao da frente Z.

`[data-kaz-board-size]` continua mostrando o tamanho real do **canvas**, que
nunca muda. Screen, densidade, colapso e fullscreen são estado **local
descartável**: não entram em `kaz_scene_json`, não afetam `dirty()`/`baseline`,
não usam `localStorage` e não são enviados no POST (nenhum controle novo tem
`name=`).

**Densidade:** `compact` `200px/220px`, `normal` `236px/264px`, `wide`
`300px/340px`. Dois botões colapsam cada sidebar (`data-kaz-collapsed`).

## 4. Tela cheia

| Modo | Como | O que muda |
| --- | --- | --- |
| Nesta aba | botão `⛶ Tela cheia` / tecla `F` | `requestFullscreen()` + `data-kaz-fullscreen="1"`; sem a API, fica só o CSS `position:fixed; inset:0` |
| **Em outra página** | link `↗ Tela cheia em nova página` (`target="_blank"`, `rel="noopener noreferrer"`, `&kaz_fullscreen=1`) | controller publica `kaz_fullscreen_page`; `theme_form.twig` omite **header e footer** do Admin; `data-kaz-page-mode="fullscreen"`; shell `100vw × 100vh` |
| Sair | link `Sair da página cheia` | volta para `{{ action }}` sem o parâmetro |

Não há rota, controller, migration, sessão compartilhada ou transporte de
estado: a outra página carrega o **estado gravado no servidor**. Por isso os
selos `Alterações não salvas` / `Salvo` e o botão de submit na navbar permitem
salvar antes de abrir. Isso é uma decisão explícita de escopo — sem
`localStorage`, sem `fetch`, sem `postMessage`, sem upload.

`theme_form.twig` continua com **3 linhas** e os **2 `|raw`** existentes, agora
condicionados por `{% if not kaz_fullscreen_page %}`. **Sem JavaScript** o botão
`Salvo` continua enviando o formulário e os 14 módulos permanecem no DOM. O shell tem **zero**
`|raw`, zero `<script>` inline e zero atributo `on*`.

## 5. Contratos preservados

- `dirty()` / `data-kaz-dirty` / `data-kaz-file-status` → exclusivos da cena.
- `data-kaz-form-dirty` → selo separado do formulário (frente Z).
- `syncField()`, `submit`, `baseline`, `MAX_LAYERS=48`, fail-closed, 6 blocos,
  15 módulos (Design + 14), 8 abas, 15 ícones SVG, 3 colunas.
- **53 `name=`** no shell + 2 no `theme_form.twig`, 3 formulários irmãos
  (principal, import, restore), nenhum aninhado, `csrf_token` nos 3,
  `can_modify`/`disabled`, `formaction="{{ compare_url }}"`,
  `formtarget="theme-preview"`, iframe `sandbox=""`, `{{ footer|raw }}`.
- 418 chaves × 3 idiomas, 14 `CONFIG_KEYS`, 25 `TEMPLATE_KEYS`, 14
  manifestos, 11 hashes, `schema.sql`, 10 `PENDING_DECISION`, 77 paths
  BDREditor, `release_approved:false`.
- Cache `-kaz-theme-editor-z` → **`-kaz-theme-editor-aa`**.

## 6. Fora de escopo / limitações

Sem migration, schema, coluna, `custom_js`, `|raw` novo, chave de tradução,
manifesto, CDN, fonte remota, `<script>` inline, `on*`, `innerHTML`, `eval`,
`fetch`, `localStorage`, upload, WebGL ou Three.js real, renderização pública
da cena, colaboração multiusuário, comentários, protótipo navegável, View mode,
componentes reutilizáveis, flex/grid layout ou múltiplos boards. A Fullscreen
API depende de gesto do usuário e de suporte do navegador; o modo CSS é o
fallback. A cena continua sem renderização no site público.

Nenhum `PENDING_DECISION`, `STATIC_ONLY`, `NOT_RUN`, `SKIP` ou
`BLOCKED_NOT_RUN` foi convertido em `PASS`. F9-27 segue `BLOCKED_NOT_RUN`;
BDREditor/F9-103 pausado; P2/F9-18 não reaberto. Nenhuma escrita no GitHub
foi feita por este pacote; o CI remoto não foi verificado aqui.
**CI verde ou health check não significa release aprovada.**

---

## Frente AB — tema publicado de verdade, editor responsivo em abas

A frente AA foi aplicada, mas a interface ficou **inutilizável**: o canvas
aparecia como uma faixa fina, os 14 formulários legados ficavam empilhados
no centro, havia dois scrolls verticais e nenhuma ação gerava tema no site.
A AB corrige as causas e fecha a ponte com o catálogo.

### Diagnóstico do que estava quebrado

| Causa | Correção AB |
| --- | --- |
| `render()` não chamava `paintModules()` e o boot nunca chamava `setModule('design')` → `.kaz-modules` nunca recebia `hidden` | ver §2: `paintChrome()` já chamava `paintModules()`; a causa real é o HTML sem `hidden` no servidor |
| `.kaz-screenbar` inserida **dentro** do `.kaz-center` (réguas/viewport absolutos) → sobreposição | barra movida para fora do `.kaz-workspace` |
| `.kaz-workspace { height: 548px }` fixo + `.kaz-modules` no fluxo → página longa com scroll aninhado | `.kaz-shell` em flex column com `height: calc(100dvh - 104px)`; `.kaz-stage` é o único scroll |
| `.kaz-screen` com `display:flex` + board `inline-block` → canvas achatado | `.kaz-screen` em bloco; `.kaz-board` e `.kaz-board__frame` a `100%` |
| `.kaz-io` (JSON) e `.kaz-extra` sempre visíveis | recolhidos em `<details>` "Avançado" |
| 15 links de módulo verticais na sidebar | barra de **abas horizontais** no topo do centro |
| A cena SVG não era consumida por nada no catálogo | novo compilador publica CSS + camadas reais na home |

### Resultado real: editor → site publicado

`ThemeSceneCss::compile()` reutiliza `ThemePackage::normalizeScene()` (fail-closed)
e produz dois artefatos:

- **`css`** — apenas declarações `--rms-scene-*` (inteiros, hex validado, `px`),
  concatenadas ao `theme_token_css` do `common/header` e emitidas no `style`
  do `<body>` com `|e('html_attr')`. **Nenhum `|raw` novo.**
- **`layers`** — escalares allowlistados (posições em porcentagem, `fill`,
  `radius`, `font`, texto truncado a 80 caracteres sem caracteres de controle).
  `partial/theme-scene-block.twig` itera com `{{ l.text }}` sob **autoescape**.

A cadeia completa: `edit()` grava `kaz_scene` no `draft_config` → `Publicar`
(`design/theme.activate`, rota já existente) copia o rascunho para
`published_config` → `startup/theme` resolve o `ThemeSnapshot` →
`catalog_theme_scene_css` / `catalog_theme_scene_view` → `common/header` e
`common/home` → `<section class="rms-scene">` na home publicada.
`theme.css` consome as variáveis e posiciona as camadas de forma responsiva
(`aspect-ratio`, porcentagens, `@container`, `@media`).

O fluxo na navbar ficou explícito: **Salvar rascunho**, **Publicar** (com
confirmação nativa em `<details>`, sem `on*` e sem JS) e **Prévia** (iframe
`sandbox=""` já existente). Os 14 módulos seguem acessíveis pelas abas.

### Contrato

Zero migration, schema, coluna, `custom_js`, `|raw` novo, chave de tradução,
manifesto, CDN, `<script>` inline, `on*`, `innerHTML`, `eval`, `fetch`,
`localStorage`, upload ou WebGL real. Os 14 `data-kaz-module-panel`, os 30
`data-kaz-module` e os 53 `name=` do shell foram preservados; `theme_form.twig`
continua com 3 linhas e os 2 `|raw` existentes. Cache `-kaz-theme-editor-aa`
→ **`-kaz-theme-editor-ab`**. Nenhum gate virou `PASS`; `release_approved`
permanece `false`. **CI verde ou health check não significa release aprovada.**
