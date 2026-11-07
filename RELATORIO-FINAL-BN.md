# Relatório Final — Frente BN (David Universe 3D)

**Data:** 2026-11-07 · **HEAD:** `ad7643de6ca2` · **Pedido:** *"um projeto que
estava trabalhando o novo tema oficial do meu site
https://davidalmeida.xyz/threejs/?lang=pt … fazer um tema completo seguindo
este que está online como amostra"*.

**Contexto:** aceite visual explícito da BM (*"ficou muito bom"*). Seguem os
mesmos critérios de BI/BJ/BM: site real completo, 25 fontes Twig, Recorte A
(**dados validados, sem código executável**), ThreeJS declarativo e prévia
Chromium real.

## 1. A amostra online (medida, não presumida)

`fetch_page` nos 2 chunks + HTML bruto (953.239 bytes, SPA Vite/React) +
**Chromium 141 headless real** com WebGL:

| Propriedade | Valor medido |
|---|---|
| Título | `David L. Almeida — Desenvolvedor Criativo` |
| `<html lang>` | `pt-BR` |
| `body` background | `rgb(18, 21, 22)` = **`#121516`** |
| `body` color | `rgb(223, 223, 223)` = **`#dfdfdf`** |
| Fonte corpo | `"Open Sans", system-ui, sans-serif` |
| Fonte títulos | `"Poiret One", cursive` |
| `theme-color` | `#121516` |
| Canvas WebGL | **1** (planeta wireframe, nave, estrelas, orbes) |
| Seções / altura | 10 / 13.548px desktop · 20.611px mobile |
| Imagens | 21–22 · 0 pageerrors |
| Paleta | `#5cc6d0` (25×), `#121516`, `#a7b3b5`, `#323a3d`, `#eb767e`, `#ffdf00` |

**Conteúdo transcrito:** 9 fases — 00 Decolagem, 01 Sobre, 02 Processo (6
níveis), 03 Por que investir (3 power-ups), 04 Habilidades (classe +
inventário com 3×5 slots), 05 Serviços (3 blocos ×5 itens), 06 Catálogo (9
projetos: Juvenile World Academy, Steel of Death, Farmer Life Simulation,
Chameleon Digital Art, Rogério Consultoria, Pitbul Color, Cat Fe, Classic Car,
Selva Dev), 07 Depoimentos + Instagram, 08 Contato. HUD de jogo:
*"FASE 00 — DECOLAGEM"*, *"DISTÂNCIA 0 KM"*, *"ORBES 00/10"*,
*"Mova o mouse para pilotar · Role para voar · Colete os orbes"*,
*"Controles"*, *"Jogador 1 LVL 01"*.

Capturas de referência em `evidencia/ref-*.png` e `evidencia/ref.json`.

## 2. O tema entregue

`David Universe 3D` (`david_universe_3d`) — **88.864 bytes**, 25 fontes Twig,
checksum do config canonizado
`e081f0d26759034b15f1e200e2248199bbee43dea30765fb8977704c3f29a1d4`.

| Elemento | Como ficou |
|---|---|
| HUD fixo | marca `DA / DAVID L. ALMEIDA · Creative Developer`, 6 links, `ORBES 00/10` com 5 orbes coloridos, faixa `FASE 00 / DISTÂNCIA 0 KM` |
| Hero | `EU SOU` + `DAVID L. ALMEIDA` + `Desenvolvido` + tagline real + `Iniciar missão →` / `Ver catálogo` + painel **Controles** + redes |
| Fases | 10 `.du-phase` numeradas com números fantasma, tags `FASE 0X`, `Nível 0X · OK`, `Power-up 0X · +1` |
| Cartões | `.du-card` translúcidos com cantos HUD (mesmo desenho da amostra) |
| Catálogo | **9 níveis** com selos **SVG inline** (sem `.webp` remotas) |
| Inventário / serviços | 3×5 slots e 3 blocos ×5 itens, conteúdo real |
| Contato | ficha do jogador + formulário (nome/e-mail/mensagem) |
| Rodapé | `DAVID L. ALMEIDA` fantasma, © 2026, São Paulo, `contact@davidalmeida.xyz` |
| Cores | `#5cc6d0` / `#121516` / `#dfdfdf` / `#a7b3b5` / `#eb767e` / `#ffdf00` |

**Sem fontes remotas:** a amostra carrega Google Fonts (Poiret One / Open
Sans / Lato), o que é **proibido** pelas restrições do projeto. O CSS usa
`--du-display` com stack local (`"Poiret One", "Century Gothic", "Trebuchet
MS", system-ui`) — se a fonte existir no sistema ela é usada, senão cai na
stack sem rede. As 9 miniaturas de projeto são SVG inline determinísticos.

### ThreeJS declarativo (Recorte A)

O tema **não contém JavaScript**. O slot é o partial nativo
`partial/theme-scene.twig` (atributos `data-*`), e o `scene-runner.js`
self-hosted do Catálogo monta o WebGL. `conditions` allowlistadas trocam a
cena por rota — confirmado pelo `ThemeSnapshot` real:

| Rota | Cena | Slot | Partículas | Cor | Vel. |
|---|---|---|---|---|---|
| `common/home` | `hero-particles` | `hero` | 520 | `#5cc6d0` | 0.06 |
| `catalog/project_*` | `portfolio-3d` | `home_top` | 500 | `#eb767e` | 0.07 |
| `tool/tool_*`, `blog/blog_list` | `tools-3d` | `home_top` | 350 | `#ffdf00` | 0.08 |
| `error/not_found` | `not-found` | `footer_top` | 200 | `#a7b3b5` | 0.04 |
| mobile | `hero-particles` + `du-scene-lite` | `hero` | 180 | `#5cc6d0` | 0.04 |

`kaz_scene`: **20 camadas** (960×540, grid 8) desenhando HUD, planeta, cartões
de fase e CTA — dados de edição/versionamento para a prévia do Admin, não
renderização no front. `parent` vazio (autossuficiente, evita o defeito BL).

## 3. Validação com as classes reais (PHP 8.4 WASM)

`evidencia/export-bn.txt` — `ThemePackage`, `ThemeSnapshot`, `ThemeSceneCss`,
`TokenRegistry` e `ConditionEngine` do HEAD `ad7643de…`:

| Verificação | Resultado |
|---|---|
| `validateConfig()` | **`[]`** |
| tokens modo escuro (AA) | OK |
| `ThemeSceneCss::compile()` | **20 camadas**, CSS 1629 bytes allowlistado |
| `ThemeSnapshot` nas 4 rotas | cenas corretas |
| `export()`/`encode()` | 88.864 bytes (< 512 KiB) |
| `decode()` | **`ok=true`, `errors=[]`** |
| checksum canonizado | OK |
| **Total** | **14 OK / 0 FALHAS** |

## 4. Prévia visual real (Chromium 141)

`evidencia/layout-bn.json` + 5 capturas. Correção aplicada: o HUD (orbes +
nav de idiomas) estourava 33px em 390px; compactado e revalidado em
**390 / 360 / 320px** com 0 overflow.

| Captura | Cena | Altura | Fases | Cards | Overflow | `<script>` | Pageerrors |
|---|---|---|---|---|---|---|---|
| Home 1440 | `hero-particles` | 320px | 10 | 37 | 0 | **0** | 0 |
| Home 390 | `hero-particles` | 210px | 10 | 37 | 0 | **0** | 0 |
| Catálogo | `portfolio-3d` | 230px | 1 | 6 | 0 | **0** | 0 |
| Ferramentas | `tools-3d` | 230px | 1 | 3 | 0 | **0** | 0 |
| 404 | `not-found` | 170px | 0 | 0 | 0 | **0** | 0 |

`bodyBg = rgb(18,21,22)` e botão `rgb(92,198,208)` medidos — idênticos à
amostra. **`BN-Amostra-vs-Tema.png`** traz a comparação lado a lado do hero
com a captura real de `davidalmeida.xyz/threejs/?lang=pt`.

> As capturas mostram o **fallback declarativo** (pontilhado + planeta em
> CSS). A animação WebGL/scroll-driven da amostra é feita em runtime pelo
> `scene-runner.js` do Catálogo; a prévia prova layout, HUD, slots,
> parâmetros e conteúdo, não o movimento da espaçonave.

## 5. O que NÃO foi mexido

Nenhuma escrita do agente no GitHub; nenhum arquivo `dev/`, contrato,
`TEMPLATE_KEYS`, `CONFIG_KEYS`, `CHECKSUMS`, manifesto, schema, `ALTER`,
migration, runtime, MySQL ou deploy. Nenhum `.js`/`.twig` solto, `|raw`,
`<script>`, handler `on*=`, `innerHTML`, `eval`, `fetch`, `@import`, `url()`,
`<link>`, fonte/CDN, upload em disco ou contrato novo. Nenhuma decisão
D01–D10; Recorte A intacto.

## 6. Limites

`release_approved: false` · F9-27 `BLOCKED_NOT_RUN` · F9-18
`release_ready: false` · 10 `PENDING_DECISION`. CI verde, PHP WASM,
Chromium e guardas **não significam release aprovada**. **Aceite visual da
BN**, health no Admin real e aplicação dos patches BL **ainda pendentes**.

## 7. Como usar

Ver `GUIA-BN.md`: importar no **tema ativo** e **Ativar** (sem Ativar,
`save()` grava só `draft_config` — causa raiz da BL). Os patches A/B da BL
continuam **propostas não aplicadas**: o HEAD `ad7643de…` tem **`dev/`
intacto**.
