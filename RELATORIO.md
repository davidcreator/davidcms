# Frente L — Condições por Site (continuação da K)

O roadmap **A→J está concluído**; a K fechou o idioma por domínio e foi
aplicada no `main` 892764cf8. Ao pedir o próximo passo, a L foi tratada como a
**continuação funcional natural**: a J resolve *qual site* está ativo e a K usa
isso para o idioma, mas o **motor de condições declarativas da frente A não
enxergava o site**. Não havia como escrever "no site `shop`, use a cor
laranja" sem duplicar regras por rota. **Não é** auditoria, dossiê ou pacote
de governança.

## Recorte

- **`ConditionEngine`**: `site` entra em `FIELDS` e `spec()`; `site_ids`
  expõe os 8 IDs estáveis de `ThemeSites::IDS` (`primary`…`staging`).
  Operadores permitidos **apenas** `eq` / `neq` / `in` / `not_in` (e os sem
  valor). Qualquer outro valor — inclusive itens de uma lista `in`, lista
  vazia, inteiro, `null`, nome de host ou texto livre — é rejeitado com
  `site_invalid` / `site_op_invalid`. `resolve()` só aceita um ID allowlistado
  no contexto. **Fail-closed:** sem a classe `ThemeSites`, `siteIds()` fica
  vazio e a dimensão nunca valida nem casa.
- **`startup/theme.php`**: injeta `context['site']` a partir **exclusivamente**
  de `ThemeSites::match()` — o `HTTP_HOST` continua sendo apenas comparado,
  nunca publicado. Sem `sites` no config, o ID cai para `primary`; nenhuma
  query, cookie, sessão ou escrita nova (continuam 2 SELECTs).
- **Admin**: `simulateContext()` aceita `context[site]` com allowlist; o
  `compare()` (prévia C) passa a avaliar **um contexto por painel** — o
  publicado usa a sua política de sites, o rascunho usa a dele. O resumo do
  contrato na aba JSON lista os 8 IDs, legível **sem JavaScript**.
- **Builder visual**: `site` aparece automaticamente no `<select>` de campos;
  com operador escalar o valor vira um `<select>` de IDs (não é mais digitado);
  o simulador ganha a linha **Site**, enviada em `context[site]`.
- Guia `docs/GUIA-CONDICOES-POR-SITE.md` (novo). 8 arquivos no ZIP,
  1 novo, arquivos completos, sem patches.

**Nenhuma** chave de tradução (418 ×3 intactas), JS novo, CSS, schema, query,
migration, coluna, tabela, cookie, cron, escrita no banco, manifesto, global
Twig, `|raw`, `<script>` inline, `fetch`/`eval`/`innerHTML` novo, upload, GLB,
WebGL, BDREditor, `custom_js`, vhost/DNS ou escrita remota.

## Compatibilidade e limites

Sem regra que use `site`, o comportamento é **idêntico ao das frentes A–K**: o
contexto ganha uma chave allowlistada que nenhuma regra consulta. Preservados:
6 presets D, 12 seções F, 10 componentes G, 5 campos/7 motivos H, 12 mídias/
6 modelos I, 8 sites J, idioma por site K, 25 templates, o hotfix do
`Autoloader`, os 14 manifestos e os contratos congelados.

**Limitações conhecidas** (documentadas no guia, não reabertas): (1) a prévia
avalia as condições sobre o **config efetivo do tema raiz** da J — se um site
aponta para outro tema por `slug`, as condições devem estar nesse tema; (2)
`startup/language` (K) roda **depois** de `startup/theme`, então
`context['language']` continua sendo o default do config; para condições por
domínio use `site`. A L não altera essa precedência para não reabrir a K.

## Validação executada

| Verificação | Resultado |
|---|---|
| `php -l` PHP 8.5 real nos 3 arquivos PHP | 3 OK / 0 FAIL |
| `node --check` (8 JS de A–K, 1 modificado) | 8 OK |
| **Autoloader real** (regressão hotfix + A–K) | 74 PASS / 0 FAIL |
| `ConditionEngine`: dimensão `site` + fail-closed + regressão A | 85 PASS / 0 FAIL |
| Pipeline Catalog `J → K → L` (mocks de DB/sessão) | 55 PASS / 0 FAIL |
| Controller Admin + `compare()` por painel + Twig 3.14 | 79 PASS / 0 FAIL |
| **jsdom 29**: builder, `context[site]`, degradação sem JS | 50 PASS / 0 FAIL |
| `verify-checksums` (A e B) | 14 manifestos / 142 arquivos / 0 divergentes |
| `repo-health` (A e B) | 6 PASS / 0 FAIL / 0 SKIP |
| `verify-ci-workflow` | 31 PASS / 0 FAIL |
| `verify-contract-freeze` | exit 0 |

**Total: 343 asserções, 0 falhas.** 42 arquivos congelados
conferidos por hash antes/depois (todos os 15 da J, os 4 da K, os 3 idiomas,
`schema.sql`, `home.twig`, `twig.php`, `autoloader.php`, `framework.php`,
`startup/language.php`, `common/header.php`, engines, runtime e guias). Os
14 `CHECKSUMS*.txt` permanecem **idênticos** e não foram reemitidos.
Nenhum contrato congelado, `release_approved`, workflow, checker ou runtime
WebGL foi modificado. Nenhuma escrita remota. Nenhum `Deprecated:`/`Warning:`
no PHP 8.5 WASM. F9-27 segue `BLOCKED_NOT_RUN`; BDREditor pausado; P2 do
F9-18 não reaberto.

PHP 8.5 via WASM + Twig 3.14 + jsdom com mocks de DB/sessão/response. **Não**
houve CMS completo, MySQL real, sessão Admin autenticada, browser com rede,
DNS/vhost, WebGL, E2E, migration, staging, deploy ou escrita no GitHub.
**CI verde e health verde ≠ release aprovada.**

Aplicar: substituir pelos 6 arquivos completos (o guia é novo) e rodar
`bash tools/verify-checksums.sh && bash tools/repo-health.sh`.
