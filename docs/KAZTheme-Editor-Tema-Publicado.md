# KAZTheme-Editor — Tema Publicado de Verdade (Frente AB)

> **O que você desenha no editor passa a aparecer no site publicado.**
> O workspace fica responsivo dentro do Admin, os 14 módulos viram abas e o
> boot não empilha mais formulários sobre o canvas.

## 1. Pedido

1. *"Ele não se auto ajusta ao tamanho de telas"* → §3.
2. *"As telas ficam atropeladas"* → §2 e §3.
3. *"Nada funciona… nenhuma funcionalidade tem resultado para criação de temas"* → §4.

Decisões confirmadas: **gerar tema publicado de verdade**, **dentro do Admin
mas 100% responsivo**, **14 módulos mantidos em abas**.

Base: `main` @ `a408f928b380d93c710191e21ab70e360c86bc4e` (frente AA aplicada
pelo usuário, 3.177 entradas / 2.595 blobs / 14 manifestos / `truncated:false`).

## 2. O que a imagem realmente mostrava: o estado SEM JavaScript

A primeira leitura sugeriu que `render()` não chamasse `paintModules()`.
**Não era esse o caso**: `paintChrome()` (já chamado por `render()`) sempre
incluiu `paintModules()`. Com o JS executando, os testes de DOM confirmam que
os 14 painéis ficam escondidos tanto no HEAD AB quanto no pacote.

O problema é que o **HTML entregue pelo servidor não vinha com `hidden`**.
Qualquer falha no carregamento ou na execução do `kaz-theme-editor.js`
(cache, 404, CSP, erro de outro script, JS desabilitado) deixava:

- os **14 formulários legados visíveis e empilhados** dentro do `.kaz-center`
  — exatamente "Nome", "Chave do tema", "Tokens de cor" e "Cena Three.js
  aprovada" da imagem;
- o **canvas SVG vazio** (ninguém desenhou as camadas), aparecendo só como a
  faixa branca fina no topo;
- **nenhum botão respondendo**, porque todos os handlers estão nesse arquivo.

Isso explica as três queixas de uma só vez: "atropelado", "nada funciona" e
"sem resultado". A conclusão prática é que **o resultado de um tema não pode
depender do JS do Admin**.

**Correção AB (fail-safe no servidor):**

- `.kaz-modules` e os 14 `[data-kaz-module-panel]` passam a sair do Twig com
  `hidden`. O JS apenas **alterna** o painel ativo; nunca é necessário para
  esconder no boot.
- `<noscript>` revela tudo de forma organizada, para nenhum campo ficar
  inacessível sem JavaScript.
- `render()` ganha `paintModules()` explícito e o boot marca
  `data-kaz-ready="1"` — defesa em profundidade, não a causa.
- **O resultado real foi movido para o servidor**: `ThemeSceneCss` compila a
  cena e o catálogo a renderiza. Salvar e Publicar funcionam mesmo que o JS
  do editor falhe, porque são submits nativos do formulário.

Os testes cobrem os dois estados: sem JS o HEAD AB mostra **14 painéis
visíveis** e o pacote AB mostra **0**; com JS, ambos mostram 0.

## 3. Layout responsivo e abas

| Antes (AA) | Agora (AB) |
| --- | --- |
| `.kaz-workspace { height: 548px }` fixo | `.kaz-shell` flex column, `height: calc(100dvh - 104px)`, `min-height: 520px` |
| `.kaz-screenbar` dentro do `.kaz-center` (sobrepunha réguas/viewport absolutos) | barra **externa**, antes do `.kaz-workspace` |
| réguas + viewport + `.kaz-modules` soltos no centro | todos dentro de `.kaz-stage` (`position:relative; flex:1; min-height:0`) — **um único scroll** |
| `.kaz-screen { display:flex }` + `.kaz-board { display:inline-block }` → canvas achatado | `.kaz-screen` em bloco, `.kaz-board`/`.kaz-board__frame` a `100%` |
| 15 links de módulo empilhados na sidebar | `.kaz-module-tabs`: **abas horizontais** com `overflow-x:auto` |
| `.kaz-io` (JSON bruto) e `.kaz-extra` sempre visíveis | dois `<details>` "Avançado", fechados por padrão |
| navbar com ~20 itens comprimidos | `flex-wrap: wrap`, Publicar em `<details>` de confirmação |

O grid usa `minmax(180px,220px) minmax(0,1fr) minmax(200px,260px)`; abaixo de
980px vira uma coluna com `grid-template-rows: auto minmax(420px,62vh) auto`.
Sem JavaScript, `.kaz-nojs` devolve os painéis ao fluxo — nenhum campo fica
inacessível.

## 4. A ponte com o catálogo (resultado real)

### Arquivos novos

- `dev/system/library/theme/theme_scene_css.php` — `ThemeSceneCss::compile()`.
- `dev/catalog/view/template/partial/theme-scene-block.twig` — HTML estático
  que itera as camadas.

### Compilação (segura por construção)

`ThemeSceneCss::compile($config['kaz_scene'])` reusa
`ThemePackage::normalizeScene()` — a mesma validação fail-closed do pacote
(`version=1`, canvas 320–3840 × 240–2160, grid 8, 5 kinds, 5 roles, até 48
camadas, 65.536 bytes). Saída:

```
--rms-scene-w:960;--rms-scene-h:540;--rms-scene-grid:8;
--rms-scene-1-fill:#2563eb;--rms-scene-1-radius:16px;--rms-scene-1-font:24px;
... ;--rms-scene-count:3;
```

A whitelist final (`/[^A-Za-z0-9#:.;\-]/`) barra `{`, `}`, `<`, `>`, aspas,
`url(`, `expression(` e barra invertida. **Não há seletores nem HTML no CSS** —
são declarações que vão para o atributo `style` do `<body>`, já escapadas por
`|e('html_attr')` em `common/header.twig`.

`layers` traz, por camada visível: porcentagens relativas ao canvas (3 casas),
`fill` hex validado, `radius` 0–64, `font` 8–96, `kind`/`role` allowlistados e
`text` truncado a 80 caracteres, sem caracteres de controle. O Twig emite
`{{ l.text }}` com **autoescape** — nenhum `|raw` novo em todo o pacote.

### Cadeia de publicação

```
edit() POST  -> draft_config.kaz_scene
Publicar     -> design/theme.activate (rota existente, CSRF)
             -> published_config = draft_config, status = 1
startup/theme-> ThemeSnapshot::resolve() -> scene_css + scene_view
             -> config: catalog_theme_scene_css / catalog_theme_scene_view
common/header-> theme_token_css .= scene_css   (style do body)
common/home  -> theme_scene_view
partial      -> <section class="rms-scene"> com as camadas
theme.css    -> posicionamento responsivo
```

`ThemeSnapshot` ganhou um **9º parâmetro opcional** `?ThemeSceneCss`, com
instância padrão — nenhuma chamada existente precisou mudar. O `startup`
publica duas chaves novas de config (sem migration, sem coluna, sem tabela).

### Fluxo na interface

- **Salvar rascunho** — `submit` do `#kaz-theme-form` (já existente).
- **Publicar** — `<details>` de confirmação + `<button type="submit"
  form="kaz-theme-form" formaction="{{ activate_url }}" formmethod="post">`.
  `activate()` já valida permissão e CSRF e já copia o rascunho para o
  publicado. Sem rota nova, sem `on*`, sem JS.
- **Prévia** — módulo `comparar` / iframe `sandbox=""` já existentes.
- O selo `PUBLICADO/RASCUNHO` e `has_unpublished_changes` continuam sendo a
  fonte de verdade do estado.

## 5. Contratos preservados

- `theme_form.twig`: 3 linhas, 2 `|raw` existentes, `kaz_fullscreen_page`.
- `#kaz-theme-editor` `data-kaz-owner="1"`, `<form id="kaz-theme-form">`
  filho, 3 formulários irmãos sem aninhamento, CSRF, `can_modify`,
  `formaction`/`formtarget`, iframe `sandbox=""`, pacote/restores.
- 14 `data-kaz-module-panel`, 30 `data-kaz-module`, 53 `name=` no shell,
  8 abas, 15 ícones SVG, 3 zoom AA + tela cheia/página separada, 7 presets
  de tela, densidade e colapso.
- `dirty()` exclusivo da cena; `data-kaz-form-dirty` informativo;
  `syncField()`/`submit`/`baseline`/`MAX_LAYERS`/fail-closed intactos.
- 418 chaves × 3 idiomas, 14 `CONFIG_KEYS`, 25 `TEMPLATE_KEYS`,
  14 manifestos, 11 hashes, 10 `PENDING_DECISION`, `schema.sql`,
  77 paths BDREditor, `release_approved:false`.
- Cache `-kaz-theme-editor-aa` → **`-kaz-theme-editor-ab`**.

## 6. Fora de escopo / limitações

Sem migration, schema, coluna, `custom_js`, `|raw` novo, chave de tradução,
manifesto, CDN, fonte remota, `<script>` inline, `on*`, `innerHTML`, `eval`,
`fetch`, `localStorage`, upload, WebGL/Three.js real ou escrita remota.
A cena **não** substitui os templates Twig do catálogo nem gera páginas
novas: ela adiciona uma composição visual responsiva à home (e suas cores
se integram aos tokens `--theme-*` já existentes). PHP WASM, Twig e jsdom com
mocks não são um servidor; a verificação em navegador real continua sendo
necessária antes de qualquer release.

Nenhum `PENDING_DECISION`, `STATIC_ONLY`, `NOT_RUN`, `SKIP` ou
`BLOCKED_NOT_RUN` foi convertido em `PASS`. F9-27 segue `BLOCKED_NOT_RUN`;
BDREditor/F9-103 pausado; P2/F9-18 não reaberto. Nenhuma escrita no GitHub
foi feita por este pacote; CI remoto não verificado aqui.
**CI verde ou health check não significa release aprovada.**

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
