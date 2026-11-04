# KAZTheme-Editor — Motor Total (Frente Z)

> **O KAZTheme-Editor não é mais um campo dentro da página de tema.
> Ele é o motor total, o dono absoluto e o criador absoluto de toda a edição
> de temas do ReamurCMS.**

## 1. O que mudou

`dev/admin/view/template/design/theme_form.twig` foi reduzido a três linhas:

```twig
{{ header|raw }}
{% include 'design/kaz_theme_editor.twig' %}
{{ footer|raw }}
```

Todo o conteúdo que existia nele (198 linhas, 28.952 bytes no HEAD
`909511ece6802928fbf658d3a3eaa20ee39784a3`) foi **movido integralmente** para
`dev/admin/view/template/design/kaz_theme_editor.twig`, que passa a ser a casca
`#kaz-theme-editor` com `data-kaz-owner="1"`.

O workspace inspirado no Penpot (navbar, 3 colunas, réguas, viewport,
inspector, statusbar, 15 ícones SVG, 8 abas) continua intacto. A diferença é
que agora **ele contém o formulário de tema**, e não o contrário.

## 2. Arquitetura

```
#kaz-theme-editor  [data-kaz-owner="1"]        <- DONO DA PÁGINA
├── header.kaz-topbar                          marca · heading_title · estado
│                                              Salvar (form=kaz-theme-form) · Voltar
│                                              undo/redo · avatar · status
├── alertas error/notice + text_intro
├── <form id="kaz-theme-form" method="post" action="{{ action }}">
│   ├── input hidden csrf_token
│   └── .kaz-workspace
│       ├── aside esquerda   Camadas · Ativos · Tokens · Histórico
│       │                    + Módulos do tema (15 botões)
│       ├── .kaz-center      réguas + viewport/board (canvas)
│       │                    + .kaz-modules (14 <section> com os cards)
│       └── aside direita    Design · Prototype · Inspect · Histórico
│   ├── footer.kaz-statusbar zoom · módulo · selo de formulário · camadas
│   └── .kaz-io              textarea name="kaz_scene_json"
├── .kaz-extra               [FORA do form principal, DENTRO do editor]
│   ├── card #theme-package  + <form action="{{ import_url }}">
│   ├── <iframe name="theme-preview" sandbox="">
│   └── histórico             + <form action="{{ revision.restore }}">
```

**Não há `<form>` aninhado.** São quatro tipos de formulário irmãos: o
principal, o de importação de pacote e um por revisão restaurável. O botão
Salvar da navbar aponta para o principal via `form="kaz-theme-form"`; o botão
Salvar original do card Geral continua onde sempre esteve.

## 3. Os 14 módulos

| # | Módulo | Conteúdo preservado |
| --- | --- | --- |
| 01 | Geral | `name`, `slug`, `primary`, `surface`, `scene`, `button_save`, `cancel` |
| 02 | Herança | `parent_theme`, `theme_parents` |
| 03 | Tokens | `token_groups`, `token_fields`, `tokens[...]`, contraste `token_pairs` |
| 04 | Three.js | `three_enabled`, `three_scene`, `three_slot`, `three_count`, `three_color`, `three_speed` |
| 05 | Cena 3D | `#theme-scene-editor`, `scene_thumb_base`, `scene_presets`, miniatura |
| 06 | Seções | `theme-section-builder`, `sections[]`, `sections_order` |
| 07 | Agendamento | `theme-schedule`, `schedule[...]`, probe com `formaction="{{ compare_url }}" formtarget="theme-preview"` |
| 08 | Mídia | `theme-media`, `media_thumb_base`, `media_snippets_json`, `media[...]` |
| 09 | Modelos | `theme-page-models`, `page_models_json` |
| 10 | Sites | `theme-sites`, `sites_json`, `sites_published_json`, `text_sites_probe_unknown`, `data-sites` |
| 11 | Componentes | `theme-component-library`, `component_thumb_base`, `component_snippets_json` |
| 12 | Condições | `theme-condition-builder`, `data-spec`, `data-routes`, `data-languages`, `data-devices`, `data-csrf`, `conditions` |
| 13 | Prévia | `theme-compare`, `theme_state`, `has_unpublished_changes`, `compare_url` |
| 14 | Templates | 25 `TEMPLATE_KEYS`, `templates[...]`, `preview_links[...]` |

O módulo `Design` (canvas, camadas, blocos, inspector) é o zero-ésimo e é o
ativo por padrão. Clicar em qualquer módulo troca o painel central sem recarregar
a página e sem perder o estado do canvas.

## 4. Estado e contratos

- `dirty()` / `data-kaz-dirty` / `data-kaz-file-status` → **inalterados**,
  exclusivos de `kaz_scene_json` (frente X).
- `data-kaz-form-dirty` → **novo**, selo informativo separado que observa
  `input`/`select`/`textarea` do form principal, ignorando `kaz_scene_json`
  e inputs de arquivo. Não bloqueia envio; zera no submit.
- `formSnapshot()` usa apenas leitura de `.value`/`.checked`; sem
  `localStorage`, sem `fetch`, sem serialização de cena.
- `root.__kaz` expõe `owner()`, `modules()`, `module()`, `setModule(n)`,
  `panelVisible(n)`, `formDirty()` e `formId()` além da API U/V/W/X/Y.
- `zoom`, `pan`, aba ativa, ferramenta e **módulo ativo** são estado local
  descartável: não entram em `kaz_scene_json` e não afetam o `baseline`.
- Cache de assets: `$kazAssetZ = ASSET_V . '-kaz-theme-editor-z'`.

## 5. Degradação e acessibilidade

Sem JavaScript, os 14 painéis são exibidos empilhados (nenhum `hidden` no HTML
estático) — nenhum campo fica inacessível. Com JavaScript, `paintModules()`
esconde os inativos no boot. Botões de módulo usam `aria-pressed`; o painel
central tem `aria-label`; o iframe mantém `sandbox=""` e `referrerpolicy`.

## 6. O que esta frente NÃO faz

Não cria migration, schema, coluna, `custom_js`, chave de tradução, manifesto
ou `|raw` novo (`{{ header|raw }}`/`{{ footer|raw }}` são os dois já
existentes). Não adiciona CDN, fonte remota, `<script>` inline, `on*`,
`innerHTML`, `eval`, `fetch`, upload, WebGL ou Three.js real. Não renderiza a
cena no site público. Não converte `PENDING_DECISION`, `STATIC_ONLY`,
`NOT_RUN`, `SKIP` ou `BLOCKED_NOT_RUN` em `PASS`. Não altera
`release_approved` (permanece `false`), os 14 manifestos, os 11 hashes,
`schema.sql`, os 10 `PENDING_DECISION`, os 77 paths do BDREditor, nem reabre
F9-27, P2/F9-18 ou o BDREditor. Não reentrega as frentes A–Y.

Nenhuma escrita no GitHub foi feita por este pacote; o CI remoto não foi
verificado aqui. **CI verde ou health check não significa release aprovada.**

---

## Frente AA — interatividade total, tamanhos de tela e página cheia

A frente Z entregou a interface esperada, mas alguns controles do workspace
ainda eram decorativos (avatar, "Página 1", swatches, tokens, amostra
tipográfica, selo de status, painel Protótipo). A frente AA **dá função real a
todos eles** e acrescenta três capacidades pedidas.

### Todos os botões e recursos operacionais

| Controle | Antes | Agora |
| --- | --- | --- |
| `Página 1` | `<span>` estático | botão `data-kaz-action="pages"` → abre Páginas + volta ao canvas |
| Avatar `1` | `<span>` estático | botão `data-kaz-action="users"` → informa a sessão local (sem rede) |
| Selo `Alterações não salvas` | texto | botão `data-kaz-action="revert"` → descarta alterações da cena ao baseline |
| Selo `Salvo` | texto | `<button type="submit" form="kaz-theme-form">` → salva o tema sem JS |
| 6 swatches | `<span>` | `<button data-kaz-swatch>` → aplica a cor à camada selecionada |
| Tokens `primary`/`surface` | texto | `<button data-kaz-token-apply>` → aplica o token à camada |
| Amostra `Aa` | texto | botão `data-kaz-action="add-text"` → insere camada de texto |
| Páginas | texto | botão `Ir para o board` → Design + Camadas + ajuste de zoom |
| Protótipo | 3 parágrafos | 3 botões reais: prévia do tema, ajustar zoom, tela cheia |
| Exportar cena | só preenchia o textarea | também baixa `kaztheme-scene.json` quando o navegador oferece `Blob`/`createObjectURL`; sem a API, o textarea continua sendo a fonte |
| Desfazer/refazer, duplicar, excluir, ordem, visibilidade, bloqueio, 6 blocos, 5 kinds, 8 abas, 15 módulos, zoom/pan/réguas/Inspect | já funcionais | **inalterados** |

Os botões dos 14 módulos legados (`data-section-move`, `data-media-insert`,
`data-component-insert`, `data-site-probe-button`, `data-model-apply`,
`data-scene-preset`, `data-tcb-*`, `data-theme-package-form`) continuam sendo
atendidos pelos seus próprios scripts já existentes — nenhum foi reimplementado.

### Ajuste do tamanho das telas

Uma `.kaz-screenbar` no centro do workspace oferece 7 tamanhos: **Tema (cena)**,
Desktop `1440×900`, Laptop `1280×800`, Tablet `768×1024`, Mobile `390×844`,
Widescreen `1920×1080` e **Personalizado** (largura `320–3840`, altura
`240–2160`). O board da cena passa a ficar dentro de um `.kaz-screen` com as
dimensões escolhidas, o rótulo e o statusbar acompanham, e `Ajustar à tela`
recalcula o zoom pelo **tamanho da tela**, não pelo canvas.

**O tamanho da tela NÃO altera `scene.canvas`**: é um frame de visualização,
estado local descartável como zoom/pan/abas/módulo. `fitZoom()` continua idêntico
no boot porque o preset padrão é `Tema` (= `scene.canvas`).

A mesma barra controla a **densidade das colunas** — Compacto `200/220`, Normal
`236/264`, Amplo `300/340` — e dois botões para **ocultar/mostrar** cada sidebar.
Atalhos novos: `F` tela cheia, `[` compacto, `]` amplo, `Shift+1..6` presets.

### Tela cheia em outra página

- **`⛶ Tela cheia`** — Fullscreen API (`requestFullscreen`) nesta aba, com
  fallback para o modo CSS quando a API não existe.
- **`↗ Tela cheia em nova página`** — `<a target="_blank"
  rel="noopener noreferrer" href="{{ action }}&amp;kaz_fullscreen=1">`.
  O controller lê apenas esse GET, publica `kaz_fullscreen_page` e o
  `theme_form.twig` **omite `{{ header|raw }}` e `{{ footer|raw }}`** nesse modo.
  O shell recebe `data-kaz-page-mode="fullscreen"` e ocupa `100vw × 100vh`, sem
  chrome do Admin. Um link `Sair da página cheia` volta à rota normal.
- Não há rota nova, controller novo, migration, sessão compartilhada ou
  transporte de estado: a nova página carrega o **estado gravado no servidor**.
  Alterações não salvas devem ser salvas antes de abrir a outra página — o selo
  `Alterações não salvas` e o botão `Salvo` estão na navbar para isso.
- Os **dois** `|raw` (`header`/`footer`) continuam sendo os já existentes, agora
  condicionados; o shell do editor permanece com **zero** `|raw`.

### Contrato

`dirty()` e `data-kaz-dirty` continuam exclusivos de `kaz_scene_json`;
`data-kaz-form-dirty` continua separado. `syncField()`, `submit`, `baseline`,
`MAX_LAYERS`, fail-closed, os 6 blocos, os 14 módulos, os 53 `name=`, os 3
formulários irmãos, o iframe `sandbox=""`, CSRF, `can_modify` e
`formaction/formtarget` foram preservados. Nenhum controle novo tem `name=` —
o POST é byte-idêntico ao da frente Z. Cache key `-kaz-theme-editor-z` →
`-kaz-theme-editor-aa`.

Sem migration, schema, coluna, `custom_js`, `|raw` novo, chave de tradução,
manifesto, `localStorage`, CDN, fonte remota, `<script>` inline, `on*`,
`innerHTML`, `eval`, `fetch`, upload, WebGL/Three.js real, cena pública ou
escrita remota. Nenhum `PENDING_DECISION`, `STATIC_ONLY`, `NOT_RUN`, `SKIP` ou
`BLOCKED_NOT_RUN` virou `PASS`; `release_approved` permanece `false`.
**CI verde ou health check não significa release aprovada.**
