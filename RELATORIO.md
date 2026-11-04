# Frente U — KAZTheme-Editor (fundação visual)

**Data:** 4 de novembro de 2026 (America/Sao_Paulo)  
**Base:** `main` @ `018cafb3846445d7e2e0f541554d662ce79be67c` (pai `f993fe3…`, frente T aplicada **11/11** no raw).  
**Estado:** pacote instalável entregue para aplicação. **Não foi aplicado no GitHub por nós**; não é release aprovada.

## 1. O que foi entregue

Um **editor de temas com nome próprio**, dentro do gerenciador de temas do Admin, com interface inspirada em Figma/Penpot: canvas SVG com grade e snap, árvore de camadas, inspector, seleção, arrastar, redimensionar, duplicar, excluir, reordenar, ocultar, bloquear, desfazer/refazer e atalhos de teclado. Estrutura de fácil edição, espelhando a separação do BDREditor (JS de modelo/UI + CSS escopado + ponto de montagem + documento de referência), mas com **arquivos, identidade e contrato exclusivos**.

| Arquivo | Papel |
|---|---|
| `dev/admin/view/js/kaz-theme-editor.js` | Modelo `kaztheme-scene/v1`, validação fail-closed, SVG, camadas, inspector, drag/resize, histórico, atalhos, export/import, conexão com o formulário |
| `dev/admin/view/css/kaz-theme-editor.css` | Layout de 3 colunas, responsivo, `forced-colors`, foco visível |
| `dev/admin/view/template/design/kaz_theme_editor.twig` | Ponto de montagem incluído **dentro** do `<form>` principal |
| `dev/admin/controller/design/theme.php` | `kazScene()` puro derivado do config + publicação de `kaz_scene_json` + 2 assets com cache key `-kaz-theme-editor-u` |
| `dev/admin/view/template/design/theme_form.twig` | Um `{% include %}` antes de fechar o formulário |
| `docs/KAZTheme-Editor.md` | Referência: identidade, contrato, segurança, limites |
| `docs/KAZTheme-Editor-Guia-Rapido.md` | Uso e aplicação |

**7 arquivos completos, sem patches, sem manifestos a atualizar.** O ZIP tem **9 entradas** (7 do pacote + este `RELATORIO.md` e o `INDICE.md` da frente, gerados como entradas finais sem duplicar nomes).

## 2. Conexão com o tema existente

`ThemePackage::CONFIG_KEYS` continua com as **13 chaves** originais, `validateConfig()` intacto e `TEMPLATE_KEYS` com 25 entradas. O editor **não cria chave nova**: camadas com papel `primary`/`surface` escrevem em `name="primary"`/`name="surface"`; `section` marca `sections[]` e `sections_order`; `component` marca `components[<id>][enabled]`. A validação do servidor continua soberana.

**Aplicação não destrutiva (corrigida durante a validação):** abrir a tela não executa nenhum binding; seções e componentes são marcados, nunca desmarcados, e `sections_order` preserva os IDs já salvos. Assim uma visita ao editor não apaga a configuração do tema. Também foi corrigido o bloqueio que impedia desbloquear uma camada.

**Limitação honesta:** a cena (posições, texto, tipos) é um artefato de trabalho **browser-local**, como os *style presets* do BDREditor. Ela sobrevive por **Exportar cena / Importar cena** em JSON no textarea, offline. Gravá-la como chave do config exigiria evoluir `ThemePackage`, `validateConfig()`, `export()`, `decode()` e manifestos em frente posterior, com decisão explícita.

## 3. Segurança

Apenas `createElement`, `createElementNS`, `setAttribute`, `textContent`, `appendChild`, `removeChild`, `addEventListener`. Zero `innerHTML`, `outerHTML`, `document.write`, `eval`, `Function`, `fetch`, `XMLHttpRequest`, jQuery AJAX, `srcdoc`, tag `script` inline, atributo `on*`, `localStorage`, `|raw` novo, `custom_js`, WebGL, Three.js, upload, migration, schema, coluna, chave de tradução nova ou escrita remota. Texto do usuário vai para `textContent`; o JSON da cena entra por `|e('html_attr')`. Import malformado ou fora do contrato é **rejeitado integralmente** sem alterar o estado. Nenhum dos 14 manifestos cobre os 7 arquivos (7/7 livres).

## 4. Validação executada (números reais)

A/B **real** com bootstraps PHP separados (`FU_MODE=head|pkg`), bibliotecas reais do HEAD U em ambos, controller/template do HEAD vs do pacote. 12 cenários de config por lado; formulário Twig 3.14 renderizado nos dois; editor executado de verdade no jsdom 29.1.1.

| Verificação | Resultado |
|---|---|
| H1 contratos A–T + `kazScene()` puro + `CONFIG_KEYS` + BDREditor | **209/0** |
| H2 pipeline `edit()` no pacote, 12 cenários, valores literais | **446/0** |
| H3 A/B HEAD × pacote, 12 cenários, congelados e novos | **686/0** |
| H4a Autoloader real carregando o controller do pacote | **16/0** |
| H4b regressão A–T, manifestos, 418×3, gates, BDREditor | **223/0** |
| H5 jsdom: DOM real, editor executado, 12 cenários, docs/CSS | **490/0** |
| `php -l` (PHP 8.5.12 WASM) | **1/0** |
| `node --check` (40 JS do HEAD U + 1 novo) | **41/0** |
| **Total de asserções** | **2070/0** (+42 lint/sintaxe = **2112/0**) |

Réplica A/B: **1486 paths**, **2 alterados**, **5 novos**, **0 idênticos no pacote**, **1484 congelados idênticos**; guards **4/4 verdes e byte-idênticos** (`verify-checksums` 14 manifestos/142 arquivos, `repo-health`, `verify-ci-workflow`, `verify-contract-freeze`); 14 manifestos idênticos A/B; 418 chaves ×3; 25 `TEMPLATE_KEYS`; **37 arquivos de A–T intactos**; T **11/11** no raw; BDREditor **77 paths** nenhum no pacote e todos congelados; `schema.sql` presente; build idempotente **7/7**; CRC **9/9**; `sha256sum -c` **45/45**.

A/B do formulário: HEAD sem `#kaz-theme-editor`, sem cena, sem assets; pacote com editor, `kaztheme-scene/v1` de 5 camadas derivadas do config, 2 assets, **2 SELECTs inalterados** em todos os 12 cenários, `primary`/`surface`/`sites_published_json` idênticos, nenhuma tag `script`, nenhum `|raw` novo (2 no HEAD, 2 no pacote), `scene_attr_escaped=true`.

## 5. Limitações e estado do repositório

- **Não disponível neste ambiente:** PHP nativo, Composer, PHPStan, rsync, navegador real, MySQL, sessão autenticada, migration, staging, deploy, E2E e CI remoto verificado. PHP WASM 8.5.12, Twig 3.14 e jsdom com mocks **não são um servidor**. Nenhuma escrita no GitHub foi feita por nós; CI verde/health coerente **não é release aprovada**.
- F9-27 continua `BLOCKED_NOT_RUN` com `release_approved:false`; `docs/F9-27-Base-Hashes.json`, `docs/F9-18-Risk-Register.json`, `tools/decision-matrix.json` e `schema.sql` intocados. Nenhum `PENDING_DECISION`, `STATIC_ONLY`, `NOT_RUN`, `SKIP` ou `BLOCKED_NOT_RUN` foi convertido em `PASS`; nenhum guard foi relaxado.
- BDREditor/F9-103 **pausado e não tocado**; P2/F9-18 não reaberto; a limitação de hreflang/variantes com query da frente **N permanece**. As frentes A–T não foram reentregues.
- O relatório/índice da raiz do HEAD ainda são os da frente T; as **entradas finais** de `RELATORIO.md` e `INDICE.md` neste ZIP são as desta frente (padrão A–T).

## Instalação

Extrair os 7 arquivos completos sobre a raiz do repositório, preservando a estrutura de diretórios (5 são novos). `RELATORIO.md` e `INDICE.md` são informativos e podem substituir os da raiz. Não há patches nem manifestos a atualizar. Depois: `bash tools/verify-checksums.sh && bash tools/repo-health.sh`. Sem aplicação, o comportamento do Admin permanece idêntico ao HEAD U.
