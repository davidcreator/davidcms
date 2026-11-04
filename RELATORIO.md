# ReamurCMS — Frente Y: Workspace inspirado no Penpot

**Data:** 4 de novembro de 2026 (America/Sao_Paulo)  
**Base inspecionada:** `main` @ `d68ce60ff0a42234f551ace4e919c38cb06b6e67` — pai `cd92573c507b…`, `2026-11-04T15:43:30Z`, mensagem `feat(kaz-theme-editor): add block presets and unsaved changes indicator`.  
**Árvore:** 3.174 entradas, 2.592 blobs, 14 manifestos `CHECKSUMS*.txt`, `truncated:false`.  
**Estado:** 1 ZIP instalável entregue para aplicação. **Não foi aplicado no GitHub por nós.** Não é release aprovada.

## 1. O pedido desta frente

> *"Estamos realizando passos mas minha interface não está nem 1% semelhante com o Penpot, concorrente open source do Figma; podemos focar nessa fase agora para deixar a interface semelhante."*

As frentes U/V/W/X entregaram um editor **funcional**, mas com aparência de um
`card` Bootstrap comum: toolbar de botões de texto, três colunas claras e um
SVG sem moldura. A frente Y troca **apenas a casca** por um workspace de design
escuro, sem alterar nenhum contrato de dados, servidor, campo allowlistado ou
comportamento anterior.

## 2. Referência (não houve cópia de código ou assets)

- [Interface tour — Penpot Help Center](https://help.penpot.app/user-guide/first-steps/the-interface/): descreve o Workspace com 20 áreas — viewport infinito, toolbar, main menu, pages, layers, rulers, color/typography palettes, design properties, prototype/inspect/view modes, share, history, comments, zoom, users, assets, design tokens e file status.
- [How to Move Figma Components to Penpot — freeCodeCamp](https://www.freecodecamp.org/news/how-to-recreate-figma-components-in-penpot/): confirma *"controls on the right, files/assets/layers on the left, canvas in the middle"* e **dark mode por padrão**.

O mapeamento é de **organização espacial e vocabulário**, implementado com
HTML/CSS/SVG próprios. Nenhum código, logotipo, fonte, ícone ou imagem do
Penpot foi copiado.

## 3. O que mudou (visível no Admin)

| Área do Penpot | KAZTheme-Editor Y |
| --- | --- |
| Navbar, toolbar, história, usuários, file status | `.kaz-topbar`: marca, nome do arquivo, página, **15 ícones SVG**, desfazer/refazer, avatar `1`, selo `Salvo` / `Alterações não salvas` |
| Pages / Layers / Assets / Design tokens / History | **5 abas** na sidebar esquerda |
| Viewport infinito, réguas, board | `.kaz-viewport` pontilhado, pan pela ferramenta **Mão**, zoom **25–200%**, réguas H/V e board `Tema · 960 × 540` |
| Design properties / Prototype / Inspect | **3 abas** na direita; Design com seções Layout, Aparência, Tipografia, Elemento e Tema |
| Color/typography palettes, tokens | 6 swatches, amostra `Aa`, tokens `primary`/`surface` lidos de `data-kaz-*` |
| Zoom, coordenadas, status | `.kaz-statusbar` com `− / % / + / Ajustar`, coordenadas, contagem e status |

Tema escuro com variáveis CSS escopadas (`--kaz-bg:#18181b`,
`--kaz-accent:#31efb8`, `--kaz-blue:#7b61ff`), 3 colunas
`236px · 1fr · 264px`, responsivo até 900 px e regras `forced-colors`.
Ícones são `<svg>` literais e estáticos no Twig.

**Funcionalidades novas de interface:** `setZoom`/`fitZoom`, `setPan`,
`setTool('move'|'hand')`, `setTab` (8 abas), réguas, lista de histórico,
Inspect somente-leitura (CSS + JSON da camada), swatches, tokens, coordenadas,
contagem e espelhamento do estado de gravação no `file status`. Atalhos novos:
`V` mover, `H` mão, `R/T/B/C/S` criar camadas, `+`/`-` zoom, `0` ajustar,
`Ctrl` + roda do mouse.

**Zoom/pan/abas/ferramenta são estado LOCAL e descartável**: não entram no
`kaz_scene_json`, não são gravados, não usam `localStorage` e **não sujam** o
indicador de alterações não salvas da X. O `scale()` do drag continua usando
`getBoundingClientRect()`, que já reflete o `transform` do board.

## 4. Arquivos do pacote (8 entradas, todos completos)

| Arquivo | Bytes | Tipo | SHA-256 |
| --- | ---: | --- | --- |
| `docs/KAZTheme-Editor-Biblioteca-de-Blocos.md` | 4826 | substitui | `6b800f79605210b6…` |
| `docs/KAZTheme-Editor-Guia-Rapido.md` | 6329 | substitui | `4ffda7946732cfc4…` |
| `docs/KAZTheme-Editor.md` | 12205 | substitui | `8a142d04d357b9de…` |
| `docs/KAZTheme-Editor-Interface-Penpot.md` | 7661 | NOVO | `3b076f466511ebfa…` |
| `dev/admin/view/css/kaz-theme-editor.css` | 16395 | substitui | `4034f84cddc088dc…` |
| `dev/admin/view/template/design/kaz_theme_editor.twig` | 18817 | substitui | `d6e491c941517f5a…` |
| `dev/admin/view/js/kaz-theme-editor.js` | 32834 | substitui | `6166948e8749238f…` |
| `dev/admin/controller/design/theme.php` | 69616 | substitui | `28b59abf20cb9b12…` |

`RELATORIO.md` e `INDICE.md` são as duas últimas entradas do ZIP e **não** são
arquivos do repositório (os dois paths já existem na raiz do `main` e não foram
tocados). Os 8 paths estão **LIVRES** nos 14 manifestos (142 entradas, 90 paths
cobertos). Não há patches, diff ou arquivo parcial. Cache key dos assets:
`-kaz-theme-editor-x` → `-kaz-theme-editor-y`.

## 5. O que NÃO mudou (congelado)

- `ThemePackage` (14 `CONFIG_KEYS`, `MAP_KEYS` sem `kaz_scene`,
  `validateScene()`, `normalizeScene()`, `validateConfig()`, `decode()`,
  `export()`, 25 `TEMPLATE_KEYS`), `ThemeSites`, `theme_form.twig`,
  `theme-sites.js`, `ThemeSnapshot`, `ConditionEngine`, `startup/*`, runtime do
  Catalog, 418 chaves de tradução × 3 idiomas, 14 manifestos, 11 artefatos
  congelados, `schema.sql`, `F9-27-Base-Hashes.json`,
  `tools/decision-matrix.json`, `release_approved`, 10 `PENDING_DECISION` e 77
  paths do BDREditor/F9-103 — **byte-idênticos** na réplica A/B.
- Comportamento U/V/W/X: bindings aditivos (nunca no boot), `syncField()` +
  `submit`, `baseline`/`dirty()`, os 6 blocos allowlistados (agora na aba
  **Ativos**), `MAX_LAYERS`, fail-closed, descarte de linhas vazias do mapa de
  sites e diagnóstico `(shop)`.
- Sem migration, coluna, `custom_js`, `|raw` novo, chave de tradução nova,
  `localStorage`, WebGL, Three.js real, upload, CDN, fontes remotas,
  biblioteca de ícones, `<script>` inline, atributo `on*`, `innerHTML`, `eval`,
  `fetch`, escrita remota, deploy ou release aprovada.
- **A cena continua sem renderização no site público**; child-themes
  continuam substituindo-a integralmente.
- **Não há** protótipo navegável, View mode, comentários, colaboração
  multiusuário, componentes reutilizáveis, shared libraries, flex/grid layout
  ou múltiplos boards. As abas **Protótipo** e **Páginas** dizem isso em texto;
  o doc da frente Y lista cada limite.

## 6. Validação executada (tudo real, sem inferência)

| Verificação | Resultado |
| --- | --- |
| H1 — contrato, bibliotecas, HEAD Y = X 7/7, gates | **276/0** |
| H2 — pipeline do pacote (12 cenários reais `edit()`/`compare()`) | **962/0** |
| H3 — A/B real HEAD Y × pacote Y (12 cenários + congelados) | **1641/0** |
| H4a — Autoloader real carregando controller do pacote | **34/0** |
| H4b — regressão A–X, manifestos, traduções, BDREditor | **226/0** |
| H5 — jsdom: zoom/pan/abas/réguas/Inspect + U/V/W/X | **1206/0** |
| `php -l` (PHP WASM 8.5.12) em todos os `.php` do recorte | **136/0** |
| `node --check` em todos os `.js` do recorte | **471/0** |
| **Total de asserções** | **4952/0** |

Bootstraps separados HEAD/pacote, PHP 8.5.12 WASM + Twig 3.14 + mocks:
**12 + 12 cenários**, `twig_error=0` nos 24. **28 campos A/B idênticos** —
incluindo `block_buttons`, `dirty_*`, `note_saved`/`note_derived`, `name_field`,
`sites_field`, `raw_markers`, `js_clean`, `config_keys_count`, `error`,
`redirect`, `saved`, `saved_layers` e `db_calls`; apenas os 24 campos de UI e o
cache key divergem. O pacote grava `post_valido`, `post_sites_vazios`,
`post_bloco_hero` e `post_sem_campo` nos **dois** lados e rejeita
`post_invalido` e `post_49_camadas` nos **dois** lados com `error_validation`.

**H5 executou o workspace:** zoom 1→1.5→2→0.25→1.25, `fitZoom()` 0.75, pan
por API e por clique real com a Mão, clamp de zoom/pan, `Ctrl`+roda, troca de
ferramenta e de abas por API e por clique, 13 marcas na régua H / 7 na V, 6
swatches, tokens, histórico crescendo e marcando o estado atual, Inspect com
CSS+JSON via `textContent` (zero nós filhos), e a prova de que **nenhuma**
operação de interface altera `dirty()` ou a cena. Também executou os 6 blocos
da X a partir da aba Ativos, o fail-closed, o limite de 48 camadas, o
`submit` e a coexistência com `theme-sites.js` da W.

**Réplica A/B:** 1150 paths / 17998752 bytes / 0 vazios;
7 alterados + 1 novo + 1142 congelados;
14 manifestos idênticos; 8/8 paths livres.

**Guards HEAD × réplica (4/4 verdes, saídas IGUAIS):**
`verify-checksums.sh` (3 PASS / 0 FAIL, 14 manifestos / 142 arquivos),
`repo-health.sh` (6 PASS / 0 FAIL / 0 SKIP), `verify-ci-workflow.sh`
(31 PASS / 0 FAIL), `verify-contract-freeze.py` (11/11 hashes, 10 pendentes).
**Nenhum checker novo foi criado.** `verify-f11-01.sh` (via `repo-health`)
executa `npm ci --prefix dev/system/storage` e cria 178 arquivos locais
ignorados pelo Git — o HEAD Y versiona **apenas** `node_modules/index.html`;
a réplica foi medida antes disso, os artefatos foram removidos depois e
manifestos/congelados revalidados. A comparação normaliza o caminho do ROOT
impresso no cabeçalho dos guards.

**Sobreposições legitimas de frentes anteriores:** X **7/7** byte-idêntica no
HEAD Y; W **3/4** e V **2/7** porque a X (e agora a Y) sobrepuseram os mesmos
arquivos do editor — em especial `theme.php`, cujo cache key mudou em cada
frente. Não é regressão: o comportamento W/V está coberto pelos 28 campos
congelados do A/B e pelos testes de save/prévia.

Build idempotente 8/8; CRC 10/10 do ZIP; `sha256sum -c` global 49/49.

## 7. Limitações declaradas

Sem PHP nativo, Composer, PHPStan, rsync, navegador real, MySQL, sessão Admin
autenticada, migration, staging, deploy, E2E ou **CI remoto verificado**. PHP
WASM, Twig 3.14 e jsdom 29.1.1 com mocks **não são um servidor**: a validação
é de contrato, sintaxe, render Twig e DOM, não de requisição HTTP real nem de
aparência em navegador real. **Nenhuma escrita no GitHub foi feita por nós**; o
HEAD Y é escrita do usuário. Nenhum status `PENDING_DECISION`, `STATIC_ONLY`,
`NOT_RUN`, `SKIP` ou `BLOCKED_NOT_RUN` foi convertido em `PASS`. F9-27 segue
`BLOCKED_NOT_RUN` com `release_approved:false`; BDREditor/F9-103 pausado;
P2/F9-18 não reaberto; a limitação de hreflang da frente N permanece.
**CI verde / health coerente ≠ release aprovada.**

## 8. Instalação

1. Extrair os 8 arquivos completos sobre a raiz do repositório (7 substituem,
   1 é novo). Nenhum manifesto precisa ser atualizado.
2. `bash tools/verify-checksums.sh && PYTHON_CMD=python3 bash tools/repo-health.sh`.
3. Admin → Design → Temas → editar um tema: o card é agora um workspace escuro
   com navbar, 3 colunas, réguas, board e barra de status. Limpar o cache do
   navegador não é necessário (cache key `-y`).
4. Se o workspace não aparecer, confirme que o CSS/JS servidos são os
   `-kaz-theme-editor-y` e relate o comportamento.

---
**Próximo passo:** aguardar confirmação de aplicação e o retorno visual antes de
qualquer nova frente.
