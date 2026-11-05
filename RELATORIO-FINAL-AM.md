# Frente AM — Ajuste direto na tela · ReamurCMS KAZTheme-Editor

**Data:** 2026-11-05 · **Base:** `main` `5fb5eb2f553dd08f8849b475777c1cf4dd48bc3e`
(AL aplicada; HEAD reauditado nesta frente, **22/22 arquivos AL byte a byte**;
tree 3264 / 2680).

## Motivo: reprovacao em uso real

O usuario aplicou a AL e escreveu: *"aplicado com sucesso github atualizado.
Podemos prosseguir com o próximo passo **ainda não consigo mexer para ajustar
coisas na tela**"*, enviando `image-8.png`. A imagem mostrava os controles e as
barras azuis da AL, mas o **canvas cortado**. Isso e uma reprovacao de uso,
nao uma aprovacao visual nem de release.

### Causa medida (nao foi falta de handler)

| Medida em 1490x728 | Valor |
| --- | --- |
| Zoom no boot | **100%** — o `fitZoom()` da AC so era chamado quando `matchMedia('(max-width: 980px)')` casava |
| Board | `scrollWidth 1032` vs `clientWidth 666`; `scrollHeight 648` vs `clientHeight 289` |
| Screenbar | 68 px (a AL inseriu controles e a barra quebrou em 2 linhas) |
| Alvo de clique da camada | `x=1050, y=806`, **fora da area visivel** |
| Arraste / resize depois de ajustar manualmente | **ja funcionavam**: x 40→192, y 40→120, w 560→688, h 220→280 |

Ou seja: os handlers de `mousedown`/`mousemove`/`mouseup` e
`data-kaz-handle` existiam; o usuario nao alcancava a camada. Mesmo o
`Ajustar à tela` manual deixava `scrollHeight 324` vs `clientHeight 289`,
porque `fitZoom()` ignora o `margin-top` do frame e o padding do viewport.

## Correcao (somente interacao/apresentacao)

| Entrega | Implementacao |
| --- | --- |
| Auto-ajuste universal | Camada aditiva chama o **botao publico** `[data-kaz-action="screen-fit"]` da AC no boot, em **qualquer** largura, duas passagens (layout + fontes/CSS), em `resize` e apos mexer nas colunas. Se ainda houver rolagem, reduz um passo de zoom ate `overflow <= 4`. Nenhuma `matchMedia`. |
| Maior zoom util | O CSS AM enxuga `margin-top` do frame e o padding do viewport em desktop — as margens que `fitZoom()` nao contabiliza. Em 1490x728 o editor agora abre em **45%** com **0** de overflow, em vez de cair para 25%. |
| Screenbar em 1 linha | `flex-wrap: nowrap` + rolagem horizontal acima de 980 px; altura volta de 68 para **38 px**, devolvendo altura ao canvas sem esconder os controles (eles ficam no inicio). |
| Ajustar tamanho dos elementos | **2 divisorias arrastaveis** entre as 3 colunas: `role="separator"`, `aria-valuenow`, pointer events com `setPointerCapture`, setas do teclado e duplo clique para restaurar. `data-kaz-cols` so e aplicado **apos um arraste**; sem ele nenhuma largura e sobrescrita, entao a densidade e o colapso de AA/AJ permanecem intactos. |
| X/Y/W/H ao vivo | `MutationObserver` no canvas sincroniza os campos do inspector **durante** o arraste; antes eles so mudavam no `mouseup`. Escreve apenas `.value`, sem disparar eventos e sem loop. |

Camada **aditiva** com marcador proprio `-aj-am`: 2 assets novos e 2 linhas
no controller. **CSS, JS, Twig e os paineis de AJ/AL nao foram alterados**;
os 4 usos de `$kazAssetAJ` e o marcador `-aj-al` permanecem intactos, entao
os PROBEs e harnesses A–AL seguem medindo exatamente o mesmo codigo.

DOM puro: sem escrita dinamica de marcacao, execucao dinamica, rede,
armazenamento, migracao, coluna, rota Admin, traducao nova, `|raw` novo,
`name=` novo, alteracao de contrato de dados, CSRF, CSP ou sandbox.
`window.__kazAjuste()` e diagnostico opcional **sem console obrigatorio**.
As larguras das colunas **nao persistem** entre recarregamentos, por contrato.

## Evidencia A/B (Chromium, mesma pagina, mesmo viewport 1490x728)

| Cenario | Zoom | Overflow | Screenbar | Camada | Arraste |
| --- | --- | --- | --- | --- | --- |
| **Controle** (`al_normal.html`, sem AM) | 100% | 366 | 68 px | fora da janela | inalcancavel |
| **Com AM** (`am_normal.html`) | 45% | **0** | **38 px** | **dentro da janela** | x 40→176, y 40→104; w 560→672, h 220→280 |

`evidencia/controle_sem_am.png` reproduz `image-8.png`. `am_arraste.png`,
`am_redimensionar.png`, `am_colunas.png` e `am_restaurar.png` mostram a
interacao; `am_mobile.png` cobre 390 px com as alcas escondidas.

| Item | Resultado |
| --- | --- |
| HEAD / tree | `5fb5eb2f553d` · 3264 / 2680 · 22/22 AL byte a byte |
| Pacote | 23 arquivos (5 novos) · replica 2232 → 2237 (18 alterados + 5 novos + 2214 intactos) |
| Guards | 4/4 identicos · 14 manifestos intocados |
| Sintaxe | 397 PHP / 622 JS-CJS, 0 erros |
| PROBE AM | 68/0 |
| Chromium AM | 40/0 (controle A/B + 8 cenarios, 1490x728 e 390x844) |
| Regressoes A–AL | 1585/0 assercoes acumuladas |
| Build | idempotente 5/5 · CRC 32/32 · `sha256sum -c` 62/62 |
| ZIP | 32 entradas · `.sha256` externo |

Nota metodologica: o PROBE da AL congela a assercao **"17 documentos"**, que e
a contagem exata do pacote daquela frente. A AM acrescenta o 18. documento
por desenho, entao esse harness e executado sobre o **pacote AL congelado**
(a base), sem alterar nenhuma assercao. A nao-regressao sobre a replica AM e
provada pelos guards 4/4 identicos, pelos 14 manifestos intocados, pelo
Chromium AL 56/0, pelos 2214 arquivos congelados e pela verificacao de que o
**texto integral da AL** continua presente como prefixo nos 17 documentos,
com a secao AM apenas anexada.

## Instalacao e verificacao

Copiar `dev/` e `docs/` sobre a instalacao com a AL ja aplicada; `tools/` e
`evidencia/` sao so validacao. **Limpe o cache de CDN/proxy** — os novos
assets usam `-aj-am`; o editor principal continua `-aj` e a ergonomia
continua `-aj-al`. Abra o editor: o board deve caber inteiro, sem rolagem,
com as camadas alcancaveis. Arraste uma camada (X/Y mudam ao vivo), arraste
a alca do canto (W/H acompanham), arraste a divisoria azul entre as colunas
e de duplo clique para restaurar.

```bash
php tools/kaz-theme-ajuste-probe.php
node tools/verify-kaz-ajuste.cjs
```

## Limites — isto NAO e uma aprovacao

`image-7.png` e `image-8.png` continuam sendo o criterio do usuario.
Harnesses, PROBE, Chromium headless, CI local e PHP WASM **nao provam** o
Admin real autenticado. Ainda estao pendentes: **duas capturas desktop e
390 px do Admin real**, `copy(__kazHealth())` em producao, CI remoto e a
avaliacao visual do usuario. `release_approved` continua `false`; F9-27
`BLOCKED_NOT_RUN`, BDREditor/F9-103 pausado, P2/F9-18 nao reaberto.
Nao reabrimos backend, BD, migration, ciclo criar/editar/salvar/Publicar nem
release. **CI verde, PROBE e Chromium headless não significam release
aprovada.** Nenhuma escrita nossa foi feita no GitHub; a aplicacao do pacote
e feita pelo usuario.
