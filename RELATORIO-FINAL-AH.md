# Frente AH — Liberação e Publicar · ReamurCMS KAZTheme-Editor

**Data:** 2026-11-05 · **Base:** `main` `8cfce45dae00` (AG aplicada; auditoria
19/19 arquivos AG byte a byte, tree 3216 entradas / 2633 blobs / não truncada).

## O que esta frente corrige

`activate()` copia `draft_config` → `published_config`/`config` e
`getEffectiveTheme()` lê `published_config`. O elo estava correto no código, mas
**nunca tinha sido exercitado**, e a tela do Admin **não dizia** se havia
rascunho pendente. O usuário salvava, pré-visualizava e não sabia se o catálogo
já usaria aquele tema. O `__kazHealth()` da AE exige abrir o DevTools e colar um
JSON — até hoje não foi colado, e um diagnóstico que depende de JavaScript é
justamente o que falha no cenário silencioso de AD/AE.

**Entrega:** `#kaz-release-status` no topo do editor, renderizado no servidor,
com `PUBLICADO` / `RASCUNHO_PENDENTE` / `SEM_TEMA_ATIVO`, chaves pendentes e
métricas allowlistadas (6 partes de CSS da AG, bytes, camadas da cena, asset).
Funciona **com JavaScript desligado** — a prova está em
`evidencia/sem_javascript.png`, idêntica a `evidencia/pendente.png`.

`ThemeReleaseProbe::summarize()` é pura (sem IO, banco, sessão, request ou
`eval`), devolve apenas escalares allowlistados e passa pelo autoescape do Twig.
Sem `|raw` novo, `name=` novo, `data-kaz-module` novo, chave de tradução, rota,
migration ou coluna. O bloco é **irmão** do `.kaz-shell`: a árvore medida pela AC
(`#kaz-theme-form > .kaz-*`) não muda.

## Prova do elo Publicar

`tools/kaz-theme-release-probe.php` monta publicado A e rascunho B com
`ThemeSnapshot::resolve()` real, mostra `RASCUNHO_PENDENTE`, simula `activate()`
(`published = draft`, contrato da SQL), mostra `PUBLICADO`, verifica
estaticamente as SQL dos dois models e prova que
`ThemePreviewState::css(resolve(rascunho))` === `css(resolve(publishado))`.
**O que a prévia mostra é o que a home do catálogo vai renderizar.**

## Numeros desta validacao

| Item | Resultado |
| --- | --- |
| HEAD / tree | `8cfce45dae00` · 3216 / 2633 · 19/19 AG byte a byte |
| Pacote | 19 arquivos (4 novos) · réplica 2217 → 2221 (15 alterados + 4 novos + 2202 intactos) |
| Guards | 4/4 idênticos HEAD × réplica · 14 manifestos intocados |
| Sintaxe | 393 PHP / 616 JS-CJS, 0 erros |
| PROBE AH | 44/0 |
| Chromium AH | 21/0 (inclui JavaScript desligado e mobile 390px) |
| Regressões A–AG | 1169/0 asserções acumuladas |
| Build | idempotente 5/5 · CRC 25/25 · `sha256sum -c` 58/58 |
| ZIP | 25 entradas · `.sha256` externo |

## Instalacao

Copiar `dev/` e `docs/` sobre a instalação. `tools/` e `evidencia/` são apenas
validação, não precisam ir para produção. **Nenhuma migration, SQL, coluna ou
atualização de `release_approved` é necessária.** Cache do editor passa de
`-ag` para `-ah`; se usar CDN/proxy, limpe o cache.

Para conferir: `php tools/kaz-theme-release-probe.php` e, no Admin, editar → o
painel diz **Rascunho pendente**; Ativar/Publicar → **Publicado**; recarregar a
home do catálogo → o mesmo tema.

## Limites — leia antes de considerar aprovado

Harnesses, PROBE e Chromium headless **não** substituem sessão Admin
autenticada, banco ou servidor de produção. A simulação de `activate()`
reproduz o contrato de dados, não executa SQL real nem transação. Ainda **não
há** nova avaliação visual do usuário desde `image-7.png`, nem veredito
`copy(__kazHealth())` colado do servidor real. `release_approved` continua
`false`; F9-27 `BLOCKED_NOT_RUN`, BDREditor/F9-103 pausado, P2/F9-18 não
reaberto. **CI verde ou health local não significam release aprovada.**
Nenhuma escrita foi feita no GitHub; CI remoto não foi verificado.
