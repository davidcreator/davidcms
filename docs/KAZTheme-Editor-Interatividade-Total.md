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

---

## Frente AC — validação em navegador real e responsividade corrigida

A AB foi validada com PHP WASM, Twig e jsdom — **nenhum deles executa CSS de
verdade**. A AC roda Chromium (Playwright) sobre o HTML renderizado com as
folhas e o JavaScript reais, em 6 viewports (1920×1080, 1440×900, 1280×800,
1024×768, 768×1024, 390×844), e mede `getBoundingClientRect`, overflow,
`scrollHeight/clientHeight` e erros de console.

### O que o navegador encontrou (e os harnesses não)

O Twig abre `<form id="kaz-theme-form">` antes do `<header class="kaz-topbar">`.
**Todos** os blocos do editor — topbar, intro, screenbar, workspace, statusbar
e o painel avançado — são filhos desse `<form>`, não do `.kaz-shell`.
Consequentemente **nenhum seletor `.kaz-shell > .kaz-*` da AB casou**:

| Medição em 390×844 (Chromium) | AB | AC |
| --- | --- | --- |
| `grid-template-columns` do workspace | `236px 0px 264px` (centro com **0px**) | `1fr` (centro ocupa tudo) |
| altura da `.kaz-topbar` | **453px** (coluna de ícones) | ~40px (faixa horizontal rolável) |
| altura do `.kaz-shell` | **1478px** em 844px de viewport | cabe na viewport |
| scrolls aninhados | 4 | ≤3 |
| `.kaz-screen`/SVG | cortado | altura ≥200px |

Ou seja: a AB **anunciou** responsividade no CSS, mas ela nunca existiu no
navegador. Em tablet (768×1024) o shell media 1240px. A reclamação原始
("não se auto ajusta ao tamanho de telas", "telas atropeladas") permanecia
válida — e só apareceu quando um navegador real calculou o layout.

### Correções

- O **form** passa a ser o contêiner flex (`#kaz-theme-form`), com
  `flex:1; min-height:0`; o `.kaz-shell` mantém `height: calc(100dvh - 104px)`
  e **nunca** volta a `height:auto`.
- Toolbar vira faixa horizontal rolável (`flex-wrap:nowrap; overflow-x:auto;
  max-height:36px`) até 1280px, em vez de empilhar 15 ícones.
- Até 980px: uma coluna (`grid-template-rows: minmax(0,1fr)`) e as duas
  sidebars viram **overlays** recolhidos por padrão, abertos pelos botões
  `toggle-left`/`toggle-right` já existentes (novo atributo
  `data-kaz-mobile-open`, sem rota nova).
- Até 620px: rótulos, campos numéricos de tela e metadados da statusbar são
  ocultados; os toggles das sidebars permanecem.
- `<details>` fechados não geram layout interno (`.kaz-advanced:not([open])
  > .kaz-io { display:none }`), eliminando o 4º scroll aninhado do textarea.
- Cache `-kaz-theme-editor-ab` → **`-kaz-theme-editor-ac`**.

### Evidência

`tools/verify-kaz-layout.cjs` (novo, opcional, exige Playwright) reproduz a
medição. O pacote inclui screenshots do antes (AB) e do depois (AC) em
`evidencia/`, geradas sobre o HTML renderizado com CSS/JS reais — **não** são
capturas do servidor de produção.

Contratos preservados: `theme_form.twig` com 3 linhas e 2 `|raw`, o `<form>`
como filho do shell, 14 painéis, 30 `data-kaz-module`, 53 `name=`, 14
manifestos, `release_approved:false`. Sem migration, `|raw` novo, `on*`,
`innerHTML`, `eval`, `fetch`, `localStorage` ou escrita no GitHub por este
pacote. **CI verde ou health check não significa release aprovada.**

---

## Frente AD — Boot após o DOM: por que nenhuma ferramenta respondia

**Sintoma relatado:** todas as ferramentas inertes e **nenhum erro no console
do DevTools**. A causa não era exceção, nem banco de dados, nem CSS: era a
**ordem de carregamento**.

`dev/admin/view/template/common/header.twig` emitia, no `<head>` e **sem
`defer`**, todos os scripts registrados por `addScript()`:

```twig
{% for src in scripts %}<script src="{{ src }}"></script>{% endfor %}
```

O `kaz-theme-editor.js` é uma IIFE que começa com
`var root = document.getElementById('kaz-theme-editor'); if (!root) return;`.
No `<head>` o `<body>` ainda não existe → `root === null` → **`return`
silencioso**. Nenhum listener é anexado e o console permanece limpo. O mesmo
padrão `if (!root) return;` aparece em `theme-scene-editor.js`,
`theme-section-builder.js`, `theme-component-library.js`, `theme-schedule.js`,
`theme-media.js`, `theme-sites.js` e `theme-package.js` — ou seja, **os nove
módulos do editor de temas estavam desligados desde a AA**, o que explica a
queixa original de que "nenhuma das funcionalidades tem resultado".

Por que os harnesses anteriores não viram: o documento de teste da AC
injetava o script com `defer`. O DOM já estava montado, o boot funcionava e
251 asserções Chromium passavam — numa condição que o Admin real não tem.
A AC corrigiu o layout; **não** corrigiu a inicialização.

### Correção (contida, sem banco de dados)

1. `Document::addScriptDeferred()` / `getScriptsDeferred()` — novo par de
   métodos; `addScript()` fica intacto.
2. `common/header.php` publica `scripts_deferred`; `header.twig` emite um
   segundo loop com `<script src="…" defer></script>`. **Só** o controller
   `design/theme.php` usa o método novo; os outros 5 controllers mantêm a
   ordem anterior, sem risco para os 6 templates que têm `<script>` inline
   no corpo.
3. `design/theme.php` troca as 9 chamadas e o cache passa a
   `-kaz-theme-editor-ad` (refresh forçado).
4. `kaz-theme-editor.js` ganha **defesa em profundidade**: o corpo inteiro
   vive em `boot()`, disparado assim que `#kaz-theme-editor` existe; se ainda
   não existe, espera `DOMContentLoaded` (com `load` como fallback e guard
   de idempotência). A decisão é pela **presença do elemento**, não por
   `readyState` — jsdom reporta `loading` mesmo com o DOM já montado.

### Banco de dados

**Não é necessária nenhuma migration.** A inclusão do JS em
`design/theme.php` é incondicional e `ThemePackage::normalizeScene(
$config['kaz_scene'] ?? null)` trata a ausência da chave como estado "não
salvo" — zero coluna, zero `INSERT`, zero `UPDATE`. Fica disponível
`tools/diagnostico-kaztheme-somente-leitura.sql`, com `SELECT`s apenas, para
quem quiser confirmar; ele **não é** um arquivo de atualização e não deve ser
aplicado como migration.

### Verificação

`tools/verify-kaz-boot.cjs` (novo, exige Playwright) abre um documento com o
`<script>` no `<head>` **sem `defer`**, exatamente como o `header.twig`
produzia. Com o JS da AC ele mede `data-kaz-ready` ausente, `__kaz` ausente,
cliques sem efeito e **zero erros de console** — reproduzindo o sintoma.
Com o JS da AD ele exige boot, handlers ativos e as interações de abas,
presets, salvar e publicar. Use:
`node tools/verify-kaz-boot.cjs <url-antes> <url-depois>`.

A limitação anterior permanece: Chromium headless sobre documento local não é
o servidor de produção. Confirme no Admin real após aplicar, com refresh
forçado por causa do cache novo.

---

## Frente AE — Aderência ao `main`, fail-loud e diagnóstico em produção

Pela primeira vez foi possível ler o repositório **real** em vez de uma réplica
local: a API devolvia sempre `13b0f7b…` por cache até que uma consulta com
`per_page=5` e `nocache` novo revelou dois commits.

| Commit | Data (UTC) | Mensagem |
| --- | --- | --- |
| `50d4844f66d9…` | 2026-11-04 19:46:39 | `fix(admin/theme-editor): correct browser layout issues from AB release` (AC) |
| `56fd0c5d40fb…` | 2026-11-04 20:06:31 | `fix(admin/theme-editor): fix silent theme editor boot failure` (AD) |

`pushed_at = 2026-11-04T20:06:35Z`; árvore `3194` entradas / `2611` blobs /
`truncated:false`. Auditoria baixando o conteúdo cru de `56fd0c5d40fb`:
**19/19 arquivos entregues pelas frentes AC e AD são byte a byte idênticos ao
`main`** — incluindo `theme.php` (70.109), `kaz-theme-editor.js` (47.669),
`header.twig` (6.089), `document.php` (2.476) e o CSS (34.897). Os 405 paths
presentes no `main` e ausentes no recorte local são 227 `index.html` e 178
binários (fontes, imagens, sourcemaps) que o download filtra; **nenhum
arquivo de migration novo**. `schema.sql` segue intacto.

### O que ainda faltava

Desde a frente A, a confirmação recebida era sempre "aplicado com sucesso,
github atualizado" — nunca "as ferramentas agora respondem no meu servidor".
E o sintoma que levou quatro frentes para ser diagnosticado era justamente o
**silêncio**: ferramentas inertes com console limpo. A AD corrigiu a causa,
mas não deixou nenhum sinal visível caso o problema volte (Twig compilado em
cache, CDN servindo JS antigo, CSP, asset 404).

### Correções da AE

1. **Fail-loud** — `bootDone` passa a ser marcado **depois** de confirmar o
   root, permitindo que `DOMContentLoaded` e `load` tentem novamente. Se,
   passados 500 ms, o boot não ocorreu e a página é de fato a do editor, um
   aviso `role="alert"` é inserido com `createElement` + `textContent`,
   explicando o que verificar. Sem `innerHTML`, rede, `eval` ou exceção.
2. **`data-kaz-health="ad+ae"`** no root, para o diagnóstico saber qual
   versão do JS está viva.
3. **Cache** `-kaz-theme-editor-ad` → **`-kaz-theme-editor-ae`** (refresh).
4. **`tools/kaz-theme-editor-health.js`** (novo) — snippet **somente leitura**
   para colar no console do DevTools na página de temas. Devolve um JSON com
   veredito (`OK` / `FALHA_ASSET_OU_DEFER` / `FALHA_BOOT` /
   `OK_COM_RESSALVAS`), assets e versões, `data-kaz-ready`, `__kaz`, os 9
   módulos, medidas de layout, CSRF/form e o aviso fail-loud. Use
   `copy(__kazHealth())` para copiar.
5. **`tools/verify-kaz-health.cjs`** (novo) prova em Chromium que o health
   distingue o estado quebrado do corrigido, e que o aviso aparece **só**
   quando o boot falha.

### Banco de dados

Sem migration, novamente. A auditoria do `main` confirmou que não foi
adicionado nenhum `.sql` além do diagnóstico somente-leitura da AD. A cadeia
de publicação continua usando `draft_config`/`published_config` existentes.

### Limite

Chromium headless local não é o servidor de produção. A AE entrega o
**instrumento** para medir o servidor real; o veredito que importa é o que
`__kazHealth()` devolver no Admin dele. CI verde ou health check local não
significa release aprovada.
