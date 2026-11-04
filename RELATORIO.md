# ReamurCMS — Frente AC · Navegador Real e Responsividade Corrigida

> **A AB dizia que era responsiva. Chromium provou que não era.**
> Esta frente roda um navegador de verdade em 6 viewports, mede o layout,
> encontra o defeito e o corrige — com screenshots de antes e depois.

| | |
| --- | --- |
| Base | `main` @ `13b0f7b8475b95b3777bd3a5fc50f48890ae280e` (AB aplicada pelo usuário, `feat: add published theme scene and fix admin UI`) |
| Árvore | 3.180 entradas / 2.598 blobs / 14 manifestos / `truncated:false` |
| Arquivos | **12** completos (10 substituições + 2 novos) + 4 evidências PNG + `RELATORIO.md` + `INDICE.md` |
| Recorte | 2201 arquivos / 31,060,267 bytes |
| Réplica | 2203 arquivos / 31,096,310 bytes (10 alterados + 2 novos + 2191 intactos) |
| **Chromium real** | **251 asserções / 0 falhas** em 1920×1080, 1440×900, 1280×800, 1024×768, 768×1024, 390×844 |
| Outros harnesses | ferramenta versionada 90/0 · regressão DOM 226/0 · PROBE publicação 93/0 = **660/0** |
| Validação final | 117 OK / 0 FALHAS |
| Guards | 4/4 verdes e byte-idênticos HEAD × réplica |
| Sintaxe | 388 PHP 0 erros · 610 JS/CJS 0 falhas |
| Build | idempotente 5/5 · CRC 18/18 · `sha256sum -c` 53/53 |

---

## 1. Por que esta frente existe

A AB foi validada com 347 asserções / 0 falhas — **PHP WASM, Twig e jsdom não
calculam CSS**: não implementam cascata, flex/grid, `getBoundingClientRect`
ou `overflow`. Foi exatamente esse tipo de prova que você reprovou na AA
(*"não se auto ajusta ao tamanho de telas"*, *"telas atropeladas"*). A AC
instala Chromium (Playwright 1.56) no sandbox e abre o HTML renderizado com
as folhas CSS e o JS reais do repositório.

## 2. O defeito que só o navegador viu

`kaz_theme_editor.twig` abre `<form id="kaz-theme-form">` **antes** do
`<header class="kaz-topbar">`. Filhos diretos do `.kaz-shell`: apenas esse
`<form>` e um `<details>`. Portanto **nenhum seletor `.kaz-shell > .kaz-*`
da AB casou** — a responsividade anunciada nunca existiu no navegador.

| Medição em 390×844 | AB | AC |
| --- | --- | --- |
| `grid-template-columns` | `236px 0px 264px` (centro **0px**) | `1fr` (centro ocupa tudo) |
| altura da `.kaz-topbar` | **453px** (um ícone por linha) | ~40px (faixa rolável) |
| `.kaz-topbar__right` | `x=671` (fora dos 390px) | dentro da viewport |
| altura do `.kaz-shell` | **1478px** em 844px | cabe na viewport |
| scrolls aninhados | 4 | ≤3 |
| altura da barra de abas | **244px** (`<p>` + `align-items:stretch`) | ~34px |
| canvas | cortado / escalado duas vezes | inteiro, `aspect-ratio` preservado |

Em 768×1024 o shell media 1240px; em 1024×768 o stage tinha 151px.

## 3. Correções

1. **O form é o contêiner flex** — `#kaz-theme-form { display:flex;
   flex-direction:column; flex:1; min-height:0 }` e todos os `.kaz-shell >
   .kaz-*` viram `#kaz-theme-form > .kaz-*`.
2. **Altura nunca `auto`** — `calc(100dvh - 104px)` em todos os viewports.
3. **Toolbar em faixa rolável** (`flex-wrap:nowrap; overflow-x:auto;
   max-height:36px`) até 1280px; chrome compacto devolve altura ao stage.
4. **Uma coluna até 980px** com sidebars em **overlay** recolhidas por padrão,
   abertas pelos botões `toggle-left/right` já existentes (`data-kaz-mobile-open`).
5. **Abas compactas** — `align-items:center` e o `<p>` herdado oculto.
6. **Zoom coerente** — novo `paintFrame()` dimensiona o `.kaz-screen` com
   `w*zoom`/`h*zoom`; `paintZoom()` não aplica mais `scale()` no board (evita
   escala dupla). `fitZoom()` usa passo 0,05 com arredondamento para baixo e é
   acionado no boot apenas em telas ≤980px.
7. **`<details>` fechados sem layout interno** — elimina o 4º scroll aninhado.
8. Cache `-kaz-theme-editor-ab` → **`-kaz-theme-editor-ac`**.

## 4. Evidência

`evidencia/` traz o antes (AB, mobile) e o depois (AC, mobile e desktop) mais
a home do catálogo com a cena publicada. São capturas do documento de teste
montado localmente com CSS/JS reais — **não** são capturas do seu servidor.

`tools/verify-kaz-layout.cjs` (novo, opcional, exige Playwright) reproduz a
medição com `node tools/verify-kaz-layout.cjs <url-admin> <url-catalog>`:
falha com `exit 1` se houver overflow horizontal, canvas achatado, painéis
empilhados no boot, sidebars sobrepostas, mais de 3 scrolls aninhados ou
aspect-ratio incorreto. Não faz parte do runtime do CMS.

## 5. Instalação

Extrair na raiz preservando os caminhos — **12 arquivos completos**, sem
migration, comando, `npm install` ou passo de banco. Refresh forçado do Admin
(cache `-kaz-theme-editor-ac`). O `tools/` e `docs/` não afetam o runtime.

## 6. Contratos e limitações

`theme_form.twig` com 3 linhas e 2 `|raw`; o `<form>` como filho do shell;
14 painéis com `hidden` no servidor; 30 `data-kaz-module`; 53 `name=`;
418 chaves × 3 idiomas; 14 manifestos; 10 `PENDING_DECISION`;
`release_approved:false`. Sem migration, `|raw` novo, `on*`, `innerHTML`,
`eval`, `fetch`, `localStorage`, rota nova ou escrita no GitHub por este
pacote.

Chromium headless sobre um documento local **não substitui o seu servidor**:
não há sessão autenticada, banco, PHP nativo ou as demais folhas que o
`header.twig` carrega em produção. Continua válida a recomendação de verificar
no DevTools por que o `kaz-theme-editor.js` não executou no ambiente que
produziu a imagem original — a AB/AC tornam o editor utilizável mesmo sem JS,
mas identificar essa causa evita surpresas.

Nenhum gate virou `PASS`: F9-27 segue `BLOCKED_NOT_RUN`, BDREditor/F9-103
pausado, P2/F9-18 não reaberto. CI remoto não verificado aqui.
**CI verde ou health check não significa release aprovada.**
