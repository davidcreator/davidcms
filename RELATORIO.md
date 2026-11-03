# Frente M — Prévia comparativa por site (continuação da L)

O roadmap **A→J está concluído**; K (idioma por site) e L (condições por
site) foram aplicadas no `main` 026a2a163. Ao pedir o próximo passo, a M foi
tratada como a **continuação funcional natural**: a prévia comparativa da
frente C decidia o site **apenas pelo host** da sonda da J, enquanto o motor
da L trabalha com **IDs**. **Não é** auditoria, dossiê ou pacote de
governança.

## O problema corrigido

Se o **rascunho mudou o host** do site `shop`, um mesmo host na sonda casa com
`shop` no painel publicado e com `primary` no rascunho. A comparação passa a
ser "shop × primary" sem que a fita de contexto mostre isso — cores e
condições divergem por um motivo invisível.

## Recorte

- O card **Comparar publicado × rascunho** ganha
  `<select name="preview_site">` com os 8 IDs allowlistados de
  `ThemeSites::IDS` (`primary`…`staging`) e a opção padrão **Simular host**.
  **Nenhuma chave de tradução nova**: reutiliza `text_sites`,
  `text_sites_probe` e `text_sites_probe_help` (418 ×3 intactas).
- `compare()` com um ID escolhido usa o **host da própria política** de cada
  painel para chamar `ThemeSites::match()`: o publicado usa o host que ele
  configurou para aquele ID, o rascunho usa o dele. Resultado: compara-se
  **`shop` publicado × `shop` rascunho** mesmo com hosts diferentes. A J não
  é alterada — nenhum método, chave ou formato novo.
- **Fail-closed**: `preview_site` só é aceito em `ThemeSites::IDS`. Valor
  inválido, site sem host naquela política ou desabilitado ⇒ volta para a
  sonda. O host do request continua sendo **apenas comparado**, nunca
  refletido em HTML, global Twig, CSS ou log.
- **Correção de coerência com a L**: sem `sites` configurados, `match()`
  devolve ID vazio. A prévia passava `site=''` ao motor, enquanto o Catálogo
  (`startup/theme`) usa `primary`. A M alinha os dois (ID fora da allowlist
  ⇒ `primary`).
- A **fita de contexto** da prévia exibe o site allowlistado usado (guard
  para chamadores antigos sem a chave; tudo escapado com `htmlspecialchars`).
- O `theme-condition-builder.js` sincroniza o select da prévia com o campo
  **Site** do simulador (L). Sem JavaScript nada muda: o formulário da prévia
  e `context[site]` continuam validados no servidor.

Guia `docs/GUIA-PREVIA-POR-SITE.md` (novo). 7 arquivos no ZIP,
1 novo, arquivos completos, sem patches.

**Compatibilidade:** com `preview_site` vazio (padrão) o comportamento é
**idêntico às frentes A–L**. **Nenhuma** query, migration, coluna, tabela,
schema, cookie, cron, escrita no banco, CSS, manifesto, global Twig, `|raw`,
`<script>` inline, `fetch`/`eval`/`innerHTML` novo, upload, GLB, WebGL,
BDREditor, `custom_js`, vhost/DNS ou escrita remota.

## Validação executada

| Verificação | Resultado |
|---|---|
| `php -l` PHP 8.5 real nos 2 arquivos PHP | 2 OK / 0 FAIL |
| `node --check` (8 JS de A–L, 1 modificado) | 8 OK |
| **Autoloader real** + pacote E (export/import com condição `site`) | 75 PASS / 0 FAIL |
| `compare()` por site + fail-closed + ACL/CSRF + regressão A–L | 65 PASS / 0 FAIL |
| `edit()` + render Twig 3.14 + regressão A–L | 69 PASS / 0 FAIL |
| **jsdom 29**: select, sincronização, degradação sem JS | 48 PASS / 0 FAIL |
| `verify-checksums` (A e B) | 14 manifestos / 142 arquivos / 0 divergentes |
| `repo-health` (A e B) | 6 PASS / 0 FAIL / 0 SKIP |
| `verify-ci-workflow` | 31 PASS / 0 FAIL |
| `verify-contract-freeze` | exit 0 |

**Total: 257 asserções, 0 falhas.** 44 arquivos congelados
conferidos por hash antes/depois (15 da J, 4 da K, 6 da L, 3 idiomas,
`schema.sql`, `home.twig`, `twig.php`, `autoloader.php`, `framework.php`,
ambos os startups, `common/header.php`, engines, runtime e guias). Os
14 `CHECKSUMS*.txt` permanecem **idênticos** e não foram reemitidos.
Nenhum contrato congelado, `release_approved`, workflow, checker ou runtime
WebGL foi modificado. Nenhuma escrita remota. Nenhum
`Deprecated:`/`Warning:`/`Fatal` no PHP 8.5 WASM. F9-27 segue
`BLOCKED_NOT_RUN`; BDREditor pausado; P2 do F9-18 não reaberto.

PHP 8.5 via WASM + Twig 3.14 + jsdom com mocks de DB/sessão/response. **Não**
houve CMS completo, MySQL real, sessão Admin autenticada, browser com rede,
DNS/vhost, WebGL, E2E, migration, staging, deploy ou escrita no GitHub.
**CI verde e health verde ≠ release aprovada.**

Aplicar: substituir pelos 5 arquivos completos (o guia é novo) e rodar
`bash tools/verify-checksums.sh && bash tools/repo-health.sh`.
