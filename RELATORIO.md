# Frente T — Prévia e sonda do Admin coerentes com o site efetivo

**Base inspecionada:** `main` @ `f993fe3c6cc8fdb7fe9b57a62a09ba06094452c2`
(2026-11-04T12:22:07Z, `feat(language): align site language to effective
config`), pai `650f354e8c43fba3d46fa667f187cbf07e405683`. Árvore: 2.583
blobs, `truncated:false`, 14 `CHECKSUMS*.txt`. **Verificação da frente S
aplicada: 7/7 arquivos byte-idênticos ao HEAD T** (inclusive `RELATORIO.md` e
`INDICE.md` da raiz). Refresh seletivo do HEAD T antes do build (663 paths /
4.560.341 bytes, mais 2 arquivos de raiz), sem clone Git.

## Lacuna funcional real

A frente **R** fechou no **Catalog** a divergência entre o site usado em
`ThemeSchedule::decide()` (política **publicada**, para não criar ciclo) e o
site re-resolvido por host no config efetivo; a **S** alinhou o idioma desse
site. Os dois guias, porém, registravam: *"A sonda do Admin (M/Q/O) e a prévia
comparativa não foram tocadas"*.

Publicado `shop.host = loja.exemplo.com`; rascunho em rollout liberado para
`shop` com `shop.host = nova.exemplo.com` e condição `site eq shop`;
visitante em `loja.exemplo.com`, sonda de rollout no padrão do formulário:

| Consumidor | Catalog (R/S) | HEAD T (prévia Admin) | Frente T |
|---|---|---|---|
| `decide()` da O | `shop` → rascunho | **`primary`** → publicado | `shop` → rascunho |
| site do painel rascunho | `shop` | **`primary`** | `shop` |
| `context['site']` da L | `shop` | **`primary`** | `shop` |
| cor por `site eq shop` | `#111111` | **`#222222`** | `#111111` |
| badge do card Sites | `shop` / idioma efetivo | **`primary`** / `pt-br` | `shop` / `es-eu` |

A prévia mostrava "publicado × publicado" com cores de `primary` enquanto o
visitante recebia o rascunho de `shop`. A causa é a mesma da R: `compare()`
resolvia cada painel pelo host da **sua própria** política e a sonda da O
começava em `primary`, sem relação com o host simulado.

## O que muda (3 arquivos completos + 5 guias atualizados + 1 novo)

| Arquivo | Mudança |
|---|---|
| `dev/admin/controller/design/theme.php` | `edit()` lê a política publicada (a **mesma** consulta já existente), publica `data-sites-published` e alinha o badge ao ID estável; `compare()` calcula `$seedId` pela política publicada, usa-o em `decide()` quando `schedule_probe_site` está vazio e propaga o ID aos dois painéis por `ThemeSites::byId()` (Q/R) |
| `dev/admin/view/template/design/theme_form.twig` | Novo `data-sites-published` escapado com `e('html_attr')` e opção padrão vazia em `schedule_probe_site`, reaproveitando `text_sites_probe` — **nenhuma chave de tradução nova** |
| `dev/admin/view/js/theme-sites.js` | A sonda local de host compara a política **publicada**; sem o atributo novo cai no mapa atual (fail-closed, idêntico a A–S) |
| `docs/GUIA-SITE-EFETIVO-COERENTE.md` | Limitação da R sobre o Admin substituída |
| `docs/GUIA-IDIOMA-EFETIVO-POR-CONFIG.md` | Limitação da S sobre o Admin substituída |
| `docs/GUIA-SITE-POR-ID.md` | Observações da Q sobre P/Admin atualizadas |
| `docs/GUIA-PREVIA-POR-SITE.md` | Observação da M sobre hosts divergentes atualizada |
| `docs/GUIA-ROLLOUT-POR-SITE.md` | Sonda da O atualizada |
| `docs/GUIA-PREVIA-SITE-EFETIVO.md` | Guia de uso, soberania, segurança e limites (novo) |

## Soberania preservada (o ponto central)

- **`preview_site` (Q/M)** com ID allowlistado e ativo nos dois painéis continua
  sendo a fonte de verdade; o `byId()` da Q não é alterado.
- **`schedule_probe_site` (O)** com ID explícito continua **soberano**: a T não
  reescreve a escolha do usuário. Apenas a nova opção **vazia** segue `$seedId`.
- **`preview_language`** continua soberano: é escolha explícita do simulador,
  equivalente ao `?language=` da S. `LanguageNegotiator::alignSite()` **não** é
  chamado no Admin e a precedência da K não é reaberta (medido: `es-eu` nos
  dois lados em `preview_language_es`).
- **`site_probe_host` / `HTTP_HOST`** continuam **apenas comparados**, nunca
  refletidos em HTML, global Twig, CSS ou log (`has_evil=false` nos 20
  cenários, nos dois lados).
- **Fail-closed:** ID fora da allowlist, site ausente/desabilitado, política
  publicada vazia ou `byId()` inativo ⇒ mantém o resultado legado de `match()`.

## Compatibilidade e segurança

- `ThemeSites`, `ThemeSchedule`, `ThemeSnapshot`, `ThemeCompareView`,
  `LanguageNegotiator`, `startup/theme.php`, `startup/language.php`, a ordem
  `['startup/setting','startup/theme','startup/language']`, os 14 manifestos,
  os contratos, as 418 chaves ×3 e os 25 `TEMPLATE_KEYS` ficam **intocados**.
  Nenhuma reabertura de A–S.
- **Sem consulta nova:** `getPublishedConfig()` já era chamada em `edit()`;
  `compare()` continua com `db_calls=3` nos 20 cenários. `match()`, `byId()` e
  `decide()` são puros. O contador de instrumentação `match_calls` cresce 1
  pela leitura extra da política publicada, sem afetar o HTML.
- **A/B real:** 20 cenários. **8 com HTML byte-idêntico** (sonda explícita da O,
  sem sites, host desconhecido, idioma mudado, sites diferentes etc.) e **12
  com correção funcional** (decisão, ID do site, contexto, cor ou fita). Casos
  centrais: `normal_vazio` `primary/publicado → shop/rascunho`;
  `host_mudado_vazio` `draft_id primary→shop`, `draft_primary #222222→#111111`,
  `matched ['site-primary']→['site-shop']`, `reason default→id`,
  `byId_calls 0→1`. `host_mudado_explicito_shop` prova que a sonda explícita
  da O já decidia certo e a T corrige **somente** o alinhamento dos painéis.
- **Form A/B:** `data-sites-published` ausente → presente (`shop.host=loja.test`
  enquanto o rascunho tem `nova.test`), badge `primary/pt-br → shop/es-eu`,
  `schedule_probe_site` 8 → 9 opções com primeiro valor `''`, asset
  `-theme-sites-j → -theme-sites-t`.
- **Sem:** escrita em banco/arquivo/sessão/cookie, migration, schema, coluna,
  tabela, cron, `|raw` novo, `<script>` inline, `fetch`/`eval`/`innerHTML`,
  CSS novo, global Twig novo, chave de tradução, vhost/DNS, upload, GLB,
  WebGL, BDREditor, `custom_js`, pacote sobreposto ou escrita remota. O JS
  continua apenas DOM (`textContent`, `getAttribute`, `JSON.parse`). Os 14
  `CHECKSUMS*.txt` permanecem intactos; **9/9 arquivos do pacote livres**.

## Validação executada (tudo `/0`)

| Harness | Resultado |
|---|---|
| `php -l` (1 PHP do pacote, PHP 8.5.12 WASM) | **1/0** |
| `node --check` (40 JS da réplica B com o pacote aplicado) | **40/0** |
| H1 `ft_sites_test.php` (contratos puros A–S + pureza dos 3 arquivos) | **102/0** |
| H2 `ft_pipeline_test.php` (pipeline real do pacote, 20 cenários + `edit()`) | **443/0** |
| H3 `ft_ab_test.php` (A/B **real** HEAD T × pacote T, campo a campo) | **556/0** |
| H4a `ft_autoload_test.php` (Autoloader real do controller do pacote) | **16/0** |
| H4b `ft_regression_test.php` (contratos, 418 ×3, 25 templates, guias, S 7/7) | **237/0** |
| H5 `ft_jsdom_test.js` (jsdom 29.1.1: form, sonda JS real, HTML, guias) | **260/0** |
| **Total de asserções** | **1655/0** |

A/B **real**, não por inferência: `/tmp/ft_probe.php` executa `compare()` e
`edit()` duas vezes (bootstrap do HEAD T e bootstrap do pacote T, separados)
sobre 20 cenários; o H3 compara os JSONs campo a campo com valores literais.
O H5 renderiza 10 HTMLs de prévia e 2 forms, e ainda **executa o
`theme-sites.js` de cada lado** no jsdom: digitando `loja.test`, a sonda do
HEAD responde `primary · default` e a do pacote `shop · exact`.

Réplica A/B de validação com 829 paths (leitura remota, sem clone Git,
sem vendor/imagens): `verify-checksums` (14 manifestos), `repo-health`,
`verify-ci-workflow` e `verify-contract-freeze` **verdes e idênticos** antes e
depois. 8 arquivos alterados + 1 novo, 821 paths
congelados idênticos, 14 manifestos byte-idênticos, 25 `TEMPLATE_KEYS`
congelados, 418 chaves ×3, 29 arquivos de A–S intactos, frente S
7/7, build idempotente 9/9, `unzip -t`/CRC OK, `sha256sum -c` 44/44 OK.

## Impacto crítico

Verificado e corrigido onde apareceu: a primeira rodada do H4b acusou 10
falhas, **todas em expectativas do harness** — regex das 418 chaves,
`RELATORIO.md`/`INDICE.md` que ainda não estavam no recorte local, um trecho
do guia Q com quebra de linha e o nome do próprio closure `$alignSite()` da T
(confundido com `LanguageNegotiator::alignSite()`). Nenhuma linha do código
entregue foi alterada por essas correções. Também foi feito o **refresh da
árvore do HEAD T** antes de qualquer build e os bootstraps A/B foram mantidos
separados (cada lado carrega o seu `theme.php`, o seu `theme_form.twig` e o seu
`theme-sites.js`) — sem isso a A/B seria falsa. Nenhuma regressão A–S
detectada. O H5 confirmou ainda o `iframe` de prévia legado (1 nos dois lados,
com `sandbox`) e não o converteu em defeito novo.

## Limitações honestas — o que a T **não** fecha

Permanece a limitação da **N**: o CMS usa `?language=` (Adendo F2-3 do ADR
0001), então as variantes `hreflang` são URLs com query, não paths/hosts
distintos — exigiria DNS/vhost, fora de escopo. A prévia continua sendo uma
**simulação declarativa**: não há sessão autenticada, MySQL, browser com rede,
CMS completo, E2E, staging ou deploy. O `preview_language` é uma escolha do
simulador, não a negociação real da P/S. A T **não** foi aplicada no GitHub
por nós: este pacote é instalável, pendente de aplicação/confirmação.

Sem CMS completo, MySQL, sessão de Admin autenticada, browser com rede,
DNS/vhost, WebGL, E2E, migration, staging, deploy, PHP nativo, Composer,
PHPStan, rsync ou escrita no GitHub. PHP 8.5.12 WASM, Twig 3.14 e jsdom 29.1.1
com mocks **não são um servidor**. CI remoto não verificado. **CI verde /
health verde ≠ release aprovada**; F9-27 segue `BLOCKED_NOT_RUN` com
`release_approved:false`, BDREditor/F9-103 pausado. Nenhum status
`NOT_RUN`/`SKIP`/`BLOCKED_NOT_RUN` foi convertido em `PASS`.

## Instalação

Extrair os 9 arquivos completos sobre a raiz do repositório (o guia é novo),
preservando a estrutura de diretórios. Não há patches nem manifestos a
atualizar. Depois: `bash tools/verify-checksums.sh && bash tools/repo-health.sh`.
Com `schedule_probe_site` explícito ou sem divergência de hosts entre
publicado e rascunho, o comportamento é idêntico ao das frentes A–S.
