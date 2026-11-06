# Relatório Final — Frente BC (KAZEditor-Theme · import por arquivo, recorte A)

**Data:** 2026-11-06 · **HEAD:** `df0d3d34e4903a948fcf5bdfa19823c072ef41a0`
**CI remoto:** ✅ 7/7 success · **Decisões do usuário:** recorte **A** + **dados
validados, sem código executável**.

## 1. Ponto de partida (levantamento)

O pedido era "importar temas e templates Twig e ThreeJS, com gestão estilo
WordPress". A auditoria mostrou que **o subsistema já é bem completo**:

- `design/theme` com lista, `edit`, `preview`, `restore` (revisões), `export`
  e `import` (sempre como **rascunho**, nunca publica);
- `rms_design_theme` + `rms_design_theme_revision`, no máximo **um tema ativo**;
- `ThemePackage` com `FORMAT=reamurcms-theme`, `VERSION=1`, **512 KiB**,
  **checksum SHA-256** com `hash_equals`, fail-closed, child-theme até 5 níveis;
- **25 fontes Twig allowlistadas** (`TEMPLATE_KEYS`) como strings no JSON;
- ThreeJS como **dados** (`three` + `kaz_scene` + `ScenePresetRegistry`);
- tokens, condições, seções, componentes, scheduler, mídia, sites, snapshot.

E `docs/GUIA-PACOTES-CHILD-THEME.md` proíbe textualmente **upload em disco,
`eval`, `call_user_func`, HTML, `|raw` novo e código executável**. O
`theme-package.js` **já lia um `.json` local** via `FileReader` — só faltava
UX e feedback.

## 2. O que foi implementado

**Recorte A**: seleção/arraste de arquivo com prévia e validação no cliente,
**sem alterar o backend**. O POST continua enviando `theme_package` (string)
para `design/theme.import`, e **toda** a validação de segurança permanece no
servidor (`ThemePackage::decode()`: tamanho, formato, versão, identidade,
`hash_equals` do checksum, allowlists, ACL `modify`, CSRF).

| Arquivo | Ação |
|---|---|
| `dev/admin/view/js/theme-package.js` | reescrito: drag-and-drop, prévia (nome/slug/formato/pai/arquivo/tamanho), validação fail-closed no client, mensagens de erro, limpeza ao editar manualmente |
| `dev/admin/view/template/design/kaz_theme_editor.twig` | zona de drop + `<dl>` de prévia + `role="status" aria-live="polite"`; `accept` aceita `.reamurtheme.json` |
| `dev/admin/controller/design/theme.php` | **1 linha**: injeta as 10 novas chaves de idioma no `$data` |
| `dev/admin/language/{en-us,pt-br,es-eu}/design/theme.php` | 10 rótulos/mensagens por idioma |

### Validação no cliente (espelho do contrato, não substituto)

Recusa com mensagem traduzida: arquivo **> 512 KiB**, extensão/MIME inválidos,
JSON inválido, `format != reamurcms-theme`, `version != 1`, `name` vazio,
`slug` fora de `^[a-z0-9_-]{1,48}$`, `config` ausente ou `checksum` sem 64 hex
minúsculos. **O checksum não é calculado no cliente** — isso é do servidor.

### Superfície de segurança

Sem `fetch`, `XMLHttpRequest`, `eval`, `Function`, `innerHTML`, `outerHTML`,
`document.write`, `localStorage`, `sessionStorage`, `import()` ou `require()`.
A prévia usa **apenas `textContent`**. Nenhum arquivo é escrito no servidor:
`FileReader` lê no navegador e preenche a mesma `<textarea>`. **Sem JS, colar o
JSON continua funcionando.** Nenhum `|raw` novo, nenhum `<script>`, nenhum
iframe novo, nenhuma migração, nenhuma ACL nova, nenhuma rota nova.

**Não foi feito** (decisão "dados validados"): upload de ZIP com `.twig`/`.js`
reais, Twig arbitrário, JS ThreeJS do usuário, escrita em disco, sandbox Twig,
ampliação de `TEMPLATE_KEYS`, migration ou endpoint novo.

## 3. Validação

| Verificação | Resultado |
|---|---|
| `node --check` no JS | ✅ |
| `php -l` (PHP **8.5.12** WASM) no controller + 3 idiomas | ✅ **4/4** |
| **Harness jsdom funcional** | ✅ **31 OK / 0 FALHAS** — arquivo válido, prévia completa, pai ausente, >512 KiB, extensão inválida, JSON inválido, 5 pacotes malformados, drag-and-drop, edição manual, 10 checagens de superfície |
| Twig `if/endif` e `for/endfor` balanceados, 0 `\|raw` novo | ✅ |
| Controller: `import()`, ACL, CSRF e `decode()` **intactos** | ✅ |
| Idiomas: prefixo original byte a byte preservado | ✅ 3/3 |
| `tools/verify-theme-import-file.cjs` | ✅ 0 FALHAS |
| `verify-checksums.sh` / `repo-health.sh` na réplica fiel | ✅ 142 arquivos / 0 divergentes · **6 PASS / 0 FAIL / COERENTE** |
| `verify-contract-freeze.py` | ✅ COERENTE |
| **Nenhum dos 6 alvos em `CHECKSUMS*.txt`** | ✅ nenhum manifesto a regenerar |

## 4. Limites

Nenhuma escrita do agente no GitHub, nenhuma migration, contrato congelado,
hash, `schema.sql`, `TEMPLATE_KEYS`, `ThemePackage`, manifesto ou workflow
alterado. Nenhuma conversão de `PENDING`/`NOT_RUN`/`BLOCKED_NOT_RUN` em `PASS`,
nenhuma decisão das **10 `PENDING_DECISION`**, nenhuma mudança em
`PROPOSAL_PENDING` / `DESIGN_GATE_PENDING` (F9-25), nenhuma declaração de
release.

`release_approved: false` · F9-27 `BLOCKED_NOT_RUN` · F9-18
`release_ready: false`. CI verde, PHP WASM, jsdom e guardas estáticos **não
significam release aprovada** e não substituem o aceite no Admin real.

**Passo 2 ainda aberto:** o guia de health foi aplicado, mas o JSON do Admin
real **não foi colado** — o health observacional segue pendente.

## 5. Próximos passos (dependem de você)

1. **Aceite** do import por arquivo no Admin real.
2. **Recorte B** — ampliar `TEMPLATE_KEYS` (diga quais templates).
3. **Recorte D** — gestão estilo WordPress (screenshot, duplicar, excluir).
4. **Recorte C** — ZIP com arquivos reais: exige decisão explícita de
   segurança, sandbox Twig, ACL dedicada e revisão de migration (hoje vetado).
5. Passos do roteiro: health (2), D01–D10 com **AR** (3), F918 com **AS** (4).
