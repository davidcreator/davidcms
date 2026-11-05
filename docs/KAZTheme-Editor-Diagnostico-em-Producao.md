# KAZTheme-Editor — Diagnóstico em Produção (Frente AE)

Este documento é para **você**, no seu servidor. Responde à pergunta que as
frentes A–AD não conseguiam responder: *"o editor está realmente vivo aqui?"*.

## Por que isto existe

Você relatou: **"nenhuma ferramenta responde e não existe erro no console do
DevTools"**. A causa foi um `return` silencioso no boot (Frente AD), já
corrigido. Mas, se o problema voltar por cache de Twig, CDN, CSP ou asset
404, o sintoma seria outra vez o silêncio. A Frente AE faz duas coisas:

- **Fail-loud:** se o JS não inicializar, aparece um aviso vermelho na própria
  página, dizendo o que verificar. Console limpo deixa de ser sinônimo de
  "está tudo bem".
- **Health check:** um snippet de leitura que você executa no console e que
  devolve um JSON com o veredito.

## Passo a passo

1. Aplique o pacote AE (2 arquivos de código + ferramentas + docs), extraia na
   raiz e dê **Ctrl+F5** no Admin — o cache do asset mudou para
   `-kaz-theme-editor-ae`.
2. Abra a página de edição de tema (`Admin → Design → Temas → editar`).
3. Abra o DevTools (F12) → aba **Console**.
4. Abra `tools/kaz-theme-editor-health.js`, copie todo o conteúdo, cole no
   console e pressione Enter. O relatório é impresso na hora.
5. Para me enviar: digite `copy(__kazHealth())` e cole o JSON na resposta.

## Como ler o veredito

| `veredito` | Significado | O que checar |
| --- | --- | --- |
| `OK` | boot, API, 9 módulos, CSS e CSRF presentes | nada — pode seguir para a próxima frente |
| `FALHA_ASSET_OU_DEFER` | JS ausente ou sem `defer` | `header.twig` ativo contém o loop `scripts_deferred`? O `theme.php` usa `addScriptDeferred`? Há Twig compilado em cache? |
| `FALHA_BOOT` | asset carregou, mas `__kaz` não existe | Network: `kaz-theme-editor.js?v=…-kaz-theme-editor-ae` é **200** e não é HTML de erro? Há CDN servindo versão antiga? CSP bloqueia? |
| `OK_COM_RESSALVAS` | funciona, mas há ressalvas em `problemas[]` | ler a lista (ex.: CSRF ausente, overflow horizontal) |

O campo `problemas[]` usa prefixos estáveis: `SEM_ROOT`, `SEM_ASSET_JS`,
`SEM_ASSET_CSS`, `ASSET_ANTIGO`, `SEM_DEFER`, `SEM_BOOT`, `SEM_API`,
`AVISO_VISIVEL`, `SEM_CSRF`, `SEM_FORM`, `LAYOUT_CENTRO_ESTREITO`,
`OVERFLOW_HORIZONTAL`.

## O que o JSON contém

- `assets.js[].version` — deve terminar em `-kaz-theme-editor-ae`.
- `boot.ready` — deve ser `"1"`; `boot.api` deve ser `"object"`.
- `boot.layers` — número de camadas da cena (≥6 no tema de exemplo).
- `modulos` — os 9 módulos, cada um com `root`, `asset`, `version` e `defer`.
- `layout` — medidas reais de `.kaz-shell`, `.kaz-center`, stage, viewport,
  `scrollsAninhados` e `overflowHorizontal`.
- `publicacao` — presença de CSRF, `#kaz-theme-form` e do bloco Publicar.

## Segurança

O snippet é **somente leitura**: não faz requisição, não escreve no DOM, não
usa `innerHTML`, `eval`, `localStorage` nem `document.cookie`. Não faz parte
do runtime do CMS — se não for executado, nada muda. O fail-loud do editor,
por sua vez, usa apenas `createElement` + `textContent`.

## E se o veredito for `OK`?

Aí sim sabemos que a AD funcionou em produção, e a próxima frente pode voltar
a avançar no objetivo declarado: **fazer o editor produzir config, CSS e
templates reais que o catálogo renderiza**, com prova ponta a ponta no seu
servidor — em vez de empilhar funcionalidade sobre um editor que não inicializa.


## Frente AF — prévia e publicado mostram a mesma cena (2026-11-05)

**Problema medido no `main` `5932b910aca5`.** `ThemeSnapshot::resolve()` já
compilava `kaz_scene` em `scene_view`/`scene_css`, e o catálogo publicado
(`startup/theme.php` → `header.php` → `home.php` → `home.twig` →
`partial/theme-scene-block.twig`) renderizava `.rms-scene*`. Mas a tela de
**Comparar** e o **Pré-visualizar** não entregavam essa cena ao Twig:
`Twig::renderSandboxPreview()` fixava `theme_scene=['enabled'=>false]` e não
definia `theme_scene_view`; `ThemeCompareView::panel()` não carregava nenhuma
regra `.rms-scene*`. Consequência: o usuário desenhava a composição no editor,
salvava, pré-visualizava e **não via nada** — só depois de Publicar a cena
aparecia. Console limpo, sem erro, sem efeito visível.

**Correção (4 arquivos, sem migration, sem rota nova, sem `|raw` novo):**

1. `dev/system/library/template/twig.php` — `renderSandboxPreview()` ganha 3º
   argumento opcional `$sceneView` e publica `theme_scene_view` no contexto
   sandbox. Novo `previewSceneView()` normaliza a cena **fail-closed**: canvas
   320–3840 × 240–2160, até 48 camadas, `kind`/`role` por allowlist, `fill`
   `#rrggbb`, percentuais 0–100, `radius` 0–64, `font` 8–72, texto ≤80 com
   caracteres de controle removidos. Sem HTML, sem caminho, sem executável.
   Assinatura compatível: chamadores legados sem o 3º argumento continuam
   válidos e recebem cena desabilitada.
2. `dev/admin/controller/design/theme.php` — `compare()` passa o `scene_view`
   **de cada estado** (`$pubState` / `$draftState`) a `renderPreviewFragment()`;
   `preview()` compila `kaz_scene` do rascunho com `ThemeSceneCss` e injeta o
   `<style>` estático da cena. Cache de assets `-ae` → `-af`.
3. `dev/system/library/theme/theme_scene_css.php` — novo `previewCss()` devolve
   as regras `.rms-scene*` **literais/estáticas** (sem `url()`, `@import`,
   `expression`, tags ou dado do usuário), espelhando `theme.css` do catálogo.
   Inclui `container-type:inline-size` e `@container`: **medido no Chromium**,
   sem elas o texto de 32px ficava cortado num painel de 666px. O limite sobe
   de 620px para 1200px apenas na prévia, porque o catálogo chega a 1200px e
   cada painel de comparação é mais estreito.
4. `dev/system/library/theme/theme_compare_view.php` — um único `<style>` com
   `previewCss()` no `<head>`; o `scene_css` allowlistado (`--rms-scene-*`) vai
   para o atributo `style` de cada painel; fallback `sceneBlock()` renderiza a
   cena quando um template customizado não inclui o partial. **Sem** `<script>`,
   `srcdoc`, `iframe`, `form`, `|raw`; CSP `default-src 'none'; img-src data:;
   style-src 'unsafe-inline'` intacta. `sceneBlock()` não duplica quando o
   fragmento já traz `rms-scene`.

**Prova ponta a ponta** (`tools/kaz-theme-preview-parity.php`, 43 asserções):
`ThemeSceneCss::compile()` → `previewSceneView()` → `home.twig` real em Twig
sandbox → `ThemeCompareView::render()` → extração das camadas → **as 6 camadas
da prévia são idênticas, em estilo e texto, às 6 do HTML publicado**. O mesmo
template renderizado **sem** `theme_scene_view` produz zero `.rms-scene` (a
lacuna da AE reproduzida). `tools/verify-kaz-preview.cjs` repete a comparação
em Chromium real (desktop 1440×900 e mobile 390×844), 22 asserções, compara
também a **cor computada** e prova que **nenhum texto foi cortado**; salva
screenshots. `ThemeSnapshot::diff()` acusa `kaz_scene.layers.N.text` entre
publicado e rascunho. A única diferença visual restante é a tipografia
responsiva (16px no painel vs 32px no catálogo), deliberada e prevista no
próprio `theme.css`.

**Escopo:** nenhuma coluna, tabela, migration ou `schema.sql` foi alterado —
`kaz_scene` continua vivendo em `draft_config`/`published_config`. Nenhum
manifesto, hash congelado, tradução ou `PENDING_DECISION` foi tocado.
Aprovação de release permanece `false`; esta frente **não** foi avaliada em
sessão Admin autenticada nem no servidor de produção.
