# KAZTheme-Editor — Estado efetivo na prévia (Frente AG)

**Data:** 2026-11-05 · **Base:** `2494669eda6e` (AF aplicada, auditoria 18/18
byte a byte no `main`) · **Status:** entregue para instalação, **não** aprovado
em produção.

## A lacuna

A AF fechou a **cena**: prévia e publicado passaram a desenhar as mesmas
camadas. Mas o editor edita muito mais do que a cena. `ThemeSnapshot::resolve()`
calcula o estado efetivo do tema, e `common/header.php` publica no `style` do
body — nesta ordem:

```
token_css + component_css + schedule_css + media_css + site_css + scene_css
```

A prévia isolada (`preview()`) injetava **zero** dessas partes e usava
`theme.primary` fixo `#2563eb`; os painéis de comparação injetavam **duas**
(`token_css` e `scene_css`). Então alterar cor, fonte, densidade, radius,
componentes, agenda ou sites não mudava nada antes de Publicar. Sem erro, sem
console: o usuário editava e a prévia mentia.

A causa é estrutural, não um bug pontual: **dois consumidores diferentes do
mesmo estado**, cada um com sua própria lista de partes. É o mesmo padrão que
produziu a AF.

## O que a AG entrega

| Consumidor | Antes (AF) | Depois (AG) |
| --- | --- | --- |
| `preview()` isolado | sem tokens, `theme` fixo | estado via `ThemeSnapshot`, `theme.css` real, 6 partes no body |
| `compare()` — publicado | cena + `token_css` | estado completo de `$pubState` |
| `compare()` — rascunho | cena + `token_css` | estado completo de `$draftState` |
| `renderSandboxPreview()` | `theme.primary=#2563eb`, globais vazios | globais allowlistados do estado |
| `ThemeCompareView::panel()` | 2 de 6 partes | 6 de 6, na ordem do `header.php` |

`ThemePreviewState` é a **única** tradutora. `css()` é fail-closed por parte:
uma parte com `<`, `>`, `{`, `}`, `\`, `javascript:`, `@import`,
`expression(` ou `behavior:` é descartada, nunca relaxada. `globals()` só
emite chaves `theme`/`theme_*` planas com escalares ≤256 bytes — `header` e
`footer` do sandbox não podem ser sobrescritos. `catalogCss()` lê o `theme.css`
real com limite de 131.072 bytes e validação; sem ele as variáveis existiriam
mas nenhuma regra as consumiria.

**Sem** `|raw` novo, tag script, `srcdoc`, `iframe`, `form`, `eval`,
`innerHTML`, `localStorage`, rede, migration ou rota Admin nova. CSP e sandbox
do iframe permanecem como na AE/AF.

## Como verificar

1. `php tools/kaz-theme-preview-state.php` → PROBE somente leitura. Constrói
   dois estados A/B, compara `css()`, prova a paridade com a concatenação do
   `header.php`, testa fail-closed e renderiza `home.twig` em Twig sandbox
   com `theme.primary` real. Gera `/tmp/ag_site/state_a.html` e
   `/tmp/ag_site/state_b.html`.
2. Sirva os dois HTMLs e rode
   `node tools/verify-kaz-preview-state.cjs <url-a> <url-b>` → Chromium real
   mede `getComputedStyle` e confirma que **radius, fonte e cor mudam entre
   A e B**.
3. No Admin de verdade: altere a cor primária / densidade / radius / um
   componente → Salvar → Comparar (os dois painéis devem differir) →
   Pré-visualizar → Publicar → home do catálogo (o mesmo resultado).

## Limites

Harnesses, PROBE e Chromium headless não substituem a sessão Admin
autenticada, o banco ou o servidor de produção. Não há nova avaliação visual
do usuário desde `image-7.png`, nem veredito `copy(__kazHealth())` colado do
servidor. `release_approved` continua `false`; F9-27 `BLOCKED_NOT_RUN`,
BDREditor/F9-103 pausado, P2/F9-18 não reaberto. **CI verde ou health local
não significam release aprovada.**


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
