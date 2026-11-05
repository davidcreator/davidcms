# KAZTheme-Editor — Prévia × Publicado (Frente AF)

**Data:** 2026-11-05 · **Base:** `5932b910aca5591e98362821bf82266fcf67238f`
(AE aplicada) · **Status:** entregue para instalação, **não** aprovado em produção.

## O que estava quebrado

O editor permitia desenhar a composição visual do tema (blocos,
posições, cores, textos). `buildDraftConfig()` validava e gravava `kaz_scene`
em `draft_config`, `activate()` copiava para `published_config`, e o catálogo
publicado renderizava `.rms-scene*`. **Mas a prévia não.** Ao clicar em
*Comparar* ou *Pré-visualizar*, o usuário via um HTML sem a cena — e só
descobria o resultado depois de Publicar. Não havia erro, nem aviso, nem
entrada no console: era uma perda silenciosa de informação entre dois
consumidores do mesmo estado.

Causa exata: `Twig::renderSandboxPreview()` montava o contexto com
`theme_scene=['enabled'=>false]` e **omitia** `theme_scene_view`. O
`home.twig` inclui `partial/theme-scene-block.twig`, que começa com
`{% if theme_scene_view.enabled|default(false) %}`; sem a chave, o `default`
caía em falso. Além disso, `ThemeCompareView::panel()` não tinha nenhuma regra
`.rms-scene*` — mesmo que a chave chegasse, as camadas ficariam empilhadas em
blocos sem `position:absolute`.

## O que a AF entrega

| Etapa | Antes (AE) | Depois (AF) |
|---|---|---|
| `compare()` — painel publicado | sem `scene_view` | `scene_view` de `$pubState` |
| `compare()` — painel rascunho | sem `scene_view` | `scene_view` de `$draftState` |
| `preview()` | sem cena | `ThemeSceneCss::compile($draft['kaz_scene'])` |
| `renderSandboxPreview()` | `theme_scene.enabled=false` | `theme_scene_view` allowlistado |
| `ThemeCompareView` | sem `.rms-scene*` | `<style>` estático + `scene_css` por painel |
| Template customizado sem partial | nada | `sceneBlock()` determinístico |

Os dois painéis mostram **cenas diferentes quando o rascunho difere** — era
esse o objetivo da comparação. `ThemeSnapshot::diff()` já apontava a mudança
em `kaz_scene.layers.N.text`; agora o olho confirma o que o diff diz.

### Defeito visual encontrado *depois* do primeiro harness

O primeiro `verify-kaz-preview.cjs` passava 18/18 comparando atributos
`style`, mas as screenshots mostravam **texto cortado** na prévia e **cor
divergente** (azul no painel, roxo no catálogo). Causas medidas no Chromium:
o painel tem 666px de largura, acima do `@container (max-width:620px)` do
`theme.css`, então o `font-size:32px` em px absoluto não escalava; e o HTML
publicado de evidência não injetava `--theme-primary` como `header.php` faz.
Correções: `previewCss()` passou a espelhar `container-type:inline-size` e a
estender a `@container` a 1200px **apenas na prévia**, e o PROBE passou a
publicar as variáveis do tema no HTML de evidência. O harness ganhou
comparação de **cor computada** e asserção de **nenhum texto cortado**.
É o mesmo tipo de armadilha da frente AA: contagem de atributos não é
renderização.

## Segurança

- `previewSceneView()` é **fail-closed**: entrada inválida → cena desabilitada.
- Valores escalares allowlistados; texto com autoescape do Twig; `kind`/`role`
  por enum; `fill` por `#rrggbb`; sem `innerHTML`, `eval`, `srcdoc`, `formaction`.
- O `<style>` da comparação é **uma string literal da classe**, não dado do
  usuário. CSP e sandbox do iframe permanecem como na AE.
- Nenhum `|raw` novo, nenhuma rota Admin nova, nenhuma escrita em banco,
  nenhuma migration, nenhuma chave de tradução nova.

## Como verificar

1. **PROBE (servidor/local):** `php tools/kaz-theme-preview-parity.php`
   → `PROBE AF: 43 OK / 0 FALHAS`. Gera `/tmp/af_site/preview.html` e
   `/tmp/af_site/published.html`.
2. **Navegador real:** sirva esses dois HTMLs e rode
   `node tools/verify-kaz-preview.cjs <url-preview> <url-publicado>`
   → `verify-kaz-preview: 22 OK / 0 FALHAS`.
3. **No Admin de verdade:** desenhe a cena → Salvar → Comparar (a cena deve
   aparecer nos 2 painéis) → Publicar → abrir a home do catálogo (a mesma cena
   deve aparecer). Se a prévia aparecer e a home não, o problema está no
   `startup/theme.php`, não na AF; se nenhuma aparecer, cole
   `copy(__kazHealth())` da AE para descartar boot.

## Limites

Harness, PROBE e Chromium headless **não** substituem a sessão Admin
autenticada, o banco ou o servidor de produção. Esta frente corrige uma lacuna
de dados entre prévia e publicado; não reivindica aprovação visual do usuário
nem veredito de `__kazHealth()` em produção. `release_approved` continua `false`.


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
