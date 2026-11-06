# Guia — Reamur ThreeJS Reference (tema de referência 3D)

**Arquivo:** `docs/temas/Reamur-ThreeJS-Reference.reamurtheme.json`
**Formato:** `reamurcms-theme` v1 · **42.665 bytes** · **checksum SHA-256**
`16461680c8a1e5a42bf0c53bb1906bdeca794e99d49e3d9e3a4e1b3d94611742`.

## O que é

Um **tema completo focado em ThreeJS**, importável, criado para servir de
referência ao desenvolver outros temas 3D. Ele cobre **todos os mecanismos,
presets, slots e limites** do subsistema — sem escrever uma linha de JavaScript.

Foi gerado pelo `ThemePackage::export()` e validado pelo
`ThemePackage::decode()` real, além de `ScenePresetRegistry`,
`ConditionEngine::evaluate()` e `ThemeSceneCss::compile()`.

**O tema nunca escreve JavaScript**: ele declara parâmetros allowlistados; o
runtime self-hosted (`scene-runner.js`) emite os atributos `data-*` e monta a
cena. Isso é o modelo de **dados validados, sem código executável** que você
confirmou nas frentes BC/BF.

## Como usar

1. Admin → **Temas do Catálog** → **Importar tema**.
2. Escolha/arraste `Reamur-ThreeJS-Reference.reamurtheme.json`.
3. Prévia: **25 fontes Twig**, ThreeJS `hero-particles` / `hero`, 24 camadas no
   `kaz_scene`.
4. **Importar como rascunho** (nunca publica sozinho) → revisar → **Ativar**.

Dica: `?variant=b` ativa o preset **nebula**; em `device=mobile` ou antes das
07h a cena é **desligada** automaticamente.

## Os dois mecanismos 3D

| Mecanismo | Onde | O que faz |
|---|---|---|
| **`theme_scene`** (runtime declarativo) | `three` + `partial/theme-scene.twig` | emite `data-scene`, `data-count`, `data-color`, `data-speed`; o `scene-runner.js` faz `boot()` |
| **`theme_scene_view`** (composição) | `kaz_scene` + `partial/theme-scene-block.twig` | o `ThemeSceneCss` compila as camadas em CSS allowlistado (`--rms-scene-*`) e itera no Twig com autoescape |

Globais seguros disponíveis nos templates: `theme_scene`
(`enabled/scene/slot/count/color/speed`), `theme.vars`, `theme.flags`,
`theme.classes`, `theme.matched`.

## Os 6 presets, todos demonstrados

`three` de base = **aurora**. Cada regra condicional aplica um preset real e
foi conferida com `ScenePresetRegistry::matchingPreset()`:

| Regra | Quando | Preset | Cena / slot / count / cor / speed |
|---|---|---|---|
| `home-aurora` | `route = common/home` | aurora | `hero-particles` / `hero` / 400 / `#8a4fff` / 0.05 |
| `portfolio-geometric` | `path` contém `project` | geometric | `portfolio-3d` / `home_top` / 500 / `#22d3ee` / 0.06 |
| `tools-workshop` | `path` contém `tool` | workshop | `tools-3d` / `home_top` / 350 / `#f59e0b` / 0.08 |
| `notfound-lost` | `route = error/not_found` | lost | `not-found` / `footer_top` / 200 / `#94a3b8` / 0.04 |
| `variant-nebula` | `query.variant = b` | nebula | `hero-particles` / `hero` / 800 / `#6ea8fe` / 0.12 |
| `mobile-off` | `device = mobile` **ou** `hour < 7` | off | desligado (acessibilidade / performance) |

As 4 cenas allowlistadas e os **3 slots** (`hero`, `home_top`, `footer_top`)
aparecem. As regras usam `vars.scene_preset`, `flags` e `classes` para mostrar
como o Twig lê a decisão.

## `kaz_scene` (editor de composição)

`kaztheme-scene/v1`, canvas **960×540 / grid 8**, **24 camadas** cobrindo os
**5 kinds** (`rect`, `text`, `button`, `card`, `section`) e os **5 roles**
(`none`, `primary`, `surface`, `section`, `component`), com 7 `bindId`
demonstrando vínculo a seções/componentes, uma camada `locked` e `visible`
explícito. `ThemeSceneCss::compile()` gera CSS com 24 camadas a partir dele.

## As 25 fontes Twig

Todas as chaves `TEMPLATE_KEYS`, com classes próprias `thr-*`, focadas em 3D:
incluem `partial/theme-scene-block.twig` (composição) e
`partial/theme-scene.twig` (runtime) nos slots corretos — `common/home` e
`partial/hero-3d` em `hero`, `catalog/project_list` / `tool/tool_list` em
`home_top`, `common/footer` / `error/not_found` em `footer_top`. Usam
`|default(...)` e os globais `theme_scene` / `theme.*`.

## Segurança

Sem `<script>`, `<iframe>`, `<object>`, `<embed>`, `javascript:`, `on…=`,
`|raw`, `{% autoescape`, `srcdoc=`, `formaction=`, CDN, fontes externas,
`@import`, `!important`, `--bs-*` ou `--rms-*` nas fontes. Sem arquivos
`.twig`/`.js` no servidor, upload em disco, `eval` ou código executável. O
checksum cobre o `config` canônico; um byte alterado invalida o pacote.

## Como criar outros temas a partir dele

- **Exportar rascunho** depois de editar cores/cena e usar como novo pacote;
- usar `parent: 'reamur_threejs_reference'` para um **child-theme** (herda as
  25 fontes e mescla `three`/`tokens` por chave, até 5 níveis);
- trocar apenas `three` e `conditions.rules` para criar variações de campanha;
- trocar `kaz_scene.layers` para redesenhar a composição publicada.

Para **novas cenas** é preciso registrar o módulo em `AssetManifest` e nas
allowlists — isso é decisão de código, não deste pacote de dados.

## Verificação

```bash
node tools/verify-threejs-reference.cjs <raiz>
```

CI verde, validação WASM e guardas estáticos **não significam release
aprovada**. `release_approved: false` · F9-27 `BLOCKED_NOT_RUN` · F9-18
`release_ready: false` · 10 `PENDING_DECISION`. Nenhuma migration, schema,
contrato ou escrita do agente no GitHub.
