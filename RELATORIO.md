# Frente Q — Site por ID na prévia (fecha a limitação da M)

**Base inspecionada:** `main` @ `6eb23384a61ff9c7a01e05e6ebac5587702d6008`
(2026-11-04T11:28:55Z, `feat(language/multisite): implement unified
site-specific language negotiation`), pai `9285acdcc55a9eb39db7d4e5b1c48339c0c49780`.
Árvore: 2.580 blobs, `truncated:false`. **Verificação da frente P aplicada:
6/6 arquivos byte-idênticos ao HEAD Q.** Refresh seletivo da árvore do HEAD Q
antes do build (660 paths / 4.536.828 bytes), sem clone Git.

## Lacuna funcional real

A frente **M** passou a permitir escolher o site da prévia comparativa por um
`<select name="preview_site">` com os 8 IDs allowlistados de
`ThemeSites::IDS`. Mas o `compare()` fazia um **round-trip por host**: pegava o
`host` daquele ID em cada política e chamava `ThemeSites::match()`. O guia da M
declarava a limitação (`docs/GUIA-PREVIA-POR-SITE.md:48`):

> o select escolhe o site pelo host configurado; se um site usa o mesmo host de
> outro dentro da mesma política, vale a ordem allowlistada da J (o primeiro
> habilitado)

`match()` percorre `IDS` e devolve o **primeiro** habilitado com aquele host.
Com `primary.host == shop.host`, escolher **`shop`** fazia os dois painéis
resolverem **`primary`** — a comparação virava `primary × primary` sem aviso.
Quando o rascunho mudava `shop.host` para o host de um site anterior na ordem,
a comparação virava **`shop × primary`**, exatamente o defeito que a própria M
tinha sido criada para evitar.

## O que muda (3 arquivos completos + 1 guia novo)

| Arquivo | Mudança |
|---|---|
| `dev/system/library/theme/theme_sites.php` | Novo `byId(array $sites, mixed $rawId): array` — resolvedor **puro** por ID allowlistado, sem round-trip por host; `REASONS` ganha `'id'` (publicado por `spec()`) |
| `dev/admin/controller/design/theme.php` | `compare()` usa `byId()` nos dois painéis quando `preview_site` é válido; fallback **byte a byte idêntico ao legado M** nos demais casos |
| `docs/GUIA-PREVIA-POR-SITE.md` | Limitação substituída pelo comportamento resolvido |
| `docs/GUIA-SITE-POR-ID.md` | Guia de uso, compatibilidade, segurança e limites (novo) |

`byId()` valida: ID em `IDS`, presença na política normalizada, `enabled=true`
e host válido via `validHost()`. Qualquer divergência devolve `emptySite()` ⇒
o `compare()` cai para a sonda de host (fail-closed). O retorno tem o **mesmo
formato** de `match()` (`id`, `host`, `slug`, `language`, `enabled`, `reason`,
`active`), com `reason='id'`: `ThemeSnapshot::resolve()`, `bars()`, `css()`,
`globals()` e a fita do `ThemeCompareView` seguem **inalterados**.

**Efeito visível:** com hosts duplicados, a fita de contexto e as barras de
sites da prévia passam a mostrar **`shop`** (antes: `primary`), e os dois
painéis comparam o mesmo site. Medido por A/B real: `primary/primary →
shop/shop` (hosts duplicados) e `shop/primary → shop/shop` (rascunho com host
colidindo). A sonda de rollout (`schedule_probe_site`, O) e a sessão de
idioma (`language_site`, P) **já** usavam IDs; nenhum arquivo delas foi tocado.

## Compatibilidade (A/B real, 12 cenários)

- **Sem `preview_site`** (padrão "Simular host"): caminho legado da M intacto.
- **`preview_site` forjado / fora da allowlist / `<script>`**: `$forced=''` ⇒
  legado. **ID ausente ou desabilitado em qualquer painel**: `byId()` devolve
  vazio ⇒ legado, inclusive o uso individual de `$hPub`/`$hDraft`.
- Nesses **5 cenários de fallback o resultado A/B é idêntico campo a campo**
  (IDs, motivos, contextos, tamanho do HTML e fita). Nos 5 cenários com ID
  válido e sem colisão, os IDs/contextos são idênticos e só o `reason` muda de
  `exact` para `id` — como documentado.
- **A sonda de host continua soberana** e continua simulando `match(host)`,
  que é exatamente o que o Catalog faz no request real, onde só existe
  `HTTP_HOST`. A frente Q **não** muda `match()`, `normalize()`, `resolve()`,
  `IDS`, `KEYS`, `LANGUAGES`, `SCOPES` nem a ordem das pré-ações.

**Sem:** migration, schema, coluna, tabela, escrita em banco/arquivo, cookie,
sessão, cron, `custom_js`, `<script>` inline, `|raw` novo, `fetch`/`eval`/
`innerHTML`, JS, CSS, Twig, chave de tradução (418 ×3 intactas), manifesto,
contrato, global Twig novo, vhost/DNS, upload, GLB, WebGL, BDREditor ou
escrita remota. Os 14 `CHECKSUMS*.txt` permanecem intactos e **nenhum** dos
4 arquivos do pacote é coberto por eles.

## Validação executada (tudo `/0`)

| Harness | Resultado |
|---|---|
| `php -l` (2 PHP do pacote, PHP 8.5.12 WASM) | **2/0** |
| `node --check` (40 JS do HEAD Q) | **40/0** |
| H1 `fq_sites_test.php` (`byId()` puro, allowlist, fail-closed, regressão J) | **141/0** |
| H2 `fq_compare_test.php` (A/B **real** HEAD Q × pacote Q, 12 cenários + regressão A–P) | **226/0** |
| H3a `fq_autoload_test.php` (Autoloader real de `ThemeSites` com `byId()`) | **8/0** |
| H3b `fq_regression_test.php` (contratos, 418 ×3, 25 templates, guias, pacote) | **126/0** |
| H4 `fq_jsdom_test.js` (jsdom 29.1.1: fita A/B, barras ativas, form, guias) | **115/0** |
| **Total de asserções** | **658/0** |

A/B **real**, não por inferência: `/tmp/fq_probe.php` executa o `compare()`
duas vezes (bootstrap do HEAD Q e bootstrap do pacote Q) instrumentando o
`ThemeSnapshot` por `__call`, e o H2 compara os JSONs resultantes.

Réplica A/B de validação com 826 paths (leitura remota, sem clone Git,
sem vendor/imagens): `verify-checksums` (14 manifestos), `repo-health`,
`verify-ci-workflow` e `verify-contract-freeze` **verdes e idênticos** antes e
depois. 3 arquivos alterados + 1 novo, 823 paths
congelados idênticos, 14 manifestos byte-idênticos, 25 `TEMPLATE_KEYS`
congelados, 418 chaves ×3, 27 arquivos de A–P intactos, frente P
6/6, build idempotente 4/4, `unzip -t`/CRC OK, `sha256sum -c` OK.

## Impacto crítico

Verificado e corrigido onde apareceu: o **probe inicial chamava
`compare('0')`**, e com `theme_id=0` o `compare()` usa `published=[]` — isso
zerava o painel publicado e fazia a A/B parecer "toda igual", ocultando o
bug. Corrigido para `compare('1')` e a A/B passou a mostrar
`primary/primary → shop/shop`. Também foi feito o **refresh completo da
árvore do HEAD Q** antes de qualquer build (lição da frente P, onde a base
local ainda era o HEAD anterior). As demais divergências eram expectativas dos
harnesses (`spec()['reasons']`, barras verdes de outros cards no jsdom,
`$mode` fora de escopo, loader Twig apontando para diretório inexistente) —
corrigidas sem alterar o código entregue. Nenhuma regressão A–P detectada.

## Limitações honestas — o que a Q **não** fecha

A limitação da frente **O** permanece: se o **rascunho** alterar o host de um
site, o site da decisão de rollout vem da política **publicada** (evita ciclo
com a escolha do config efetivo), enquanto `context['site']` das condições
(L) vem da política efetiva. A Q atua apenas na **prévia do Admin**, onde o ID
é conhecido; no request real só existe o host. A limitação da **N**
(`?language=` gera variantes com query, não paths/hosts distintos) também
permanece — exigiria DNS/vhost, fora de escopo.

Sem CMS completo, MySQL, sessão de Admin autenticada, browser com rede,
DNS/vhost, WebGL, E2E, migration, staging, deploy, PHP nativo, Composer,
PHPStan, rsync ou escrita no GitHub. PHP 8.5.12 WASM, Twig 3.14 e jsdom
29.1.1 com mocks **não são um servidor**. CI remoto não verificado.
**CI verde / health verde ≠ release aprovada**; F9-27 segue
`BLOCKED_NOT_RUN` com `release_approved:false`, BDREditor/F9-103 pausado.
Nenhum status `NOT_RUN`/`SKIP`/`BLOCKED_NOT_RUN` foi convertido em `PASS`.

## Instalação

Extrair os 4 arquivos completos sobre a raiz do repositório (o guia é novo),
preservando a estrutura de diretórios. Não há patches nem manifestos a
atualizar. Depois: `bash tools/verify-checksums.sh && bash tools/repo-health.sh`.
Sem `preview_site`, o comportamento é idêntico ao das frentes A–P.
