# Site efetivo coerente com a decisão de rollout (Frente R)

A frente **O** documentou uma limitação de arquitetura: para não criar um
ciclo (a escolha do config depende da decisão de rollout, que depende do
site), o `decide()` recebe o site resolvido pela política **publicada**.
Mas, logo abaixo, `startup/theme.php` resolvia o site **de novo** por
`ThemeSites::match($sitesCfg, $siteHost)` sobre o config **efetivo** (que
pode ser o rascunho). Esse segundo resultado alimentava `context['site']`
das condições (L), o tema-raiz da cadeia de child-themes (J),
`catalog_theme_site` e `catalog_theme_site_language`.

## O defeito real

Publicado: `shop.host = loja.exemplo.com`. Rascunho em rollout:
`shop.host = nova.exemplo.com`. Visitante acessa **`loja.exemplo.com`**:

| Consumidor | HEAD R (antes) | Frente R |
|---|---|---|
| `ThemeSchedule::decide()` (O) | `shop` | `shop` |
| `context['site']` (L) | **`primary`** | `shop` |
| tema-raiz `ThemeSites::resolve()` (J/E) | **`primary`** | `shop` |
| `catalog_theme_site_language` (K/P) | **`primary`** | `shop` |

O rascunho liberado para `shop` era renderizado com as cores, as regras de
`site` e o idioma de `primary` — sem nenhum aviso. A frente **Q** já tinha
mostrado o mesmo efeito na prévia do Admin; a R fecha o lado do **Catalog**.

## O que muda (1 arquivo completo + 2 guias atualizados + 1 novo)

| Arquivo | Mudança |
|---|---|
| `dev/catalog/controller/startup/theme.php` | Após `match()` no config efetivo, se o ID divergir do ID estável da política publicada, usa `ThemeSites::byId($sitesCfg, $siteIdSeed)` (frente Q) para recuperar os atributos do site correto; fail-closed para o legado |
| `docs/GUIA-ROLLOUT-POR-SITE.md` | Limitação da O substituída pelo comportamento resolvido |
| `docs/GUIA-SITE-POR-ID.md` | Seção "o que a Q não fecha" atualizada |
| `docs/GUIA-SITE-EFETIVO-COERENTE.md` | Este guia (novo) |

**A decisão da O é preservada integralmente:** o `decide()` continua recebendo
o ID da política publicada, `SCOPES` não muda, `sites=[]` continua sem
restrição e `reason='site'` continua na mesma posição da precedência. A R
apenas **propaga** esse mesmo ID aos consumidores do config efetivo. Nenhuma
consulta nova: `byId()` é puro e opera sobre `$sitesCfg` já normalizado.

## Compatibilidade e fail-closed

- **Sem divergência** (`published.sites == effective.sites`, caso normal de
  A–Q): `$effectiveId === $siteIdSeed`, o bloco novo **não executa**. `$site`
  continua sendo exatamente o retorno de `match()`, inclusive
  `reason='exact'`/`'default'` e `active`. Comportamento **byte a byte idêntico**.
- **Sem sites configurados**: `match()` devolve `emptySite()`, o bloco novo
  não executa e `context['site']` continua caindo em `primary` (K/L).
- **ID ausente ou desabilitado** no config efetivo: `byId()` devolve
  `emptySite()`, `active=false` ⇒ mantém o resultado legado de `match()`.
- **ID forjado / texto livre**: `$siteIdSeed` já é allowlistado por `IDS` com
  fallback `primary`; `byId()` valida de novo. Nenhum dado do `HTTP_HOST` é
  usado além da comparação já existente na J.

## Segurança

Nenhum host do request é publicado, nenhum texto livre entra no ID, nenhuma
escrita em banco/arquivo/sessão/cookie, nenhum `|raw`, `<script>` inline,
`fetch`/`eval`/`innerHTML`, JS, CSS, Twig, chave de tradução (418 ×3
intactas), global Twig novo, manifesto, contrato, migration, schema, coluna,
tabela, cron, vhost/DNS, upload, GLB, WebGL, BDREditor, `custom_js` ou
escrita remota. Os 14 `CHECKSUMS*.txt` permanecem intactos; nenhum arquivo
do pacote é coberto por eles. Ordem das pré-ações
`['startup/setting','startup/theme','startup/language']` inalterada.

## Limitações honestas — o que a R **não** fecha

A divergência de idioma da **P** (negociação pela política publicada versus
`catalog_theme_site_language` do config efetivo) foi **fechada pela frente S**:
depois da decisão, `LanguageNegotiator::alignSite()` realinha o código ao
idioma do mesmo ID no config efetivo, sem reabrir a precedência da K —
`?language=`, sessão e default continuam soberanos.

Permanece a limitação da **N** (`?language=` gera variantes com query, não
paths/hosts distintos) — exigiria DNS/vhost, fora de escopo. A sonda do Admin
(M/Q/O) e a prévia comparativa não foram tocadas.

Sem CMS completo, MySQL, sessão autenticada, browser com rede, DNS/vhost,
WebGL, E2E, migration, staging, deploy, PHP nativo, Composer, PHPStan, rsync
ou escrita no GitHub: validação com PHP 8.5.12 WASM, Twig 3.14 e jsdom 29.1.1
com mocks, que **não são um servidor**. CI remoto não verificado.
**CI verde / health verde ≠ release aprovada**; F9-27 segue
`BLOCKED_NOT_RUN` com `release_approved:false`, BDREditor/F9-103 pausado.

## Instalação

Extrair os 4 arquivos completos sobre a raiz do repositório (o guia é novo),
preservando a estrutura de diretórios. Não há patches nem manifestos a
atualizar. Depois: `bash tools/verify-checksums.sh && bash tools/repo-health.sh`.
Sem divergência de hosts entre publicado e rascunho, o comportamento é
idêntico ao das frentes A–Q.
