# ReamurCMS — Frente Z: KAZTheme-Editor como motor total

**Data:** 4 de novembro de 2026 (America/Sao_Paulo)  
**Base inspecionada:** `main` @ `909511ece6802928fbf658d3a3eaa20ee39784a3` — pai `d68ce60ff0a4…`, `2026-11-04T16:17:24Z`, mensagem `feat(kaz-theme-editor): add Penpot-style workspace UI overhaul`.  
**Árvore:** 3.175 entradas, 2.593 blobs, 14 manifestos `CHECKSUMS*.txt`, `truncated:false`.  
**Estado:** 1 ZIP instalável entregue para aplicação. **Não foi aplicado no GitHub por nós.** Não é release aprovada.

## 1. O pedido desta frente

> *"Vamos tomar uma direção bem pesada e extensa agora: o KAZTheme-Editor não é um campo dentro da página tema, ele deve ser o motor total com todos os formulários, blocos e todos os recursos de edição de temas do ReamurCMS dentro dele. Atualmente o KAZTheme-Editor é uma parte do criador de temas e não é o dono absoluto, o criador absoluto."*

Antes, `theme_form.twig` (198 linhas / 28.952 bytes) era o dono da página e
incluía `design/kaz_theme_editor.twig` na linha 183 — **um campo a mais** dentro
do `<form>` principal. A frente Z **inverte a hierarquia**: o editor passa a ser
a página inteira.

## 2. A inversão

| | Antes (HEAD Z) | Depois (pacote Z) |
| --- | --- | --- |
| `theme_form.twig` | 198 linhas / 28.952 bytes, 14 cards, 4 tipos de formulário | **3 linhas / 79 bytes**: `header`, include do editor, `footer` |
| `kaz_theme_editor.twig` | um `card` incluído dentro do form | **57.443 bytes / 540 linhas**: workspace + os 14 módulos + pacote + iframe + histórico |
| Dono da página | `theme_form.twig` | **`#kaz-theme-editor`** com `data-kaz-owner="1"` |
| `<form>` principal | ancestral do editor | **filho** do editor (`#kaz-theme-form`) |

Os **14 cards foram movidos byte a byte** por um parser balanceado de `<div>`:
Geral, Herança, Tokens, Three.js, Cena 3D, Seções, Agendamento, Mídia,
Modelos, Sites, Componentes, Condições, Prévia e Templates. Cada um virou um
`<section data-kaz-module-panel="…">` no centro do workspace, com um botão na
sidebar esquerda. O módulo `Design` (canvas/camadas/blocos) é o ativo por padrão.

**Prova de não-perda:** os **52 `name="…"`** do formulário original continuam
presentes e com as mesmas contagens; no HTML renderizado o A/B mediu
**`names_total` 194 = 194**, `card_ids` 10 = 10, `data_cards` 13 = 13,
`csrf_fields` 2 = 2, `formaction` 2 = 2, `formtarget="theme-preview"` 2 = 2,
`iframe` 1 = 1 e `sandbox=""` 1 = 1. `formaction="{{ compare_url }}"`,
`formtarget="theme-preview"`, `can_modify`/`disabled`, `button_save`,
`button_back`, `cancel`, `error`/`notice`, `revisions[].restore`,
`data-sites`, `data-models`, `data-snippets`, `data-thumb-base`,
`data-schedule-card`, `data-spec` e `{{ footer|raw }}` foram preservados.

**Sem formulários aninhados.** O `<form id="kaz-theme-form">` principal é
irmão do formulário de importação (`action="{{ import_url }}"
data-theme-package-form`) e dos formulários de restauração de revisão, todos
dentro de `.kaz-extra`, ainda dentro de `#kaz-theme-editor`. O botão Salvar da
navbar usa `form="kaz-theme-form"`; o botão Salvar original do card Geral
continua onde sempre esteve. `form_nested=false` nos 24 cenários e no DOM real.

## 3. O que é novo na interface

- **Navbar soberana:** `heading_title`, estado publicado/rascunho, Salvar,
  Voltar, desfazer/refazer, avatar, `file status`, 15 ícones SVG.
- **Sidebar esquerda:** 5 abas da Y (Camadas, Ativos, Tokens, Design, Histórico)
  + **Módulos do tema · 14 recursos** com numeração `01`–`14` e `aria-pressed`.
- **Centro:** réguas + viewport/board quando `Design` está ativo; painel do
  módulo selecionado quando qualquer outro está ativo, com cabeçalho e botão
  "Voltar ao canvas".
- **Sidebar direita:** inspector da Y (Design / Prototype / Inspect / Histórico).
- **Statusbar:** zoom, **módulo ativo**, selo de formulário alterado, camadas,
  coordenadas e versão `Frente Z · motor total`.
- **`.kaz-extra`:** pacote (export/import), `<iframe name="theme-preview"
  sandbox="">` e histórico de revisões — todos **dentro do editor**.

**Estado de alterações.** `dirty()`, `data-kaz-dirty` e
`data-kaz-file-status` continuam **exclusivamente** o estado de
`kaz_scene_json` (contrato da frente X). A frente Z acrescenta apenas o selo
informativo `data-kaz-form-dirty`, alimentado por `formSnapshot()` /
`formDirty()`, que observa os demais campos do formulário via `input`/`change`,
ignora `kaz_scene_json` e inputs de arquivo, e zera no `submit`. Nenhum dos dois
bloqueia o envio. `root.__kaz` expõe `owner()`, `modules()`, `module()`,
`setModule(n)`, `panelVisible(n)`, `formDirty()` e `formId()` além de toda a API
U/V/W/X/Y.

**Degradação.** Sem JavaScript, os 14 painéis são exibidos empilhados (nenhum
`hidden` no HTML estático) — nenhum campo fica inacessível. Com JavaScript,
`paintModules()` esconde os inativos no boot. Zoom, pan, aba ativa, ferramenta
e **módulo ativo** são estado local descartável: não entram em
`kaz_scene_json`, não usam `localStorage` e não afetam o `baseline`.

Cache key dos assets: `-kaz-theme-editor-y` → **`-kaz-theme-editor-z`**.

## 4. Arquivos do pacote (10 entradas, todos completos)

| Arquivo | Bytes | Tipo | SHA-256 |
| --- | ---: | --- | --- |
| `dev/admin/controller/design/theme.php` | 69,616 | substitui | `96ee4ab0a6b272ea…` |
| `dev/admin/view/css/kaz-theme-editor.css` | 21,804 | substitui | `0a79bf34df24f1d2…` |
| `dev/admin/view/js/kaz-theme-editor.js` | 36,469 | substitui | `12ab9c0e915b6261…` |
| `dev/admin/view/template/design/kaz_theme_editor.twig` | 57,443 | substitui | `6038efa6ffe0c223…` |
| `dev/admin/view/template/design/theme_form.twig` | 79 | substitui | `eb964d823a31444a…` |
| `docs/KAZTheme-Editor-Biblioteca-de-Blocos.md` | 8,029 | substitui | `9203c922e8fdc4b8…` |
| `docs/KAZTheme-Editor-Guia-Rapido.md` | 9,532 | substitui | `b91db4c5912ad904…` |
| `docs/KAZTheme-Editor-Interface-Penpot.md` | 10,864 | substitui | `99e7aa7b3ee09585…` |
| `docs/KAZTheme-Editor-Motor-Total.md` | 6,190 | NOVO | `bf44cd03cdb8cb78…` |
| `docs/KAZTheme-Editor.md` | 15,408 | substitui | `16ae11c5d54e1aca…` |

`RELATORIO.md` e `INDICE.md` são as duas últimas entradas do ZIP e **não** são
arquivos do repositório (os dois paths já existem na raiz do `main` e não foram
tocados). Os 10 paths estão **LIVRES** nos 14 manifestos (142 entradas, 90 paths
cobertos). Não há patches, diff ou arquivo parcial: 9 substituem, 1 é novo.

## 5. O que NÃO mudou (congelado)

- `ThemePackage` (14 `CONFIG_KEYS`, `MAP_KEYS` sem `kaz_scene`,
  `validateScene()`, `normalizeScene()`, `validateConfig()`, `decode()`,
  25 `TEMPLATE_KEYS`), `ThemeSites`, `theme-sites.js`, `ThemeSnapshot`,
  `ConditionEngine`, `startup/*`, runtime do Catalog, 418 chaves de tradução ×
  3 idiomas, 14 manifestos, 11 artefatos congelados, `schema.sql`,
  `F9-27-Base-Hashes.json`, `tools/decision-matrix.json`,
  `release_approved`, 10 `PENDING_DECISION` e 77 paths do BDREditor/F9-103 —
  **byte-idênticos** na réplica A/B.
- Comportamento U/V/W/X/Y: bindings aditivos (nunca no boot), `syncField()` +
  `submit`, `baseline`/`dirty()`, os 6 blocos allowlistados, `MAX_LAYERS`,
  fail-closed, descarte de linhas vazias do mapa de sites, 8 abas, 15 ícones,
  3 colunas `236px · 1fr · 264px` e o tema escuro `--kaz-bg:#18181b`.
- Sem migration, schema, coluna, `custom_js`, `|raw` novo, chave de tradução
  nova, manifesto, `localStorage`, CDN, fontes remotas, `<script>` inline,
  atributo `on*`, `innerHTML`, `eval`, `fetch`, upload, WebGL/Three.js real,
  cena pública, escrita remota, deploy ou release aprovada. Os **dois** `|raw`
  do `theme_form.twig` são os já existentes (`header`/`footer`); o shell do
  editor tem **zero** `|raw`.
- **A cena continua sem renderização no site público**; child-themes
  continuam substituindo-a integralmente.
- **Não há** protótipo navegável, View mode, comentários, colaboração
  multiusuário, componentes reutilizáveis, flex/grid layout ou múltiplos boards.
- F9-27 segue `BLOCKED_NOT_RUN`; BDREditor/F9-103 pausado; P2/F9-18 não
  reaberto; a limitação de hreflang da frente N permanece.

**Sobreposições legítimas:** a Y foi aplicada pelo usuário no HEAD Z, então
**Y 8/8** byte-idêntica, **X 0/7**, **W 3/4** e **V 2/7** — a Y sobrepôs os
mesmos arquivos do editor. Não é regressão: os 28 campos congelados do A/B e
os testes de save/prévia cobrem o comportamento W/V/X/Y.

## 6. Validação executada (tudo real, sem inferência)

| Verificação | Resultado |
| --- | --- |
| H1 — contrato do pacote, bibliotecas, HEAD Z = Y 8/8, gates | **289/0** |
| H2 — pipeline real do pacote (12 cenários `edit()`/`compare()`) | **1802/0** |
| H3 — A/B real HEAD Z × pacote Z (12 cenários + contratos Z) | **844/0** |
| H4a — Autoloader real carregando controller do pacote | **34/0** |
| H4b — regressão A–Y, manifestos, traduções, BDREditor | **227/0** |
| H5 — jsdom: workspace + U/V/W/X/Y + **motor total no DOM real** | **1841/0** |
| `php -l` (PHP WASM 8.5.12) em todos os `.php` do recorte | **136/0** |
| `node --check` em todos os `.js` do recorte | **471/0** |
| **Total de asserções** | **5644/0** |

Bootstraps separados HEAD/pacote, PHP 8.5.12 WASM + Twig 3.14 + mocks:
**12 + 12 cenários**, `twig_error=0` nos 24, **4 gravações em cada lado**.
**== F11-11 — congelamento de contratos (11 artefatos) ==
   11/11 hashes coerentes | 10 pendentes de decisao

== RESULTADO: COERENTE — nenhum contrato alterado sem decisao ==
   Lembrando: decisoes pendentes NAO sao aprovacao. F9-27 segue BLOCKED_NOT_RUN.
 comparações de campos congelados idênticas** (28 campos × 12 casos) —
incluindo `block_buttons`, `dirty_*`, `note_saved`/`note_derived`,
`name_field`, `sites_field`, `raw_markers`, `js_clean`, `config_keys_count`,
`error`, `redirect`, `saved`, `saved_layers` e `db_calls`. Divergem apenas a
casca Z, o cache key e o selo de versão.

**H5 executou o motor total:** `owner() === true`, `formId() ===
'kaz-theme-form'`, form **filho** do root e **não ancestral**, zero `<form>`
aninhados, 15 módulos / 14 painéis / 29 links; `paintModules()` escondendo 13
painéis no boot; clique real em `sites` trocando painel, viewport, rótulo e
`aria-pressed` **sem sujar a cena**; os 14 módulos percorridos por `setModule()`;
"Voltar ao canvas"; `setModule('naoexiste') === false`; os 10 cards, o iframe
`sandbox=""`, o form de import **fora** do form principal, os 2 `csrf_token`,
os 2 `formtarget`, os 25 `textarea[name^="templates["]`, os `data-*` dos cards,
o selo `data-kaz-form-dirty` acendendo com `input` real e apagando no `submit`
**sem** alterar `dirty()` da cena; e os 2 `|raw` existentes no
`theme_form.twig` contra 0 no shell.

**Réplica A/B:** 1151 paths / 18,036,551 bytes / 0 vazios;
9 alterados + 1 novo + 1141 congelados;
14 manifestos idênticos; 10/10 paths livres.

**Guards HEAD × réplica (4/4 verdes, saídas IGUAIS):**
`verify-checksums.sh` (3 PASS / 0 FAIL, 14 manifestos / 142 arquivos),
`repo-health.sh` (6 PASS / 0 FAIL / 0 SKIP), `verify-ci-workflow.sh`
(31 PASS / 0 FAIL), `verify-contract-freeze.py` (11/11 hashes, 10 pendentes).
**Nenhum checker novo foi criado.** `verify-f11-01.sh` (via `repo-health`)
executa `npm ci --prefix dev/system/storage` e cria 178 arquivos locais
ignorados pelo Git — o HEAD Z versiona **apenas** `node_modules/index.html`;
a réplica foi medida antes disso, os artefatos foram removidos depois e
manifestos/congelados revalidados. A comparação normaliza o caminho do ROOT
impresso no cabeçalho dos guards.

Build idempotente **10/10**; CRC 12/12 do ZIP; `sha256sum -c` global 50/50.

## 7. Limitações declaradas

Sem PHP nativo, Composer, PHPStan, rsync, navegador real, MySQL, sessão Admin
autenticada, migration, staging, deploy, E2E ou **CI remoto verificado**. PHP
WASM, Twig 3.14 e jsdom 29.1.1 com mocks **não são um servidor**: a validação
é de contrato, sintaxe, render Twig e DOM, não de requisição HTTP real nem de
aparência em navegador real. **Nenhuma escrita no GitHub foi feita por nós**; o
HEAD Z é escrita do usuário. Nenhum status `PENDING_DECISION`, `STATIC_ONLY`,
`NOT_RUN`, `SKIP` ou `BLOCKED_NOT_RUN` foi convertido em `PASS`.
F9-27 segue `BLOCKED_NOT_RUN` com `release_approved:false`.
**CI verde / health coerente ≠ release aprovada.**

## 8. Instalação

1. Extrair os 10 arquivos completos sobre a raiz do repositório (9 substituem,
   1 é novo). Nenhum manifesto precisa ser atualizado.
2. `bash tools/verify-checksums.sh && PYTHON_CMD=python3 bash tools/repo-health.sh`.
3. Admin → Design → Temas → editar um tema: a página inteira é o workspace
   KAZTheme-Editor. Use a sidebar esquerda ("Módulos do tema") para alternar
   entre os 14 formulários; `Design` volta ao canvas. Limpar o cache do
   navegador não é necessário (cache key `-kaz-theme-editor-z`).
4. Se os módulos não aparecerem, confirme que o CSS/JS servidos são os
   `-kaz-theme-editor-z` e relate o comportamento.

---
**Próximo passo:** aguardar confirmação de aplicação e o retorno visual antes de
qualquer nova frente.
