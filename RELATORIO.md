# ReamurCMS — Frente AA · Interatividade Total, Tamanhos de Tela e Tela Cheia

> **Pacote instalável.** Aplica funcionalidades a **todos os botões e recursos**
> do KAZTheme-Editor, permite **ajustar o tamanho das telas** e oferece
> **tela cheia em outra página** — sem refazer o layout Z aprovado.

| | |
| --- | --- |
| Base | `main` @ `08eca35d39c7464d2543e21995807ea9c162d62c` (frente Z aplicada pelo usuário) |
| Árvore | 3.176 entradas / 2.594 blobs / 14 manifestos / `truncated:false` |
| Arquivos | **11** completos (10 substituições + 1 doc novo) + `RELATORIO.md` + `INDICE.md` no ZIP |
| Recorte validado | 2195 arquivos / 30,976,154 bytes no HEAD AA |
| Réplica | 2196 arquivos / 31,026,031 bytes (10 alterados + 1 novo + 2185 congelados) |
| Validação | **5767 asserções / 0 falhas** (H1 193, H2 2718, H3 1502, H4a 40, H4b 138, H5 1176) |
| Probes | **14 + 14** cenários reais `edit()`/`compare()`/`fullscreen`, `twig_error=0`, 5 gravações por lado |
| Congelados A/B | 392 comparações, 0 divergências |
| Guards | **4/4 verdes e byte-idênticos** HEAD × réplica |
| Sintaxe | 387 PHP `php -l` 0 erros · 607 JS/CJS `node --check` 0 falhas novas |
| Build | idempotente **10/10** |
| SHA-256 | conteúdo (11 arquivos): `a1e91f6b415cc8fd21e59f86b5bc5e51f1348286534df2b3d8656bc9740783ec` · ZIP: ver `.sha256` externo |
| CRC | 13/13 entradas, `sha256sum -c` 51/51 |

---

## 1. O que o pedido pedia

1. *"Aplicar as funcionalidades para todos os botões e recursos"* → §2.
2. *"Que possa ajustar o tamanho das telas"* → §3.
3. *"Tenha uma opção de usar o tamanho tela cheia em outra página"* → §4.

A frente Z deixou a interface **como esperado**; nenhum layout foi refeito.

## 2. Todos os botões e recursos agora executam uma ação

| Controle (antes decorativo) | Agora |
| --- | --- |
| `Página 1` | botão `data-kaz-action="pages"` → abre Páginas + módulo Design |
| Avatar `1` | botão `data-kaz-action="users"` → informa a sessão local (sem rede) |
| Selo `Alterações não salvas` | botão `data-kaz-action="revert"` → descarta a cena ao `baseline` |
| Selo `Salvo` | `<button type="submit" form="kaz-theme-form">` → salva **sem JavaScript** |
| 6 swatches | `<button data-kaz-swatch>` → aplica a cor à camada selecionada |
| Tokens `primary`/`surface` | `<button data-kaz-token-apply>` → aplica o token à camada |
| Amostra tipográfica `Aa` | botão `data-kaz-action="add-text"` → insere camada de texto |
| Painel Páginas | `Ir para o board` → Design + Camadas + ajuste de zoom |
| Painel Protótipo | 3 botões reais: ir para a prévia, ajustar zoom, tela cheia |
| Exportar cena | além do textarea, baixa `kaztheme-scene.json` quando `Blob`/`createObjectURL` existem |

**Já funcionais e inalterados:** 15 `data-kaz-action` originais, 8 abas, 3 zoom,
2 ferramentas, 5 kinds, 6 blocos, 29 links de módulo, 11 campos do inspector,
drag/resize/pan/roda Ctrl, teclado, réguas, Inspect, histórico e os 14 módulos.

Os botões dos módulos legados (`data-section-move`, `data-media-insert`,
`data-component-insert`, `data-site-probe-button`, `data-model-apply`,
`data-scene-preset`, `data-tcb-*`, `data-theme-package-form`) continuam sendo
atendidos pelos seus scripts originais — nenhum foi reimplementado.

**Atalhos novos:** `F` tela cheia, `[` compacto, `]` amplo, `Shift+1..6` presets
de tela. Nenhum conflita com `V/H/R/T/B/C/S/+/-/0`, `Ctrl+Z`, `Ctrl+D`,
`Delete` ou setas.

## 3. Ajuste do tamanho das telas

Uma `.kaz-screenbar` no centro oferece **7 tamanhos**: Tema (cena), Desktop
`1440×900`, Laptop `1280×800`, Tablet `768×1024`, Mobile `390×844`, Widescreen
`1920×1080` e Personalizado (largura `320–3840`, altura `240–2160`, com clamp).

O board passa a ficar dentro de `.kaz-screen`, que recebe as dimensões em px;
rótulo do frame, `[data-kaz-screen-size]` e o statusbar espelham a escolha.
`Ajustar à tela` recalcula o zoom pelo **tamanho da tela** (`fitZoom()` usa
`screenSize`), e não pelo canvas. Como o preset inicial é `Tema`
(= `scene.canvas`), o boot permanece idêntico à frente Z.

**O tamanho da tela não altera `scene.canvas`** — é um frame de visualização,
estado local descartável como zoom/pan/abas/módulo. `[data-kaz-board-size]`
continua mostrando o canvas real. Nenhum controle novo tem `name=`: **o POST
é byte-idêntico ao da frente Z** (53 `name=` no shell, 194 no HTML renderido).

A mesma barra controla a **densidade das colunas** — Compacto `200/220`,
Normal `236/264`, Amplo `300/340` — e oculta/mostra cada sidebar
(`data-kaz-collapsed`).

## 4. Tela cheia em outra página — decisão de escopo

| Modo | Mecanismo | Efeito |
| --- | --- | --- |
| Nesta aba | `⛶ Tela cheia` / `F` | `requestFullscreen()` + `data-kaz-fullscreen="1"`; sem a API, apenas o CSS `position:fixed; inset:0` |
| **Em outra página** | `↗ Tela cheia em nova página` → `<a target="_blank" rel="noopener noreferrer" href="{{ action }}&amp;kaz_fullscreen=1">` | controller publica `kaz_fullscreen_page` a partir do GET; `theme_form.twig` **omite `{{ header|raw }}` e `{{ footer|raw }}`**; `data-kaz-page-mode="fullscreen"`; shell `100vw × 100vh`, sem chrome do Admin |
| Sair | `Sair da página cheia` | volta a `{{ action }}` sem o parâmetro |

**Decisão tomada:** não foi criada rota nova nem controller novo. O parâmetro
`kaz_fullscreen=1` é lido somente em `edit()`, e a outra página carrega o
**estado gravado no servidor**. Não há `localStorage`, `fetch`, `postMessage`,
sessão compartilhada ou upload. Por isso o selo `Alterações não salvas` e o
botão `Salvo` (submit) ficam na navbar: salve antes de abrir a outra página.
Isso evita transporte de estado e mantém o contrato de segurança Z.

`theme_form.twig` continua com **3 linhas** e os **2 `|raw`** já existentes,
agora condicionados por `{% if not kaz_fullscreen_page %}`. O shell tem **zero**
`|raw`, zero `<script>` inline e zero atributo `on*`.

## 5. Contratos preservados

- `dirty()` / `data-kaz-dirty` / `data-kaz-file-status` exclusivos da cena;
  `data-kaz-form-dirty` separado e informativo (frente Z).
- `syncField()`, `submit`, `baseline`, `MAX_LAYERS=48`, fail-closed, 6 blocos,
  15 módulos Design+14, 8 abas, 15 ícones SVG, 3 colunas, 29→30 links.
- `#kaz-theme-editor` `data-kaz-owner="1"`, `<form id="kaz-theme-form">`
  **filho**, 3 formulários irmãos (principal/import/restore) sem aninhamento,
  `csrf_token` nos 2 renderizados, `can_modify`, `formaction="{{ compare_url }}"`,
  `formtarget="theme-preview"`, iframe `sandbox=""`, pacote/restores.
- `kaztheme-scene/v1`, `CONFIG_KEYS` 14, `TEMPLATE_KEYS` 25, 418 chaves × 3
  idiomas, 14 manifestos idênticos / 90 paths cobertos / 11 paths LIVRES,
  11 hashes, `schema.sql`, 10 `PENDING_DECISION`, 77 paths BDREditor.
- Cache `-kaz-theme-editor-z` → **`-kaz-theme-editor-aa`**.

Sem migration, schema, coluna, `custom_js`, `|raw` novo, chave de tradução,
manifesto, CDN, fonte remota, `<script>` inline, `on*`, `innerHTML`, `eval`,
`fetch`, `localStorage`, upload, WebGL/Three.js real, cena pública ou escrita
remota. PHP WASM, Twig e jsdom com mocks **não são um servidor**.

## 6. Validação

| Harness | Escopo | Resultado |
| --- | --- | --- |
| H1 | contrato estático, HEAD AA = Z 10/10, árvore, manifestos, gates | 193/0 |
| H2 | pipeline real do pacote, 14 cenários × 115 campos + semântica | 2718/0 |
| H3 | A/B real HEAD × pacote, congelados + divergências AA | 1502/0 |
| H4a | autoloader real das 15 classes + Twig | 40/0 |
| H4b | regressão: manifestos, traduções, gates, docs, bibliotecas | 138/0 |
| H5 | **DOM real jsdom**: todos os botões, 7 presets, clamp, densidade, colapso, fullscreen, outra página, atalhos, submit | 1176/0 |

Probes: `14 + 14` cenários (`get`, `post`, `post_fullscreen`, `compare`, XSS,
49 camadas, bloco hero, sites vazios), `twig_error=0`, 5 gravações por lado,
392 comparações congeladas sem divergência. Guards 4/4 verdes e
byte-idênticos: `verify-checksums` 3/0 e 142 arquivos, `repo-health` 6/0/0,
`verify-ci-workflow` 31/0, `verify-contract-freeze` 11/11 hashes e 10 pendentes.

Build idempotente 10/10; ZIP 13 entradas com CRC íntegro; `.sha256`
externo; `sha256sum -c` 51/51 em `/home/user/entregas/`.

## 7. Instalação

Extraia o ZIP na raiz do repositório, preservando os caminhos. Os **11 arquivos
são completos** (não são patches). Não há migration, comando, `npm install`,
cache-bust manual além da query `-kaz-theme-editor-aa` já emitida pelo
controller, nem passo de banco. Faça refresh forçado do Admin para carregar o
novo CSS/JS. Abra `design/theme.edit` e use `↗ Tela cheia em nova página`.

## 8. Limitações e status

Sem PHP nativo, Composer, PHPStan, navegador real, MySQL, sessão autenticada,
staging, deploy ou E2E neste sandbox. A Fullscreen API depende de gesto do
usuário e do suporte do navegador (fallback CSS fornecido). A cena continua sem
renderização no site público; não há colaboração multiusuário, comentários,
protótipo navegável, View mode, componentes reutilizáveis ou múltiplos boards.

Nenhum `PENDING_DECISION`, `STATIC_ONLY`, `NOT_RUN`, `SKIP` ou
`BLOCKED_NOT_RUN` foi convertido em `PASS`. F9-27 segue `BLOCKED_NOT_RUN`;
BDREditor/F9-103 pausado; P2/F9-18 não reaberto; `release_approved:false`.
Nenhuma escrita no GitHub foi feita por este pacote e o CI remoto não foi
verificado aqui. **CI verde ou health check não significa release aprovada.**
