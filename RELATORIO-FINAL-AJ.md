# Frente AJ — Aceitação visual sem console · ReamurCMS KAZTheme-Editor

**Data:** 2026-11-05 · **Base:** `main` `50a386442653c5846899f2f71c2a6b9cc51e7e24`
(AI aplicada; auditoria 17/17 arquivos AI byte a byte; tree 3240 / 2656).

## O que esta frente fecha

A AE deu `window.__kazHealth()` (26 verificações, 4 estados), mas ele exige
abrir o DevTools e colar um JSON — nunca foi colado. A AH deu um painel
server-side de **liberação**, não de **saúde do editor**. E havia uma lacuna
medida no `kaz-theme-editor.js`: `bootDone = true` era marcado **antes** de
`if (!canvas || !layerList || !jsonBox) return;`. Se o boot abortasse no meio,
o fail-loud de 500 ms não disparava: tela silenciosa outra vez.

**Entrega:** `#kaz-acceptance`, irmão de `#kaz-release-status` e do
`.kaz-shell`, emitido pelo servidor no estado `aguardando` (amarelo), com
`<noscript>` explícito. Um novo sinal `bootComplete` só liga **no fim** do
boot; ali o painel passa a `pronto` (verde) e mostra, em `textContent`, o
asset servido, módulos, camadas, o veredito da AH e se a prévia e o catálogo
usam o mesmo tema. Se o JS carregar mas o boot falhar, o painel passa a
`falhou` (vermelho) com a causa — **sem console e sem JSON para colar**.
Com asset 404 nenhum JS roda: o amarelo server-side é o aviso que resta.

Tudo por `textContent` + `setAttribute`: sem `innerHTML`, `eval`, rede,
`localStorage`, `|raw` novo, chave de tradução, rota Admin, migration ou
coluna. Cache `-ah` → `-aj`. Contratos AD/AE preservados
(`data-kaz-health="ad+ae"`, `data-kaz-boot-warning`, boot por presença).

| Item | Resultado |
| --- | --- |
| HEAD / tree | `50a3864426` · 3240 / 2656 · 17/17 AI byte a byte |
| Pacote | 21 arquivos (3 novos) · réplica 2224 → 2227 (18 alterados + 3 novos + 2206 intactos) |
| Guards | 4/4 idênticos · 14 manifestos intocados |
| Sintaxe | 395 PHP / 618 JS-CJS, 0 erros |
| PROBE AJ | 45/0 |
| Chromium AJ | 31/0 (5 cenários + mobile 390px) |
| Regressões A–AI | 1321/0 asserções acumuladas |
| Build | idempotente 5/5 · CRC 29/29 · `sha256sum -c` 60/60 |
| ZIP | 29 entradas · `.sha256` externo |

## Instalação e verificação

Copiar `dev/` e `docs/` sobre a instalação; `tools/` e `evidencia/` são só
validação. **Limpe o cache de CDN/proxy** (versão `-ah` → `-aj`). Abra o
editor no Admin: o painel deve ficar **verde** em menos de um segundo, com o
asset `-aj`. Se ficar amarelo ou vermelho, o problema é de inicialização do
JavaScript — não de tema nem de banco — e o próprio texto diz o que checar.

```bash
php tools/kaz-theme-acceptance-probe.php
node tools/verify-kaz-acceptance.cjs     # AJ_BASE ou 5 URLs
```

## Limites

O painel mede o boot no navegador do cliente, mas é diagnóstico: não
substitui sessão Admin autenticada, MySQL, deploy ou avaliação visual do
usuário. `copy(__kazHealth())` do servidor de produção e uma avaliação visual
desde `image-7.png` ainda não foram fornecidos. `release_approved` continua
`false`; F9-27 `BLOCKED_NOT_RUN`, BDREditor/F9-103 pausado, P2/F9-18 não
reaberto. **CI verde, PROBE e Chromium headless não significam release
aprovada.** Nenhuma escrita foi feita no GitHub; CI remoto não foi verificado.
