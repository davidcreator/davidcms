# KAZTheme-Editor — Workspace inspirado no Penpot (Frente Y)

**Data:** 4 de novembro de 2026 (America/Sao_Paulo)  
**Base:** `main` @ `d68ce60ff0a42234f551ace4e919c38cb06b6e67` (frente X aplicada **7/7** no raw).  
**Estado:** pacote instalável entregue para aplicação. **Não foi aplicado no GitHub por nós**; não é release aprovada.

## 1. Motivo

O retorno do usuário foi direto: *"minha interface não está nem 1% semelhante
com o Penpot, concorrente open source do Figma; podemos focar nessa fase agora
para deixar a interface semelhante"*. As frentes U, V e X tinham entregue um
editor **funcional**, mas com a aparência de um `card` Bootstrap comum: toolbar
de botões de texto, três colunas claras e canvas SVG sem moldura. A frente Y
substitui essa casca por um **workspace de design escuro**, reconhecível como
um editor do tipo Figma/Penpot, sem alterar nenhum contrato de dados.

## 2. Referência usada

A interface do Penpot é documentada no *Interface tour* do Help Center
(<https://help.penpot.app/user-guide/first-steps/the-interface/>), que descreve
o Workspace com 20 áreas numeradas: viewport infinito, toolbar, main menu,
pages, layers, rulers, color palette, typography palette, design properties,
prototype/inspect/view modes, share, history, comments, zoom, users, assets,
design tokens e file status. A documentação de terceiros confirma a composição:
**controles à direita, arquivos/ativos/camadas à esquerda, canvas ao centro e
dark mode por padrão**
(<https://www.freecodecamp.org/news/how-to-recreate-figma-components-in-penpot/>).

Não foi copiado nenhum código, asset, marca, logotipo ou imagem do Penpot. O
mapeamento abaixo é de **organização espacial e vocabulário**, implementado com
HTML/CSS/SVG próprios e o JavaScript já existente do KAZTheme-Editor.

## 3. Mapeamento Penpot → KAZTheme-Editor

| # | Penpot (Workspace) | Frente Y | Natureza |
| --- | --- | --- | --- |
| 1 | Viewport infinito | `.kaz-viewport` com fundo pontilhado, pan pela ferramenta Mão e `overflow:auto` | funcional |
| 2 | Toolbar | 15 ícones SVG na navbar: mover, mão, 5 tipos de camada, duplicar, excluir, ordem, visibilidade, bloqueio | funcional |
| 3 | Main menu / nome do arquivo | marca + `Tema · KAZTheme-Editor` + `Página 1` | informativo |
| 4 | Pages | aba **Páginas** (1 página, 1 board) | informativo |
| 5 | Layers | aba **Camadas**: árvore real, seleção, contagem, oculto/bloqueado | funcional |
| 6 | Rulers | réguas horizontal e vertical com marcas a cada 80 px | funcional |
| 7 | Color palette | 6 swatches na aba **Ativos** | informativo |
| 8 | Typography palette | amostra `Aa` na aba **Ativos** | informativo |
| 9 | Design properties | aba **Design**: Layout, Aparência, Tipografia, Elemento, Tema | funcional |
| 10 | Prototype mode | aba **Protótipo** com explicação somente-leitura | informativo |
| 11 | Inspect mode | aba **Inspecionar**: CSS + JSON da camada, somente-leitura | funcional |
| 12–15 | View / Share / Comments / Users | avatar `1` (editor local); sem colaboração | informativo |
| 14 | History | aba **Histórico** com os estados locais do desfazer | funcional |
| 16 | Zoom | `−`, `%`, `+`, `Ajustar` + `Ctrl` + roda do mouse | funcional |
| 18 | Assets | aba **Ativos** com os 6 blocos da frente X e miniaturas | funcional |
| 19 | Design tokens | aba **Tokens** com `primary`/`surface` lidos de `data-kaz-*` | informativo |
| 20 | File status | selo `Salvo` / `Alterações não salvas` na navbar | funcional |

**Zoom** de 25% a 200% em passos de 25%, aplicado por `transform` no board.
**Pan** arrasta o canvas apenas com a ferramenta Mão; com Mover, o drag
continua movendo/redimensionando camadas. O `scale()` do drag usa
`getBoundingClientRect()`, que já inclui o `transform`, então as coordenadas
permanecem corretas em qualquer zoom. Réguas, histórico, Inspect, swatches,
tokens, coordenadas e contagem são pintados por `paintChrome()` a cada
`render()` — sempre com `createElement`, `setAttribute`, `textContent` e
`style`, nunca com `innerHTML`.

## 4. Identidade visual

Tema escuro por padrão com variáveis CSS escopadas em `.kaz-card`
(`--kaz-bg:#18181b`, `--kaz-panel:#1f1f23`, `--kaz-accent:#31efb8`,
`--kaz-blue:#7b61ff`). Layout de 3 colunas: `236px · 1fr · 264px`, altura de
548 px, navbar de 46 px, barra de status de 32 px, réguas de 22 px, board
branco 960×540 com rótulo e sombra (o "artboard"). Ícones são `<svg>` literais
no Twig, com `stroke: currentColor`. Há `@media` para 1280 px e 900 px
(empilhamento) e regras `forced-colors: active` para alto contraste.

## 5. O que NÃO mudou

- Contrato `kaztheme-scene/v1` (1, canvas 960×540/grid 8, 5 `kind`, 5 `role`,
  48 camadas, 64 KiB, 14 chaves por camada, validação fail-closed cliente e
  servidor).
- `CONFIG_KEYS` (14 chaves com `kaz_scene`), `MAP_KEYS`, `validateScene()`,
  `normalizeScene()`, `validateConfig()`, `decode()`, `export()`,
  `resolveChain()`, `ThemeSnapshot`, `ConditionEngine`, `startup/*`, runtime do
  Catalog e as 418 chaves de tradução × 3 idiomas.
- Comportamento das frentes U/V/W/X: bindings aditivos (nunca no boot),
  `syncField()` + `submit`, `baseline` e `dirty()`, 6 blocos allowlistados,
  `MAX_LAYERS`, descarte de linhas vazias do mapa de sites e diagnóstico
  `(shop)`.
- Os 14 manifestos `CHECKSUMS*.txt`, os 11 artefatos congelados por hash,
  `schema.sql`, `F9-27-Base-Hashes.json`, `tools/decision-matrix.json`,
  `release_approved:false`, os 10 `PENDING_DECISION` e os 77 paths do
  BDREditor/F9-103.
- **Zero** migration, schema, coluna, `custom_js`, `|raw` novo, chave de
  tradução nova, `localStorage`, WebGL, Three.js real, upload, CDN, fontes
  remotas, biblioteca de ícones, `<script>` inline, atributo `on*`,
  `innerHTML`, `eval`, `fetch`, escrita remota, deploy ou release aprovada.

**Estado de interface é local e descartável:** zoom, pan, ferramenta ativa e
abas **não** entram no `kaz_scene_json`, **não** são persistidos e **não**
mudam o `baseline` (inserir um bloco suja o estado; mudar de aba ou dar zoom,
não). A cena continua **sem renderização no site público**; child-themes
continuam substituindo-a integralmente.

## 6. Limites explicitamente declarados

Não há protótipo navegável, View mode, comentários, colaboração multiusuário,
componentes reutilizáveis, shared libraries, flex/grid layout, paths/curvas,
elipse, imagem, upload, design tokens graváveis ou fórmulas. As abas
**Protótipo** e **Páginas** dizem isso em texto. O board é único e fixo no
tamanho do canvas da cena (não há múltiplos boards). O viewport é "infinito"
no sentido de pan/área de trabalho, não de conteúdo ilimitado: o contrato
limita a 48 camadas e coordenadas `-2000..2000`.

## 7. Instalação

Extrair os 8 arquivos completos sobre a raiz do repositório (7 substituem,
1 é novo). Não há patches nem manifestos a atualizar — os 8 paths estão
**LIVRES** nos 14 `CHECKSUMS*.txt` (90 paths cobertos). Depois:
`bash tools/verify-checksums.sh && PYTHON_CMD=python3 bash tools/repo-health.sh`.
A navbar passa a exibir `-kaz-theme-editor-y` como cache key dos assets.

## 8. Limitações do ambiente

PHP 8.5 WASM, Twig 3.14 e jsdom 29.1.1 com mocks **não são um servidor**. Não
houve PHP nativo, Composer, PHPStan, MySQL, sessão Admin autenticada,
navegador real, E2E, staging, deploy ou CI remoto verificado. **Nenhuma escrita
no GitHub foi feita por nós.** F9-27 segue `BLOCKED_NOT_RUN` com
`release_approved:false`; BDREditor/F9-103 pausado; P2/F9-18 não reabertos; a
limitação de hreflang da frente N permanece. **CI verde/health ≠ release aprovada.**
