# Frente N — Matriz hreflang multilíngue (SEO)

**Base inspecionada:** `main` @ `6cd1d9c254776d2947b6645a88cbb7f240c8ac34`
(2026-11-03T21:00:23Z, `feat: add site selector for theme comparison preview`),
pai `026a2a163665a3a6cc49abbf9bc1815e40192793`. Árvore: 2.576 blobs,
`truncated:false`. **Verificação da frente M aplicada: 5/5 arquivos
byte-idênticos ao HEAD N.**

## Lacuna funcional real

O próprio `dev/catalog/controller/common/sitemap.php` (F4-07 / ADR 0031)
documenta: *"matriz hreflang é tarefa futura, renúncia na ADR"*. O `<head>` do
Catálogo publica canonical, OG/Twitter e descoberta do sitemap, e o switcher
F2-3/F4-02 já lista os idiomas ativos — mas **nenhuma** variante de idioma é
declarada para motores de busca, nem no HTML nem no `sitemap.xml`. A frente N
implementa essa tarefa pendente sem mexer no canonical, no dedup, nas rotas ou
nas decisões A–M.

## O que muda (4 arquivos completos + 1 guia novo)

| Arquivo | Mudança |
|---|---|
| `dev/catalog/controller/common/header.php` | Publica `hreflangs[]`, `hreflang_default` e `og.locale_alternate`, derivados do canonical e dos idiomas ativos do switcher |
| `dev/catalog/view/template/common/header.twig` | `<link rel="alternate" hreflang>` por idioma + `x-default`, e `<meta property="og:locale:alternate">`; tudo autoescapado |
| `dev/catalog/controller/common/sitemap.php` | Matriz `xhtml_links` por `<loc>` (idiomas ativos + `x-default`); 1 SELECT de leitura |
| `dev/catalog/view/template/common/sitemap.twig` | `xmlns:xhtml` no `<urlset>` e `<xhtml:link rel="alternate">` por URL |
| `docs/GUIA-HREFLANG-MULTILINGUE.md` | Guia de instalação, regras de segurança e limites (novo) |

**Sem:** migration, schema, coluna, tabela, escrita, sessão, cookie, cron,
`custom_js`, `<script>` inline, `|raw` novo, `fetch`/`eval`/`innerHTML`,
chave de tradução, token CSS, JS, manifesto, contrato, global Twig ou vhost/DNS.
Os 14 `CHECKSUMS*.txt` permanecem intactos; nenhum arquivo do pacote está
coberto por eles.

## Segurança (fail-closed)

- Origem das URLs = **`site_url`** (config) via `requestOrigin()`; **nunca**
  `HTTP_HOST`. Testado com `HTTP_HOST=evil-marker.test`: zero ocorrência.
- Sem canonical (rota suja `index.php`) ⇒ `hreflangs=[]` e **nenhum** alternate.
- Allowlist `pt-br|en-us|es-eu` **e** `getEnabledCodes()` (`status=1` +
  diretório real). Nenhum parâmetro do request é copiado para as URLs.
- `x-default` = próprio canonical / próprio `<loc>` (sem `language`).
- `&` das URLs é escapado como `&amp;` pelo autoescape do Twig; XML validado
  por `simplexml_load_string` e `DOMParser`.

## Validação executada (tudo `/0`)

| Harness | Resultado |
|---|---|
| `php -l` (2 PHP do pacote, PHP 8.5.12 WASM) | **2/0** |
| `node --check` (9 JS de tema do HEAD) | **9/0** |
| H1 `fn_header_test.php` (`header.php` + Twig 3.14) | **60/0** |
| H2 `fn_sitemap_test.php` (`sitemap.php` + Twig, XML) | **60/0** |
| H3 `fn_regression_test.php` (autoloader real + A–M) | **56/0** |
| H4 `fn_jsdom_test.js` (jsdom 29.1.1 + DOMParser) | **49/0** |
| **Total de asserções** | **236/0** |

Réplica A/B de validação com 1201 paths (leitura remota, sem clone Git,
sem vendor/imagens): `verify-checksums`, `repo-health`,
`verify-ci-workflow` e `verify-contract-freeze` **verdes e idênticos** antes e
depois. 1197 arquivos congelados idênticos, 14 manifestos byte-idênticos,
25 TEMPLATE_KEYS congelados, 418 chaves ×3, build idempotente 5/5, `unzip -t`/CRC OK,
`sha256sum -c` do pacote OK.

## Impacto crítico

Verificado e corrigido onde apareceu: o build inicial inseriu `|raw` dentro de
um **comentário Twig**, o que inflaria os guards de contagem — removido. As
demais divergências encontradas eram expectativas dos harnesses (formato
`og:locale` legado `pt_br`, assinatura de `ThemeCompareView::render()`,
`ThemePackage::spec()/decode()`, `site_rows` no form e a classe `ScenePreset` — canônica apenas em
`ScenePresetRegistry` —),
corrigidas sem alterar o código entregue. Nenhuma regressão A–M detectada:
`ConditionEngine` com `site`, `ThemeSites` 8 IDs, `ThemePackage` export/import,
`ThemeCompareView` com `Sites <code>shop</code>`, form Admin M com
`#preview-site` e 8 IDs, builder L/M com `data-ctx="site"`.

## Limitações honestas

Sem CMS completo, MySQL, sessão autenticada, browser com rede, DNS/vhost,
WebGL, E2E, migration, staging, deploy, PHP nativo, Composer, PHPStan, rsync ou
escrita no GitHub. PHP 8.5.12 WASM, Twig 3.14 e jsdom 29.1.1 com mocks **não
são um servidor**. CI remoto não verificado. O CMS usa `?language=`
(Adendo F2-3/ADR 0001), portanto as variantes são URLs com query, não
paths/hosts distintos; `hreflang` entre domínios exigiria DNS/vhost, fora de
escopo. Idiomas extras no painel aparecem no switcher, mas não entram na
matriz (allowlist dos 3 códigos do core). `og:locale` segue o
`str_replace('-','_')` do F4-07 (`pt_br`), sem alterar o metadado legado.
**CI verde / health verde ≠ release aprovada**; F9-27 segue
`BLOCKED_NOT_RUN` com `release_approved:false`, BDREditor/F9-103 pausado.

## Instalação

Extrair os 4 arquivos completos sobre a raiz do repositório (o guia é novo),
preservando a estrutura de diretórios. Não há patches, nem manifestos a
atualizar. Depois: `bash tools/verify-checksums.sh && bash tools/repo-health.sh`.
Sem canonical, o HTML é idêntico ao das frentes A–M.
