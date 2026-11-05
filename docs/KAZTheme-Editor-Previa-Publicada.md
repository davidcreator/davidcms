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


## Frente AH — Publicar com prova e estado da liberação visível (2026-11-05)

**Problema medido no `main` `8cfce45dae00` (AG aplicada, 19/19 idêntica).**
`activate()` copia `draft_config` → `published_config`/`config` e
`getEffectiveTheme()` lê `published_config`; o elo está correto no código, mas
**nunca foi exercitado por nenhum harness** e, principalmente, **a tela do
Admin não dizia se havia rascunho pendente**. O usuário editava, salvava,
pré-visualizava e não tinha como saber se o que via já era o que o catálogo
renderizaria. O instrumento da AE (`__kazHealth()`) exige abrir o console e
colar um JSON — desde AE até AG ele nunca foi colado.

**Correção (3 arquivos de código + 1 novo, sem migration/rota/tradução):**

1. **Nova** `ThemeReleaseProbe::summarize()` — **pura** (sem IO, banco,
   sessão, request, eval). Compara `draft_config` e `published_config` por
   primeiro nível canonizado (`ksort` + serialização determinística) e devolve
   **apenas escalares allowlistados**: `verdict`
   (`PUBLICADO`/`RASCUNHO_PENDENTE`/`SEM_TEMA_ATIVO`), `pending`,
   `pending_count`, `pending_keys`, `css_parts` (quantas das 6 partes de CSS da
   AG estão presentes), `css_bytes`, `scene_layers`, `asset_ok`, rótulo e dica.
2. `design/theme.php::edit()` — publica `kaz_release` no `$data` a partir do
   tema já carregado, do `ThemeSnapshot` e da cena; cache `-ag` → `-ah`.
3. `kaz_theme_editor.twig` — bloco `#kaz-release-status` com `role="status"`,
   autoescape em todos os valores, **sem `|raw`, sem `name=`, sem
   `data-kaz-module` e sem chave de tradução nova**. É **irmão** do
   `.kaz-shell`, não filho: não altera a árvore medida pela AC.

**Por que server-side:** o bloco aparece **mesmo com JavaScript desabilitado**.
É a resposta direta ao cenário "nenhuma ferramenta responde e não existe erro
no console" (AD): o veredito não depende de `bootDone`, de asset ou de
`__kazHealth()`.

**Prova:** `tools/kaz-theme-release-probe.php` constrói rascunho B e
publicado A, mostra `RASCUNHO_PENDENTE`, simula `activate()` (`published = draft`)
e mostra `PUBLICADO`; verifica estaticamente as duas SQL dos models; e prova
que `ThemePreviewState::css(resolve(rascunho))` é **idêntico** a
`css(resolve(published_config após ativar))` — ou seja, **a prévia e a home do
catálogo renderizam o mesmo tema**. `tools/verify-kaz-release.cjs` confirma no
Chromium que o painel está visível com JS ligado **e desligado**.

**Escopo:** nenhuma migration, coluna, `schema.sql`, manifesto, hash
congelado, tradução, `PENDING_DECISION` ou `release_approved` alterado.
`release_approved` permanece `false`; aprovação em produção e o veredito
`__kazHealth()` do servidor real continuam pendentes.


## Frente AI — Ciclo real de criação de tema (2026-11-05)

**Base:** `main` `39697000fd4057a3f5ab220d50c0fffc4d1d8df0` (AH aplicada;
auditoria 19/19 arquivos AH byte a byte, tree 3226 entradas / 2643 blobs).

Depois de A–AH corrigirem layout, boot, cena, estado efetivo e liberação,
faltava uma coisa que nenhum harness tinha feito: **executar o controller**.
A AH simulava `activate()` por atribuição de array; a AF/AG renderizavam
prévias isoladas. Ninguém havia provado que o POST do formulário chega ao
banco, que Publicar copia o rascunho para o publicado e que a **home do
catálogo** renderiza a cor, o raio e a fonte escolhidos no editor.

`tools/kaz-theme-lifecycle-probe.php` executa o **código real do projeto**:
`buildDraftConfig()` (método privado, via Reflection) com os 11 serviços reais
do `framework.php` registrados no `Registry`; `ModelDesignTheme::save()`;
`Controller::activate()` com CSRF e ACL reais; `ModelCommonTheme::
getEffectiveTheme()`; `ThemeSnapshot::resolve()`; `ThemePreviewState::css()`;
e a tag `<body>` do `header.twig` real renderizada por Twig 3. As **SQL dos
models são reais**, interpretadas por um banco em memória. O POST é montado a
partir dos campos `name=` do formulário real.

`tools/verify-kaz-lifecycle.cjs` abre no Chromium a home **antes** e **depois**
de Publicar e mede `getComputedStyle()`: `rgb(37,99,235)` → `rgb(220,38,38)`,
raio `12px` → `32px`, fonte `16px` → `20px`. PROBE 54/0, Chromium 22/0.

**Esta frente não altera nenhum arquivo `dev/`.** Não há migration, coluna,
rota, tradução, `|raw` novo, asset novo nem quebra de contrato: é evidência
executável, não uma correção de produto. Não substitui MySQL, sessão Admin
autenticada, deploy ou avaliação visual do usuário. `release_approved`
permanece `false`.


## Frente AJ — Aceitação visual sem console (2026-11-05)

**Base:** `main` `50a386442653c5846899f2f71c2a6b9cc51e7e24` (AI aplicada;
auditoria 17/17 arquivos AI byte a byte, tree 3240 entradas / 2656 blobs).

A AE entregou `window.__kazHealth()` com 26 verificações em 4 estados, mas o
usuário precisa abrir o DevTools e colar um JSON — desde então isso nunca foi
feito. A AH deu um painel **server-side** de liberação (rascunho/publicado),
mas não dizia se o **editor** tinha inicializado. E havia uma lacuna medida:
`kaz-theme-editor.js` marcava `bootDone = true` **antes** de
`if (!canvas || !layerList || !jsonBox) return;`. Se o boot abortasse no meio,
o fail-loud de 500 ms não disparava e a tela ficava silenciosa outra vez.

**Correção:** `#kaz-acceptance`, irmão do `.kaz-shell`, emitido pelo servidor
no estado `aguardando` (amarelo), com `<noscript>` explícito. Um novo sinal
`bootComplete` só fica `true` **no fim** do boot; ali o painel passa a
`pronto` (verde) e mostra, em `textContent`, o asset servido, o número de
módulos e camadas, o veredito da AH e se prévia e catálogo usam o mesmo tema.
Se o boot falhar, o fail-loud existente continua e o painel passa a `falhou`
(vermelho) com a causa — **sem console, sem JSON para colar**.

DOM puro (`textContent` + `setAttribute`): sem `innerHTML`, `eval`, rede,
`localStorage`, `|raw` novo, chave de tradução, rota Admin, migration ou
coluna. Cache do editor passa de `-ah` para `-aj`.

**Prova:** `tools/kaz-theme-acceptance-probe.php` (45/0) e
`tools/verify-kaz-acceptance.cjs` (31/0) medem **5 cenários** no Chromium:
boot normal, JavaScript desligado, asset 404, boot abortado no meio (o caso
que a AE não detectava) e um **controle com o JS da AH** que reproduz o
silêncio original. Com asset 404 nenhum JavaScript roda — nem o fail-loud —
e o painel amarelo server-side é justamente o aviso que resta. Com boot
abortado, o painel fica vermelho. `release_approved` permanece `false`.

## Frente AL — Aparência e ergonomia visual (2026-11-05)

Camada **aditiva**: `kaz-theme-editor-ergonomia.css` e
`kaz-theme-editor-ergonomia.js` são carregados **depois** dos assets `-aj`,
com o marcador de cache `-aj-al`. O CSS, o JS, o Twig e o painel de aceitação
da AJ **não foram alterados** — os `tools/` e os harnesses A–AJ seguem válidos.

| O que | Como |
| --- | --- |
| Barras de rolagem conformes ao tema | `scrollbar-color` + `::-webkit-scrollbar-*` em todos os containers roláveis; `color-mix()` sobre `--kaz-al-primary` / `--kaz-al-surface`, preenchidos pelo JS a partir de `data-kaz-primary` / `data-kaz-surface` e atualizados **ao vivo** ao editar os campos de cor. `--rms-color-*` / `--rms-radius` também são honrados quando presentes. |
| Tamanho de todos os elementos | `--kaz-ui` com 4 níveis (compacto .86 / normal 1 / confortável 1.14 / amplo 1.30) escalando **em conjunto** tipografia, altura de botões, ícones, abas, gaps e paddings do chrome. O canvas não escala — o zoom da cena continua independente. |
| Expandir / recolher ferramentas | 4 botões na screenbar (Ícones, Abas, Status, Recolher tudo/Expandir tudo), estado em `data-kaz-fold`, `aria-pressed` e `role="status"`. A screenbar nunca é recolhida, portanto a reabertura está sempre disponível. |

Com `normal` todos os `calc()` resolvem nos valores originais do CSS, e as
regras responsivas da AC (36/38/28 px) foram replicadas com a mesma escala
para não cortar controles ampliados. DOM puro (`createElement`, `textContent`,
`setAttribute`): sem `innerHTML`, `eval`, `fetch`, `localStorage`, migração,
coluna, rota Admin, tradução nova, `|raw` novo ou `name=` novo. Preferências
são estado local descartável, como densidade e fullscreen da AA.

**Prova:** `tools/kaz-theme-ergonomia-probe.php` e
`tools/verify-kaz-ergonomia.cjs` (Chromium, 5 cenários + 390 px). `release_approved`
permanece `false`; isto é apresentação, não aprovação de release.
