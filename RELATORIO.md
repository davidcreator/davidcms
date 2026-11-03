# Frente D — Editor de cena com miniatura e presets

Série A→J: **A** (builder de condições), **B** (design tokens) e **C** (prévia
rascunho × publicado) já estão aplicadas no `main` 6967028f9; **esta é a D**.
Próxima: **E — pacotes versionáveis / child-theme**. Um pacote instalável por
frente.

## Recorte deliberado

A ADR 0072 fixa que as **cenas nativas** continuam allowlistadas e versionadas
em código, sem scene builder genérico nem upload de GLB. As *thumbs
declarativas* do BDREditor (`threejs-thumb.js`, `custom_js` em iframe opaco)
são **outro subsistema** (F9-103, pausado) e não foram tocadas. D trabalha
apenas nos parâmetros que `ConditionEngine::normalizeThree()` já aceita:
`enabled`, `scene`, `slot`, `count`, `color`, `speed`. **Nenhuma chave nova
entra no `config` do tema** — `ThemeSnapshot`, o `startup` do Catalog, a prévia
da frente C e o runtime Three.js permanecem intactos.

## O que faltava

O card Three.js do formulário de tema era só seis campos soltos: sem referência
visual do que cada cena produz, sem combinações prontas e sem feedback ao
alterar cor/partículas/velocidade.

## Entregue (13 arquivos)

- **`ScenePresetRegistry`** novo: **6 presets allowlistados** — Aurora suave,
  Nebulosa densa, Portfólio geométrico, Oficina 3D, 404 noturno e Sem cena.
  `find()`, `apply()`, `validate()`, `normalize()`, `matchingPreset()`,
  `svg()`, `dataUri()`. Todos os valores passam pelas mesmas allowlists e
  limites do motor (cenas, slots, 50–800, hex 6, 0,01–0,20); cada preset foi
  testado contra `normalizeThree()`.
- **Miniatura SVG determinística** por cena (partículas, losangos isométricos,
  engrenagem esquemática, "404" e desligado). Sem `<script>`, `on*`, `url(`,
  `<style>`, `foreignObject`, `href`/`xlink`, `rand()` ou texto livre do
  usuário — só hex validado e números formatados. Servida como **imagem**,
  nunca como HTML inline (sem `|raw` novo).
- **Editor no formulário**: miniatura ao vivo, `<select name="scene_preset">`
  com "Personalizado (manter campos)" e 6 cartões com miniatura, nome,
  descrição e valores. `theme-scene-editor.js` aplica um preset em um clique,
  atualiza a miniatura a cada alteração (debounce 180 ms) e volta para
  *Personalizado* quando algum campo é editado manualmente. O select **sempre
  abre em Personalizado**: se viesse pré-selecionado, um save sem JS
  sobrescreveria edições manuais nos campos `three_*`. Sem JS o select continua
  funcionando no POST.
- **Endpoint `design/theme.sceneThumb`**: GET idempotente, ACL `access`,
  `no-store`, `nosniff`, CSP `default-src 'none'`, `Content-Disposition:
  inline`, `image/svg+xml`, zero escrita. Sem CSRF porque não há mutação
  (mesmo padrão dos assets públicos allowlistados). Parâmetros normalizados
  fail-closed antes de gerar o SVG; ACL negada devolve apenas o SVG "off".
- **Prévia da frente C**: cada painel (publicado/rascunho) mostra também a
  miniatura da cena via data URI — a CSP existente já permite `img-src data:`.
- **10 chaves de UI + 6 nomes + 6 descrições + 1 rótulo de view ×3 idiomas**;
  estilos no CSS do builder já existente (nenhuma ferramenta/checker novo).
  Guia `docs/GUIA-EDITOR-CENA-PRESETS.md`.

## Segurança

Nenhum preset cria cena, caminho, código executável, GLB, JavaScript de cena
ou campo de script. SVG é imagem, não documento ativo; mesmo aberto direto, a
CSP e a ausência de scripts bloqueiam execução. `buildDraftConfig()` valida o
id do preset antes de usá-lo (`error_scene_preset`) e continua aplicando
`normalizeThree()`, portanto prévia e save seguem as mesmas regras. O editor JS
não usa `innerHTML`, `eval`, `fetch` nem `document.write`.

## Validação executada

| Verificação | Resultado |
|---|---|
| `php -l` PHP 8.5 real nos 7 arquivos PHP | 7 OK / 0 FAIL |
| `node --check` (editor novo + builder A inalterado) | 2 OK |
| `ScenePresetRegistry`: presets, allowlists, fail-closed, SVG, injeção | 134 PASS / 0 FAIL |
| Controller Admin com mocks: presets, `sceneThumb`, `compare`, regressão A/B/C | 52 PASS / 0 FAIL |
| Render Twig 3.14 + `ThemeCompareView` com miniaturas | 35 PASS / 0 FAIL |
| **jsdom 29**: presets, miniatura ao vivo, edição manual, regressão A/B/C | 39 PASS / 0 FAIL |
| `verify-checksums` (A e B) | 14 manifestos / 142 arquivos / 0 divergentes |
| `repo-health` (A e B) | 6 PASS / 0 FAIL / 0 SKIP |
| `verify-ci-workflow` | 31 PASS / 0 FAIL |
| `verify-contract-freeze` | exit 0 |

**Total: 260 asserções, 0 falhas.** Os 14 `CHECKSUMS*.txt` permanecem
**idênticos** (nenhum arquivo da frente é coberto por eles) e não foram
reemitidos. Nenhum contrato congelado, `schema.sql`, `release_approved`,
workflow, checker, `ConditionEngine`, `TokenRegistry`, `SceneConfig` ou
arquivo do runtime Three.js/BDREditor foi modificado. Nenhuma escrita remota.
F9-27 segue `BLOCKED_NOT_RUN`; BDREditor pausado; P2 do F9-18 não reaberto.

## Limitações

PHP 8.5 via WASM + Twig 3.14 + jsdom, com mocks de DB/sessão/response. **Não**
houve CMS completo, MySQL real, sessão Admin autenticada, browser com rede,
WebGL, E2E, migration, staging, deploy ou escrita no GitHub. A miniatura é uma
representação esquemática estática, não um frame do WebGL; a conferência visual
da cena real é do usuário. **CI verde e health verde ≠ release aprovada.**

Aplicar: substituir pelos arquivos completos e rodar
`bash tools/verify-checksums.sh && bash tools/repo-health.sh`.
