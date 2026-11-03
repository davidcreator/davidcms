# Editor de cena com miniatura e presets (Frente D)

Complementa `docs/GUIA-TEMAS-CONDICOES-THREEJS.md`,
`docs/GUIA-PREVIA-RASCUNHO-PUBLICADO.md` e a ADR 0072.

## Recorte deliberado

A ADR 0072 fixa que as **cenas nativas** continuam allowlistadas e versionadas
em código, sem scene builder genérico nem upload de GLB. As *thumbs
declarativas* do BDREditor (`threejs-thumb.js`, `custom_js` em iframe opaco)
são **outro subsistema** (F9-103, pausado) e não foram tocadas. A frente D
trabalha apenas nos parâmetros que `ConditionEngine::normalizeThree()` já
aceita: `enabled`, `scene`, `slot`, `count`, `color`, `speed`. Nenhuma chave
nova entra no `config` do tema — portanto `ThemeSnapshot`, o `startup` do
Catalog, a prévia da frente C e o runtime Three.js permanecem intactos.

## O que faltava

O card Three.js do formulário de tema era só seis campos soltos: não havia
referência visual do que cada cena produz, nem combinações prontas, nem
feedback ao alterar cor/partículas/velocidade.

## Entregue

- **`ScenePresetRegistry`** (`dev/system/library/three/scene_preset.php`),
  registrado como `three_scene_preset`: **6 presets allowlistados** — Aurora
  suave, Nebulosa densa, Portfólio geométrico, Oficina 3D, 404 noturno e Sem
  cena. `find()`, `apply()`, `validate()`, `normalize()`, `matchingPreset()`,
  `svg()` e `dataUri()`. Todos os valores passam pelas mesmas allowlists e
  limites do motor (cenas, slots, 50–800, hex 6, 0,01–0,20).
- **Miniatura SVG determinística** por cena: partículas, losangos
  isométricos, engrenagem esquemática, "404" e o estado desligado. Sem
  `<script>`, `on*`, `url(`, `<style>`, `foreignObject`, `href`/`xlink`,
  `rand()` ou texto livre do usuário — só hex validado e números formatados.
  Servida como **imagem**, nunca como HTML inline (sem `|raw` novo).
- **Editor no formulário**: miniatura ao vivo, `<select name="scene_preset">`
  com "Personalizado (manter campos)" e 6 cartões com miniatura, nome,
  descrição e valores. O JS `theme-scene-editor.js` aplica um preset em um
  clique (preenchendo os seis campos), atualiza a miniatura a cada alteração
  (debounce 180 ms) e volta para *Personalizado* quando algum campo é editado
  manualmente. **Sem JS o `<select>` continua funcionando no POST.** O select
  **sempre abre em "Personalizado"**: se um preset viesse pré-selecionado, um
  save sem JS sobrescreveria edições manuais feitas nos campos `three_*`. O
  badge de preset ativo é calculado pelo JS comparando os valores correntes.
- **Endpoint `design/theme.sceneThumb`**: GET idempotente, ACL `access`,
  `no-store`, `nosniff`, CSP `default-src 'none'`, `Content-Disposition:
  inline`, `image/svg+xml`, zero escrita. Não exige CSRF porque não há mutação
  (mesmo padrão dos assets públicos allowlistados). Todos os parâmetros são
  normalizados fail-closed antes de gerar o SVG.
- **Prévia da frente C**: cada painel (publicado/rascunho) agora mostra também
  a miniatura da cena, via data URI — a CSP existente já permite `img-src data:`.
- 10 chaves de UI + 6 nomes + 6 descrições + 1 rótulo de view ×3 idiomas;
  estilos no CSS do builder já existente.

## Segurança

Nenhum preset cria cena, caminho, código executável, GLB, JavaScript de cena
ou campo de script. SVG é imagem, não documento ativo; mesmo aberto direto, a
CSP e a ausência de scripts bloqueiam execução. `buildDraftConfig()` valida o
id do preset antes de usá-lo (`error_scene_preset`) e continua aplicando
`normalizeThree()`, portanto a prévia e o save seguem as mesmas regras.

## Arquivos

`scene_preset.php` e `theme-scene-editor.js` (novos), `framework.php`,
`admin/controller/design/theme.php`, `design/theme_form.twig`,
`theme-condition-builder.css`, `theme_compare_view.php`, 3 idiomas e este guia.
Nenhum manifesto `CHECKSUMS*.txt`, contrato congelado, `schema.sql`, workflow,
checker, `ConditionEngine`, `TokenRegistry`, `SceneConfig` ou arquivo do runtime
Three.js/BDREditor foi modificado. Os 14 manifestos não cobrem estes alvos e
não foram reemitidos.

## Limitações

PHP 8.5 WASM + Twig 3.14 + jsdom, com mocks. **Não** houve CMS completo, MySQL
real, sessão Admin autenticada, browser com rede, WebGL, E2E, migration,
staging, deploy ou escrita no GitHub. A miniatura é uma representação
esquemática estática, não um frame do WebGL; a conferência visual da cena real é
do usuário. **CI verde e health verde ≠ release aprovada.**
