# ReamurCMS — Frente AB · Tema Publicado de Verdade + Workspace Responsivo

> **O que é desenhado no editor passa a aparecer no site publicado.**
> Corrige o estado "sem JavaScript" que deixava a interface atropelada,
> organiza os 14 módulos em abas e torna o editor responsivo dentro do Admin.

| | |
| --- | --- |
| Base | `main` @ `a408f928b380d93c710191e21ab70e360c86bc4e` (frente AA aplicada pelo usuário) |
| Árvore | 3.177 entradas / 2.595 blobs / 14 manifestos / `truncated:false` |
| Arquivos | **19** completos (16 substituições + 3 novos) + `RELATORIO.md` + `INDICE.md` |
| Recorte | 2198 arquivos / 31,020,174 bytes no HEAD AB |
| Réplica | 2201 arquivos / 31,062,893 bytes (16 alterados + 3 novos + 2182 congelados) |
| Harnesses | **347 asserções / 0 falhas** (PROBE pacote 93, PROBE HEAD 28, DOM real 226) |
| Validação final | 130 OK / 0 FALHAS |
| Guards | **4/4 verdes e byte-idênticos** HEAD × réplica |
| Sintaxe | 388 PHP `php -l` 0 erros · 609 JS/CJS `node --check` 0 falhas |
| Build | idempotente **10/10** |
| SHA-256 | (arquivo .sha256 externo) |
| CRC | 21/21 entradas, `sha256sum -c` 52/52 |

---

## 1. O que foi pedido e o que a imagem mostrava

Você aplicou a AA e relatou: *"ele não se auto ajusta ao tamanho de telas"*,
*"as telas ficam atropeladas"* e *"nada funciona… nenhuma funcionalidade tem
resultado para criação de temas"*. Definições confirmadas: **gerar tema
publicado de verdade**, **dentro do Admin mas 100% responsivo**, **14 módulos
mantidos em abas**.

**Diagnóstico corrigido:** a imagem é o **estado sem JavaScript**. A primeira
leitura sugeriu que `render()` não chamasse `paintModules()`, mas
`paintChrome()` (já chamado por `render()`) sempre incluiu essa chamada — os
testes de DOM confirmam que, com JS, os painéis ficam escondidos tanto no HEAD
AB quanto no pacote. O problema real é que **o HTML entregue pelo servidor não
vinha com `hidden`**. Qualquer falha no carregamento/execução do
`kaz-theme-editor.js` deixava os 14 formulários legados empilhados sobre um
canvas SVG vazio, com todos os handlers inertes. Isso explica as três queixas
de uma vez.

## 2. Resultado real: editor → site publicado

Nova library `ThemeSceneCss` compila `kaztheme-scene/v1` (reusando
`ThemePackage::normalizeScene()`, fail-closed) em:

- **`css`** — só declarações `--rms-scene-*` (inteiros, hex, `px`), com whitelist
  final; concatenadas ao `theme_token_css` e emitidas no `style` do `<body>`
  com `|e('html_attr')`. **Nenhum `|raw` novo.**
- **`layers`** — escalares allowlistados (posições em porcentagem, cor, raio,
  fonte, texto ≤80 caracteres). `partial/theme-scene-block.twig` itera com
  `{{ l.text }}` sob **autoescape**.

Cadeia: `edit()` → `draft_config.kaz_scene` → **Publicar**
(`design/theme.activate`, rota existente com CSRF) →
`published_config` → `startup/theme` → `ThemeSnapshot` →
`catalog_theme_scene_css` / `catalog_theme_scene_view` → `common/header` e
`common/home` → `<section class="rms-scene">` na home → `theme.css`
(`aspect-ratio`, porcentagens, `@container`, `@media`).

**O resultado não depende do JS do Admin**: salvar e publicar são submits
nativos, e a compilação acontece no servidor.

## 3. Interface corrigida

| Antes | Agora |
| --- | --- |
| 14 painéis sem `hidden` no HTML → empilhados se o JS falhar | `hidden` **no Twig** + `<noscript>` que revela tudo organizado |
| `.kaz-workspace { height:548px }` fixo, scroll aninhado | `.kaz-shell` flex column, `calc(100dvh - 104px)`, `.kaz-stage` como único scroll |
| `.kaz-screenbar` dentro do `.kaz-center` (sobrepunha réguas) | barra externa, antes do workspace |
| `.kaz-screen` flex + board `inline-block` → canvas achatado | bloco, board/frame a `100%`, SVG com `viewBox` |
| 15 links verticais na sidebar | `.kaz-module-tabs`: **abas horizontais** no topo do centro |
| JSON/pacote/prévia sempre visíveis | 2 `<details>` "Avançado" fechados |
| Sem fluxo de publicação visível | **Salvar rascunho** · **Publicar** (confirmação `<details>`) · **Prévia** |

Abas, 7 presets de tela, densidade, colapso, tela cheia (local e em outra
página) e todos os botões da AA seguem funcionando — 226 asserções no DOM real.

## 4. Validação

| Harness | Escopo | Resultado |
| --- | --- | --- |
| PROBE pacote | compilador, whitelist de CSS, fail-closed, clamp, XSS, `ThemeSnapshot`, HTML do partial e da home, autoescape, salvar → publicar, `activate_url` | 93/0 |
| PROBE HEAD | A/B: o HEAD AB **não** tem `scene_view`, `activate_url` nem o botão Publicar | 28/0 |
| DOM real | estado **sem JS** (HEAD 14 painéis visíveis × pacote 0), estado com JS, abas, canvas não achatado, 7 presets, todos os botões, submit, atalhos | 226/0 |

Réplica A/B com 14 manifestos idênticos / 90 paths cobertos / 19 LIVRES;
guards 4/4 (`verify-checksums` 3/0 e 142 arquivos, `repo-health` 6/0/0,
`verify-ci-workflow` 31/0, `verify-contract-freeze` 11/11 hashes e 10
pendentes); build idempotente 10/10; CRC 21/21 e `sha256sum -c` 52/52.

## 5. Instalação

Extraia o ZIP na raiz do repositório preservando os caminhos — **19 arquivos
completos**, sem patches. Sem migration, comando, `npm install` ou passo de
banco. Faça refresh forçado do Admin (cache `-kaz-theme-editor-ab`). Edite a
cena, **Salvar rascunho**, **Publicar** e abra a home do catálogo: a composição
aparece com as cores e posições desenhadas.

## 6. Contratos e limitações

`theme_form.twig` com 3 linhas e os 2 `|raw` existentes; `#kaz-theme-editor`
dono, `<form id="kaz-theme-form">` filho, 3 forms irmãos, CSRF, `can_modify`,
`formaction`/`formtarget`, iframe `sandbox=""`, 14 painéis, 30 `data-kaz-module`,
53 `name=`, 418 chaves × 3 idiomas, 14 `CONFIG_KEYS`, 25 `TEMPLATE_KEYS`,
`schema.sql`, 10 `PENDING_DECISION`, 77 paths BDREditor, `release_approved:false`.

Sem migration, schema, coluna, `custom_js`, `|raw` novo, chave de tradução,
manifesto, CDN, `<script>` inline, `on*`, `innerHTML`, `eval`, `fetch`,
`localStorage`, upload ou WebGL real. A cena **não** substitui templates Twig
nem cria páginas: adiciona uma composição visual responsiva à home, integrada
aos tokens `--theme-*`. PHP WASM, Twig e jsdom com mocks não são um servidor;
**verificação em navegador real continua necessária antes de qualquer release**.

Nenhum gate virou `PASS`: F9-27 segue `BLOCKED_NOT_RUN`, BDREditor/F9-103
pausado, P2/F9-18 não reaberto. Nenhuma escrita no GitHub foi feita por este
pacote; CI remoto não verificado aqui. **CI verde ou health check não significa
release aprovada.**
