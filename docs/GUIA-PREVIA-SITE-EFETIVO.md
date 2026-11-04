# Prévia e sonda do Admin coerentes com o site efetivo (Frente T)

A frente **R** fechou, no **Catalog**, a divergência entre o site usado em
`ThemeSchedule::decide()` (política **publicada**, para não criar ciclo) e o
site re-resolvido por host no config **efetivo**. A frente **S** fechou o
idioma desse site. Os guias da R e da S registraram, porém, que **a sonda do
Admin (M/Q/O) e a prévia comparativa não foram tocadas**.

## O defeito real

Publicado: `shop.host = loja.exemplo.com`. Rascunho em rollout liberado para
`shop`: `shop.host = nova.exemplo.com`, cor `#333333`, condição
`site eq shop`. Visitante em `loja.exemplo.com`:

| Consumidor | Catalog (R/S) | HEAD T (prévia Admin) | Frente T |
|---|---|---|---|
| `decide()` da O | `shop` → rascunho | **`primary`** → publicado | `shop` → rascunho |
| site do painel rascunho | `shop` | **`primary`** | `shop` |
| `context['site']` da L | `shop` | **`primary`** | `shop` |
| cor por `site eq shop` | `#333333` | **`#222222`** | `#333333` |

A prévia mostrava "publicado × publicado" e cores de `primary` enquanto o
visitante recebia o rascunho de `shop`. A causa é a mesma da R: `compare()`
resolvia cada painel pelo host da **sua própria** política e a sonda da O
começava em `primary`, sem relação com o host simulado.

## O que muda (3 arquivos completos + 5 guias atualizados + 1 novo)

| Arquivo | Mudança |
|---|---|
| `dev/admin/controller/design/theme.php` | `edit()` lê a política publicada (mesma consulta já existente), publica `data-sites-published` e alinha o badge do card ao ID estável; `compare()` calcula `$seedId`, usa-o em `decide()` quando a sonda da O está vazia e propaga o ID aos dois painéis por `ThemeSites::byId()` (Q/R) |
| `dev/admin/view/template/design/theme_form.twig` | Novo `data-sites-published` (escapado com `e('html_attr')`) e opção padrão vazia em `schedule_probe_site`, reaproveitando `text_sites_probe` — **nenhuma chave de tradução nova** |
| `dev/admin/view/js/theme-sites.js` | A sonda local de host passa a comparar a política **publicada**; sem o atributo novo, cai no mapa atual (fail-closed e compatível com A–S) |
| `docs/GUIA-SITE-EFETIVO-COERENTE.md` | Limitação da R sobre o Admin substituída |
| `docs/GUIA-IDIOMA-EFETIVO-POR-CONFIG.md` | Limitação da S sobre o Admin substituída |
| `docs/GUIA-SITE-POR-ID.md` | Observações da Q sobre P/Admin atualizadas |
| `docs/GUIA-PREVIA-POR-SITE.md` | Observação da M sobre hosts divergentes atualizada |
| `docs/GUIA-ROLLOUT-POR-SITE.md` | Sonda da O atualizada |
| `docs/GUIA-PREVIA-SITE-EFETIVO.md` | Este guia (novo) |

## Soberania preservada

- **`preview_site` (Q/M)** com ID allowlistado e ativo nos dois painéis continua
  sendo a fonte de verdade; o `byId()` da Q não é alterado.
- **`schedule_probe_site` (O)** com ID explícito continua **soberano**: a T não
  reescreve a sonda do usuário. Apenas a nova opção **vazia** segue `$seedId`.
- **`preview_language`** continua soberano. É uma escolha explícita do
  simulador, equivalente ao `?language=` da S: `alignSite()` **não** é chamado
  no Admin e a precedência da K não é reaberta.
- **`site_probe_host`** continua **apenas comparado**, nunca refletido em HTML,
  global Twig, CSS ou log. `HTTP_HOST` do Admin só alimenta `match()`.
- **Fail-closed:** ID fora da allowlist, site ausente/desabilitado, política
  publicada vazia ou `byId()` inativo ⇒ mantém o resultado legado de `match()`.

## Compatibilidade e segurança

- `ThemeSites`, `ThemeSchedule`, `ThemeSnapshot`, `ThemeCompareView`,
  `LanguageNegotiator`, `startup/theme.php`, a ordem das pré-ações, os 14
  manifestos, os contratos, as 418 chaves ×3 e os 25 `TEMPLATE_KEYS` ficam
  **intocados**. Nenhuma reabertura de A–S.
- **Sem consulta nova:** `getPublishedConfig()` já era chamada em `edit()`; em
  `compare()` nenhuma leitura adicional é feita. `match()`, `byId()` e
  `decide()` são puros.
- **Sem:** escrita em banco/arquivo/sessão/cookie, migration, schema, coluna,
  tabela, cron, `|raw`, `<script>` inline, `fetch`/`eval`/`innerHTML`, CSS novo,
  global Twig novo, chave de tradução, vhost/DNS, upload, GLB, WebGL,
  BDREditor, `custom_js`, pacote sobreposto ou escrita remota. O JS continua
  apenas DOM (`textContent`, `getAttribute`, `JSON.parse`).
- Os 14 `CHECKSUMS*.txt` permanecem intactos; nenhum arquivo do pacote é
  coberto por eles.

## Limitações honestas — o que a T **não** fecha

Permanece a limitação da **N**: o CMS usa `?language=` (Adendo F2-3 do ADR
0001), então as variantes `hreflang` são URLs com query, não paths/hosts
distintos — exigiria DNS/vhost, fora de escopo. A prévia continua sendo uma
**simulação declarativa**: não há sessão autenticada, MySQL, browser com rede,
CMS completo, E2E, staging ou deploy. O `preview_language` é uma escolha do
simulador, não a negociação real da P/S.

Sem CMS completo, MySQL, sessão de Admin autenticada, browser com rede,
DNS/vhost, WebGL, E2E, migration, staging, deploy, PHP nativo, Composer,
PHPStan, rsync ou escrita no GitHub: validação com PHP 8.5.12 WASM, Twig 3.14
e jsdom 29.1.1 com mocks, que **não são um servidor**. CI remoto não
verificado. **CI verde / health verde ≠ release aprovada**; F9-27 segue
`BLOCKED_NOT_RUN` com `release_approved:false`, BDREditor/F9-103 pausado.

## Instalação

Extrair os 9 arquivos completos sobre a raiz do repositório (o guia é novo),
preservando a estrutura de diretórios. Não há patches nem manifestos a
atualizar. Depois: `bash tools/verify-checksums.sh && bash tools/repo-health.sh`.
Com `schedule_probe_site` explícito ou sem divergência de hosts/publicado, o
comportamento é idêntico ao das frentes A–S.
