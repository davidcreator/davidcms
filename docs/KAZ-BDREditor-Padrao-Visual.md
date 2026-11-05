# Frente AN — Padrão visual BDREditor ↔ KAZTheme-Editor

**Data:** 2026-11-05 · **Escopo decidido:** identidade visual completa, **somente telas Admin**, foco `#174ea6` preservado · **Não é release**

## Objetivo

Deixar o BDREditor com a mesma aparência do KAZTheme-Editor para manter o padrão entre as ferramentas, sem reabrir backend, banco, migration, ciclo de Publicar, release, CI, F9-27, P2/F9-18 nem o ciclo de criação de tema.

Decisões registradas nesta frente: `abrangencia=identidade`, `onde=admin`, `foco=manter`.

## Diagnóstico antes da mudança

| | KAZTheme-Editor | BDREditor |
| --- | --- | --- |
| Paleta | escura: `--kaz-bg #18181b`, `--kaz-panel #1f1f23`, texto `#e9e9ee` | clara: `--rb-surface #fff`, `#f8fafc`, texto `#172033` |
| Accent | verde-menta `#31efb8` | azul `#215bd6` / `#315ee8` |
| Folha | 34.897 bytes · 315 regras · 138 classes `kaz-*` | **119.293 bytes · 1140 regras · 383 classes · 842 cores hex literais** |
| Estrutura | screenbar, 3 colunas, canvas, inspector, abas | **já tem** canvas, outline/estrutura, inspector, ribbon, fullscreen, abas |
| Integração | `theme.php` | `blog/blog.php` + `cms/page.php` → `blog_form.twig`, `cms/page_*` |

Conclusão: a **estrutura já é equivalente**; o que diverge é a **identidade visual**. O `blog-block-editor.css` usa centenas de cores literais, então uma camada aditiva só com variáveis não bastaria — a frente AN combina remapeamento das 8 variáveis `--rb-*` com sobrescrita explícita dos componentes de chrome.

### Regra de composição

**Chrome escuro, canvas claro.** No KAZ o board 960×540 (o conteúdo que será publicado) é branco sobre um workspace escuro. A AN reproduce isso: toolbar, ribbon, painéis, inspector, outline, campos e botões ficam escuros; `.rb-canvas` e os blocos dentro dele **permanecem brancos com texto escuro**. Assim a leitura do conteúdo não muda e o padrão entre as ferramentas é visualmente idêntico.

## Entregas

| Pedido / item | Implementação |
| --- | --- |
| Mesma paleta | `--kaz-bdre-*` espelham os 16 tokens do KAZ; as 8 variáveis nativas `--rb-*` são remapeadas no host. |
| Botões, abas, campos, painéis | Sobrescrita com `!important` e escopo `[data-blog-block-editor]` (especificidade 0,2,0) cobrindo 30+ superfícies e estados (`aria-pressed`, `is-active`, `primary`, `disabled`). |
| Barras de rolagem conforme o tema | `scrollbar-color` + `::-webkit-scrollbar-{thumb,track,corner}` com `color-mix()` sobre `--kaz-bdre-primary`, `forced-colors` preservado — mesma técnica da frente AL. |
| Ajustar tamanho de todos os elementos | Seletor `ELEMENTOS` com 4 níveis (`--kaz-ui` .86 / 1 / 1.14 / 1.30) escalando fontes, gaps, paddings e alturas do chrome em 10+ grupos `calc()`. O canvas não escala. |
| Expandir / recolher ferramentas | Botões `Ícones`, `Abas`, `Status` e `Recolher tudo` / `Expandir tudo`, com `aria-pressed` e `role="status"`. A barra de controles é o primeiro filho do host e **nunca é recolhida**, então a reabertura está sempre disponível. |
| Foco / acessibilidade | **Contrato F9-26 preservado**: `outline: 3px solid #174ea6`, `outline-offset: 2px`. Foi acrescentado apenas um halo claro (`box-shadow`) para manter contraste sobre o fundo escuro. `prefers-reduced-motion` respeitado. |

## Arquitetura aditiva

Marcador próprio **`-kaz-bdre-an`**, para não alterar as contagens de `$assetVersion` / `$version` dos controllers nem os checkers e2e existentes.

| Arquivo | Papel |
| --- | --- |
| `dev/admin/controller/blog/blog.php` | +3 linhas: registra CSS + JS aditivos |
| `dev/admin/controller/cms/page.php` | +3 linhas: registra CSS + JS aditivos |
| `dev/admin/view/css/blog-block-editor-kaz.css` | novo · tema, scrollbars, escala, dobras |
| `dev/admin/view/js/blog-block-editor-kaz.js` | novo · controles, `MutationObserver`, `window.__kazBdre()` |
| `docs/KAZ-BDREditor-Padrao-Visual.md` | este documento |
| `tools/` | PROBE + verificador Chromium |

**`dev/admin/view/css/blog-block-editor.css` (119 KB) e `blog-block-editor.js` (600 KB) não foram alterados.** Também não foram tocados: `blog-block-editor-init.js`, `blog-block-import.js`, `bdre-concurrency.js`, os 4 Twigs de host, `dev/catalog/view/css/blog-blocks.css`, `schema.sql`, arquivos de linguagem, `theme_form.twig`, os 14 manifestos `CHECKSUMS*.txt`, os 11 artefatos congelados, `decision-matrix.json`, `docs/F9-26-Contrato-A11y-Performance.json` e `docs/F9-27-Status.json`.

O companion JS **não monta, não edita e não serializa** o documento de blocos: ele só cria a barra de controles e aplica atributos de apresentação no host. `window.__kazBdre()` é diagnóstico opcional — **não é preciso abrir o console**. Preferências são estado local descartável, sem armazenamento por contrato.

DOM puro (`createElement`, `textContent`, `setAttribute`): sem escrita dinâmica de marcação, execução dinâmica, rede, armazenamento, `|raw` novo, `name=` novo, tradução nova, migração, coluna, rota Admin, CSRF, CSP ou sandbox alterados.

## Instalação e verificação

Copiar `dev/` e `docs/` sobre a instalação; `tools/` e `evidencia/` são só validação. **Limpe o cache de CDN/proxy** (novo marcador `-kaz-bdre-an`). Abra uma edição de Post (Blog) ou Página (CMS): o chrome do editor deve estar escuro com accent verde, o canvas branco, e uma barra `ELEMENTOS` no início.

```bash
php tools/kaz-bdre-visual-probe.php
node tools/verify-kaz-bdre-visual.cjs
```

## Limites — isto NÃO é uma aprovação

Camada de apresentação: não prova sessão Admin autenticada, MySQL, deploy, CI remoto, nem a avaliação visual do usuário. A frente AM recebeu confirmação funcional do usuário ("sim, agora consigo"), mas **as 2 capturas desktop + 390 px do Admin real**, `copy(__kazHealth())` em produção e o CI remoto (403 `Must have admin rights`) continuam pendentes. `image-7.png` e `image-8.png` seguem como critério histórico: harnesses, PROBE e Chromium headless não substituem a tela real.

`release_approved` continua `false`, F9-27 `BLOCKED_NOT_RUN`, F9-18 `release_ready:false` com achados P1 em aberto, P2/F9-18 não reaberto. **CI verde, PROBE e Chromium headless não significam release aprovada.** Nenhuma escrita nossa foi feita no GitHub; a aplicação do pacote é feita pelo usuário.
