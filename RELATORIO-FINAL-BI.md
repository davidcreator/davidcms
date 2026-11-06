# Relatório Final — Frente BI (tema de site real em Twig)

**Data:** 2026-11-06 · **HEAD auditado:** `a704d2ae969c3ca89e89c352d4c84260508c123d`
**CI remoto:** ✅ 7/7 success · **Pedido:** *"fazer um tema real para twig como um site completo… não tem nada parecido com um site ou com um tema profissional de uso real"*.

## 1. A correção

A crítica foi aceita: BG/BH entregaram **25 esqueletos** com `|default('')`,
divs genéricas, sem CSS, sem identidade e sem conteúdo. Eram exemplos de
estrutura, não um site. Esta frente entrega **Atlas Studio**, um site
institucional completo.

| Antes (BG/BH) | Agora (BI) |
|---|---|
| `|default('')` e texto genérico | Textos editoriais reais em todas as fontes |
| Nenhum CSS | **CSS próprio de ~14 KB** embutido |
| `div` soltas | `<header>`, `<nav>`, `<main>`, `<section>`, `<article>`, `<footer>` |
| Sem conteúdo | 9 seções, 16 cards, 11 páginas internas, 15 SVG inline |
| Sem prévia | **`preview-desktop.png`**, **`preview-mobile.png`**, `preview.html` |

## 2. O que foi entregue

**`docs/temas/Atlas-Studio.reamurtheme.json`** — pacote `reamurcms-theme` v1
importável, ~62 KB, checksum
`4241cd9b430c66c72929ab0bee41c2a22cb9bbbe71141b28a5fe713baff72534`.

| Bloco | Conteúdo |
|---|---|
| `name` / `slug` / `parent` | `Atlas Studio` / `atlas_studio` / vazio |
| `primary` / `surface` | `#4338ca` / `#ffffff` |
| **`templates`** | **25/25 fontes**, ~55 KB de Twig com conteúdo real |
| `tokens` | 22 tokens (contraste AA validado) |
| `conditions` | `version=1`, `rules=[]` |
| `three` | **desligado** — site editorial, sem 3D |
| `kaz_scene` | `v1`, 960×540, 3 camadas de composição |

Acompanham: guia, verificador, `preview.html`, dois PNGs e evidências de layout.

## 3. Como o tema vira site de verdade

A leitura do `common/header.twig` e do `home.php` mostrou que:

- o `header.twig` do sistema **já inclui `partial/site_header.twig`** (com `only`);
- o `<body>` já recebe `--theme-primary` / `--theme-surface` e `theme_token_css`;
- as rotas usam `{{ header }}` no início e `{{ footer }}` no fim.

Então `partial/site_header.twig` = `<style>` (CSS do tema) + cabeçalho real;
`common/footer.twig` = rodapé em 4 colunas + fechamento do documento; e as rotas
compõem `<main>` com home completa (hero, clientes, serviços, sobre, método,
portfólio, depoimentos, blog, contato, CTA) e páginas internas com trilha,
conteúdo e barra lateral. O CSS usa `var(--theme-primary, …)` — **trocar a cor
no editor recoloriza o site inteiro**.

**Limite honesto:** o formato `reamurcms-theme` só aceita as 25 `templates` como
strings; não existe chave `css` nem assets. Por isso o CSS fica embutido em
`<style>` no `site_header`, uma vez por página. Funciona e é seguro, mas não é
um arquivo `.css` separado — isso exigiria mudança de allowlist/renderer
(**Recorte B**, sem autorização).

## 4. Prévia renderizada — e dois bugs que ela revelou

Gerei `preview.html` extraindo **o próprio CSS do pacote** e resolvendo as
diretivas simples, e capturei com Playwright em viewport real:

| | desktop 1440 | mobile 390 |
|---|---|---|
| `scrollWidth` | 1440 | 390 |
| overflow | **nenhum** | **nenhum** |
| seções / cards | 9 / 16 | 9 / 16 |
| botão primário | `rgb(255,255,255)` sobre `rgb(67,56,202)` | idem |

Dois achados corrigidos durante a construção:

1. **Bug real de CSS:** `.atlas a` (especificidade 0,1,1) vencia
   `.atlas-btn-primary` (0,1,0) — o texto do CTA ficava roxo sobre fundo roxo,
   **invisível**. Corrigido com `.atlas a.atlas-btn-primary`.
2. **Falso alarme de overflow no mobile:** o Chromium headless com
   `--window-size=390` reportava `innerWidth=500` (largura mínima da janela),
   cortando o texto na captura. Refeito com `page.setViewportSize(390, 844)`:
   `scrollWidth=390`, zero overflow.

Também troquei o rodapé de `<div>` para `<footer>` por semântica.

## 5. Validação

| Verificação | Resultado |
|---|---|
| `ThemePackage::validateConfig()` / `decode()` reais (PHP 8.5.12 WASM) | ✅ **0 erros / ok=true** |
| **Harness PHP do site** | ✅ **36 OK / 0 FALHAS** |
| Checksum independente (Python + Node) | ✅ idêntico |
| 25 fontes: allowlist, segurança, Twig balanceado | ✅ 25/25, 0 violações |
| CSS: tokens, responsivo, foco, sem `!important`/CDN/`--bs-*` | ✅ |
| Prévia: 9 seções, 16 cards, header/footer/CTA, sem overflow | ✅ desktop + mobile |
| `tools/verify-site-theme.cjs` | ✅ 0 FALHAS |
| `verify-checksums` / `repo-health` / `verify-contract-freeze` | ✅ 142/0 · 6 PASS/0 FAIL · COERENTE |
| CI remoto do HEAD | ✅ 7/7 success (lido, não disparado) |

## 6. O que NÃO foi mexido

Nenhuma migration, `schema.sql`, contrato congelado, hash, manifesto,
workflow, `ThemePackage`, `TEMPLATE_KEYS`, `header.twig`, controller, model,
idioma, renderer ou arquivo `.twig`/`.css`/`.js` no repositório. Os arquivos
novos ficam em `docs/temas/` e não entram em `CHECKSUMS*.txt`. **Nada é
instalado automaticamente** — import continua sempre como rascunho.

Sem código executável, `<script>`, `|raw`, `javascript:`, `on…=`, CDN, fontes
externas, `@import`, `!important`, `--bs-*` ou `--rms-*`.

## 7. Limites e pendências

Nenhuma escrita do agente no GitHub, nenhum runtime/MySQL/staging/deploy,
nenhuma conversão de `PENDING`/`NOT_RUN`/`BLOCKED_NOT_RUN` em `PASS`, nenhuma
decisão das **10 `PENDING_DECISION`**, nenhuma mudança em
`PROPOSAL_PENDING` / `DESIGN_GATE_PENDING`, nenhuma declaração de release.

`release_approved: false` · F9-27 `BLOCKED_NOT_RUN` · F9-18
`release_ready: false`. CI verde, PHP WASM, Chromium e guardas estáticos **não
significam release aprovada** e não substituem o aceite no Admin real.

**Ainda pendentes:** JSON do **health** do Admin real e os aceites visuais.

## 8. Próximos passos

1. **Importar** o Atlas Studio no Admin real, ativar e comparar com as prévias.
2. Se quiser **CSS/assets como arquivos reais**, autorize o **Recorte B**
   (muda `CONFIG_KEYS`/allowlist/renderer).
3. Trocar segmento, paleta ou conteúdo conforme a sua preferência.
4. Roteiro: health (2), D01–D10 com **AR** (3), F918 com **AS** (4).
