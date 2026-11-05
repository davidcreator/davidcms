# KAZTheme-Editor — Ciclo real: formulário → banco → Publicar → home (Frente AI)

**Data:** 2026-11-05 · **Base:** `main` `39697000fd4057a3f5ab220d50c0fffc4d1d8df0`
(AH aplicada, 19/19 arquivos AH byte a byte) · **Status:** evidência
executável entregue, **não** aprovado em produção.

## Por que esta frente existe

A reprovação original (`image-7.png`) dizia que **nenhuma das funcionalidades
presentes tinha resultado para criação de temas**. As frentes AC–AH fecharam
responsividade, boot silencioso, diagnóstico, paridade de cena, estado efetivo
completo e o painel server-side de liberação. Mas todos os números vinham de
harnesses que **inspecionavam** ou **simulavam** o fluxo:

| Frente | O que provava | O que ficava de fora |
| --- | --- | --- |
| AH | `published = draft` por atribuição de array; CSS da prévia == CSS do publicado | nenhuma SQL executada, nenhum controller chamado |
| AF/AG | prévia isolada e painéis publicado × rascunho | a home real do catálogo |
| A–AE | formulário, layout, boot, persistência da cena | o POST completo indo para o banco |

A AI fecha exatamente esse vazio: **executa o código do `main`** de ponta a
ponta e mostra o resultado no navegador.

## O que é executado

```
POST (campos name= reais do Twig)
  → Theme\Controller::buildDraftConfig()        [Reflection, método privado]
  → Registry com 11 serviços reais do framework.php
  → ModelDesignTheme::save()                    [INSERT real]
  → getDraftConfig() / getPublishedConfig()
  → ThemeReleaseProbe::summarize()              [AH: SEM_TEMA_ATIVO]
  → Controller::activate()                      [ACL + CSRF reais]
  → ModelDesignTheme::activate()                [2 UPDATE + revisão reais]
  → ModelCommonTheme::getEffectiveTheme()       [SELECT real, status=1]
  → ThemeSnapshot::resolve() + ThemePreviewState::css()
  → header.twig real (Twig 3, autoescape)       [<body style="--rms-*">]
  → Chromium: getComputedStyle() antes × depois
```

O `FakeDb` **interpreta as strings SQL que os models realmente emitem** —
`INSERT`, `UPDATE … SET published_config=draft_config`, `SELECT … WHERE
status='1'` — sobre uma tabela em memória. Não é um mock que devolve
respostas prontas: se a SQL mudar, o PROBE falha. As 4 escritas reais são
contadas e conferidas.

## Resultado medido

| Medida | Antes de Publicar | Depois de Publicar |
| --- | --- | --- |
| `getComputedStyle(#kaz-probe).backgroundColor` | `rgb(37, 99, 235)` | `rgb(220, 38, 38)` |
| `border-radius` | `12px` | `32px` |
| `font-size` | `16px` | `20px` |
| `--theme-primary` no `<body>` | `#2563eb` | `#dc2626` |
| painel da AH | `SEM_TEMA_ATIVO` / `RASCUNHO_PENDENTE` | `PUBLICADO`, `pending=0` |

`ThemePreviewState::css(resolve(rascunho))` === `css(resolve(publicado))`
(1001 bytes). Evidências em `evidencia/antes.png`, `evidencia/depois.png` e
`evidencia/depois_mobile.png` (390 px).

**Observação registrada, não corrigida:** num tema recém-criado,
`published_config` é `'{}'` e `ModelDesignTheme::getPublishedConfig()` usa o
fallback legado `config` (F13-C), que no INSERT recebe o próprio rascunho. O
painel da AH, por sua vez, lê `published_config` direto e com `status=0`
reporta `SEM_TEMA_ATIVO`. Os dois comportamentos são distintos e
**permanece como está**: não há pedido de mudança, e a AH já foi aplicada.

## Como rodar

```bash
php tools/kaz-theme-lifecycle-probe.php          # PROBE 54/0, gera HTMLs
node tools/verify-kaz-lifecycle.cjs              # Chromium 22/0, gera PNGs
```

Variáveis: `AI_ROOT` (raiz do projeto, padrão dois níveis acima de `tools/`),
`AI_TWIG_AUTOLOAD` (padrão `vendor/autoload.php`), `AI_OUT` (HTMLs, padrão
`/tmp/ai_site`), `AI_SHOTS` (PNGs, padrão `/tmp/ai_shots`). O CJS aceita duas
URLs como argumentos; por padrão abre os HTMLs via `file://`. Requer PHP 8.1+,
Twig 3.x, Node e Playwright.

## Escopo e limites

**Nenhum arquivo `dev/` é alterado por esta frente.** Não há migration,
coluna, rota Admin, chave de tradução, `|raw` novo, asset, manifesto, hash
congelado, `PENDING_DECISION` ou `release_approved` mexido. O pacote contém
apenas 2 tools, documentação e evidências.

Limites honestos: o banco é em memória, **não** MySQL; `activate()` é chamado
com stubs mínimos de `request`/`session`/`user`/ACL, **não** com uma sessão
Admin autenticada; a home é o fragmento real do `header.twig` mais um elemento
de medição, **não** a página completa com todos os controllers; não há deploy.
Ainda não foi colado o `copy(__kazHealth())` do servidor de produção nem houve
nova avaliação visual do usuário desde `image-7.png`. `release_approved`
continua `false`; F9-27 `BLOCKED_NOT_RUN`, BDREditor/F9-103 pausado,
P2/F9-18 não reaberto. **CI verde, PROBE e Chromium headless não significam
release aprovada.** Nenhuma escrita foi feita no GitHub; CI remoto não foi
verificado.


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
