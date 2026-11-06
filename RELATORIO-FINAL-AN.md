# RELATÓRIO FINAL — Frente AN
## Padrão visual BDREditor ↔ KAZTheme-Editor

**Data:** 2026-11-05 · **Base remota:** `b89790108e42347c4fe19128a9d241d90ee7d49e` · **Não é release**

### 1. Pedido e escopo

> "Vamos trabalhar agora novamente no BDREditor e deixar ele com a mesma aparencia do KAZTheme-Editor para manter o padrão entre as ferramentas"

Decisões confirmadas: `abrangencia=identidade` (visual completo, DOM mantido, sem clonar screenbar/3 colunas/divisórias), `onde=admin` (sem `dev/catalog/view/css/blog-blocks.css`), `foco=manter` (`#174ea6` intacto).

### 2. Diagnóstico

O KAZ tem 34.897 bytes / 315 regras / tokens escuros (`#18181b`, `#1f1f23`, `#e9e9ee`, accent `#31efb8`). O BDREditor tem **119.293 bytes / 1140 regras / 383 classes / 842 cores hex literais** com fundo `#fff` e accent `#215bd6`. A estrutura (canvas, outline, inspector, ribbon, abas, fullscreen) **já é equivalente** — divergia apenas a identidade visual. Como as cores são literais, só remapear `--rb-*` não bastaria.

**Composição adotada:** *chrome escuro, canvas claro* — idêntico ao KAZ, onde o board branco do tema publicado repousa sobre o workspace escuro.

### 3. O que foi feito

| Item | Resultado medido |
| --- | --- |
| Paleta, botões, abas, campos, painéis | 16 tokens `--kaz-bdre-*`, 8 variáveis `--rb-*` remapeadas, 30+ superfícies sobrescritas |
| Cobertura do chrome | **29 → 0** elementos com fundo claro fora do canvas (Chromium, 329/341 elementos varridos) |
| Canvas do conteúdo | permanece `rgb(255,255,255)` com texto `rgb(23,32,51)` |
| Barras de rolagem | `scrollbar-color` = `color(srgb .165 .627 .499) color(srgb .126 .178 .178)`; regras `::-webkit-scrollbar-thumb` carregadas (0 → 2) |
| Escala de todos os elementos | `--kaz-ui` .86 / 1 / 1.14 / 1.30; fonte do botão **13,12 px → 17,056 px** no nível "Amplo" |
| Expandir/recolher | 3 dobras + "Recolher tudo"/"Expandir tudo", `aria-pressed` true↔false; a barra de controles nunca é recolhida |
| Foco F9-26 | `rgb(23,78,166) solid 3px` + `outline-offset 2px` **inalterados**; apenas halo claro acrescentado |
| Contraste do masthead | título **13,57:1**, subtítulo **5,98:1** (WCAG ≥ 4,5) |
| Documento de blocos | **idêntico ao controle**, 243 bytes, zero alteração |
| Exceções JS | 0 no controle, 0 com AN |
| Responsividade | desktop overflow **0**; 390 px: excesso do host **824 px → 53 px** (página 841 → 70), sem agravar |

### 4. Arquitetura

**Camada aditiva**, marcador `-kaz-bdre-an`. `blog-block-editor.css` (119 KB) e `blog-block-editor.js` (600 KB) **não foram alterados** — nem `init.js`, `import.js`, `bdre-concurrency.js`, os 4 Twigs de host, `schema.sql`, traduções, `theme_form.twig`, os 14 manifestos, os 11 congelados, `decision-matrix.json`, F9-26, F9-27 ou F9-18.

O companion JS usa DOM puro, não monta/edita/serializa o documento, e reconstrói a barra via `MutationObserver` porque o `init.js` **substitui os filhos do host ao montar** (descoberto no A/B). `window.__kazBdre()` é diagnóstico opcional — **não é preciso abrir o console**.

Registro nos 2 controllers: `blog/blog.php` e `cms/page.php`, +3 linhas cada, com variável própria `$kazBdreAsset` para preservar as contagens de `$assetVersion` / `$version`.

### 5. Validação

| Verificação | Resultado |
| --- | --- |
| PROBE `kaz-bdre-visual-probe.php` | **57 OK / 0 FALHAS** |
| Chromium A/B `verify-kaz-bdre-visual.cjs` | **51 OK / 0 FALHAS** |
| 4 guards (`verify-checksums.sh`, `repo-health.sh`, `verify-ci-workflow.sh`, `verify-contract-freeze.py`) | **4/4 rc=0** |
| Réplica fiel do HEAD | 2601 arquivos → **2606** (2 alterados + 5 novos) |
| Árvore remota | 2601 blobs / 40.150.628 bytes baixados, **0 divergentes** |
| Sintaxe | 3 PHP + 2 JS/CJS, 0 erros |
| Contratos | `release_approved:false`, F9-27 `BLOCKED_NOT_RUN`, F9-18 `release_ready:false` intactos |

### 6. Limites — isto NÃO é aprovação

A prévia usa o `preview/index.html` do repositório, que **não carrega o CSS do Admin real**; por isso o formulário ao redor aparece claro nas capturas. No Admin instalado, o entorno já recebe o tema das frentes AA–AM. Os **70 px** de overflow restantes a 390 px vêm do `.langtabs`/`.card` desse formulário, **fora de `[data-blog-block-editor]`** — corrigi-los exigiria reabrir o layout do formulário, fora do escopo `manter estrutura`.

Seguem pendentes as **2 capturas desktop + 390 px do Admin real**, `copy(__kazHealth())` em produção, o CI remoto (403 `Must have admin rights`) e o veredito visual do usuário. `image-7.png` e `image-8.png` continuam como critério histórico. **CI verde, PROBE, WASM e Chromium headless não significam release aprovada.**

`release_approved` continua `false`; F9-27 `BLOCKED_NOT_RUN`; F9-18 `release_ready:false` com P1 aberto; backend, banco, migration, ciclo criar/editar/salvar/Publicar, P2/F9-18 e F9-103 **não foram reabertos**. Nenhuma escrita nossa foi feita no GitHub — a aplicação do pacote é feita pelo usuário.
