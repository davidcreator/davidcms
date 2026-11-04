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
