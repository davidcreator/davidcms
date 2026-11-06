# Guia — David Creator (tema completo baseado em davidalmeida.xyz)

**Arquivo:** `docs/temas/David-Creator.reamurtheme.json`
**Formato:** `reamurcms-theme` v1 · **~63 KB** · **checksum SHA-256**
`c9fb44b0a82849b19991db53c415474ed71fcaf83d4f4b987838cd7bb3ab0201`.

## O que é

Um **site completo** que reproduz a identidade e o conteúdo do seu portfólio
**davidalmeida.xyz**, seguindo exatamente os critérios que você aprovou na
frente BI (Atlas Studio): 25 fontes Twig allowlistadas, CSS próprio, cabeçalho
e rodapé reais, conteúdo editorial, prévias renderizadas e `decode()` validado.

**Aceite visual registrado:** em 2026-11-06 você avaliou a BI como *"Agora sim
ficou perfeito e como esperado"*. Este tema mantém o mesmo padrão — **não é
release**, é um aceite de direção visual.

## O que foi lido do site real

Conteúdo transcrito de `https://davidalmeida.xyz/catalog/` e folhas de estilo
`style.css` / `skeleton.css` / `layout.css`, além de capturas do site em
1440 px:

| Elemento | Reprodução no tema |
|---|---|
| Hero cósmico "I'M / DAVID / L. ALMEIDA / CREATIVE DEVELOPER" | gradientes radiais + estrelas em CSS, sem imagem externa |
| Acento **ciano `#5CC6D0`** sobre fundo escuro `#1b1f24` | `config.primary` / `config.surface` + CSS |
| Header "DAVID CREATOR", nav `HOME ABOUT SERVICES WORKS BLOG CONTACT TOOLS ALUNOS` | `partial/site_header.twig` |
| Tipografia fina, caixa alta, tracking largo | `--dj-display`, `font-weight:200`, `letter-spacing` |
| WHO I AM? · processo de 6 etapas · UNITY/CREDIBILITY/ATTRACTION | `partial/about`, `partial/methodology` |
| MY SKILLS · TECH / PLATFORMS / PROGRAMS | `partial/skills` |
| WHAT DO I DO? · Graphic Design / Web Design / SEO | `partial/services` |
| CLIENT TESTIMONIALS (Silvio, Pedrinho, Bruno) | `partial/testimonials` |
| WHAT IT LOOKS LIKE? (JWA, Steel of Death, Hiperion Gamer, Selva Dev…) | `partial/portfolio` |
| CONNECTED TOOLS (Fluxa, Quotia, Vertex, Solis) | `partial/tools` |
| BELOVED CLIENTS · LATEST NEWS | `partial/clients`, `partial/news` |
| LET'S HAVE A CHAT? / SAY HELLO · São Paulo · contato@davidalmeida.xyz · WhatsApp | `partial/contact`, `common/contact`, rodapé |
| Redes sociais e © 2025 | hero + rodapé |

**11 seções** na home, **34 cards**, 11 páginas internas, 12 SVG inline.

**O que não foi copiado:** as fotos/`.webp` do portfólio e as fontes externas
(Lato, Open Sans, Poiret One). O formato `reamurcms-theme` não aceita assets
nem CDN, então os visuais são **vetoriais determinísticos** (gradientes, SVG,
blocos de cor) e a tipografia usa a stack de sistema. O conteúdo textual é
fiel; a imagem não é um clone pixel a pixel.

## Como usar

1. Admin → **Temas do Catálog** → **Importar tema**.
2. Arraste `David-Creator.reamurtheme.json`.
3. Prévia: **25 fontes Twig**, ThreeJS `hero-particles` / `hero`.
4. **Importar como rascunho** → revisar → **Ativar**.

Nada é publicado automaticamente. ThreeJS vem **ligado** (o site original é
cósmico/3D), com a cena `hero-particles` na cor ciano.

## Como o tema se encaixa no ReamurCMS

O `header.twig` do sistema inclui `partial/site_header.twig` (com `only`) e
injeta `--theme-primary` / `--theme-surface` no `<body>`; as rotas usam
`{{ header }}` e `{{ footer }}`. Então:

- `partial/site_header.twig` → `<style>` (CSS de ~16 KB) + `<header>` + nav;
- rotas → `<main>` com as seções + CTA;
- `common/footer.twig` → `<footer>` em 4 colunas + fechamento.

O CSS usa `var(--theme-primary, #5cc6d0)` e `var(--theme-surface, #1b1f24)` —
trocar as cores no editor recoloriza o site.

**Nota sobre tokens:** o `TokenRegistry` exige contraste AA da primária sobre
a superfície **e** do branco sobre o botão; com superfície escura isso é
matematicamente impossível. Por isso a identidade escura vive em
`config.surface` + CSS do tema, e os 22 tokens usam um conjunto AA válido
(`color_primary #0e6d7a`) que alimenta o `theme_token_css` do sistema. O
`decode()` real confirma `validateConfig() = []`.

**Limite do formato:** o CSS fica embutido em `<style>` no `site_header`, uma
vez por página. Para um `.css` separado e imagens reais é preciso autorizar o
**Recorte B** (muda allowlist/renderer).

## Segurança

O tema inteiro é **dados validados, sem código executável**. Sem `<script>`,
`<iframe>`, `javascript:`, `on…=`, `|raw`, `{% autoescape`, `srcdoc=`,
`formaction=`, CDN, fontes externas, `@import`, `!important`, `--bs-*` ou
`--rms-*`. Sem arquivos `.twig`/`.js` no servidor, upload em disco ou `eval`.

## Personalizar

- **Cores:** `primary` / `surface` ou tokens no editor.
- **Conteúdo:** edite as 25 fontes (strings no JSON).
- **Variações:** `parent: 'david_creator'` cria child-theme.
- **Exportar rascunho** gera um novo pacote versionável.

## Verificação

```bash
node tools/verify-david-theme.cjs <raiz>
```

CI verde, validação WASM, Chromium e guardas estáticos **não significam
release aprovada**. `release_approved: false` · F9-27 `BLOCKED_NOT_RUN` ·
F9-18 `release_ready: false` · 10 `PENDING_DECISION`. Nenhuma migration,
schema, contrato ou escrita do agente no GitHub.
