# KAZTheme-Editor — Estado da liberação (Frente AH)

**Data:** 2026-11-05 · **Base:** `8cfce45dae00` (AG aplicada, auditoria 19/19
byte a byte no `main`) · **Status:** entregue para instalação, **não** aprovado
em produção.

## O que faltava

As frentes AC–AG fecharam layout, boot, cena e estado efetivo na prévia. Mas
existia um silêncio entre **Salvar** e **Publicar**:

- `activate()` (Admin) copia `draft_config` → `published_config` e `config`;
- `getEffectiveTheme()` (Catálogo) lê `published_config`;
- nenhum lugar da tela dizia **se havia rascunho pendente** ou se o que a
  prévia mostrava já era o publicado.

O `__kazHealth()` da AE resolveria parte da dúvida, mas exige abrir o DevTools
e colar um JSON — nunca foi colado. E um diagnóstico que depende de
JavaScript é justamente o que falha no cenário "nenhuma ferramenta responde e
não existe erro no console".

## O que a AH entrega

Um **`#kaz-release-status`** no topo do editor, renderizado **no servidor**:

| Veredito | Significado |
| --- | --- |
| `PUBLICADO` | `draft_config` e `published_config` idênticos; prévia e catálogo usam o mesmo tema |
| `RASCUNHO_PENDENTE` | há chaves alteradas; o painel lista até 16 delas e orienta a publicar |
| `SEM_TEMA_ATIVO` | nenhum tema com `status=1` |

Abaixo, métricas allowlistadas: quantas das **6 partes de CSS** da AG estão
presentes, bytes de CSS, camadas da cena e se a versão do asset é `-ah`.

`ThemeReleaseProbe::summarize()` é **pura**: sem IO, banco, sessão, request ou
`eval`. Devolve **somente escalares**, todos passam pelo autoescape do Twig.
Sem `|raw` novo, sem `name=` novo (o contrato de 53 campos permanece), sem
`data-kaz-module` novo, sem chave de tradução, sem rota, sem migration, sem
coluna. O bloco é **irmão** do `.kaz-shell`, não filho — a árvore medida pela
AC (`#kaz-theme-form > .kaz-*`) não muda.

## Prova do elo Publicar

`tools/kaz-theme-release-probe.php` (somente leitura):

1. constrói publicado A e rascunho B com `ThemeSnapshot::resolve()` real;
2. `summarize(B, A, …)` → `RASCUNHO_PENDENTE`, `pending_count > 0`;
3. simula `activate()` — `published = draft`, exatamente o que a SQL faz;
4. `summarize(B, B, …)` → `PUBLICADO`, `pending = 0`;
5. verifica estaticamente que `activate()` escreve `published_config` e
   `config` a partir de `draft_config`, e que `getEffectiveTheme()` lê
   `published_config`;
6. **prova central**: `ThemePreviewState::css(resolve(rascunho))` ===
   `ThemePreviewState::css(resolve(publishado após ativar))`. O que você viu
   na prévia é o que a home do catálogo vai renderizar.

`tools/verify-kaz-release.cjs` abre os dois estados no Chromium e confirma que
o painel aparece **com JavaScript ligado e desligado**, que os atributos
`data-kaz-release-*` batem com o veredito e que o painel não contém nenhum
campo de formulário.

## Como verificar no servidor

1. `php tools/kaz-theme-release-probe.php` → PROBE.
2. Sirva `/tmp/ah_site/*.html` e rode
   `node tools/verify-kaz-release.cjs <url-pendente> <url-publicado>`.
3. No Admin: abra um tema → o painel deve dizer **Rascunho pendente** após
   editar e **Publicado** depois de Ativar/Publicar. Recarregue a home do
   catálogo e confirme o mesmo resultado. **Se o painel não aparecer, o
   problema é de renderização do Admin, não de JavaScript.**

## Limites

Harnesses, PROBE e Chromium headless não substituem a sessão Admin
autenticada, o banco ou o servidor de produção. A simulação de `activate()`
reproduz o **contrato de dados** (`published = draft`), não executa SQL real
nem transação. Ainda não há nova avaliação visual do usuário desde
`image-7.png`, nem veredito `copy(__kazHealth())` colado do servidor.
`release_approved` continua `false`; F9-27 `BLOCKED_NOT_RUN`,
BDREditor/F9-103 pausado, P2/F9-18 não reaberto. **CI verde ou health local
não significam release aprovada.**


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

## Frente AM — Ajuste direto na tela (2026-11-05)

**Causa medida em uso real (`image-8.png`):** em desktop o editor abria com
zoom **100%**, porque o `fitZoom()` da AC so era chamado no boot em
`max-width: 980px`. Em 1490×728 o board 960×540 ficava cortado
(`scrollWidth 1032` vs `clientWidth 666`) e as camadas caiam **fora da area
clicavel** — o arraste e o redimensionamento existentes funcionavam, mas
nao havia como alcanca-los. Ajustando manualmente, medimos x 40→192,
y 40→120 e w 560→688, h 220→280.

| O que | Como |
| --- | --- |
| Auto-ajuste universal | `kaz-theme-editor-ajuste.js` chama o botao publico `Ajustar à tela` da AC em **qualquer** viewport, no boot, apos fontes e em `resize`; reduz um passo de zoom ate o board caber de verdade (o `fitZoom` nao contabiliza `margin-top` do frame nem o padding do viewport). |
| Screenbar em 1 linha | A AL inseriu 413 px de controles e, acima de 1280 px, a barra quebrava em 2 linhas (68 px medidos). `flex-wrap: nowrap` + rolagem horizontal devolvem a altura ao canvas sem esconder os controles (eles ficam no inicio). |
| Divisorias redimensionaveis | 2 alcas com `role="separator"`, `aria-valuenow`, arraste por pointer events, teclado (setas) e duplo clique para restaurar. Sem `data-kaz-cols` **nenhuma** largura e sobrescrita: densidade e colapso das frentes AA/AJ continuam intactos. |
| X/Y/W/H ao vivo | `MutationObserver` no SVG acompanha o retangulo selecionado durante o arraste; antes os campos do inspector so atualizavam no `mouseup`. |

Camada **aditiva**: marcador `-aj-am`, dois arquivos novos e duas linhas no
controller. CSS, JS, Twig e paineis de AJ/AL **nao foram alterados**; os
marcadores `-aj` (4 usos) e `-al` permanecem intactos, entao os PROBEs e
harnesses A–AL seguem medindo exatamente o mesmo codigo.

Sem escrita dinamica de marcacao, execucao dinamica, rede, armazenamento,
migracao, coluna, rota Admin, traducao nova, `|raw` novo, `name=` novo ou
alteracao de CSP/sandbox. `release_approved` permanece `false`.
