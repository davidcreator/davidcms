# Frente E — Pacotes versionáveis e child-theme

Série A→J: **A** (builder de condições), **B** (design tokens), **C** (prévia
rascunho × publicado) e **D** (editor de cena) já estão aplicadas no `main` c3be5a561;
**esta é a E**. Próxima: **F — section builder**. Um pacote instalável por
frente.

## Recorte

**Sem migration e sem schema.** `rms_design_theme` já tem `config`,
`draft_config`, `published_config`, `slug` e `status`; o parent é apenas uma
chave allowlistada (`parent`) dentro do JSON. Nenhum arquivo é lido/escrito no
servidor: export devolve JSON na resposta, import recebe JSON no POST. Sem
upload, `eval`, `call_user_func`, regex do usuário, `|raw` novo ou HTML novo.

## O que faltava

Temas só existiam no banco, um a um. Não havia como transportar um tema entre
instalações, versioná-lo, nem criar uma variação que herdasse de outro — cada
cópia exigia duplicar cores, tokens, condições, cena e 25 fontes Twig.

## Entregue (16 arquivos)

- **`ThemePackage`** novo: `FORMAT = reamurcms-theme`, `VERSION = 1`, limite de
  512 KiB e cadeia limitada a 5 níveis. `export()`/`encode()` geram pacote com
  **`checksum` SHA-256 do config canônico** (chaves ordenadas recursivamente);
  `decode()` é fail-closed em tamanho, JSON, formato, versão, identidade,
  checksum (`hash_equals`) e `validateConfig()` — que reusa `ConditionEngine`,
  `TokenRegistry` e a mesma regex de fontes Twig. Um byte alterado no config
  invalida o pacote. Códigos de erro estáveis.
- **Allowlist única**: `ThemePackage::TEMPLATE_KEYS` passa a ser a fonte; o
  controller Admin deixou de ter uma constante privada duplicada (5 usos).
- **Child-theme resolvido em runtime**: `resolveChain()` mescla pai → filho
  (mapas `tokens`/`templates`/`three` por chave; escalares e `conditions`
  substituídos). Ciclo, profundidade ou pai ausente **param** a caminhada sem
  loop. A resolução entra no `ThemeSnapshot::resolve()` — a **mesma** classe já
  usada pelo Catalog e pela prévia C — via `themeMap` opcional. `startup` passa
  o mapa **publicado**; `compare()` passa publicado e rascunho. Mudanças no pai
  propagam sem regravar o filho.
- **Dois models** ganham `getThemeMap('draft'|'published')` (SELECT simples,
  sem coluna nova). `getDraftConfig()`/`getPublishedConfig()` continuam
  devolvendo o config **próprio**, usado no export e no diff.
- **Admin**: card "Tema pai (child-theme)" com `<select name="parent_theme">`
  (auto-herança e slugs inválidos recusados) e card "Pacote de tema e herança"
  com export do rascunho/publicado, textarea de import, arquivo `.json`
  opcional por `theme-package.js` (FileReader, sem upload/fetch) e mensagens de
  sucesso/erro allowlistadas.
- **`design/theme.export`**: GET, ACL `access`, `no-store`, `nosniff`,
  `application/json`, `Content-Disposition: attachment`, zero escrita.
- **`design/theme.import`**: POST, ACL `modify`, CSRF, aplica como **rascunho**
  via `save()` — nunca publica nem ativa. Pacote inválido não grava nada e
  retorna código de erro estável (sem refletir texto livre). `parent` igual ao
  próprio `slug` é removido.
- **16 chaves de UI ×3 idiomas**; estilos no CSS do builder já existente. Guia
  `docs/GUIA-PACOTES-CHILD-THEME.md`.

## Compatibilidade e regressão

Temas sem `parent` continuam idênticos (`themeMap` vazio → mesmo resultado das
frentes A–D). `buildDraftConfig()` grava `parent: ''`, aceito por todas as
allowlists. Builder, tokens, prévia rascunho×publicado, presets de cena,
`simulate()`, `sceneThumb()`, `activate()` e `restore()` permanecem intactos.

## Validação executada

| Verificação | Resultado |
|---|---|
| `php -l` PHP 8.5 real nos 10 arquivos PHP | 10 OK / 0 FAIL |
| `node --check` (3 JS: A, D e o novo) | 3 OK |
| `ThemePackage`: checksum, fail-closed, sanitize, `resolveChain` | 55 PASS / 0 FAIL |
| Núcleo: herança no `ThemeSnapshot`, paridade startup, mapas dos models | 35 PASS / 0 FAIL |
| Controller Admin com mocks: export/import/parent, regressão A–D | 65 PASS / 0 FAIL |
| Render Twig 3.14 + view (sem form aninhado, rótulos, miniaturas) | 39 PASS / 0 FAIL |
| **jsdom 29**: FileReader, cards, regressão A/B/C/D | 32 PASS / 0 FAIL |
| `verify-checksums` (A e B) | 14 manifestos / 142 arquivos / 0 divergentes |
| `repo-health` (A e B) | 6 PASS / 0 FAIL / 0 SKIP |
| `verify-ci-workflow` | 31 PASS / 0 FAIL |
| `verify-contract-freeze` | exit 0 |

**Total: 226 asserções, 0 falhas.** `schema.sql` verificado por hash antes e
depois (inalterado). Os 14 `CHECKSUMS*.txt` permanecem **idênticos** e não
foram reemitidos. Nenhum contrato congelado, `release_approved`, workflow,
checker, `ConditionEngine`, `TokenRegistry`, `SceneConfig` ou runtime Three.js
foi modificado. Nenhuma escrita remota. F9-27 segue `BLOCKED_NOT_RUN`;
BDREditor pausado; P2 do F9-18 não reaberto.

## Limitações

PHP 8.5 via WASM + Twig 3.14 + jsdom, com mocks de DB/sessão/response. **Não**
houve CMS completo, MySQL real, sessão Admin autenticada, browser com rede,
WebGL, E2E, migration, staging, deploy ou escrita no GitHub. Import entre
instalações diferentes deve ser conferido pelo usuário. **CI verde e health
verde ≠ release aprovada.**

Aplicar: substituir pelos arquivos completos e rodar
`bash tools/verify-checksums.sh && bash tools/repo-health.sh`.
