# Relatório Final — Frente AP (correção de escopo da AO)

**Data:** 2026-11-06  
**Base reauditada:** `9c809050ff908b66b6f3bf97c1fc5a1aed9b3160` —
`feat: add total dark admin theme for BDREditor` (árvore 3.209 entradas,
2.626 blobs, 41.926.874 bytes, `truncated: false`), baixada byte a byte com
0 divergências.  
**Situação:** a AO foi aplicada pelo usuário e **reprovada visualmente**.
Este pacote é a **correção**, não uma continuação da AO.

---

## 1. A reprovação e a causa real

> *"A edição passou dos limites era para Editar somente o BDREditor mas acabou
> editando a barra de navegação lateral do ReamurCMS"*

`image-9.png` mostra o Admin real com sidebar, topbar, breadcrumb, cards e
campos escurecidos, item ativo e borda da sidebar **verde-menta**, e abas de
idioma com sublinhado verde. `image-10.png` mostra o KAZTheme-Editor com a
sidebar do Admin em **azul** e a identidade KAZ **apenas dentro do editor**.

A causa foi localizada no código, não inferida:

```css
/* dev/admin/view/css/reamurcms.css */
#menu > li.active > a { background-color: var(--bs-primary); }
/* dev/admin/view/css/themes/light.css */
--rms-accent: #0d6efd;
```

A AO sobrescrevia `--bs-primary: #31efb8`, `--bs-body-bg`,
`--bs-border-color` e dezenas de classes Bootstrap **no `body`/`html`**.
Como o item ativo da sidebar lê `var(--bs-primary)`, ele virou verde.

### A/B/C medido no Chromium (prévia com o markup real de `#menu`)

| Estado | item ativo da sidebar | `--bs-primary` | `.card` | `html`/`body` |
|---|---|---|---|---|
| Admin nativo + AN | `rgb(13, 110, 253)` azul | `#0d6efd` | `rgb(255,255,255)` | `rgb(244,246,249)` |
| **AO (reprovada)** | `rgb(49, 239, 184)` verde | `#31efb8` | `rgb(31,31,35)` | `rgb(24,24,27)` |
| **AP (corrigida)** | `rgb(13, 110, 253)` azul | `#0d6efd` | `rgb(255,255,255)` | `rgb(244,246,249)` |

A AO diverge do Admin nativo em **11 de 12 superfícies** medidas e em
**54 elementos** na varredura completa. A AP diverge em **zero**.

---

## 2. O que a AP entrega

**Recolhe a camada ao BDREditor**, substituindo o conteúdo da folha da AO no
mesmo caminho (nome mantido por compatibilidade; não é mais "total"):

- `[data-blog-block-editor]` e descendentes — host, chrome, biblioteca de
  padrões, inserter, prévias, tabelas, rail, inspector, outline, histórico,
  fullscreen, arraste, seleção;
- `body:has([data-blog-block-editor]) .rb-*` — **apenas** os overlays/dialogs
  que o editor cria em `document.body`; o `:has()` é só condição de presença,
  nunca alvo, e sempre exige um descendente `rb-*`;
- tokens **locais** `--kaz-ap-*` no host (nunca `:root`), scrollbars e foco
  dentro do host/overlays;
- canvas **branco** e foco F9-26 `#174ea6` / `3px` / `offset 2px`.

**Nenhuma regra** mira `html`, `body`, `:root`, `--bs-*`, `--rms-*`, `#menu`,
`.sidebar`, `.navbar`, `.page-titlebar`, `.breadcrumb`, `.container-fluid` ou
qualquer classe genérica do Admin. O PROBE varre **todos os seletores** e exige
que cada um contenha `[data-blog-block-editor]`; o verificador Chromium varre
**todos os elementos fora do editor** e exige zero divergências de
`background-color`, `color` e `border-color` contra o nativo.

O BDREditor continua com a identidade KAZ da AN: host `rgb(24,24,27)`,
chrome `rgb(31,31,35)`, accent `#31efb8`, canvas `rgb(255,255,255)` com texto
`rgb(23,32,51)`, documento de blocos idêntico (209 bytes), escala ampla e
dobras preservadas, zero overflow em desktop e 390 px.

### Arquivos (9)

| Arquivo | Ação |
|---|---|
| `dev/admin/controller/blog/blog.php` | marcador `-kaz-bdre-ao` → `-kaz-bdre-ap` |
| `dev/admin/controller/cms/page.php` | marcador `-kaz-bdre-ao` → `-kaz-bdre-ap` |
| `dev/admin/view/css/blog-block-editor-kaz-total.css` | **substituído**: escopo recolhido |
| `docs/KAZ-BDREditor-Tema-Total.md` | **substituído** pelo doc corrigido |
| `tools/kaz-bdre-total-probe.php` | **substituído** pelo PROBE de não-vazamento |
| `tools/verify-kaz-bdre-total.cjs` | **substituído** pelo A/B/C |
| `docs/Registro-Visual-AP.md` | **novo** — critério visual |
| `evidencia/ap_reprovacao_image-9.png` | **nova** — reprovação do usuário |
| `evidencia/ap_referencia_image-10.png` | **nova** — referência KAZ |

Substituir no mesmo caminho evita arquivo órfão e não exige remoção manual.
O marcador novo serve apenas para invalidar cache; a ordem AN → AP e os demais
assets não mudam.

---

## 3. O que permanece intocado

Sidebar, topbar, breadcrumb, titlebar, cards, campos, abas de idioma, botões,
alertas, badges e o acento azul do ReamurCMS; catálogo público, renderer,
documento de blocos, JS de ~600 KB, Twigs, `schema.sql`, traduções, manifestos
e os contratos F9-14 / F9-25 / F9-26 / F9-27 / F9-18. Sem JS novo, DOM novo,
`|raw`, migração, fonte externa ou CDN. Não se clona o layout do
KAZTheme-Editor (screenbar / 3 colunas).

`release_approved` segue `false`; F9-27 segue `BLOCKED_NOT_RUN`; F9-18 segue
`release_ready: false`; os 10 `PENDING_DECISION` não foram convertidos.

---

## 4. Validação

| Verificação | Resultado |
|---|---|
| Download fiel do HEAD `9c80905…` | 2.626 blobs / 41.926.874 bytes / **0 divergentes** |
| Réplica = base + pacote | 2.629 arquivos, 9/9 íntegros |
| Congelados (CSS/JS/Twigs/schema/manifestos/contratos) | **0 alterados** |
| PHP WASM `-l` | 392+ arquivos, **0 erros** |
| `node --check` JS/CJS | 620+ arquivos, **0 erros** |
| 4 guards (`checksums`, `repo-health`, `ci-workflow`, `freeze`) | **4 × rc=0** |
| 62 checkers e2e (base × réplica) | 30 rc=0 em cada, **0 divergências reais** |
| PROBE de não-vazamento | **53 OK / 0 FALHAS** |
| Chromium A/B/C | **41 OK / 0 FALHAS** |
| ZIP | CRC ok, `.sha256` conferido |

**Sobre os checkers e2e:** o único item instável é
`threejs_admin_preview_sandbox_isolated.cjs`, que alterna entre timeout e
`Target crashed` sob carga nos dois lados. Reexecutado isolado, **base e
réplica dão a mesma falha pré-existente `Target crashed`**; não há regressão
atribuída à AP. As 32 falhas restantes já existiam na base.

**Sobre os guards:** `repo-health.sh` instala um Playwright local em
`dev/system/storage/node_modules`; as duas árvores foram normalizadas antes da
comparação para que o `load-playwright.cjs` resolvesse a mesma versão
(1.56.1) nos dois lados.

---

## 5. Limites e aceitação

PHP 8.5 via WASM, Twig/jsdom e Chromium headless **não são produção**. A
prévia reconstrói as classes e os tokens do Admin; não reproduz o layout
completo da sidebar nem uma sessão autenticada. CI remoto segue não
verificado (`403 Must have admin rights`); `copy(__kazHealth())` de produção,
backend/DB/migração e o ciclo salvar–Publicar não foram executados.

Estes resultados locais **não significam release aprovada**. O veredito é
**visual, no Admin real, pelo usuário**: a AP só estará aceita quando a
sidebar/nav/topbar/formulário estiverem como antes da AO (azul, tema
ReamurCMS), o BDREditor continuar escuro e próximo de `image-10.png`, e
nenhum elemento fora do BDREditor tiver mudado. `image-9.png` permanece como
reprovação vigente até novo veredito.

Sem escrita no GitHub, sem deploy e sem declaração de release pelo agente.
