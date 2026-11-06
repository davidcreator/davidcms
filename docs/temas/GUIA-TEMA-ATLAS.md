# Guia — Atlas Studio (tema de site completo em Twig)

**Arquivo:** `docs/temas/Atlas-Studio.reamurtheme.json`
**Formato:** `reamurcms-theme` v1 · **~62 KB** · **checksum SHA-256**
`4241cd9b430c66c72929ab0bee41c2a22cb9bbbe71141b28a5fe713baff72534`.

## O que é (e o que mudou em relação aos temas anteriores)

Um **site institucional completo**, não um esqueleto de referência. A crítica
foi justa: os temas BG/BH tinham 25 fontes com `|default('')`, divs genéricas e
nenhuma identidade visual. Aqui há:

- **CSS próprio de ~14 KB** (layout, tipografia, componentes, responsividade,
  foco visível e `prefers-reduced-motion`);
- **cabeçalho real** com marca, navegação de 6 itens, CTA e seletor de idiomas;
- **rodapé real** em 4 colunas;
- **conteúdo editorial de verdade** — textos, serviços, projetos, depoimentos,
  artigos, métricas e contato de um estúdio fictício (Atlas Studio);
- **9 seções** na home, **16 cards**, 11 páginas internas com trilha, título,
  conteúdo, coluna lateral e CTA;
- **SVG inline** para ilustrações e ícones, sem nenhuma imagem externa.

As prévias **`preview-desktop.png`** e **`preview-mobile.png`** mostram o
resultado renderizado; **`preview.html`** é a composição estática usada para
gerá-las (não é executada no servidor).

## Como usar

1. Admin → **Temas do Catálog** → **Importar tema**.
2. Arraste `Atlas-Studio.reamurtheme.json` (ou cole o conteúdo).
3. Confira a prévia: **25 fontes Twig**.
4. **Importar como rascunho** → revisar → **Ativar**.

Nada é publicado automaticamente. O tema chega com ThreeJS **desligado**,
porque é um site editorial — você pode ligar a cena depois se quiser.

## Como o tema se encaixa no ReamurCMS

O `header.twig` do sistema já inclui **`partial/site_header.twig`** e injeta
`--theme-primary` / `--theme-surface` no `<body>`. Os templates de rota usam
`{{ header }}` no início e `{{ footer }}` no fim. Então as três peças visuais
substituíveis são justamente:

| Template | Papel |
|---|---|
| `partial/site_header.twig` | `<style>` com o CSS do tema + `<header>` + `<nav>` |
| rotas (`common/home`, `cms/page_info`, …) | `<main>` com as seções + CTA |
| `common/footer.twig` | `<footer>` em 4 colunas + `</body></html>` |

O CSS usa `var(--theme-primary, #4338ca)` e `var(--theme-surface, #ffffff)` —
ou seja, **trocar as cores no editor de temas recoloriza o site inteiro** sem
editar uma linha de CSS. Os 22 `tokens` (fonte, escala, largura, raio) seguem
o `TokenRegistry` com contraste AA validado.

**Limite honesto do formato atual:** o pacote `reamurcms-theme` aceita apenas
as 25 `config.templates` como strings — não há chave `css` nem assets. Por isso
a folha de estilo fica **embutida em `<style>` dentro de
`partial/site_header.twig`**, renderizada uma vez por página no início do
`<body>`. Funciona e é seguro, mas não é um arquivo `.css` separado. Se você
quiser um CSS próprio (e imagens reais), isso é uma mudança de formato —
**Recorte B** — e precisa de autorização explícita, pois altera allowlist e
renderer.

## As 25 fontes

`common/home`, `common/footer`, `common/contact`, `partial/site_header`,
`partial/hero`, `partial/hero-3d`, `partial/about`, `partial/methodology`,
`partial/skills`, `partial/services`, `partial/testimonials`,
`partial/portfolio`, `partial/tools`, `partial/news`, `partial/clients`,
`partial/contact`, `cms/page_info`, `catalog/project_list`,
`catalog/project_info`, `blog/blog_list`, `blog/blog_info`,
`tool/tool_list`, `tool/tool_info`, `checkout/quote`, `error/not_found`.

A home percorre `cms_home_sections` e inclui os partials correspondentes. Se o
CMS não devolver seções, você pode colar os includes que quiser — os partials
têm conteúdo próprio e não dependem de dados do banco para ficar
apresentáveis.

## Segurança

O tema inteiro é **dados validados, sem código executável**. Sem `<script>`,
`<iframe>`, `<object>`, `<embed>`, `javascript:`, `on…=`, `|raw`,
`{% autoescape`, `srcdoc=`, `formaction=`, CDN, fontes externas, `@import`,
`!important`, `--bs-*` ou `--rms-*`. Sem arquivos `.twig`/`.js` no servidor,
upload em disco ou `eval`. O `|default(...)` de todas as variáveis tem
**texto real**, não string vazia.

## Personalizar

- **Cores:** mude `primary` / `surface` ou os tokens no editor.
- **Conteúdo:** edite os textos diretamente nas 25 fontes (são strings no JSON).
- **Identidade:** troque `Atlas Studio`, a marca `AS` e os textos do rodapé.
- **Variações:** `parent: 'atlas_studio'` cria um child-theme que herda as 25
  fontes e mescla cores/tokens por chave (até 5 níveis).
- **Exportar rascunho** gera um novo pacote versionável.

## Verificação

```bash
node tools/verify-site-theme.cjs <raiz>
```

CI verde, validação WASM, Chromium headless e guardas estáticos **não
significam release aprovada** e não substituem o aceite no Admin real.
`release_approved: false` · F9-27 `BLOCKED_NOT_RUN` · F9-18
`release_ready: false` · 10 `PENDING_DECISION`. Nenhuma migration, schema,
contrato ou escrita do agente no GitHub.
