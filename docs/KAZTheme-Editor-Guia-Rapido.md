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
