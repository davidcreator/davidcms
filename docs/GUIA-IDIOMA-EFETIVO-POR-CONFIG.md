# Idioma efetivo coerente com o config efetivo (Frente S)

A frente **P** unificou a negociação de idioma num resolvedor puro, mas
registrou uma limitação: para não criar ciclo, a negociação acontece **antes**
da decisão de rollout e usa a política de sites **publicada**. A frente **R**
alinhou o **ID do site** ao config efetivo; faltava alinhar o **idioma desse
site**.

## O defeito real

Publicado: `sites[shop].language = pt-br`. Rascunho em rollout:
`sites[shop].language = es-eu`. Visitante novo em `loja.exemplo.com`, sem
`?language=` e sem sessão:

| Valor | HEAD S (antes) | Frente S |
|---|---|---|
| `catalog_theme_site_language` (snapshot) | `es-eu` | `es-eu` |
| `catalog_theme_language_source` | `site` | `site` |
| `catalog_theme_language_effective` | **`pt-br`** | `es-eu` |
| `language_directory` / `<html lang>` | **`pt-br`** | `es-eu` |
| `og:locale` / `hreflang` | **`pt_br`** | `es_eu` |
| `context['language']` (condições L) | **`pt-br`** | `es-eu` |

O rascunho era renderizado com o idioma do publicado, enquanto o badge do
Admin e as condições por site enxergavam outro — uma regra
`language eq es-eu` nunca casava.

## O que muda (2 arquivos completos + 2 guias atualizados + 1 novo)

| Arquivo | Mudança |
|---|---|
| `dev/system/library/language_negotiator.php` | Novo `alignSite(array $resolved, mixed $effectiveSiteLanguage, array $enabled): array` — **puro**, mesmo formato de retorno de `resolve()` |
| `dev/catalog/controller/startup/theme.php` | Guarda `getEnabledCodes()` (mesma 1 consulta), chama `alignSite()` **depois** da decisão e do realinhamento da R, e republica código/sessão/contexto **somente se o código mudou** |
| `docs/GUIA-IDIOMA-EFETIVO.md` | Limitação da P substituída pelo comportamento resolvido |
| `docs/GUIA-SITE-EFETIVO-COERENTE.md` | Seção "o que a R não fecha" atualizada |
| `docs/GUIA-IDIOMA-EFETIVO-POR-CONFIG.md` | Este guia (novo) |

## Soberania preservada (o ponto central)

`alignSite()` age **somente** quando `source === 'site'`:

- **`?language=`** (`source=request`) ⇒ **nunca** alterado, continua soberano.
- **Sessão do mesmo site** (`source=session`) ⇒ **nunca** alterada.
- **Default do painel** (`source=default`) ⇒ **nunca** alterado.
- **Troca de site** (`site_changed=true`) ⇒ a P já descarta a sessão; se a
  origem virar `site`, o realinhamento usa o idioma do site de destino.
- Código fora de `pt-br|en-us|es-eu`, não habilitado em `getEnabledCodes()`,
  vazio ou igual ao atual ⇒ **sem mudança** (fail-closed).

`catalog_theme_language_source` continua publicando apenas
`request|session|site|default`; `startup/language.php` continua **aplicando**
`catalog_theme_language_effective` sem decidir de novo. A ordem das
pré-ações `['startup/setting','startup/theme','startup/language']` e a
precedência da K ficam intactas.

## Compatibilidade e segurança

- **Sem divergência** (publicado = efetivo): `alignSite()` devolve o array
  inalterado, o código não muda e o bloco novo **não republica nada** ⇒
  comportamento **byte a byte idêntico às frentes A–R**.
- **Nenhuma consulta nova:** a allowlist já lida pela P é reutilizada
  (`selects=1`, medido em A/B nos dois lados).
- A única escrita é a **mesma sessão** que a P já gravava
  (`language` + `language_site`), atualizada para o código correto. Nenhuma
  escrita em banco/arquivo, cookie novo, cron, migration, schema, coluna,
  tabela, `|raw`, `<script>` inline, `fetch`/`eval`/`innerHTML`, JS, CSS,
  Twig, chave de tradução (418 ×3 intactas), global Twig novo, manifesto,
  contrato, vhost/DNS, upload, GLB, WebGL, BDREditor, `custom_js` ou escrita
  remota. `HTTP_HOST` continua **apenas comparado**, nunca publicado.
- Os 14 `CHECKSUMS*.txt` permanecem intactos; nenhum arquivo do pacote é
  coberto por eles.

## Limitações honestas — o que a S **não** fecha

A limitação da **N** permanece: o CMS usa `?language=` (Adendo F2-3 do ADR
0001), então as variantes `hreflang` são URLs com query, não paths/hosts
distintos; isso exigiria DNS/vhost, fora de escopo. O idioma negociado continua
sendo resolvido **antes** da decisão de rollout (necessário para não criar
ciclo) e apenas **realinhado** depois — não há segunda negociação completa.
A sonda do Admin (M/Q/O) e a prévia comparativa não foram tocadas.

Sem CMS completo, MySQL, sessão autenticada, browser com rede, DNS/vhost,
WebGL, E2E, migration, staging, deploy, PHP nativo, Composer, PHPStan, rsync
ou escrita no GitHub: validação com PHP 8.5.12 WASM, Twig 3.14 e jsdom 29.1.1
com mocks, que **não são um servidor**. CI remoto não verificado.
**CI verde / health verde ≠ release aprovada**; F9-27 segue
`BLOCKED_NOT_RUN` com `release_approved:false`, BDREditor/F9-103 pausado.

## Instalação

Extrair os 5 arquivos completos sobre a raiz do repositório (o guia é novo),
preservando a estrutura de diretórios. Não há patches nem manifestos a
atualizar. Depois: `bash tools/verify-checksums.sh && bash tools/repo-health.sh`.
Sem divergência de idioma entre publicado e rascunho, o comportamento é
idêntico ao das frentes A–R.
