# Design tokens (Frente B)

Complementa `docs/GUIA-TEMAS-CONDICOES-THREEJS.md` e
`docs/GUIA-BUILDER-CONDICOES-TEMA.md`.

## O que era possível antes

Apenas `primary` e `surface` (duas variáveis CSS no `<body>`). Tipografia,
escala, espaçamento, raios, sombras, densidade, largura e o tema escuro exigiam
reescrever Twig ou CSS à mão — e não havia qualquer verificação de contraste.

## O que passa a existir

`dev/system/library/theme/token_registry.php` (`TokenRegistry`), registrado no
`framework.php` como `theme_token_registry`. São **22 tokens allowlistados**,
em 4 grupos:

| Grupo | Tokens |
|---|---|
| Tipografia | `font_family` (4 stacks system, sem fonte remota), `font_size` 14–20, `font_scale` 1.0–1.5, `line_height` 1.2–2.0, `font_weight` 300–800, `heading_weight` 400–900 |
| Espaçamento/forma | `space_unit` 2–16, `density` (compact/comfortable/spacious), `content_width` 800–1600, `radius` 0–32, `radius_lg` 0–64, `border_width` 0–4, `shadow` 0–4 (5 sombras pré-definidas) |
| Cores claras | `color_text`, `color_muted`, `color_surface`, `color_primary`, `focus_ring` (hex 6) |
| Cores escuras | `dark_text`, `dark_muted`, `dark_surface`, `dark_primary` (hex 6) |

O `config` do tema ganha o bloco `tokens`. Temas antigos sem ele recebem os
**defaults**, então nada muda para quem já publicou.

### Onde os tokens aparecem

`startup/theme.php` normaliza a base, aplica os patches das condições e publica
`catalog_theme_tokens` + `theme.tokens` (global Twig). `common/header.php` gera
as declarações `style="...; --rms-*: valor"` no `<body>`, com
`|e('html_attr')`. `theme.css` **consome** as variáveis: `body` mapeia
`--rms-light-*` para `--rms-color-*`, e `[data-bs-theme="dark"] body` troca
pelos `--rms-dark-*`. Ou seja, os tokens têm efeito visual real e o modo escuro
funciona sem nenhum `<style>` inline (que exigiria `|raw`, proibido).

### Contraste WCAG

`TokenRegistry::contrast()` mede luminância relativa. Sete pares são exigidos no
**save** (mínimo AA 4,5:1): texto/superfície, secundário/superfície,
primária/superfície e branco/primária no claro; texto, secundário e primária
sobre a superfície no escuro. O formulário mostra cada razão medida com badge
verde/vermelho. Um valor que reprova **não é salvo**, com código de erro estável.

As condições da Frente A também podem sobrepor tokens via `then.tokens` (patch
parcial). No startup, o merge final é revalidado: se um patch tornar o tema
ilegível, ele é **ignorado** (fail-closed) e a base permanece.

### Segurança

Nenhum CSS livre. `css()` só emite valores derivados de enums, inteiros/floats
formatados, hex validado e tabelas fixas; `safeCssValue()` ainda rejeita
`;`, `{}`, `<`, `>`, aspas, `url(`, `@import` e `expression(`. Sem `|raw` novo,
sem `<style>` inline, sem fontes remotas, sem `eval` e sem IO.

## Arquivos

`token_registry.php` (novo), `framework.php`, `condition_engine.php` (chave
`tokens` aditiva em `then`), `startup/theme.php`, `common/header.php`,
`common/header.twig`, `theme.css`, `admin/controller/design/theme.php`,
`design/theme_form.twig`, `theme-condition-builder.js` (campo de patch no
`then`), `theme-condition-builder.css`, 3 idiomas e este guia.

Nenhum manifesto `CHECKSUMS*.txt`, contrato congelado, `schema.sql`, workflow,
checker ou arquivo do runtime Three.js foi modificado.

## Limitações

Validação em PHP 8.5 WASM + Twig 3.14 + jsdom. Sem browser real, WebGL,
CMS completo, banco, sessão Admin autenticada, E2E, migration, staging, deploy
ou escrita no GitHub. A conferência visual (claro/escuro) é do usuário.
CI verde e health verde não significam release aprovada.
