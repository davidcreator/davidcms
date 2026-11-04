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
