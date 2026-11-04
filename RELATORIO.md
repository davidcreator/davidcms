# Frente S — Idioma efetivo coerente com o config efetivo

**Base inspecionada:** `main` @ `650f354e8c43fba3d46fa667f187cbf07e405683`
(2026-11-04T12:05:12Z, `fix(catalog/theme): align catalog site with published
rollout ID`), pai `db6f9965942bf7e1b67fcff0232535d358edb86f`. Árvore: 2.582
blobs, `truncated:false`. **Verificação da frente R aplicada: 4/4 arquivos
byte-idênticos ao HEAD S.** Refresh seletivo da árvore do HEAD S antes do
build (662 paths / 4.551.581 bytes), sem clone Git.

## Lacuna funcional real

A frente **P** unificou a negociação de idioma num resolvedor puro, mas
registrou em `docs/GUIA-IDIOMA-EFETIVO.md:66` que a negociação acontece
**antes** da decisão de rollout e usa a política **publicada** (para não criar
ciclo). A frente **R** alinhou o **ID do site** ao config efetivo; faltava
alinhar o **idioma desse site**. Guias da P e da R apontavam isso como a
última divergência aberta.

Publicado `sites[shop].language = pt-br`; rascunho em rollout com
`sites[shop].language = es-eu`; visitante novo em `loja.exemplo.com`, sem
`?language=` e sem sessão:

| Valor | HEAD S | Frente S |
|---|---|---|
| `catalog_theme_site_language` (snapshot) | `es-eu` | `es-eu` |
| `catalog_theme_language_effective` | **`pt-br`** | `es-eu` |
| `language_directory` / `<html lang>` | **`pt-br`** | `es-eu` |
| `og:locale` / `hreflang` | **`pt_br`** | `es_eu` |
| `context['language']` (condições L) | **`pt-br`** | `es-eu` |
| cor por `language eq es-eu` | **`#111111`** | `#333333` |

Uma regra `language eq es-eu` nunca casava, e o `<head>` anunciava idioma
diferente do badge do Admin.

## O que muda (2 arquivos completos + 2 guias atualizados + 1 novo)

| Arquivo | Mudança |
|---|---|
| `dev/system/library/language_negotiator.php` | Novo `alignSite(array $resolved, mixed $effectiveSiteLanguage, array $enabled): array` — **puro**, mesmo formato de retorno de `resolve()`; `resolve()` e as constantes ficam intactos |
| `dev/catalog/controller/startup/theme.php` | Guarda `getEnabledCodes()` (mesma 1 consulta), chama `alignSite()` **depois** da decisão e do realinhamento da R, e republica código/sessão/contexto **somente se o código mudou** |
| `docs/GUIA-IDIOMA-EFETIVO.md` | Limitação da P substituída pelo comportamento resolvido |
| `docs/GUIA-SITE-EFETIVO-COERENTE.md` | Seção "o que a R não fecha" atualizada |
| `docs/GUIA-IDIOMA-EFETIVO-POR-CONFIG.md` | Guia de uso, soberania, segurança e limites (novo) |

## Soberania preservada (o ponto central)

`alignSite()` age **somente** quando `source === 'site'`:

- **`?language=`** (`source=request`) ⇒ **nunca** alterado, continua soberano.
- **Sessão do mesmo site** (`source=session`) ⇒ **nunca** alterada.
- **Default do painel** (`source=default`) ⇒ **nunca** alterado.
- Código fora de `pt-br|en-us|es-eu`, não habilitado, vazio, não-string ou
  igual ao atual ⇒ **sem mudança** (fail-closed).

`catalog_theme_language_source` continua em `request|session|site|default`;
`startup/language.php` continua **aplicando** `catalog_theme_language_effective`
sem decidir de novo. Ordem das pré-ações e precedência da K intactas. A R já
havia alinhado o ID do site; a S lê o idioma **desse mesmo ID** no config
efetivo — mesmo padrão, aplicado ao idioma.

## Compatibilidade e segurança

- **Sem divergência** (publicado = efetivo): `alignSite()` devolve o array
  inalterado, o código não muda e **nada é republicado** ⇒ comportamento
  **byte a byte idêntico às frentes A–R**. Medido em A/B real: **17 de 19
  cenários idênticos campo a campo**, inclusive os 12 de regressão da R, os
  de `?language=`/sessão/default e os de fail-closed (idioma desabilitado,
  rollout desligado, sem sites).
- **Nenhuma consulta nova:** a allowlist já lida pela P é reutilizada
  (`selects=1` nos 19 cenários, nos dois lados).
- A única escrita é a **mesma sessão** que a P já gravava (`language` +
  `language_site`), atualizada para o código correto. `HTTP_HOST` continua
  **apenas comparado**, nunca publicado (`has_evil=false` nos dois lados).
- **Sem:** migration, schema, coluna, tabela, escrita em banco/arquivo, cookie
  novo, cron, `custom_js`, `<script>` inline, `|raw` novo, `fetch`/`eval`/
  `innerHTML`, JS, CSS, Twig, chave de tradução (418 ×3 intactas), global Twig
  novo, manifesto, contrato, vhost/DNS, upload, GLB, WebGL, BDREditor ou
  escrita remota. Os 14 `CHECKSUMS*.txt` permanecem intactos; nenhum arquivo
  do pacote é coberto por eles.

## Validação executada (tudo `/0`)

| Harness | Resultado |
|---|---|
| `php -l` (2 PHP do pacote, PHP 8.5.12 WASM) | **2/0** |
| `node --check` (40 JS do HEAD S) | **40/0** |
| H1 `fs_negotiator_test.php` (`alignSite()` puro: soberania, allowlist, fail-closed) | **78/0** |
| H2 `fs_pipeline_test.php` (pipeline real, 19 cenários, condições, `<head>`, segurança) | **224/0** |
| H3 `fs_ab_test.php` (A/B **real** HEAD S × pacote S, 19 cenários) | **369/0** |
| H4a `fs_autoload_test.php` (Autoloader real do `LanguageNegotiator` com `alignSite()`) | **12/0** |
| H4b `fs_regression_test.php` (contratos, 418 ×3, 25 templates, guias, pacote) | **139/0** |
| H5 `fs_jsdom_test.js` (jsdom 29.1.1: `<head>` A/B, `og:locale`, cor, guias) | **162/0** |
| **Total de asserções** | **1026/0** |

A/B **real**, não por inferência: `/tmp/fs_probe.php` executa
`setting → theme → language` duas vezes (bootstrap do HEAD S e bootstrap do
pacote S) sobre 19 cenários e o H3 compara os JSONs campo a campo. O H5
compara o `<head>` real renderizado por Twig 3.14: `normal` e
`idioma_mudado_request` idênticos; nos 2 cenários de divergência,
`<html lang>` `pt-br → es-eu`, `og:locale` `pt_br → es_eu` e
`--theme-primary` `#111111 → #333333`, com `hreflangs`/`x-default`/canonical
inalterados.

Réplica A/B de validação com 828 paths (leitura remota, sem clone Git,
sem vendor/imagens): `verify-checksums` (14 manifestos), `repo-health`,
`verify-ci-workflow` e `verify-contract-freeze` **verdes e idênticos** antes e
depois. 4 arquivos alterados + 1 novo, 824 paths
congelados idênticos, 14 manifestos byte-idênticos, 25 `TEMPLATE_KEYS`
congelados, 418 chaves ×3, 27 arquivos de A–R intactos, frente R
4/4, build idempotente 5/5, `unzip -t`/CRC OK, `sha256sum -c` OK.

## Impacto crítico

Verificado e corrigido onde apareceu: uma asserção do H4b procurava o texto
"A divergência de idioma da **P**" no guia da R, mas o trecho real é "A
**negociação** de idioma da **P** continua" — expectativa do harness
corrigida, sem alterar o código entregue. Também foi feito o **refresh da
árvore do HEAD S** antes de qualquer build (lição das frentes P/Q/R) e os
bootstraps A/B foram separados corretamente: o do pacote carrega o
`LanguageNegotiator` **com** `alignSite()`, o do HEAD carrega o **sem** —
sem isso a A/B seria falsa. Nenhuma regressão A–R detectada.

## Limitações honestas — o que a S **não** fecha

Permanece a limitação da **N**: o CMS usa `?language=` (Adendo F2-3 do ADR
0001), então as variantes `hreflang` são URLs com query, não paths/hosts
distintos — exigiria DNS/vhost, fora de escopo. O idioma continua sendo
**negociado antes** da decisão de rollout (necessário para não criar ciclo) e
apenas **realinhado** depois; não há segunda negociação completa. A sonda do
Admin (M/Q/O) e a prévia comparativa não foram tocadas.

Sem CMS completo, MySQL, sessão autenticada, browser com rede, DNS/vhost,
WebGL, E2E, migration, staging, deploy, PHP nativo, Composer, PHPStan, rsync
ou escrita no GitHub. PHP 8.5.12 WASM, Twig 3.14 e jsdom 29.1.1 com mocks
**não são um servidor**. CI remoto não verificado. **CI verde / health verde
≠ release aprovada**; F9-27 segue `BLOCKED_NOT_RUN` com
`release_approved:false`, BDREditor/F9-103 pausado. Nenhum status
`NOT_RUN`/`SKIP`/`BLOCKED_NOT_RUN` foi convertido em `PASS`.

## Instalação

Extrair os 5 arquivos completos sobre a raiz do repositório (o guia é novo),
preservando a estrutura de diretórios. Não há patches nem manifestos a
atualizar. Depois: `bash tools/verify-checksums.sh && bash tools/repo-health.sh`.
Sem divergência de idioma entre publicado e rascunho, o comportamento é
idêntico ao das frentes A–R.
