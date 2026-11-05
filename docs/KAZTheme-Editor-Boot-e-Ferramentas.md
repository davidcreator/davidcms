# KAZTheme-Editor — Boot e Ferramentas (Frente AD)

Diagnóstico e correção do sintoma **"nenhuma ferramenta responde e não existe
erro no console do DevTools"**, relatado após a aplicação da Frente AC.

## 1. O sintoma e o que ele significa

Um erro no console indicaria exceção, 404 de asset ou bloqueio de CSP.
**Console limpo + ferramentas inertes** indica que o código nunca chegou a
registrar seus listeners. Há duas formas de isso acontecer sem erro: o
`<script>` não está no HTML, ou o script executa e **sai cedo por um `return`
condicional**. Aqui foi a segunda.

## 2. Cadeia causal medida

| Etapa | Arquivo | Fato |
| --- | --- | --- |
| 1 | `design/theme.php:200-202` | registra CSS e JS via `addScript()`; **sem condição de BD** |
| 2 | `Document::addScript()` | guarda o href em `$scripts` |
| 3 | `common/header.php:33` | `$data['scripts'] = getScripts()` |
| 4 | `common/header.twig:45-47` | `{% for src in scripts %}<script src="{{ src }}"></script>{% endfor %}` no **`<head>`**, sem `defer` |
| 5 | `kaz-theme-editor.js:71-72` | `var root = document.getElementById('kaz-theme-editor'); if (!root) return;` |
| 6 | navegador | `<body>` ainda não parseado → `root === null` → saída silenciosa |

`footer.twig:29-30` já usava `defer` em `admin.js` e no Bootstrap. A
inconsistência entre head e footer era a armadilha.

## 3. Alcance: nove módulos mortos

Todos os scripts que o controller de temas registra fazem `getElementById`
no início de uma IIFE imediata:

| Script | Linha do `if (!root) return;` |
| --- | --- |
| `theme-package.js` | 7 (`!input \|\| !area`) |
| `theme-condition-builder.js` | IIFE imediata |
| `theme-scene-editor.js` | 7 |
| `theme-section-builder.js` | 8 |
| `theme-component-library.js` | 7 |
| `kaz-theme-editor.js` | 72 |
| `theme-schedule.js` | 7 |
| `theme-media.js` | 8 (condição combinada) |
| `theme-sites.js` | 9 |

Isso é coerente com a reprovação histórica da AA: *"nada funciona, nenhuma
das funcionalidades presentes tem resultado para criação de temas"*. As
frentes AB e AC entregaram estado-sem-JS utilizável, tema publicado e
layout responsivo, mas **não** religaram a inicialização.

## 4. Por que a validação anterior não pegou

`validacao/ac_site.py` (Frente AC) montava o documento de teste com
`<script src="…" defer></script>`. Com `defer`, o DOM já existe e o boot
funciona. Chromium mediu 251/0 — corretamente, porém sobre uma ordem de
carregamento que o Admin não produz. Lição: um harness que usa atalhos de
montagem pode ser verde enquanto a página real está inerte. A AD passa a
testar explicitamente o **`<head>` sem `defer`**.

## 5. A correção

Ver seção "Frente AD" em `RELATORIO.md` e nos demais documentos. Resumo:
`addScriptDeferred()` + segundo loop `defer` no `header.twig` (impacto
restrito ao controller de temas), 9 chamadas migradas, cache
`-kaz-theme-editor-ad` e `boot()` resiliente no JS principal.

**Sem migration.** `kaz_scene` vive dentro de `draft_config`/`published_config`
já existentes; a ausência é tratada por `normalizeScene(null)`. O controller
não consulta `setting`, `extension` nem permissão para decidir incluir o JS.

## 6. Como confirmar no servidor

1. Extrair o pacote na raiz e dar **refresh forçado** (Ctrl+F5) no Admin.
2. DevTools → Network: `kaz-theme-editor.js?v=…-kaz-theme-editor-ad` deve
   vir **200**.
3. DevTools → Console: digitar
   `document.getElementById('kaz-theme-editor').__kaz.ui()` — deve devolver o
   objeto de estado, não `undefined`.
4. Clicar numa aba de módulo, trocar o preset de tela e em **Salvar**; o
   status muda e o formulário é submetido.
5. Se `__kaz` continuar `undefined`, verificar se um proxy/CDN serve o JS
   antigo em cache e se o `header.twig` ativo contém o loop
   `scripts_deferred`.

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
