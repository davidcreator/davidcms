# Frente P — Idioma efetivo unificado (K × L)

**Base inspecionada:** `main` @ `9285acdcc55a9eb39db7d4e5b1c48339c0c49780`
(2026-11-04T11:05:35Z, `feat(theme): add site-specific rollout limits`), pai
`31ee26372ff039bd015c37b0ba3f6d6a0284afab`. Árvore: 2.578 blobs,
`truncated:false`. **Verificação da frente O aplicada: 7/7 arquivos
byte-idênticos ao HEAD P.** Refresh completo da árvore do HEAD P antes do
build (639 paths / 4.412.529 bytes), sem clone Git.

## Lacuna funcional real

Duas limitações declaradas conviviam desde as frentes K e L:

1. **K** (`docs/GUIA-IDIOMA-POR-SITE.md:50`): "`startup/language` roda
   **depois** de `startup/theme`" — um visitante que já tinha idioma na sessão
   (ex.: navegou antes noutro host com o mesmo cookie) carregava esse idioma
   para o site novo.
2. **L** (`docs/GUIA-CONDICOES-POR-SITE.md:82`): pela mesma ordem,
   `context['language']` usado pelo `ConditionEngine` era o **default do
   configfile** — uma regra `language eq en-us` nunca casava quando o idioma
   vinha do domínio ou de `?language=`.

A frente P fecha as duas com **uma única fonte de verdade**, sem reabrir
J / N / O, sem mudar a ordem das pré-ações e sem query nova.

## O que muda (4 arquivos completos + 2 guias)

| Arquivo | Mudança |
|---|---|
| `dev/system/library/language_negotiator.php` | **novo** — `LanguageNegotiator` puro (`final`, só métodos estáticos); precedência `?language= > sessão (mesmo site) > site > default`, allowlist `pt-br/en-us/es-eu`, `clean()` idêntico ao legado F2-3 |
| `dev/catalog/controller/startup/theme.php` | Sobe o bloco de resolução de site (O) para **antes** do contexto; chama `resolve()` uma vez, publica `catalog_theme_language_effective` / `..._source` / `..._site_changed`, grava a sessão e injeta o código em `context['language']` |
| `dev/catalog/controller/startup/language.php` | Passa a **aplicar** o resultado (serviço `Language`, `language_directory`, origem allowlistada) sem segunda decisão nem segunda leitura; fallback fail-closed para instalação parcial |
| `docs/GUIA-IDIOMA-EFETIVO.md` | **novo** — guia de uso, compatibilidade, segurança e limites |
| `docs/GUIA-CONDICOES-POR-SITE.md` | Limitação da L substituída pelo comportamento resolvido |
| `docs/GUIA-IDIOMA-POR-SITE.md` | Limitação da sessão substituída pelo comportamento resolvido |

**Compatibilidade:** a ordem `['startup/setting','startup/theme','startup/language']`
em `dev/system/config/catalog.php` **não muda**. `catalog_theme_language_source`
continua em `{request, session, site, default}` (badge do Admin da K) e
`language_directory` continua alimentando `<html lang>`, `og:locale` e a
matriz `hreflang` da N. Sessões antigas sem `language_site` mantêm o
comportamento da K: **zero invalidação retroativa**. A troca explícita via
`?language=` continua soberana.

**Sem:** migration, schema, coluna, tabela, escrita em arquivo, cron, cookie
novo, `custom_js`, `<script>` inline, `|raw` novo, `fetch`/`eval`/`innerHTML`,
chave de tradução (418 ×3 intactas), token CSS novo, JS novo, CSS novo,
manifesto, contrato, global Twig novo, vhost/DNS, upload, `<select>` novo ou
escrita remota. Os 14 `CHECKSUMS*.txt` permanecem intactos e **nenhum** dos
6 arquivos do pacote é coberto por eles.

## Segurança (fail-closed)

- Códigos fora de `pt-br|en-us|es-eu` ou não habilitados em `getEnabledCodes()`
  são descartados; `enabled=[]` ⇒ `en-us`. Nenhum texto livre do `HTTP_HOST`
  entra na decisão: o site vem de `ThemeSites::match()` (J/O) e o idioma do
  site da política **publicada** (mesmo critério da O, para não criar ciclo).
- A única escrita nova é a chave de sessão `language_site` (allowlistada),
  ao lado de `language`. Trocar de site com o mesmo cookie invalida apenas o
  idioma herdado; dentro do mesmo site a sessão continua prevalecendo.
- `startup/language.php` sem `catalog_theme_language_effective` cai no default
  do painel/configfile; um valor forjado (`<script>`) é recusado pela
  allowlist. `LanguageNegotiator` tem zero IO (verificado por `token_get_all`
  descontando docblocks).
- Classe **nova** resolvida pelo **`Autoloader` real**
  (`Reamur\System` → `DIR_SYSTEM`), validada em harness próprio — regra do
  hotfix do fatal `ScenePresetRegistry` preservada.

## Validação executada (tudo `/0`)

| Harness | Resultado |
|---|---|
| `php -l` (3 PHP do pacote, PHP 8.5.12 WASM) | **3/0** |
| `node --check` (40 JS do HEAD P) | **40/0** |
| H1 `fp_negotiator_test.php` (resolvedor puro: precedência, troca de site, fail-closed) | **78/0** |
| H2 `fp_pipeline_test.php` (`setting → theme → language` + condições reais da L) | **58/0** |
| H3a `fp_autoload_test.php` (Autoloader real da classe nova) | **5/0** |
| H3b `fp_regression_test.php` (regressão A–O, 418 ×3, 25 templates, guias) | **70/0** |
| H4 `fp_jsdom_test.js` (jsdom 29.1.1 + `<head>` real da N com Twig 3.14) | **64/0** |
| **Total de asserções** | **318/0** |

Réplica A/B de validação com 824 paths (leitura remota, sem clone Git,
sem vendor/imagens): `verify-checksums` (14 manifestos), `repo-health`,
`verify-ci-workflow` e `verify-contract-freeze` **verdes e idênticos** antes e
depois. 4 arquivos alterados + 2 novos, 820 paths
congelados idênticos, 14 manifestos byte-idênticos, 25 `TEMPLATE_KEYS`
congelados, 418 chaves ×3, 21 arquivos de A–O intactos, frente O
7/7, build idempotente 6/6, `unzip -t`/CRC OK, `sha256sum -c` OK.

## Impacto crítico

Verificado e corrigido onde apareceu: **a base local ainda era o snapshot do
HEAD O**, e só 10 arquivos tinham sido atualizados. O `theme_schedule.php`
local não tinha `sites`/`site` da frente O enquanto o HEAD P real os tem —
isso teria produzido um pacote P construído sobre uma base falsa. Foi feito o
**refresh completo da árvore do HEAD P** (639 paths) e confirmado que a O está
7/7 byte-idêntica antes de reconstruir o pacote e rerodar todos os harnesses.
As demais divergências eram expectativas dos harnesses (normalização legada
que remove espaços/dígitos mas preserva hífen, default inválido caindo no
primeiro idioma habilitado, `spec()['site_ids']` pertencer ao
`ConditionEngine` e não ao `ThemeSchedule`, cor sem `published`, host
**configurado** ≠ host do request, termos proibidos citados **em docblocks ou
backticks**) — corrigidas sem alterar o código entregue, por comparação de
contagens A/B e parsing de tokens. Nenhuma regressão A–O detectada.

## Limitações honestas

O idioma do site usado na negociação vem da política **publicada** (evita
ciclo com a escolha do config efetivo); `catalog_theme_site_language` do
snapshot (J/K) continua publicado normalmente para o Admin. Sem CMS completo,
MySQL, sessão autenticada, browser com rede, DNS/vhost, WebGL, E2E, migration,
staging, deploy, PHP nativo, Composer, PHPStan, rsync ou escrita no GitHub.
PHP 8.5.12 WASM, Twig 3.14 e jsdom 29.1.1 com mocks **não são um servidor**.
CI remoto não verificado. **CI verde / health verde ≠ release aprovada**;
F9-27 segue `BLOCKED_NOT_RUN` com `release_approved:false`, BDREditor/F9-103
pausado. Nenhum status `NOT_RUN`/`SKIP`/`BLOCKED_NOT_RUN` foi convertido em
`PASS`.

## Instalação

Extrair os 6 arquivos completos sobre a raiz do repositório (2 são novos),
preservando a estrutura de diretórios. Não há patches nem manifestos a
atualizar. Depois: `bash tools/verify-checksums.sh && bash tools/repo-health.sh`.
Sem sessão com `language_site` e sem `?language=`, o comportamento é idêntico
às frentes A–O.
