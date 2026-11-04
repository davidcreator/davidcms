# Site por ID — desempate determinístico na prévia (Frente Q)

A frente **M** passou a permitir escolher o site da prévia comparativa por um
`<select name="preview_site">` com os 8 IDs allowlistados de
`ThemeSites::IDS`. Mas o `compare()` fazia um **round-trip por host**: pegava
`host` daquele ID em cada política e chamava `ThemeSites::match()`.

Isso deixou uma limitação declarada no guia da M:

> se um site usa o mesmo host de outro dentro da mesma política, vale a ordem
> allowlistada da J (o primeiro habilitado)

`match()` percorre `IDS` e devolve o **primeiro** site habilitado com aquele
host. Com `primary.host == shop.host`, escolher **`shop`** na prévia fazia os
dois painéis resolverem **`primary`** — a comparação virava `primary × primary`
(ou `shop × primary` com hosts divergentes) sem que a fita de contexto
mostrasse o motivo. A mesma causa aparecia quando o rascunho alterava o host
de um site para um valor já usado por outro.

## O que a frente Q entrega

| Arquivo | Mudança |
|---|---|
| `dev/system/library/theme/theme_sites.php` | Novo `byId(array $sites, mixed $rawId): array` — resolvedor **puro** por ID allowlistado; `REASONS` ganha `'id'` e `spec()` passa a publicá-lo |
| `dev/admin/controller/design/theme.php` | `compare()` usa `byId()` nos dois painéis quando `preview_site` é válido; fallback **byte a byte idêntico ao legado M** nos demais casos |
| `docs/GUIA-PREVIA-POR-SITE.md` | Limitação substituída pelo comportamento resolvido |
| `docs/GUIA-SITE-POR-ID.md` | Este guia (novo) |

`byId()` valida: ID em `IDS`, presença do site na política normalizada,
`enabled=true` e host válido por `validHost()`. Qualquer divergência devolve
`emptySite()` ⇒ `compare()` cai para a sonda de host (fail-closed). O retorno
tem o mesmo formato de `match()` (`id`, `host`, `slug`, `language`, `enabled`,
`reason`, `active`), com `reason='id'` — por isso `ThemeSnapshot::resolve()`,
`bars()`, `css()`, `globals()` e a fita do `ThemeCompareView` continuam
funcionando **sem nenhuma alteração**.

## Compatibilidade

- **Sem `preview_site`** (opção padrão "Simular host"): `$forcedOk=false` e o
  caminho legado da M é executado sem nenhuma mudança — comportamento idêntico
  às frentes A–P.
- **`preview_site` forjado / fora da allowlist**: `$forced=''` ⇒ legado.
- **ID ausente ou desabilitado em qualquer painel**: `byId()` devolve vazio ⇒
  legado, inclusive o uso individual de `$hPub`/`$hDraft`.
- **Sonda de host (`site_probe_host`) continua soberana** e continua
  simulando `match(host)` — que é exatamente o que o Catalog faz no request
  real, onde só existe `HTTP_HOST`. A frente Q **não** muda `match()`,
  `normalize()`, `resolve()`, `IDS`, `KEYS`, `LANGUAGES` nem `SCOPES`.
- A sonda de rollout (`schedule_probe_site`, frente O) e a sessão de idioma
  (`language_site`, frente P) **já** usavam IDs allowlistados; nenhum arquivo
  delas foi tocado. Nenhuma reabertura de J / K / L / N / O / P.

## Segurança

Nenhum dado do request é usado por `byId()`: apenas o ID allowlistado do POST
e a política de sites já normalizada. O host do visitante continua sendo
**apenas comparado**, nunca refletido em HTML, global Twig, CSS ou log.
Sem `|raw`, `<script>` inline, `fetch`/`eval`/`innerHTML`, JS, CSS, chave de
tradução (418 ×3 intactas), manifesto, contrato, migration, schema, coluna,
tabela, escrita em banco/arquivo, cookie, sessão, cron, vhost/DNS, upload,
GLB, WebGL, BDREditor, `custom_js` ou escrita remota. Os 14 `CHECKSUMS*.txt`
permanecem intactos; nenhum arquivo do pacote é coberto por eles.

## Limitações honestas — o que a Q **não** fecha

A Q atua apenas na **prévia do Admin**, onde o ID é conhecido. A divergência
que ela expôs no **Catalog** (rollout decidido para um site, mas renderizado
com outro quando o rascunho altera hosts) foi **fechada pela frente R**, que
reaproveita `byId()` para propagar o ID estável da política publicada ao
config efetivo — sem criar ciclo e sem reabrir a decisão da O.

Permanece a limitação da frente **N** (`?language=` gera variantes com query,
não paths/hosts distintos) — exigiria DNS/vhost, fora de escopo.

A divergência de idioma da **P** foi **fechada pela frente S**
(`LanguageNegotiator::alignSite()`), sem reabrir a precedência da K. A sonda do
Admin e a prévia comparativa, que a Q deixou intactas, foram **alinhadas pela
frente T** ao ID estável da política publicada usado pela R no Catalog.

Sem CMS completo, MySQL, sessão de Admin autenticada, browser com rede,
DNS/vhost, WebGL, E2E, migration, staging, deploy, PHP nativo, Composer,
PHPStan, rsync ou escrita no GitHub: validação com PHP 8.5.12 WASM, Twig 3.14
e jsdom 29.1.1 com mocks, que **não são um servidor**. CI remoto não
verificado. **CI verde / health verde ≠ release aprovada**; F9-27 segue
`BLOCKED_NOT_RUN` com `release_approved:false`, BDREditor/F9-103 pausado.

## Instalação

Extrair os 4 arquivos completos sobre a raiz do repositório (o guia é novo),
preservando a estrutura de diretórios. Não há patches nem manifestos a
atualizar. Depois: `bash tools/verify-checksums.sh && bash tools/repo-health.sh`.
Sem `preview_site`, o comportamento é idêntico ao das frentes A–P.
