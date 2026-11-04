# Frente O — Rollout de rascunho por site (H × J)

**Base inspecionada:** `main` @ `31ee26372ff039bd015c37b0ba3f6d6a0284afab`
(2026-11-04T10:47:26Z, `feat(seo): add hreflang multilingual support`), pai
`6cd1d9c254776d2947b6645a88cbb7f240c8ac34`. Árvore: 2.577 blobs,
`truncated:false`. **Verificação da frente N aplicada: 5/5 arquivos
byte-idênticos ao HEAD O.**

## Lacuna funcional real

A frente **H** (agendamento/rollout declarativo) e a frente **J** (multisite)
não se enxergavam: `ThemeSchedule::SCOPES` só conhecia
`all | logged_in | anonymous`. Um rascunho em rollout aparecia nos **8 sites
ou em nenhum** — não havia como liberar o rascunho apenas para `shop`, por
exemplo. A frente O adiciona a dimensão `site` à decisão pura, sem alterar
janela, percentual, bucket, semente do visitante, política de sites da J,
condições da L, prévia da M ou matriz hreflang da N.

## O que muda (6 arquivos completos + 1 guia novo)

| Arquivo | Mudança |
|---|---|
| `dev/system/library/theme/theme_schedule.php` | Chave `sites` (IDs allowlistados), `reason='site'`, `decide()`/`state()` com 5º argumento opcional, `bars()`/`css()`/`globals()`/`spec()` estendidos |
| `dev/catalog/controller/startup/theme.php` | Resolve o site da decisão pela política **publicada** e passa ao `decide()`; zero query nova |
| `dev/admin/controller/design/theme.php` | `edit()` publica os 8 IDs, POST aceita `schedule[sites][]`, `compare()` ganha sonda `schedule_probe_site` |
| `dev/admin/view/template/design/theme_form.twig` | Multi-select de sites + seletor de site na sonda (reaproveita `text_sites`/`text_sites_help`) |
| `dev/admin/view/js/theme-schedule.js` | Status mostra a contagem de sites; apenas DOM |
| `dev/system/library/theme/theme_compare_view.php` | Fita do agendamento exibe `Sites: shop, blog · shop` (ou `all`) |
| `docs/GUIA-ROLLOUT-POR-SITE.md` | Guia de uso, segurança e limites (novo) |

**Sem:** migration, schema, coluna, tabela, escrita, cron, cookie, sessão,
`custom_js`, `<script>` inline, `|raw` novo, `fetch`/`eval`/`innerHTML`,
chave de tradução (418 ×3 intactas), token CSS novo, JS novo (só DOM),
manifesto, contrato, vhost/DNS, upload ou global Twig aninhado. Os 14
`CHECKSUMS*.txt` permanecem intactos; nenhum arquivo do pacote é coberto por
eles.

## Segurança (fail-closed)

- IDs vêm **somente** de `ThemeSites::IDS`; valor fora da allowlist faz
  `normalize()` devolver `null` ⇒ `error_schedule`. `HTTP_HOST` continua sendo
  apenas **comparado** por `ThemeSites::match()`, nunca publicado.
- **Sem ciclo de decisão:** o site do `decide()` vem da política de sites
  **publicada**, resolvida antes da escolha do config. A política do config
  efetivo (J/L) continua comandando `context['site']`. `match()` é puro.
- O 5º argumento de `decide()`/`state()` é **opcional** (`''` ⇒ `primary`):
  todos os chamadores A–N seguem válidos. `sites=[]` ⇒ nenhuma restrição,
  comportamento **idêntico** às frentes A–N (testado em 4 hosts).
- `ThemeSchedule::SCOPES` **não muda**: `scope` continua sendo o público e
  `sites` é uma dimensão independente. Precedência:
  `disabled > pending > expired > scope > site > zero > outside_bucket > active`.
- POST endurecido: `schedule[sites]` precisa ser **array** (ou ausente);
  uma string é recusada antes do cast. Nada do visitante é renderizado.

## Validação executada (tudo `/0`)

| Harness | Resultado |
|---|---|
| `php -l` (4 PHP do pacote, PHP 8.5.12 WASM) | **4/0** |
| `node --check` (9 JS de tema do HEAD) | **9/0** |
| H1 `fo_schedule_test.php` (`ThemeSchedule` + pacotes E) | **90/0** |
| H2 `fo_startup_test.php` (`startup/theme.php` + J/K/L/M/N) | **51/0** |
| H3 `fo_admin_test.php` (`edit`/`buildDraftConfig`/`compare` + Twig) | **60/0** |
| H4 `fo_jsdom_test.js` (jsdom 29.1.1 + JS real no DOM) | **63/0** |
| **Total de asserções** | **277/0** |

Réplica A/B de validação com 804 paths (leitura remota, sem clone Git,
sem vendor/imagens): `verify-checksums` (14 manifestos / 142 arquivos),
`repo-health`, `verify-ci-workflow` e `verify-contract-freeze` **verdes e
idênticos** antes e depois. 798 arquivos congelados idênticos, 14 manifestos
byte-idênticos, 25 `TEMPLATE_KEYS` congelados, 418 chaves ×3, 7 arquivos de
K/L/M/N intactos, build idempotente 7/7, `unzip -t`/CRC OK, `sha256sum -c` OK.

## Impacto crítico

Verificado e corrigido onde apareceu: o POST inicial fazia
`(array)$rawSchedule['sites']`, e `(array)'shop'` virava `['shop']` — uma
string passaria na allowlist por acidente. Endurecido para exigir array e
devolver `error_schedule` caso contrário (teste `O.a.post.xss.0`).
As demais divergências eram expectativas dos harnesses (formato de `sites`
exigindo host válido em `ThemeSites::normalize()`, operador `+` do PHP que não
sobrescreve chaves, `percent=0` no form vazio, termos proibidos presentes
**apenas em comentários legados** da H) — corrigidas por comparação de
contagens A/B, sem alterar o código entregue. Nenhuma regressão A–N
detectada: `ConditionEngine` com `site`, `ThemePackage` export/import com
`schedule.sites`, `ThemeCompareView` com `Sites <code>shop</code>`, form Admin
M com `#preview-site`, `<head>` e `sitemap.xml` da N intactos.

## Limitações honestas

Sem CMS completo, MySQL, sessão autenticada, browser com rede, DNS/vhost,
WebGL, E2E, migration, staging, deploy, PHP nativo, Composer, PHPStan, rsync ou
escrita no GitHub. PHP 8.5.12 WASM, Twig 3.14 e jsdom 29.1.1 com mocks **não
são um servidor**. CI remoto não verificado. **Limitação de arquitetura:** se o
rascunho alterar o host de um site, o site da decisão vem da política
publicada (necessário para evitar o ciclo), enquanto `context['site']` das
condições (L) vem da política efetiva; nesse caso use o seletor de site da
prévia (M) para comparar pelo ID. A sonda do Admin sempre usa um ID
allowlistado. **CI verde / health verde ≠ release aprovada**; F9-27 segue
`BLOCKED_NOT_RUN` com `release_approved:false`, BDREditor/F9-103 pausado.

## Instalação

Extrair os 6 arquivos completos sobre a raiz do repositório (o guia é novo),
preservando a estrutura de diretórios. Não há patches, nem manifestos a
atualizar. Depois: `bash tools/verify-checksums.sh && bash tools/repo-health.sh`.
Sem `schedule.sites`, o comportamento é idêntico ao das frentes A–N.
