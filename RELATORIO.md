# ReamurCMS — Frente AG · O estado efetivo do tema na prévia

> **A AF fechou a cena; a AG fecha todo o resto.** `ThemeSnapshot::resolve()`
> já era a única fonte de verdade do que o visitante vê. Faltava fazer a
> **prévia** e os **painéis de comparação** consumirem esse estado por inteiro
> — e não apenas `token_css` + `scene_css`. **Sem migration, sem coluna, sem
> rota nova, sem `|raw` novo.**

| | |
| --- | --- |
| Base | `main` @ `2494669eda6e` (AF aplicada), `pushed_at=2026-11-05T11:10:07Z` |
| Árvore real | 3.209 entradas / 2.626 blobs / `truncated:false` |
| **Aderência** | **18/18 arquivos da AF byte a byte idênticos ao `main`**, 0 divergentes |
| Arquivos | **19** completos (15 substituições + 4 novos) + 3 evidências PNG + `RELATORIO.md` + `INDICE.md` |
| Réplica | 2213 → 2217 arquivos (15 alterados + 4 novos + 2198 intactos) |
| **Efeito visível** | PROBE 51/0 · Chromium 24/0 — **8 campos medidos mudam entre dois rascunhos**: radius, fonte, cor primária, superfície, cor computada e o próprio `theme.primary` no Twig |
| Harnesses | AG 51 + 24 · AF 43 + 22 · health 26 · boot 22 · Chromium 251 + 251 · layout 90 · DOM 226 · PROBE publicação 93 · alcance 5 = **1104/0** |
| Validação final | 177 OK / 0 FALHAS |
| Guards | 4/4 verdes e byte-idênticos |
| Sintaxe | 391 PHP 0 erros · 615 JS/CJS 0 falhas |
| Build | idempotente 5/5 · CRC 24/24 · `sha256sum -c` 57/57 |

---

## 1. O problema medido

`ThemeSnapshot::resolve()` devolve 39 chaves, entre elas seis blocos de CSS:
`token_css`, `component_css`, `schedule_css`, `media_css`, `site_css` e
`scene_css`. `startup/theme.php` publica todas e `common/header.php` as
concatena **nesta ordem** no `style` do body.

No `main` `2494669eda6e`:

- `preview()` (prévia isolada de um template) injetava **zero** dessas partes
  e nenhum CSS que as consumisse;
- `Twig::renderSandboxPreview()` fixava
  `theme=['primary'=>'#2563eb','surface'=>'#ffffff','vars'=>[],...]`;
- `ThemeCompareView::panel()` injetava apenas **duas** das seis partes
  (`token_css` + `scene_css`, vindas da AF).

Consequência: depois da AF a **cena** aparecia, mas alterar cor primária,
tipografia, densidade, radius, componentes, agenda ou sites no editor
**continuava sem nenhum efeito antes de Publicar**. Sem erro e sem console —
a mesma perda silenciosa da AF, um nível acima. Causa estrutural: **dois
consumidores do mesmo estado, cada um com sua própria lista de partes**.

## 2. A correção (5 arquivos de código)

| Arquivo | Mudança |
| --- | --- |
| `theme_preview_state.php` (**novo**) | `ThemePreviewState`: `css()` (6 partes na ordem do `header.php`, fail-closed por parte), `colorVars()`, `bodyClasses()`, `globals()` (só `theme`/`theme_*`, planos, ≤256 bytes) e `catalogCss()` (o `theme.css` real, leitura limitada) |
| `twig.php` | 4º argumento **opcional** `$themeState`; aplica os globais ao contexto; a cena sanitizada da AF continua prevalecendo |
| `design/theme.php` | `preview()` resolve o rascunho com o **mesmo** `ThemeSnapshot` que `compare()` já usava; injeta `catalogCss()+previewCss()`, `css()+colorVars()` no body e `bodyClasses()` na classe; `compare()` repassa `$pubState`/`$draftState`; cache `-af` → `-ag` |
| `theme_compare_view.php` | `panel()` usa `ThemePreviewState::css()` + `colorVars()`, corrige os nomes das variáveis para os emitidos pelo `TokenRegistry`, faz `border-radius` consumir `--rms-radius` e aplica `bodyClasses()` |

Um detalhe que só a medição revelou: **injetar variáveis não basta**. Sem o
`theme.css` real, as declarações `--rms-*` existiriam, mas nenhuma regra as
usaria — a prévia continuaria idêntica. `catalogCss()` fecha esse ponto.

`css()` descarta (nunca relaxa) qualquer parte com `<`, `>`, `{`, `}`,
barra invertida, `javascript:`, `@import`, `expression(` ou `behavior:`, e
limita cada parte a 8.192 bytes e o total a 65.536. `globals()` não pode
sobrescrever `header`/`footer` do sandbox. CSP, iframe sandbox, autoescape e
proibições de tag script / `srcdoc` / `iframe` / `form` permanecem intactos.

## 3. Prova ponta a ponta

`tools/kaz-theme-preview-state.php` (**51/0**, somente leitura) constrói
dois rascunhos A/B com `ThemeSnapshot::resolve()` real: radius 12→32px,
fonte 16→20px, cor primária `#2563eb`→`#dc2626`, texto/superfície
diferentes e um componente `card` habilitado. Verifica que `css(A) !==
css(B)`, que `css($state)` é **idêntico à concatenação que `header.php`
publica**, que o fail-closed descarta uma parte maliciosa mantendo as demais,
que `globals()` não vaza `header`/`footer`, e que o Twig sandbox renderiza o
`theme.primary` **do rascunho** (não o `#2563eb` fixo).

`tools/verify-kaz-preview-state.cjs` (**24/0**) abre os dois HTMLs em
Chromium real (desktop 1280×800 e mobile 390×844), mede `getComputedStyle` e
confirma **8 divergências**: `--rms-radius`, `--rms-font-size`,
`--theme-primary`, superfície, `border-radius` e `font-size` computados do
card, cor computada e os atributos renderizados pelo Twig. Salva
`evidencia/estado_a.png`, `estado_b.png` e `estado_b_mobile.png`.

**Rota de aceitação no servidor:** alterar cor primária / densidade / radius
/ um componente → Salvar → Comparar (os dois painéis devem diferir) →
Pré-visualizar → Publicar → home do catálogo (o mesmo resultado).

## 4. O que não foi feito

- **Nenhuma migration, coluna, tabela ou `schema.sql` alterado**; o hash
  congelado foi conferido contra o `main` real. `kaz_scene` continua em
  `draft_config`/`published_config`.
- Nenhum manifesto, hash congelado, tradução, `PENDING_DECISION` ou
  `release_approved` tocado. F9-27 segue `BLOCKED_NOT_RUN`; BDREditor/F9-103
  pausado; P2/F9-18 não reaberto.
- Nenhuma escrita nossa no GitHub: o pacote é para **você** instalar.
- **Harnesses, PROBE e Chromium headless não são o seu Admin autenticado.**
  Ainda não há nova avaliação visual do usuário desde `image-7.png`, nem
  veredito `copy(__kazHealth())` colado do servidor de produção. Esta frente
  **não** alega aprovação de release; CI verde ou health local não significam
  release aprovada.
