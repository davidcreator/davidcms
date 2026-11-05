# KAZTheme-Editor — referência (Frentes U + V + X + Y)

**Data:** 4 de novembro de 2026 (America/Sao_Paulo)  
**Estado:** fundação visual (U), cena persistente (V), correção do mapa de sites (W), biblioteca de blocos + estado não salvo (X) e **workspace visual inspirado no Penpot (Y)**; não é release aprovada.  
**Nome próprio:** KAZTheme-Editor, o editor de temas do ReamurCMS — na mesma linha de identidade do BDREditor, mas com escopo, documentos e arquivos próprios.

## 1. O que é

KAZTheme-Editor é um **criador de temas visual** dentro do gerenciador de temas do Admin. A inspiração de interface é o Figma e o Penpot: canvas com grade, árvore de camadas à esquerda, inspector à direita, seleção, arrastar, redimensionar, duplicar, ocultar, bloquear, reordenar, desfazer/refazer e atalhos de teclado.

A implementação é **DOM + SVG puro**. Não há WebGL, Three.js, canvas 2D, bibliotecas externas, upload, rede ou código executável vindo do usuário. Isso mantém o editor fácil de editar: cada responsabilidade mora em um arquivo pequeno e legível.

### O que este editor NÃO é

- Não é o BDREditor. O BDREditor/F9-103 continua pausado e **nenhum arquivo dele foi alterado**.
- Não é F9-27, nem P2/F9-18, nem reabertura das frentes A–U. `release_approved` continua `false` e F9-27 continua `BLOCKED_NOT_RUN`.
- Não é renderização da cena no site público. A cena é **dado de edição e versionamento**: volta ao abrir o tema, viaja no pacote e é herdada por child-themes. O que o visitante vê continua vindo dos campos allowlistados (`primary`, `surface`, `sections`, `components`, tokens etc.) que o editor escreve. Renderizar a cena no Catalog exigiria evoluir `ThemeSnapshot`/runtime em frente posterior.
- Não é migration, schema, coluna nova, `custom_js`, WebGL, upload ou chave de tradução nova.
- CI verde ou health coerente **não significa release aprovada**.

## 2. Arquitetura e mapa de arquivos

| Caminho | Responsabilidade |
| --- | --- |
| `dev/admin/view/js/kaz-theme-editor.js` | Modelo da cena, validação fail-closed, render SVG, camadas, inspector, drag/resize, histórico, atalhos, export/import, sincronização do campo `kaz_scene_json` e conexão com o formulário. |
| `dev/admin/view/css/kaz-theme-editor.css` | Estilos escopados em `.kaz-*`, layout de 3 colunas, responsivo e `forced-colors`. |
| `dev/admin/view/template/design/kaz_theme_editor.twig` | Ponto de montagem incluído por `theme_form.twig`, dentro do `<form>` principal; o textarea é um campo real submetido. |
| `dev/admin/controller/design/theme.php` | `kazScene()` (fallback derivado do config), leitura da cena salva, validação/gravação no `buildDraftConfig()` e assets com cache key própria. |
| `dev/system/library/theme/theme_package.php` | `CONFIG_KEYS` com `kaz_scene`, `validateScene()`/`normalizeScene()` e validação no `validateConfig()`. |
| `dev/admin/view/template/design/theme_form.twig` | Inclui o editor antes de fechar o formulário principal. |
| `docs/KAZTheme-Editor.md` | Esta referência. |
| `docs/KAZTheme-Editor-Guia-Rapido.md` | Passo a passo de uso. |
| `docs/KAZTheme-Editor-Cena-Persistente.md` | Contrato da frente V e regras de migração zero. |
| `docs/KAZTheme-Editor-Biblioteca-de-Blocos.md` | Contrato dos 6 blocos da frente X e do selo de alterações não salvas. |
| `docs/KAZTheme-Editor-Interface-Penpot.md` | Mapeamento do workspace da frente Y para a interface do Penpot, limites e validação. |

O padrão de separação (JS de modelo/UI + CSS escopado + init no controller + documento de referência) é o mesmo que o BDREditor usa, mas os arquivos, nomes, identidade e contrato são **exclusivos** do KAZTheme-Editor.

## 3. Contrato de dados `kaztheme-scene/v1`

Cena JSON, sem HTML, sem código executável, sem referência a arquivos:

```json
{
  "version": 1,
  "canvas": { "w": 960, "h": 540, "grid": 8 },
  "layers": [
    { "id": "kaz-primary", "kind": "rect", "role": "primary",
      "x": 40, "y": 40, "w": 200, "h": 120, "fill": "#2563eb",
      "radius": 12, "text": "Primary", "fontSize": 16,
      "visible": true, "locked": false, "bindId": "" }
  ]
}
```

Enums allowlistados: `kind` = `rect|text|button|card|section`; `role` = `none|primary|surface|section|component`.

Validação **fail-closed no cliente e no servidor**: `version === 1`; `canvas.w` 320..3840; `canvas.h` 240..2160; `grid === 8`; no máximo 48 camadas; JSON até 64 KiB; `id`/`bindId` em `[a-z0-9_-]{1,48}` (ou `bindId` vazio), IDs únicos; coordenadas `-2000..2000`; `w` `8..960`; `h` `8..540`; `radius` `0..64`; `fontSize` `8..72`; `fill` em `#[0-9a-fA-F]{6}`; `text` com até 80 caracteres e sem padrões executáveis/markup; `visible`/`locked` booleanos. Qualquer violação **rejeita o import e o POST integralmente**, sem alterar o estado. O erro do servidor usa a chave de tradução já existente `error_validation` — nenhuma chave nova foi criada.

`ThemePackage::normalizeScene()` devolve a cena **canônica** (somente as 14 chaves allowlistadas por camada, tipos garantidos) ou `null`. `validateScene()` devolve códigos estáveis (`kaz_scene_*`).

## 4. Persistência (frente V)

`ThemePackage::CONFIG_KEYS` passou de 13 para **14 chaves** com `kaz_scene`. Nenhum artefato congelado por hash, nenhum dos 14 manifestos `CHECKSUMS*.txt` e nenhuma coluna foi alterado: `theme_package.php` já era livre na triagem da U.

| Etapa | Comportamento |
| --- | --- |
| Abrir tema com `kaz_scene` válida | `edit()` usa `normalizeScene($config['kaz_scene'])` e marca `kaz_scene_saved=true`. |
| Abrir tema A–U sem a chave | `edit()` usa `kazScene($config)` derivado das cores/seções/componentes e marca `false`. **Zero migration**, comportamento idêntico ao da U. |
| Salvar formulário | `buildDraftConfig()` lê `kaz_scene_json`, chama `normalizeScene()`; inválido ⇒ `error_validation` e nada é gravado. Válido ⇒ grava `kaz_scene` no config. |
| Exportar/importar pacote | `export()` inclui a chave via `sanitizeConfig()`; `decode()` recalcula o checksum e chama `validateConfig()`, que valida a cena. |
| Child-theme | `kaz_scene` **não** está em `MAP_KEYS`: é substituída integralmente na cadeia, nunca mesclada pela metade. |
| Condições/rollout | `then` do `ConditionEngine` continua allowlistado e não conhece `kaz_scene`; a cena não participa de `decide()`. |

**Aplicação não destrutiva:** abrir o editor não executa nenhum binding; seções e componentes são marcados, nunca desmarcados, e `sections_order` preserva os IDs já salvos.

## 5. Biblioteca de blocos e estado não salvo (frente X)

A frente X acrescenta um painel **Biblioteca de blocos** com 6 presets
allowlistados (`hero`, `card`, `banner`, `titulo`, `galeria`, `cta`). Cada preset
é uma lista **declarativa** de 2–3 templates que geram camadas reais dentro do
contrato `kaztheme-scene/v1`: `kind` entre os 5 permitidos, `role='none'`,
`bindId=''`, cores hex, coordenadas com snap na grade de 8 px e limites
respeitados. Inserir um bloco:

- cria IDs únicos, seleciona a primeira camada, entra no histórico (desfazer) e
  **sincroniza o textarea** `kaz_scene_json` — portanto é persistido ao salvar;
- respeita `MAX_LAYERS` (48): se o bloco não couber, nada é alterado;
- **não** aplica bindings ao formulário e **não** roda no boot: só por clique.

O selo **Alterações não salvas** (`data-kaz-dirty`) compara a cena atual com o
`baseline` carregado do servidor. Abrir a tela não suja o estado; desfazer até o
estado inicial limpa o selo; o `submit` atualiza o `baseline`. É um indicador
local — a validação e a gravação continuam soberanas no servidor.

O contrato de dados, `CONFIG_KEYS` (14 chaves), `MAP_KEYS`, `validateScene()`,
`normalizeScene()`, `validateConfig()`, `decode()` e os 14 manifestos **não foram
alterados** pela X. Zero migration e zero chave de tradução nova: os rótulos são
literais estáticos no Twig, como os botões existentes desde a U. O cache key dos
assets passou de `-kaz-theme-editor-v` para `-kaz-theme-editor-x`.

## 6. Workspace visual (frente Y)

A reclamação de que a interface "não estava nem 1% semelhante" ao Penpot levou
a uma reformulação **apenas de casca**: nenhum contrato de dados, servidor,
formato de cena ou campo allowlistado mudou. O workspace passa a ter:

| Área do Penpot | KAZTheme-Editor Y |
| --- | --- |
| Navbar, main menu, toolbar, histórico, usuários | `.kaz-topbar` com marca, nome do arquivo, página, 13 ferramentas SVG, desfazer/refazer, avatar e status |
| Páginas / Camadas / Ativos / Design tokens / Histórico | 5 abas na sidebar esquerda (`data-kaz-tab`) |
| Viewport infinito, réguas, boards | `.kaz-viewport` pontilhado com pan (ferramenta mão), zoom 25–200%, réguas H/V e board 960×540 |
| Design properties / Prototype / Inspect | 3 abas na sidebar direita; inspector com seções Layout, Aparência, Tipografia, Elemento e Tema |
| Color palette, typography, tokens | swatches, amostra `Aa` e tokens `primary`/`surface` lidos de `data-kaz-*` |
| Zoom, coordenadas, file status | `.kaz-statusbar` e o selo `Salvo` / `Alterações não salvas` |

O tema é **escuro por padrão** (o Penpot também), com variáveis CSS escopadas
em `.kaz-card`, responsivo até 900 px e com regras `forced-colors`. Ícones são
`<svg>` literais e estáticos no Twig — sem fontes remotas, CDN, bibliotecas de
ícones, `<script>` inline ou atributo `on*`.

**Zoom/pan/abas/ferramenta são estado LOCAL de interface**: não entram no
`kaz_scene_json`, não são gravados, não usam `localStorage` e não afetam o
`baseline` do indicador de alterações não salvas. O `scale()` do drag/resize
continua usando `getBoundingClientRect()`, que já reflete o `transform` do
board. Inserir blocos, editar camadas, desfazer, aplicar bindings e salvar
continuam exatamente como na X.

Atalhos novos (ignorados enquanto se digita): `V` mover, `H` mão,
`R/T/B/C/S` criam camadas, `+`/`-` zoom, `0` ajusta, `Ctrl+roda` zoom. Os
atalhos U/V (`Ctrl+Z`, `Ctrl+Shift+Z`, `Ctrl+D`, `Delete`, setas) permanecem.

O cache key dos assets passou de `-kaz-theme-editor-x` para
`-kaz-theme-editor-y`. **Não há** protótipo navegável, View mode, comentários,
colaboração multiusuário, componentes reutilizáveis, flex/grid layout, upload
ou renderização da cena no site público: as abas que remetem a isso são
explicitamente informativas e somente-leitura, e o documento da frente Y lista
cada limite.

## 7. Segurança

- Apenas `createElement`, `createElementNS`, `setAttribute`, `textContent`, `appendChild`, `removeChild`, `addEventListener`.
- Sem `innerHTML`, `outerHTML`, `document.write`, `eval`, `Function`, `fetch`, `XMLHttpRequest`, jQuery AJAX, `srcdoc`, tag `script` inline ou atributo `on*`.
- Sem `|raw` novo: o JSON da cena entra por `|e('html_attr')` e o textarea usa autoescape HTML.
- Texto do usuário vai para `textContent`; nunca é interpretado como markup.
- Import fail-closed; cena corrompida não executa nada e não derruba o formulário.
- `MAX_LAYERS`, `SCENE_MAX_BYTES` e limites numéricos evitam abuso de render e de payload.

## 8. Estado do repositório e validação

Base inspecionada: `main` em `d68ce60ff0a42234f551ace4e919c38cb06b6e67` (pai `cd92573c…`, frente X aplicada **7/7** no raw). Os caminhos desta frente foram triados como **livres** nos 14 manifestos. Nenhum manifesto, hash de release gate, `schema.sql`, `F9-27-Base-Hashes.json`, `tools/decision-matrix.json` ou `release_approved` foi reescrito.

Validação executada nesta frente: A/B real com bootstraps separados HEAD/pacote, réplica A/B com guards, harnesses PHP (WASM 8.5.12), Twig 3.14, jsdom 29.1.1, `node --check`, `php -l`, CRC do ZIP e `sha256sum -c`. Os números exatos estão no `RELATORIO.md` da entrega.

**Indisponível neste ambiente:** PHP nativo, Composer, PHPStan, rsync, navegador real, MySQL, sessão autenticada, migration, staging, deploy, E2E e CI remoto verificado. PHP WASM/Twig/jsdom com mocks **não são um servidor**. Nenhum status `PENDING_DECISION`, `STATIC_ONLY`, `NOT_RUN`, `SKIP` ou `BLOCKED_NOT_RUN` foi convertido em `PASS`. Nenhuma escrita no GitHub foi feita por nós.

---

## Frente Z — KAZTheme-Editor como motor total (dono absoluto)

Antes da frente Z, `theme_form.twig` era o dono da página: renderizava 14 cards
e incluía `design/kaz_theme_editor.twig` como **um campo a mais**, na linha 183,
dentro do `<form>` principal. O editor era uma parte do criador de temas.

A frente Z **inverte a hierarquia**:

| Antes | Depois |
| --- | --- |
| `theme_form.twig` = 198 linhas, 14 cards, formulários, pacote, prévia, histórico | `theme_form.twig` = 3 linhas: `header`, `{% include 'design/kaz_theme_editor.twig' %}`, `footer` |
| `kaz_theme_editor.twig` = um card incluído dentro do form | `kaz_theme_editor.twig` = **a página inteira**: workspace Penpot + os 14 módulos + pacote + iframe + histórico |
| Editor subordinado | Editor `#kaz-theme-editor` com `data-kaz-owner="1"` e `<form id="kaz-theme-form">` filho |

Os 14 módulos na sidebar esquerda, todos dentro do mesmo workspace:

`Design` (canvas/camadas) · `Geral` · `Herança` · `Tokens` · `Three.js` ·
`Cena 3D` · `Seções` · `Agendamento` · `Mídia` · `Modelos` · `Sites` ·
`Componentes` · `Condições` · `Prévia` · `Templates`.

**Contrato de não-regressão.** Os 14 cards foram movidos **byte a byte**: os
52 `name="..."` do formulário original permanecem idênticos, assim como
`csrf_token` nos 3 tipos de formulário, `can_modify`/`disabled`,
`formaction="{{ compare_url }}" formtarget="theme-preview"`, todos os `data-*`
dos cards (`data-sites`, `data-models`, `data-snippets`, `data-thumb-base`,
`data-schedule-card`, `data-spec`, ...), `button_save`/`button_back`, `cancel`,
`error`/`notice`, `revisions[].restore`, o `<iframe name="theme-preview" sandbox="">`
e `{{ footer|raw }}`.

**Sem formulários aninhados.** O `<form id="kaz-theme-form">` principal é
**irmão** dos demais: o formulário de importação de pacote
(`action="{{ import_url }}" data-theme-package-form`) e os formulários de
restauração de revisão ficam depois do `</form>` principal, dentro de
`.kaz-extra`, mas ainda dentro de `#kaz-theme-editor`. O botão Salvar da barra
superior usa `form="kaz-theme-form"`, sem duplicar campos e sem alterar o POST.

**Estado de alterações.** `dirty()` e `data-kaz-dirty` continuam sendo
**exclusivamente** o estado de `kaz_scene_json` (contrato da frente X). A frente
Z acrescenta apenas um selo informativo separado, `data-kaz-form-dirty`, que
observa os demais campos do formulário. Nenhum dos dois bloqueia o envio.

**Degradação.** Sem JavaScript, todos os módulos são exibidos empilhados —
nenhum campo fica inacessível. Zoom, pan, aba ativa, ferramenta e módulo ativo
continuam sendo estado local descartável: não entram em `kaz_scene_json`, não
usam `localStorage` e não alteram o `baseline`.

**Fora de escopo.** Sem migration, schema, coluna, `custom_js`, chave de
tradução, manifesto, `|raw` novo, upload, WebGL/Three.js real, cena pública,
CDN, fontes remotas, `<script>` inline, `on*`, `innerHTML`, `eval` ou `fetch`.
Nenhum `PENDING_DECISION`, `STATIC_ONLY`, `NOT_RUN`, `SKIP` ou
`BLOCKED_NOT_RUN` foi convertido em `PASS`; `release_approved` permanece
`false`. **CI verde ou health check não significa release aprovada.**

---

## Frente AA — interatividade total, tamanhos de tela e página cheia

A frente Z entregou a interface esperada, mas alguns controles do workspace
ainda eram decorativos (avatar, "Página 1", swatches, tokens, amostra
tipográfica, selo de status, painel Protótipo). A frente AA **dá função real a
todos eles** e acrescenta três capacidades pedidas.

### Todos os botões e recursos operacionais

| Controle | Antes | Agora |
| --- | --- | --- |
| `Página 1` | `<span>` estático | botão `data-kaz-action="pages"` → abre Páginas + volta ao canvas |
| Avatar `1` | `<span>` estático | botão `data-kaz-action="users"` → informa a sessão local (sem rede) |
| Selo `Alterações não salvas` | texto | botão `data-kaz-action="revert"` → descarta alterações da cena ao baseline |
| Selo `Salvo` | texto | `<button type="submit" form="kaz-theme-form">` → salva o tema sem JS |
| 6 swatches | `<span>` | `<button data-kaz-swatch>` → aplica a cor à camada selecionada |
| Tokens `primary`/`surface` | texto | `<button data-kaz-token-apply>` → aplica o token à camada |
| Amostra `Aa` | texto | botão `data-kaz-action="add-text"` → insere camada de texto |
| Páginas | texto | botão `Ir para o board` → Design + Camadas + ajuste de zoom |
| Protótipo | 3 parágrafos | 3 botões reais: prévia do tema, ajustar zoom, tela cheia |
| Exportar cena | só preenchia o textarea | também baixa `kaztheme-scene.json` quando o navegador oferece `Blob`/`createObjectURL`; sem a API, o textarea continua sendo a fonte |
| Desfazer/refazer, duplicar, excluir, ordem, visibilidade, bloqueio, 6 blocos, 5 kinds, 8 abas, 15 módulos, zoom/pan/réguas/Inspect | já funcionais | **inalterados** |

Os botões dos 14 módulos legados (`data-section-move`, `data-media-insert`,
`data-component-insert`, `data-site-probe-button`, `data-model-apply`,
`data-scene-preset`, `data-tcb-*`, `data-theme-package-form`) continuam sendo
atendidos pelos seus próprios scripts já existentes — nenhum foi reimplementado.

### Ajuste do tamanho das telas

Uma `.kaz-screenbar` no centro do workspace oferece 7 tamanhos: **Tema (cena)**,
Desktop `1440×900`, Laptop `1280×800`, Tablet `768×1024`, Mobile `390×844`,
Widescreen `1920×1080` e **Personalizado** (largura `320–3840`, altura
`240–2160`). O board da cena passa a ficar dentro de um `.kaz-screen` com as
dimensões escolhidas, o rótulo e o statusbar acompanham, e `Ajustar à tela`
recalcula o zoom pelo **tamanho da tela**, não pelo canvas.

**O tamanho da tela NÃO altera `scene.canvas`**: é um frame de visualização,
estado local descartável como zoom/pan/abas/módulo. `fitZoom()` continua idêntico
no boot porque o preset padrão é `Tema` (= `scene.canvas`).

A mesma barra controla a **densidade das colunas** — Compacto `200/220`, Normal
`236/264`, Amplo `300/340` — e dois botões para **ocultar/mostrar** cada sidebar.
Atalhos novos: `F` tela cheia, `[` compacto, `]` amplo, `Shift+1..6` presets.

### Tela cheia em outra página

- **`⛶ Tela cheia`** — Fullscreen API (`requestFullscreen`) nesta aba, com
  fallback para o modo CSS quando a API não existe.
- **`↗ Tela cheia em nova página`** — `<a target="_blank"
  rel="noopener noreferrer" href="{{ action }}&amp;kaz_fullscreen=1">`.
  O controller lê apenas esse GET, publica `kaz_fullscreen_page` e o
  `theme_form.twig` **omite `{{ header|raw }}` e `{{ footer|raw }}`** nesse modo.
  O shell recebe `data-kaz-page-mode="fullscreen"` e ocupa `100vw × 100vh`, sem
  chrome do Admin. Um link `Sair da página cheia` volta à rota normal.
- Não há rota nova, controller novo, migration, sessão compartilhada ou
  transporte de estado: a nova página carrega o **estado gravado no servidor**.
  Alterações não salvas devem ser salvas antes de abrir a outra página — o selo
  `Alterações não salvas` e o botão `Salvo` estão na navbar para isso.
- Os **dois** `|raw` (`header`/`footer`) continuam sendo os já existentes, agora
  condicionados; o shell do editor permanece com **zero** `|raw`.

### Contrato

`dirty()` e `data-kaz-dirty` continuam exclusivos de `kaz_scene_json`;
`data-kaz-form-dirty` continua separado. `syncField()`, `submit`, `baseline`,
`MAX_LAYERS`, fail-closed, os 6 blocos, os 14 módulos, os 53 `name=`, os 3
formulários irmãos, o iframe `sandbox=""`, CSRF, `can_modify` e
`formaction/formtarget` foram preservados. Nenhum controle novo tem `name=` —
o POST é byte-idêntico ao da frente Z. Cache key `-kaz-theme-editor-z` →
`-kaz-theme-editor-aa`.

Sem migration, schema, coluna, `custom_js`, `|raw` novo, chave de tradução,
manifesto, `localStorage`, CDN, fonte remota, `<script>` inline, `on*`,
`innerHTML`, `eval`, `fetch`, upload, WebGL/Three.js real, cena pública ou
escrita remota. Nenhum `PENDING_DECISION`, `STATIC_ONLY`, `NOT_RUN`, `SKIP` ou
`BLOCKED_NOT_RUN` virou `PASS`; `release_approved` permanece `false`.
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


## Frente AF — prévia e publicado mostram a mesma cena (2026-11-05)

**Problema medido no `main` `5932b910aca5`.** `ThemeSnapshot::resolve()` já
compilava `kaz_scene` em `scene_view`/`scene_css`, e o catálogo publicado
(`startup/theme.php` → `header.php` → `home.php` → `home.twig` →
`partial/theme-scene-block.twig`) renderizava `.rms-scene*`. Mas a tela de
**Comparar** e o **Pré-visualizar** não entregavam essa cena ao Twig:
`Twig::renderSandboxPreview()` fixava `theme_scene=['enabled'=>false]` e não
definia `theme_scene_view`; `ThemeCompareView::panel()` não carregava nenhuma
regra `.rms-scene*`. Consequência: o usuário desenhava a composição no editor,
salvava, pré-visualizava e **não via nada** — só depois de Publicar a cena
aparecia. Console limpo, sem erro, sem efeito visível.

**Correção (4 arquivos, sem migration, sem rota nova, sem `|raw` novo):**

1. `dev/system/library/template/twig.php` — `renderSandboxPreview()` ganha 3º
   argumento opcional `$sceneView` e publica `theme_scene_view` no contexto
   sandbox. Novo `previewSceneView()` normaliza a cena **fail-closed**: canvas
   320–3840 × 240–2160, até 48 camadas, `kind`/`role` por allowlist, `fill`
   `#rrggbb`, percentuais 0–100, `radius` 0–64, `font` 8–72, texto ≤80 com
   caracteres de controle removidos. Sem HTML, sem caminho, sem executável.
   Assinatura compatível: chamadores legados sem o 3º argumento continuam
   válidos e recebem cena desabilitada.
2. `dev/admin/controller/design/theme.php` — `compare()` passa o `scene_view`
   **de cada estado** (`$pubState` / `$draftState`) a `renderPreviewFragment()`;
   `preview()` compila `kaz_scene` do rascunho com `ThemeSceneCss` e injeta o
   `<style>` estático da cena. Cache de assets `-ae` → `-af`.
3. `dev/system/library/theme/theme_scene_css.php` — novo `previewCss()` devolve
   as regras `.rms-scene*` **literais/estáticas** (sem `url()`, `@import`,
   `expression`, tags ou dado do usuário), espelhando `theme.css` do catálogo.
   Inclui `container-type:inline-size` e `@container`: **medido no Chromium**,
   sem elas o texto de 32px ficava cortado num painel de 666px. O limite sobe
   de 620px para 1200px apenas na prévia, porque o catálogo chega a 1200px e
   cada painel de comparação é mais estreito.
4. `dev/system/library/theme/theme_compare_view.php` — um único `<style>` com
   `previewCss()` no `<head>`; o `scene_css` allowlistado (`--rms-scene-*`) vai
   para o atributo `style` de cada painel; fallback `sceneBlock()` renderiza a
   cena quando um template customizado não inclui o partial. **Sem** `<script>`,
   `srcdoc`, `iframe`, `form`, `|raw`; CSP `default-src 'none'; img-src data:;
   style-src 'unsafe-inline'` intacta. `sceneBlock()` não duplica quando o
   fragmento já traz `rms-scene`.

**Prova ponta a ponta** (`tools/kaz-theme-preview-parity.php`, 43 asserções):
`ThemeSceneCss::compile()` → `previewSceneView()` → `home.twig` real em Twig
sandbox → `ThemeCompareView::render()` → extração das camadas → **as 6 camadas
da prévia são idênticas, em estilo e texto, às 6 do HTML publicado**. O mesmo
template renderizado **sem** `theme_scene_view` produz zero `.rms-scene` (a
lacuna da AE reproduzida). `tools/verify-kaz-preview.cjs` repete a comparação
em Chromium real (desktop 1440×900 e mobile 390×844), 22 asserções, compara
também a **cor computada** e prova que **nenhum texto foi cortado**; salva
screenshots. `ThemeSnapshot::diff()` acusa `kaz_scene.layers.N.text` entre
publicado e rascunho. A única diferença visual restante é a tipografia
responsiva (16px no painel vs 32px no catálogo), deliberada e prevista no
próprio `theme.css`.

**Escopo:** nenhuma coluna, tabela, migration ou `schema.sql` foi alterado —
`kaz_scene` continua vivendo em `draft_config`/`published_config`. Nenhum
manifesto, hash congelado, tradução ou `PENDING_DECISION` foi tocado.
Aprovação de release permanece `false`; esta frente **não** foi avaliada em
sessão Admin autenticada nem no servidor de produção.


## Frente AG — prévia e comparação consomem o estado efetivo do tema (2026-11-05)

**Problema medido no `main` `2494669eda6e` (AF aplicada, 18/18 idêntica).**
`ThemeSnapshot::resolve()` — a única fonte de verdade do que o visitante vê —
devolve `token_css`, `component_css`, `schedule_css`, `media_css`, `site_css`,
`scene_css`, `primary`, `surface`, `vars`, `flags`, `classes`, `tokens`,
`globals` e `scene_view`. `startup/theme.php` publica tudo isso e
`common/header.php` concatena as seis partes de CSS no `style` do body, nesta
ordem: tokens → componentes → agenda → mídia → sites → cena.

Mas a prévia e os painéis só consumiam **duas** dessas partes:
`ThemeCompareView::panel()` usava `token_css` + `scene_css` (AF), e
`preview()` injetava apenas `ThemeSceneCss::previewCss()`. Pior:
`Twig::renderSandboxPreview()` fixava `theme=['primary'=>'#2563eb',...]` e
deixava `vars`/`flags`/`classes`/`tokens`/`sections` vazios. Consequência: a
AF fez a **cena** aparecer, mas alterar cor primária, tipografia, densidade,
radius, componentes, agenda ou sites no editor **continuava sem nenhum efeito
na prévia** — só depois de Publicar. A mesma perda silenciosa, um passo acima.

**Correção (5 arquivos, sem migration, sem rota nova, sem `|raw` novo):**

1. **Novo** `dev/system/library/theme/theme_preview_state.php` —
   `ThemePreviewState` traduz o estado em: `css()` (as seis partes, na ordem
   do `header.php`, **fail-closed por parte**: descarta `<`, `>`, `{`, `}`,
   barra invertida, `javascript:`, `@import`, `expression(`, `behavior:`),
   `colorVars()`, `bodyClasses()` (classes allowlistadas + `rms-theme-rollout`
   + `rms-site-*`), `globals()` (somente chaves `theme`/`theme_*`, planas,
   escalares ≤256 — `header`/`footer` do sandbox jamais sobrescrevíveis) e
   `catalogCss()` (o `theme.css` real do catálogo, leitura limitada e
   fail-closed; sem ele as variáveis existiriam mas nenhuma regra as usaria).
2. `dev/system/library/template/twig.php` — `renderSandboxPreview()` ganha 4º
   argumento **opcional** `$themeState` e aplica os globais allowlistados ao
   contexto. A cena sanitizada da AF continua prevalecendo.
3. `dev/admin/controller/design/theme.php` — `preview()` resolve o rascunho
   com o **mesmo** `ThemeSnapshot` que `compare()` já usava, injeta
   `catalogCss() + previewCss()` no `<style>`, `css()+colorVars()` no `style`
   do body, `bodyClasses()` na classe e passa o estado ao sandbox;
   `compare()` repassa `$pubState`/`$draftState` a `renderPreviewFragment()`;
   cache de assets `-af` → `-ag`.
4. `dev/system/library/theme/theme_compare_view.php` — `panel()` usa
   `ThemePreviewState::css()` (todas as seis partes) + `colorVars()`, corrige
   os nomes das variáveis para os realmente emitidos pelo `TokenRegistry`
   (`--rms-light-text`/`--rms-light-surface`), faz o `border-radius` consumir
   `--rms-radius` e aplica `bodyClasses()` na classe do painel. CSP, sandbox,
   autoescape e proibições intactos.
5. Ferramentas: `tools/kaz-theme-preview-state.php` (PROBE somente leitura) e
   `tools/verify-kaz-preview-state.cjs` (Chromium real).

**Prova:** dois estados A/B com tokens diferentes (radius 12→32px, fonte
16→20px, cor `#2563eb`→`#dc2626`, componente habilitado) geram CSS diferente;
o Chromium mede `getComputedStyle` e confirma que **a prévia muda quando o
rascunho muda**, e que `ThemePreviewState::css($state)` é **idêntico** à
concatenação que `header.php` publica. Regressões A–AF preservadas.

**Escopo:** nenhuma migration, coluna, `schema.sql`, manifesto, hash
congelado, tradução ou `PENDING_DECISION` alterado. `release_approved`
permanece `false`. Aprovação em produção e o veredito `__kazHealth()` do
servidor real **continuam pendentes**.


## Frente AH — Publicar com prova e estado da liberação visível (2026-11-05)

**Problema medido no `main` `8cfce45dae00` (AG aplicada, 19/19 idêntica).**
`activate()` copia `draft_config` → `published_config`/`config` e
`getEffectiveTheme()` lê `published_config`; o elo está correto no código, mas
**nunca foi exercitado por nenhum harness** e, principalmente, **a tela do
Admin não dizia se havia rascunho pendente**. O usuário editava, salvava,
pré-visualizava e não tinha como saber se o que via já era o que o catálogo
renderizaria. O instrumento da AE (`__kazHealth()`) exige abrir o console e
colar um JSON — desde AE até AG ele nunca foi colado.

**Correção (3 arquivos de código + 1 novo, sem migration/rota/tradução):**

1. **Nova** `ThemeReleaseProbe::summarize()` — **pura** (sem IO, banco,
   sessão, request, eval). Compara `draft_config` e `published_config` por
   primeiro nível canonizado (`ksort` + serialização determinística) e devolve
   **apenas escalares allowlistados**: `verdict`
   (`PUBLICADO`/`RASCUNHO_PENDENTE`/`SEM_TEMA_ATIVO`), `pending`,
   `pending_count`, `pending_keys`, `css_parts` (quantas das 6 partes de CSS da
   AG estão presentes), `css_bytes`, `scene_layers`, `asset_ok`, rótulo e dica.
2. `design/theme.php::edit()` — publica `kaz_release` no `$data` a partir do
   tema já carregado, do `ThemeSnapshot` e da cena; cache `-ag` → `-ah`.
3. `kaz_theme_editor.twig` — bloco `#kaz-release-status` com `role="status"`,
   autoescape em todos os valores, **sem `|raw`, sem `name=`, sem
   `data-kaz-module` e sem chave de tradução nova**. É **irmão** do
   `.kaz-shell`, não filho: não altera a árvore medida pela AC.

**Por que server-side:** o bloco aparece **mesmo com JavaScript desabilitado**.
É a resposta direta ao cenário "nenhuma ferramenta responde e não existe erro
no console" (AD): o veredito não depende de `bootDone`, de asset ou de
`__kazHealth()`.

**Prova:** `tools/kaz-theme-release-probe.php` constrói rascunho B e
publicado A, mostra `RASCUNHO_PENDENTE`, simula `activate()` (`published = draft`)
e mostra `PUBLICADO`; verifica estaticamente as duas SQL dos models; e prova
que `ThemePreviewState::css(resolve(rascunho))` é **idêntico** a
`css(resolve(published_config após ativar))` — ou seja, **a prévia e a home do
catálogo renderizam o mesmo tema**. `tools/verify-kaz-release.cjs` confirma no
Chromium que o painel está visível com JS ligado **e desligado**.

**Escopo:** nenhuma migration, coluna, `schema.sql`, manifesto, hash
congelado, tradução, `PENDING_DECISION` ou `release_approved` alterado.
`release_approved` permanece `false`; aprovação em produção e o veredito
`__kazHealth()` do servidor real continuam pendentes.


## Frente AI — Ciclo real de criação de tema (2026-11-05)

**Base:** `main` `39697000fd4057a3f5ab220d50c0fffc4d1d8df0` (AH aplicada;
auditoria 19/19 arquivos AH byte a byte, tree 3226 entradas / 2643 blobs).

Depois de A–AH corrigirem layout, boot, cena, estado efetivo e liberação,
faltava uma coisa que nenhum harness tinha feito: **executar o controller**.
A AH simulava `activate()` por atribuição de array; a AF/AG renderizavam
prévias isoladas. Ninguém havia provado que o POST do formulário chega ao
banco, que Publicar copia o rascunho para o publicado e que a **home do
catálogo** renderiza a cor, o raio e a fonte escolhidos no editor.

`tools/kaz-theme-lifecycle-probe.php` executa o **código real do projeto**:
`buildDraftConfig()` (método privado, via Reflection) com os 11 serviços reais
do `framework.php` registrados no `Registry`; `ModelDesignTheme::save()`;
`Controller::activate()` com CSRF e ACL reais; `ModelCommonTheme::
getEffectiveTheme()`; `ThemeSnapshot::resolve()`; `ThemePreviewState::css()`;
e a tag `<body>` do `header.twig` real renderizada por Twig 3. As **SQL dos
models são reais**, interpretadas por um banco em memória. O POST é montado a
partir dos campos `name=` do formulário real.

`tools/verify-kaz-lifecycle.cjs` abre no Chromium a home **antes** e **depois**
de Publicar e mede `getComputedStyle()`: `rgb(37,99,235)` → `rgb(220,38,38)`,
raio `12px` → `32px`, fonte `16px` → `20px`. PROBE 54/0, Chromium 22/0.

**Esta frente não altera nenhum arquivo `dev/`.** Não há migration, coluna,
rota, tradução, `|raw` novo, asset novo nem quebra de contrato: é evidência
executável, não uma correção de produto. Não substitui MySQL, sessão Admin
autenticada, deploy ou avaliação visual do usuário. `release_approved`
permanece `false`.


## Frente AJ — Aceitação visual sem console (2026-11-05)

**Base:** `main` `50a386442653c5846899f2f71c2a6b9cc51e7e24` (AI aplicada;
auditoria 17/17 arquivos AI byte a byte, tree 3240 entradas / 2656 blobs).

A AE entregou `window.__kazHealth()` com 26 verificações em 4 estados, mas o
usuário precisa abrir o DevTools e colar um JSON — desde então isso nunca foi
feito. A AH deu um painel **server-side** de liberação (rascunho/publicado),
mas não dizia se o **editor** tinha inicializado. E havia uma lacuna medida:
`kaz-theme-editor.js` marcava `bootDone = true` **antes** de
`if (!canvas || !layerList || !jsonBox) return;`. Se o boot abortasse no meio,
o fail-loud de 500 ms não disparava e a tela ficava silenciosa outra vez.

**Correção:** `#kaz-acceptance`, irmão do `.kaz-shell`, emitido pelo servidor
no estado `aguardando` (amarelo), com `<noscript>` explícito. Um novo sinal
`bootComplete` só fica `true` **no fim** do boot; ali o painel passa a
`pronto` (verde) e mostra, em `textContent`, o asset servido, o número de
módulos e camadas, o veredito da AH e se prévia e catálogo usam o mesmo tema.
Se o boot falhar, o fail-loud existente continua e o painel passa a `falhou`
(vermelho) com a causa — **sem console, sem JSON para colar**.

DOM puro (`textContent` + `setAttribute`): sem `innerHTML`, `eval`, rede,
`localStorage`, `|raw` novo, chave de tradução, rota Admin, migration ou
coluna. Cache do editor passa de `-ah` para `-aj`.

**Prova:** `tools/kaz-theme-acceptance-probe.php` (45/0) e
`tools/verify-kaz-acceptance.cjs` (31/0) medem **5 cenários** no Chromium:
boot normal, JavaScript desligado, asset 404, boot abortado no meio (o caso
que a AE não detectava) e um **controle com o JS da AH** que reproduz o
silêncio original. Com asset 404 nenhum JavaScript roda — nem o fail-loud —
e o painel amarelo server-side é justamente o aviso que resta. Com boot
abortado, o painel fica vermelho. `release_approved` permanece `false`.

## Frente AL — Aparência e ergonomia visual (2026-11-05)

Camada **aditiva**: `kaz-theme-editor-ergonomia.css` e
`kaz-theme-editor-ergonomia.js` são carregados **depois** dos assets `-aj`,
com o marcador de cache `-aj-al`. O CSS, o JS, o Twig e o painel de aceitação
da AJ **não foram alterados** — os `tools/` e os harnesses A–AJ seguem válidos.

| O que | Como |
| --- | --- |
| Barras de rolagem conformes ao tema | `scrollbar-color` + `::-webkit-scrollbar-*` em todos os containers roláveis; `color-mix()` sobre `--kaz-al-primary` / `--kaz-al-surface`, preenchidos pelo JS a partir de `data-kaz-primary` / `data-kaz-surface` e atualizados **ao vivo** ao editar os campos de cor. `--rms-color-*` / `--rms-radius` também são honrados quando presentes. |
| Tamanho de todos os elementos | `--kaz-ui` com 4 níveis (compacto .86 / normal 1 / confortável 1.14 / amplo 1.30) escalando **em conjunto** tipografia, altura de botões, ícones, abas, gaps e paddings do chrome. O canvas não escala — o zoom da cena continua independente. |
| Expandir / recolher ferramentas | 4 botões na screenbar (Ícones, Abas, Status, Recolher tudo/Expandir tudo), estado em `data-kaz-fold`, `aria-pressed` e `role="status"`. A screenbar nunca é recolhida, portanto a reabertura está sempre disponível. |

Com `normal` todos os `calc()` resolvem nos valores originais do CSS, e as
regras responsivas da AC (36/38/28 px) foram replicadas com a mesma escala
para não cortar controles ampliados. DOM puro (`createElement`, `textContent`,
`setAttribute`): sem `innerHTML`, `eval`, `fetch`, `localStorage`, migração,
coluna, rota Admin, tradução nova, `|raw` novo ou `name=` novo. Preferências
são estado local descartável, como densidade e fullscreen da AA.

**Prova:** `tools/kaz-theme-ergonomia-probe.php` e
`tools/verify-kaz-ergonomia.cjs` (Chromium, 5 cenários + 390 px). `release_approved`
permanece `false`; isto é apresentação, não aprovação de release.
