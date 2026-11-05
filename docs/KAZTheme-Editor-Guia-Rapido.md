# KAZTheme-Editor — guia rápido

## Workspace (frente Y)

A tela agora é um **workspace escuro de três colunas**, como o Penpot:

- **Topo:** marca, nome do arquivo, página, barra de ferramentas com ícones, desfazer/refazer, avatar e o status do arquivo (**Salvo** / **Alterações não salvas**).
- **Esquerda:** abas **Páginas**, **Camadas**, **Ativos**, **Tokens** e **Histórico**. Os 6 blocos da frente X ficam em **Ativos**, com miniaturas; **Tokens** mostra `primary`/`surface`; **Histórico** lista os estados locais do desfazer.
- **Centro:** réguas, viewport pontilhado (infinito) e o board **Tema · 960 × 540**. Use **+ / − / Ajustar** na barra inferior, `Ctrl` + roda do mouse, ou `+`, `-`, `0`. A ferramenta **Mão** (`H`) arrasta o canvas sem mover camadas; **Mover** (`V`) volta ao arraste normal.
- **Direita:** abas **Design**, **Protótipo** e **Inspecionar**. A aba Design tem as seções Layout (X/Y/W/H), Aparência, Tipografia, Elemento e Tema. Inspecionar mostra CSS e JSON **somente-leitura** da camada selecionada.
- **Rodapé:** zoom, coordenadas da seleção, status e o campo `kaz_scene_json` (exportar/importar).

Atalhos: `V` mover · `H` mão · `R` retângulo · `T` texto · `B` botão · `C` cartão · `S` seção · `+`/`-` zoom · `0` ajustar. Os atalhos anteriores continuam valendo. Protótipo/View mode/comentários **não existem**: a cena é dado de edição do tema, não um protótipo navegável.

## Onde fica

Admin → Design → Temas → editar um tema. O card **KAZTheme-Editor** aparece depois do editor de templates, **dentro do formulário principal**. O card de pacote de tema continua fora do formulário, como antes.

## Primeiro uso

1. Abra o tema. Se ele já tiver uma cena salva, o card mostra **"Cena salva neste tema"** e o canvas a reproduz. Temas mais antigos mostram **"Cena derivada do config"** com cinco camadas geradas a partir das cores, seções e componentes — basta salvar uma vez para gravar a cena própria.
2. Clique numa camada no canvas ou na lista à esquerda para selecioná-la. O inspector à direita mostra X, Y, largura, altura, cor, raio, fonte, texto, tipo, papel no tema e ID allowlistado.
3. Arraste a camada no canvas para mover; arraste o quadrado azul no canto inferior direito para redimensionar. Tudo com snap na grade de 8 px.
4. Use **+ Retângulo / Texto / Botão / Cartão / Seção** para criar camadas. **Duplicar**, **Excluir**, **↑**, **↓**, **Ocultar** e **Bloquear** agem sobre a seleção.
5. Na aba **Ativos**, clique em **Hero / Cartão / Faixa / Título / Galeria / CTA** para inserir um conjunto pronto de 2–3 camadas. Elas entram no canvas, na árvore, no histórico e no campo salvo com o tema. O bloco não muda o formulário sozinho.
6. **Desfazer** / **Refazer** ou `Ctrl+Z` / `Ctrl+Shift+Z`. Outras teclas: `Ctrl+D` duplica, `Delete` exclui, setas movem (com `Shift`, 32 px). Atalhos são ignorados enquanto você digita num campo.

## Alterações não salvas

O selo **Alterações não salvas** aparece no cabeçalho do card assim que a cena
difere da última gravação. Ele **não aparece ao abrir a página**, desaparece ao
salvar o tema e também some se você desfazer até voltar ao estado carregado.
É apenas um lembrete local: quem valida e grava continua sendo o servidor.

## Salvar a cena

O textarea **"Cena kaztheme-scene/v1 — campo salvo com o tema"** é um campo real do formulário (`name="kaz_scene_json"`). O editor o mantém sincronizado automaticamente a cada alteração e também no momento do submit — inclusive no botão de comparar/prévia.

- **Salvar o tema** grava a cena na chave `kaz_scene` do config, validada no servidor.
- Cena inválida (JSON quebrado, enum desconhecido, cor fora do padrão, ID duplicado, limite excedido) **impede o salvamento** com a mensagem de validação já existente; nada é gravado.
- **Exportar cena** / **Importar cena** continuam funcionando offline para copiar, colar e versionar o JSON.
- **Exportar pacote de tema** inclui a cena; **importar pacote** a revalida pelo checksum e pelo contrato. Child-themes substituem a cena integralmente, sem mesclar pela metade.

## Aplicar ao tema

Defina o **Papel no tema** da camada:

- `primary` ou `surface` → a cor da camada é escrita nos campos `primary` / `surface`.
- `section` → preencha **ID allowlistado** com um ID real de seção (por exemplo `hero`); a camada marca `sections[]` e entra em `sections_order`.
- `component` → preencha **ID allowlistado** com um ID real de componente; a camada marca `components[<id>][enabled]`.

Abrir a tela **não muda nada** no formulário: a propagação só acontece quando você edita cor/papel, clica em **Aplicar ao tema** ou importa uma cena. Seções e componentes são marcados sem desmarcar os já salvos. A validação do servidor (`ThemePackage::validateConfig()`, `SectionRegistry::formOrder()`, `ComponentRegistry::normalize()`) continua soberana.

## Arquivos para editar (estrutura fácil)

| Quer mudar... | Arquivo |
| --- | --- |
| Tipos de camada, validação cliente, atalhos, histórico, blocos, selo e workspace | `dev/admin/view/js/kaz-theme-editor.js` |
| Tema escuro, 3 colunas, réguas, viewport, responsivo | `dev/admin/view/css/kaz-theme-editor.css` |
| Rótulos, campos do inspector e note de estado | `dev/admin/view/template/design/kaz_theme_editor.twig` |
| Leitura/gravação da cena e assets | `dev/admin/controller/design/theme.php` |
| Contrato servidor, allowlist e normalização | `dev/system/library/theme/theme_package.php` |
| Contrato e limites | `docs/KAZTheme-Editor.md`, `docs/KAZTheme-Editor-Cena-Persistente.md` |
| Presets dos blocos | `docs/KAZTheme-Editor-Biblioteca-de-Blocos.md` |
| Workspace e mapeamento Penpot | `docs/KAZTheme-Editor-Interface-Penpot.md` |

## Limites que continuam valendo

A cena ainda **não é renderizada no site público** — ela é dado de edição/versionamento; o visual do visitante vem dos campos allowlistados. O BDREditor (F9-103) não foi tocado. F9-27 permanece `BLOCKED_NOT_RUN` com `release_approved:false`. A limitação de hreflang da frente N permanece. Não houve migration, schema, coluna, `custom_js`, upload, WebGL, Three.js, chave de tradução nova, escrita remota ou deploy. CI verde/health coerente não é release aprovada.

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
