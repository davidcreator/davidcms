# Guia — Mídia Vetorial e Modelos de Página (Frente I)

A **I** dá ao tema **imagens decorativas determinísticas** e **modelos de
página** prontos, sem sair do config JSON.

> **Não é** a biblioteca de mídia do Blog (ADR 0049 / F8-05 — filesystem,
> upload, ACL/CSRF, fronteira Blog-only), **não é** o BDREditor (F9-25/F9-103,
> pausado), **não é** upload, arquivo no disco, URL externa, GLB, cena Three.js
> nem `custom_js`. Nada aqui lê ou escreve arquivos.

## Por que SVG em variável CSS

`Twig::setThemeGlobals()` aceita strings de até **256 bytes** e mapas planos de
até 64 entradas. Um data URI SVG tem milhares de bytes e seria **descartado**
silenciosamente. Por isso:

- O **data URI** vai para `--rms-media-<id>` em `ThemeMedia::css()`, concatenado
  ao `theme_token_css` já injetado no `style` do `<body>` por
  `common/header.php` (mesmo caminho de B, G e H). `html_attr` escapa o valor.
- Os **globais Twig** são só mapas planos e curtos: `theme_media` (variante),
  `theme_media_tones`, `theme_media_ratios` e `theme_media_enabled`.
- Os snippets usam `style="background-image: var(--rms-media-<id>)"` com
  `role="img"` e `aria-label` fixo — sem `|raw`, `<script>`, handlers,
  `javascript:`, `srcdoc` ou `formaction`.

## Catálogo (12)

`hero-aurora`, `hero-grid`, `cover-desk`, `cover-code`, `portrait-a`,
`portrait-b`, `icon-spark`, `icon-shield`, `pattern-dots`, `pattern-waves`,
`logo-mark`, `thumb-project`. Cada um tem grupo, 2–3 variantes
(`soft`/`bold`/`outline`), tons allowlistados (`primary`/`neutral`/`success`/
`danger`, hex fixo), forma e proporção (`16/9`, `4/3`, `3/4`, `1/1`).

`normalize()` é **fail-closed**: ID fora do catálogo, variante/tom fora do
enum, `enabled` de tipo errado, aninhamento ou mais de 12 entradas ⇒ `null` ⇒
nenhuma mídia. `svg()`/`dataUri()` são determinísticos e não contêm texto,
URL ou script do usuário.

## Config, pacotes e prévia

`media` é uma chave do config (sem migration), integrada ao `ThemePackage`
(`CONFIG_KEYS`, `MAP_KEYS`, `media_invalid`, checksum SHA-256, `decode()` e
herança **por ID** como os componentes G), ao `ThemeSnapshot`
(`media`, `media_enabled`, `media_css`, `media_bars`, globais planos) e à
`ThemeCompareView` (fita de mídias nos dois painéis + diff `media.<id>.variant`).
O `startup/theme.php` publica `catalog_theme_media_css`.

## Modelos de página (6)

`landing`, `portfolio`, `article`, `catalog`, `contact`, `blank`. São
**constantes** no `ThemeMedia` — **não entram no config**, como os presets de
cena D. Cada modelo traz uma lista de seções F, um mapa de componentes G, um
mapa de mídias I e um snippet Twig fixo para `common/home`.

No Admin, o card **Modelos de página** exibe 6 miniaturas. O botão aplica o
modelo **apenas com o checkbox de confirmação marcado**: ele marca/desmarca as
seções e reordena a lista F (`appendChild`, DOM puro), ajusta os selects G/I,
reescreve `#sections-order` e preenche o `textarea` do template. Depois é
preciso **Salvar rascunho** — o modelo por si só não grava nada.
`theme-media.js` usa só DOM: sem `fetch`, `eval`, `innerHTML` ou
`document.write`. Sem JavaScript, a galeria e os selects continuam no HTML.

## Segurança e fronteiras

Endpoint `mediaThumb` (GET, ACL `access`, `no-store`, `nosniff`, CSP própria,
zero escrita) devolve SVG fail-closed para ID/variante/tom inválidos. 66 novas
chaves ×3 idiomas. Intocados: `schema.sql`, `twig.php`, `autoloader.php`,
`header.twig`, `home.twig`, `PageLayout`, `cms/slot.php`, engines, runtime
Three.js/WebGL, biblioteca de mídia do Blog, BDREditor, manifestos, contratos
congelados e `release_approved`.

## Compatibilidade e limites

Config sem `media` ⇒ comportamento **idêntico à H**. Validação local: PHP 8.5
WASM, Twig 3.14 e jsdom com mocks — **não** houve MySQL real, sessão Admin
autenticada, browser com rede, upload, WebGL, E2E, staging ou deploy. As
imagens são SVG esquemático, não fotografias. **CI verde ≠ release aprovada.**
