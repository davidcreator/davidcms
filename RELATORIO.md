# Frente R — Site efetivo coerente com a decisão de rollout

**Base inspecionada:** `main` @ `db6f9965942bf7e1b67fcff0232535d358edb86f`
(2026-11-04T11:48:17Z, `feat(admin/theme): fix preview site mismatch by ID`),
pai `6eb23384a61ff9c7a01e05e6ebac5587702d6008`. Árvore: 2.581 blobs,
`truncated:false`. **Verificação da frente Q aplicada: 4/4 arquivos
byte-idênticos ao HEAD R.** Refresh seletivo da árvore do HEAD R antes do
build (661 paths / 4.544.928 bytes), sem clone Git.

## Lacuna funcional real

A frente **O** registrou uma limitação em `docs/GUIA-ROLLOUT-POR-SITE.md:54`:
para não criar ciclo, o `ThemeSchedule::decide()` recebe o site resolvido
pela política **publicada**. Porém `startup/theme.php`, logo abaixo, resolvia
o site **de novo** por `ThemeSites::match($sitesCfg, $siteHost)` sobre o
config **efetivo** (que pode ser o rascunho), e esse segundo resultado
alimentava `context['site']` (L), o tema-raiz da cadeia E/J,
`catalog_theme_site` e `catalog_theme_site_language` (K/P).

Publicado `shop.host = loja.exemplo.com`; rascunho em rollout com
`shop.host = nova.exemplo.com`; visitante em **`loja.exemplo.com`**:

| Consumidor | HEAD R | Frente R |
|---|---|---|
| `decide()` (O) | `shop` | `shop` |
| `context['site']` (L) | **`primary`** | `shop` |
| tema-raiz / `catalog_theme_site` | **`primary`** | `shop` |
| `catalog_theme_site_language` (K/P) | **`primary`** | `shop` |
| cor por regra `site eq shop` | **`#222222`** (regra `site-primary`) | `#111111` (regra `site-shop`) |

O rascunho liberado para `shop` era renderizado com cores, condições e
idioma de `primary`, sem aviso. A frente **Q** já tinha exposto o mesmo
efeito na prévia do Admin; a R fecha o lado do **Catalog**.

## O que muda (1 arquivo completo + 2 guias atualizados + 1 novo)

| Arquivo | Mudança |
|---|---|
| `dev/catalog/controller/startup/theme.php` | Após `match()` no config efetivo, se o ID divergir do `$siteIdSeed` (política publicada), usa `ThemeSites::byId($sitesCfg, $siteIdSeed)` — método puro da frente Q — para recuperar os atributos do site correto; sem divergência o bloco não executa |
| `docs/GUIA-ROLLOUT-POR-SITE.md` | Limitação da O substituída pelo comportamento resolvido |
| `docs/GUIA-SITE-POR-ID.md` | Seção "o que a Q não fecha" atualizada (a O foi fechada pela R) |
| `docs/GUIA-SITE-EFETIVO-COERENTE.md` | Guia de uso, compatibilidade, segurança e limites (novo) |

**A decisão da O é preservada integralmente:** `decide()` continua recebendo
o ID da política publicada, `SCOPES` não muda, `sites=[]` continua sem
restrição e `reason='site'` permanece na mesma posição da precedência. A R
apenas **propaga** o mesmo ID aos consumidores do config efetivo. Nenhuma
consulta nova: `byId()` é puro e opera sobre `$sitesCfg` já normalizado
(`selects=1` em todos os 13 cenários, igual ao HEAD R).

## Compatibilidade e fail-closed

- **Sem divergência** (publicado = efetivo, caso normal de A–Q): o bloco novo
  **não executa**. `$site` continua sendo exatamente o retorno de `match()`,
  inclusive `reason='exact'`/`'default'` e `active`. Medido em A/B real:
  **10 de 13 cenários idênticos campo a campo**, inclusive o `<head>` da N
  byte a byte.
- **Sem sites configurados**: `match()` devolve `emptySite()`, o bloco não
  executa, `context['site']` continua caindo em `primary` (K/L).
- **ID ausente/desabilitado** no config efetivo, **rollout desligado**,
  **host desconhecido**: `byId()` devolve `emptySite()` ou não é chamado ⇒
  mantém o resultado legado (A/B idêntico).
- **ID forjado / texto livre**: `$siteIdSeed` já é allowlistado por `IDS` com
  fallback `primary`; `byId()` valida de novo. `HTTP_HOST` continua **apenas
  comparado**, nunca publicado (`has_evil=false` nos dois lados).
- **P e N intactas:** `language_directory`, `catalog_theme_language_source`,
  `catalog_theme_language_effective`, `language_site`, `<html lang>`,
  `og:locale`, `hreflangs` e `x-default` são **idênticos em A/B nos 13
  cenários**. Troca de site descarta a sessão e mesmo site a preserva.

**Sem:** migration, schema, coluna, tabela, escrita em banco/arquivo/sessão/
cookie, cron, `custom_js`, `<script>` inline, `|raw` novo, `fetch`/`eval`/
`innerHTML`, JS, CSS, Twig, chave de tradução (418 ×3 intactas), global Twig
novo, manifesto, contrato, vhost/DNS, upload, GLB, WebGL, BDREditor ou
escrita remota. Os 14 `CHECKSUMS*.txt` permanecem intactos e **nenhum** dos
4 arquivos do pacote é coberto por eles. Ordem das pré-ações inalterada.

## Validação executada (tudo `/0`)

| Harness | Resultado |
|---|---|
| `php -l` (1 PHP do pacote, PHP 8.5.12 WASM) | **1/0** |
| `node --check` (40 JS do HEAD R) | **40/0** |
| H1 `fr_pipeline_test.php` (pipeline real, 13 cenários, condições, idioma, segurança) | **186/0** |
| H2 `fr_ab_test.php` (A/B **real** HEAD R × pacote R, 13 cenários) | **250/0** |
| H3a `fr_autoload_test.php` (Autoloader real de `ThemeSites`/`LanguageNegotiator`/`ThemeSnapshot`) | **12/0** |
| H3b `fr_regression_test.php` (contratos, 418 ×3, 25 templates, guias, pacote) | **137/0** |
| H4 `fr_jsdom_test.js` (jsdom 29.1.1: `<head>` A/B, CSS `--theme-primary`, guias) | **134/0** |
| **Total de asserções** | **760/0** |

A/B **real**, não por inferência: `/tmp/fr_probe.php` executa
`setting → theme → language` duas vezes (bootstrap do HEAD R e bootstrap do
pacote R) sobre 13 cenários e o H2 compara os JSONs campo a campo. O H4
compara o `<head>` real renderizado por Twig 3.14: `normal` e
`request_language` idênticos, `host_mudado` com `--theme-primary` de
`#222222` → `#111111` e metadados de idioma inalterados.

Réplica A/B de validação com 827 paths (leitura remota, sem clone Git,
sem vendor/imagens): `verify-checksums` (14 manifestos), `repo-health`,
`verify-ci-workflow` e `verify-contract-freeze` **verdes e idênticos** antes e
depois. 3 arquivos alterados + 1 novo, 824 paths
congelados idênticos, 14 manifestos byte-idênticos, 25 `TEMPLATE_KEYS`
congelados, 418 chaves ×3, 27 arquivos de A–Q intactos, frente Q
4/4, build idempotente 4/4, `unzip -t`/CRC OK, `sha256sum -c` OK.

## Impacto crítico

Verificado e corrigido onde apareceu: as primeiras expectativas do H1 para o
cenário `sessao_troca` assumiam que a sessão prevalecia, mas **troca de site**
faz a frente P descartá-la — comportamento correto. Acrescentado o cenário
`sessao_mesmo_site` para cobrir os dois lados da P, e corrigida a expectativa
de cor quando o site é `docs` (nenhuma regra casa ⇒ cor base `#2563eb`).
Também foi feito o **refresh da árvore do HEAD R** antes de qualquer build
(lição das frentes P e Q). Nenhuma regressão A–Q detectada: 10 cenários A/B
idênticos campo a campo e as decisões da O (`effective`, `schedule_reason`,
`schedule_site`) e da P (`language_*`, `sess_*`) **inalteradas em todos os 13**.

## Limitações honestas — o que a R **não** fecha

A negociação de idioma da **P** continua usando a política **publicada** para
o idioma do site, enquanto `catalog_theme_site_language` descreve o config
efetivo; a R alinha o **site**, não a origem do idioma negociado. Alterar isso
exigiria reabrir K/P. A limitação da **N** (`?language=` gera variantes com
query, não paths/hosts distintos) permanece — exigiria DNS/vhost, fora de
escopo. A sonda do Admin (M/Q/O) e a prévia comparativa não foram tocadas.

Sem CMS completo, MySQL, sessão autenticada, browser com rede, DNS/vhost,
WebGL, E2E, migration, staging, deploy, PHP nativo, Composer, PHPStan, rsync
ou escrita no GitHub. PHP 8.5.12 WASM, Twig 3.14 e jsdom 29.1.1 com mocks
**não são um servidor**. CI remoto não verificado. **CI verde / health verde
≠ release aprovada**; F9-27 segue `BLOCKED_NOT_RUN` com
`release_approved:false`, BDREditor/F9-103 pausado. Nenhum status
`NOT_RUN`/`SKIP`/`BLOCKED_NOT_RUN` foi convertido em `PASS`.

## Instalação

Extrair os 4 arquivos completos sobre a raiz do repositório (o guia é novo),
preservando a estrutura de diretórios. Não há patches nem manifestos a
atualizar. Depois: `bash tools/verify-checksums.sh && bash tools/repo-health.sh`.
Sem divergência de hosts entre publicado e rascunho, o comportamento é
idêntico ao das frentes A–Q.
