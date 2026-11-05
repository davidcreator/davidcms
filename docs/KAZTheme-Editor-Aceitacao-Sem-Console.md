# KAZTheme-Editor — Aceitação visual sem console (Frente AJ)

**Data:** 2026-11-05 · **Base:** `main` `50a386442653c5846899f2f71c2a6b9cc51e7e24`
(AI aplicada, 17/17 arquivos AI byte a byte) · **Status:** entregue para
instalação, **não** aprovado em produção.

## O problema

Desde a frente AE existe `window.__kazHealth()`: 26 verificações, 4 estados,
`data-kaz-health="ad+ae"`, `data-kaz-boot-warning` com `role="alert"`. É um
bom instrumento, mas exige **abrir o DevTools e colar um JSON**. O usuário
nunca colou. E o sintoma que originou AD/AE era justamente o silêncio:
"nenhuma ferramenta responde e não existe erro no console". Um diagnóstico
que só existe no console não resolve um problema que se manifesta na tela.

A AH deu o primeiro painel server-side (`#kaz-release-status`), mas ele fala
de **liberação** (rascunho pendente / publicado), não de **saúde do editor**.

Havia também uma **lacuna concreta** no `kaz-theme-editor.js`:

```js
var root = document.getElementById('kaz-theme-editor');
if (!root) return;
bootDone = true;                       // marcado AQUI
/* ... */
if (!canvas || !layerList || !jsonBox) return;   // aborta DEPOIS
```

O fail-loud de 500 ms testa `bootDone`. Se o boot abortasse nesse `return`
intermediário, `bootDone` já era `true`: nenhum aviso, nenhum erro, tela morta.

## O que a AJ entrega

Um **`#kaz-acceptance`** renderizado **no servidor**, irmão de
`#kaz-release-status` e do `.kaz-shell`:

| Estado | Quando | Cor |
| --- | --- | --- |
| `aguardando` | HTML do servidor, antes de qualquer JS; também com JS desligado ou asset 404 | amarelo |
| `pronto` | boot concluído **até o fim** | verde |
| `falhou` | JS carregou mas o boot não concluiu em 500 ms | vermelho |

Com **asset 404** nenhum JavaScript roda — inclusive o fail-loud da AE —
então o estado correto é `aguardando`: o texto amarelo já diz "o JavaScript
não carregou — não é erro de tema nem de banco". Com **boot abortado no
meio** (canvas ausente), o JS existe e o `bootComplete` nunca liga: painel
vermelho, `data-kaz-boot-warning` e a causa por escrito. Um controle com o
JS da **AH** no mesmo cenário permanece amarelo e silencioso — é a lacuna
que a AJ fecha.

No estado `pronto` o painel mostra, em texto simples: asset servido (`v=` da
tag `<script>`), número de módulos `[data-kaz-module]`, número de camadas da
cena, o veredito da AH (lido de `#kaz-release-status`) e a frase
"prévia e catálogo usam o mesmo tema" quando `PUBLICADO`. Com JavaScript
desligado, um `<noscript>` diz explicitamente que os painéis continuam sendo
HTML do servidor — exatamente o cenário silencioso de AD/AE.

O novo sinal **`bootComplete`** só é ligado no fim do `boot()`; o fail-loud
passa a testá-lo e também a marcar o painel como `falhou`. `bootDone` fica
como estava (contrato AD/AE intacto). Tudo por `textContent` e
`setAttribute` — sem `innerHTML`, `eval`, rede, `localStorage`, `|raw` novo,
chave de tradução, rota, migration ou coluna. Cache `-ah` → `-aj`.

## Como verificar sem console

1. Abra o editor no Admin. O painel deve ficar **verde** em menos de um
   segundo, com o asset `-aj`.
2. Se ficar **amarelo** ou **vermelho**, o problema é de inicialização do
   JavaScript — não de tema, banco ou migration. O texto do painel já diz o
   que checar (HTTP 200 do asset, `scripts_deferred` no `header.twig`,
   cache/proxy).
3. Desabilite o JavaScript e recarregue: o `<noscript>` aparece e os painéis
   de liberação/aceitação continuam visíveis.
4. Edite → **Rascunho pendente**; Publicar → **Publicado**. O painel de
   aceitação acompanha o veredito.

Ferramentas: `php tools/kaz-theme-acceptance-probe.php` (45/0) e
`node tools/verify-kaz-acceptance.cjs` (31/0, 5 cenários no Chromium, gera
6 PNGs em `/tmp/aj_shots`). O CJS usa `AJ_BASE` (padrão
`http://127.0.0.1:8123`) ou 5 URLs explícitas; no sandbox o viewport é
900×600 porque páginas de 230 KB em 1280×800 esgotam a memória do renderer.

## Limites

O painel mede o boot **no navegador do cliente**, mas continua sendo uma
ferramenta de diagnóstico: não substitui sessão Admin autenticada, banco
MySQL, deploy ou a avaliação visual do usuário. `copy(__kazHealth())` do
servidor de produção e uma avaliação visual desde `image-7.png` ainda não
foram fornecidos. `release_approved` continua `false`; F9-27
`BLOCKED_NOT_RUN`, BDREditor/F9-103 pausado, P2/F9-18 não reaberto.
**CI verde, PROBE e Chromium headless não significam release aprovada.**
Nenhuma escrita foi feita no GitHub; CI remoto não foi verificado.
