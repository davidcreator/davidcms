# KAZTheme-Editor — Validação em Navegador Real (Frente AC)

> **A AB dizia que era responsiva. O Chromium provou que não era.**
> Esta frente mede o layout de verdade e corrige a causa raiz.

## 1. Por que esta frente existe

Você aplicou a AB e pediu o próximo passo. A AB foi validada com **347
asserções / 0 falhas** — mas PHP WASM, Twig e jsdom não implementam cascata
CSS, `flex`, `grid`, `getBoundingClientRect` ou `overflow`. Foi exatamente esse
tipo de prova que você reprovou na AA: *"não se auto ajusta ao tamanho de
telas"*, *"telas atropeladas"*.

A AC instala Chromium (Playwright 1.56) no sandbox, renderiza o editor e a
home do catálogo com as **folhas CSS e o JS reais** do HEAD
`13b0f7b8475b95b3777bd3a5fc50f48890ae280e`, e mede 6 viewports.

## 2. O defeito real

`dev/admin/view/template/design/kaz_theme_editor.twig` abre o formulário na
linha 15:

```twig
<div class="kaz-shell ..." id="kaz-theme-editor" ...>
  <form id="kaz-theme-form" method="post" action="{{ action }}">
    <header class="kaz-topbar">...</header>
    ...
    <div class="kaz-workspace">...</div>
    <footer class="kaz-statusbar">...</footer>
```

Filhos diretos do `.kaz-shell`: **apenas** `<form id="kaz-theme-form">` e
`<details class="kaz-advanced--extra">`. Logo todos os seletores da AB do tipo

```css
.kaz-shell > .kaz-workspace { ... }
.kaz-shell > .kaz-topbar   { ... }
```

**nunca casaram**. O que o navegador media em 390×844:

| Elemento | AB |
| --- | --- |
| `.kaz-workspace` | `grid-template-columns: 236px 0px 264px` — centro com **0px** |
| `.kaz-topbar` | `flex-wrap: nowrap`, 453px de altura, um ícone por linha |
| `.kaz-topbar__right` | `x = 671` (fora da viewport de 390px) |
| `.kaz-shell` | 1478px de altura em 844px de viewport |
| scrolls aninhados | 4 (sidebar L, viewport, sidebar R, textarea) |

Em 768×1024 o shell media 1240px. Nenhum harness da AB detectou isso porque
contar atributos não é calcular layout.

## 3. Correções da AC

| Regra | Efeito |
| --- | --- |
| `.kaz-shell > form#kaz-theme-form { display:flex; flex-direction:column; flex:1; min-height:0 }` | o form passa a ser o contêiner real |
| `#kaz-theme-form > .kaz-*` | substitui todos os `.kaz-shell > .kaz-*` inválidos |
| `height: calc(100dvh - 104px)` **sem** `height:auto` em ≤980px | o editor cabe sempre na viewport |
| `.kaz-toolbar { flex-wrap:nowrap; overflow-x:auto; max-height:36px }` | ferramentas em faixa rolável, não em coluna |
| `grid-template-columns: minmax(0,1fr); grid-template-rows: minmax(0,1fr)` em ≤980px | uma coluna, centro com largura real |
| sidebars `position:absolute; display:none` + `data-kaz-mobile-open` | overlays recolhidos por padrão, abertos pelos botões existentes |
| ocultar rótulos/campos numéricos em ≤620px | topbar e screenbar compactos |
| `.kaz-advanced:not([open]) > .kaz-io { display:none }` | remove o 4º scroll aninhado |

O JS ganhou duas linhas: `togglePanel()` publica `data-kaz-mobile-open` e o
boot recolhe as sidebars quando `matchMedia('(max-width:980px)')` casa
(com guard para jsdom/PHP WASM). Nenhuma rota, migration ou `on*` novo.

## 4. Como reproduzir

```bash
node tools/verify-kaz-layout.cjs <url-admin> <url-catalog>
```

O script exige Playwright + Chromium; **não** faz parte do runtime do CMS nem
é carregado pelo Admin. Ele falha com `exit 1` se houver overflow horizontal,
canvas achatado, painéis empilhados no boot, sidebars sobrepostas, mais de 3
scrolls aninhados ou aspect-ratio incorreto da cena publicada.

As imagens em `evidencia/` foram geradas sobre o HTML renderizado localmente
com CSS/JS reais do repositório — são evidência de layout, **não** capturas do
servidor de produção.

## 5. O que ainda não é prova

Chromium headless com um documento montado localmente não substitui o seu
servidor: não há sessão autenticada, banco, PHP nativo, MySQL nem as demais
folhas que o `header.twig` carrega em produção. A recomendação da AB continua
válida: verificar no DevTools por que o `kaz-theme-editor.js` não executou no
ambiente que produziu a imagem original. A AC torna o editor utilizável mesmo
sem JS, mas identificar essa causa evita surpresas.

Nenhum gate virou `PASS`; F9-27 segue `BLOCKED_NOT_RUN`, `release_approved`
permanece `false`. Nenhuma escrita no GitHub foi feita por este pacote.
**CI verde ou health check não significa release aprovada.**
