# RELATÓRIO FINAL — Frente AO
## Tema TOTAL do BDREditor ↔ KAZTheme-Editor

**Data:** 2026-11-06 · **Base remota:** `1b6ffd97e418272eebccfd573cccbe61c15ca386` · **Não é release**

### 1. Pedido e decisões

> "ReamurCMS-Frente-AN-Padrao-Visual-BDREditor aplicado com sucesso github atualizado. Podemos prosseguir com o próximo passo deixe todo tema do BDREditor semelhante ao KAZTheme-Editor"

Confirmação remota da AN: **14 adições** (5 arquivos da AN + 7 evidências + 2 docs de raiz), **2 modificações** (`blog/blog.php`, `cms/page.php`), **0 remoções**; os 7 arquivos da AN **byte a byte idênticos** ao pacote local. Árvore: 2615 blobs / 41.162.133 bytes.

Escopo respondido: `alcance=pagina_e_estados` (página Admin inteira + todos os estados) e `modo_tema=sempre_escuro` (sempre no escuro KAZ). Permanecem: somente Admin, sem clonar layout, sem alterar DOM/JS de 600 KB, foco `#174ea6` congelado.

### 2. Diagnóstico do que faltava

| Camada | Situação após a AN |
| --- | --- |
| Admin ao redor | `reamurcms.css` claro (`#f4f6f9`, `#fff`, `#f8fafc`): navbar, breadcrumb, `.page-titlebar`, `.card`, `.form-control`, `.nav-tabs`, `.input-group-text`, `.alert`, `.btn`, `.badge`, sidebar de publicação |
| Estados sob demanda | JS faz 3× `document.body.appendChild` (overlays **fora do host**); ~100 classes de command palette, padrões, inserter, prévias, tabelas, agrupamento, fullscreen, arraste/seleção; AN cobria 6 regras desses termos |
| `<html>` | superfície visível em fullscreen/workspace, não coberta pela AN |
| Preferência de tema | `data-bs-theme` / `theme_skin` / `theme_mode` continuavam claros |

A AN estava escopada em `[data-blog-block-editor]`, portanto só alcançava o chrome do editor.

### 3. Resultados medidos (Chromium A/B, Admin reconstruído com os CSS reais)

| Métrica | Controle (só AN) | Com AO |
| --- | --- | --- |
| `<html>` / `<body>` | `rgb(244,246,249)` | **`rgb(24,24,27)`** |
| `--bs-body-bg` | `#f4f6f9` | **`#18181b`** |
| `.card` | `rgb(255,255,255)` | **`rgb(31,31,35)`** |
| `.form-control` | `rgb(255,255,255)` | **`rgb(44,44,51)`** |
| `.alert` | `rgb(221,241,246)` | **`rgb(31,31,35)`** |
| `.btn-primary` | `rgb(30,145,207)` | **`rgb(49,239,184)`** |
| Título / labels | `rgb(33,37,41)` | **`rgb(233,233,238)` / `rgb(155,155,167)`** |
| Dialog / padrões / prévia / inserter | claros | **`rgb(38,38,43)`** |
| **Fundos claros fora do canvas** | **21** | **0** |
| Canvas de conteúdo | branco | **branco (inalterado)** |
| Regras / seletores AO no motor | 0 / 0 | **20 / 186** |
| `data-bs-theme` | `light` | **`light` (não alterado)** |
| Exceções JS | 0 | **0** |
| Overflow desktop / 390 px | 0 | **0 / 0** |
| Documento de blocos | — | **idêntico ao controle** |

**Não-regressão da AN:** escala "Amplo", 3 dobras, barra `ELEMENTOS` e `__kazBdre()` continuam operando; foco segue `rgb(23,78,166) solid 3px` com `outline-offset 2px`.

### 4. Arquitetura

Uma folha aditiva `blog-block-editor-kaz-total.css`, carregada **depois** da AN nos 2 controllers, marcador `-kaz-bdre-ao`. **Nenhum JS novo**, nenhuma alteração no CSS/JS da AN, nenhum Twig.

Escopo `:is(body:has([data-blog-block-editor]), html[data-kaz-bdre-tema="1"] body)` + `:is(html:has(...), html[data-kaz-bdre-tema="1"])`. O `:has()` é dinâmico (cobre overlays criados depois em `document.body`); o atributo é fallback para navegadores sem `:has()`, onde a **AN continua intacta**. "Sempre escuro" vem da sobrescrita dos tokens `--bs-*` e `--kaz-bdre-*` no `body` das telas com editor, mais regras explícitas com `!important` sobre as cores literais de `reamurcms.css`/Bootstrap — **sem mudar a preferência do usuário**.

**Inalterados:** `blog-block-editor.css` (119 KB), `blog-block-editor.js` (600 KB), init/import/concurrency, `blog-block-editor-kaz.css`, `blog-block-editor-kaz.js`, os 4 Twigs de host, `reamurcms.css`, `bootstrap.css`, skins, `admin.css`, `blog-blocks.css` do catálogo, `schema.sql`, traduções, `theme_form.twig`, os 14 manifestos, os 11 congelados, `decision-matrix.json` e os contratos F9-14/25/26/18/27.

### 5. Validação

| Verificação | Resultado |
| --- | --- |
| PROBE `kaz-bdre-total-probe.php` | **52 OK / 0 FALHAS** |
| Chromium A/B `verify-kaz-bdre-total.cjs` | **46 OK / 0 FALHAS** |
| 4 guards (`verify-checksums.sh`, `repo-health.sh`, `verify-ci-workflow.sh`, `verify-contract-freeze.py`) | 4/4 rc=0 |
| Checkers e2e (base × réplica) | 62 comparados, **0 divergências** |
| Réplica fiel do HEAD | 2615 blobs / 41.162.133 bytes / 0 divergentes → 2619 arquivos (2 alterados + 4 novos, normalizando `node_modules`) |
| Sintaxe | 392 PHP + 620+ JS/CJS, 0 erros |
| Contratos | `release_approved:false`, F9-27 `BLOCKED_NOT_RUN`, F9-18 `release_ready:false` intactos |

### 6. Limites — isto NÃO é aprovação

A prévia reconstrói os formulários com as **classes reais** de `reamurcms.css`/Bootstrap e carrega `bootstrap.css`, `reamurcms.css` e a skin clara, mas **não é** uma sessão Admin autenticada: sem MySQL, deploy ou CI remoto. Seguem pendentes as **2 capturas desktop + 390 px do Admin real**, `copy(__kazHealth())` em produção, o CI remoto (403 `Must have admin rights`) e o veredito visual do usuário. `image-7.png` e `image-8.png` continuam como critério histórico.

`:has()` cobre Chrome 105+, Safari 15.4+, Firefox 121+; sem suporte, a página volta ao tema do Admin e a AN segue funcionando (degradação graciosa, sem perda funcional).

`release_approved` continua `false`; F9-27 `BLOCKED_NOT_RUN`; F9-18 `release_ready:false` com P1 aberto. Backend, banco, migration, ciclo criar/editar/salvar/Publicar, P2/F9-18 e F9-103 **não foram reabertos**. **CI verde, PROBE, WASM e Chromium headless não significam release aprovada.** Nenhuma escrita nossa foi feita no GitHub — a aplicação do pacote é feita pelo usuário.
