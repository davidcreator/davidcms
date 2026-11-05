# ReamurCMS — Frente AF · Prévia e Publicado mostram a mesma cena

> **A lacuna era de dados, não de CSS e não de banco.** `ThemeSnapshot::resolve()`
> já compilava a cena; o catálogo já a desenhava. Faltava entregá-la ao
> **sandbox da prévia** e dar regras `.rms-scene*` aos painéis de comparação.
> **Sem migration, sem coluna, sem rota nova, sem `|raw` novo.**

| | |
| --- | --- |
| Base | `main` @ `5932b910aca5…` (AE aplicada), `pushed_at=2026-11-04T21:18:36Z` |
| Árvore real | 3.203 entradas / 2.620 blobs / `truncated:false` |
| **Aderência** | **16/16 arquivos da AE byte a byte idênticos ao `main`**, 0 divergentes |
| Arquivos | **18** completos (15 substituições + 3 novos) + 3 evidências PNG + `RELATORIO.md` + `INDICE.md` |
| Réplica | 2210 → 2213 arquivos (15 alterados + 3 novos + 2195 intactos) |
| **Paridade** | PROBE 43/0 · Chromium 22/0 — as 6 camadas da prévia são **idênticas em estilo, texto e cor computada** às 6 do publicado, **sem nenhum texto cortado** |
| Harnesses | paridade 43 + 22 · health 26 · boot 22 · Chromium 251 + 251 · layout 90 · DOM 226 · PROBE publicação 93 · alcance 5 = **1029/0** |
| Validação final | 171 OK / 0 FALHAS |
| Guards | 4/4 verdes e byte-idênticos |
| Sintaxe | 389 PHP 0 erros · 614 JS/CJS 0 falhas |
| Build | idempotente 5/5 · CRC 23/23 · `sha256sum -c` 56/56 |

---

## 1. O problema medido

No `main` `5932b910aca5`, `dev/system/library/template/twig.php` montava o
contexto da prévia assim:

```php
'theme_scene'=>['enabled'=>false, ...],   // e nenhum theme_scene_view
```

`common/home.twig` inclui `partial/theme-scene-block.twig`, cuja primeira
linha é `{% if theme_scene_view.enabled|default(false) %}`. Sem a chave, o
`default` caía em falso: **zero camadas na prévia**. Ao mesmo tempo,
`ThemeCompareView::panel()` não carregava nenhuma regra `.rms-scene*` —
as camadas, mesmo que chegassem, ficariam empilhadas sem
`position:absolute`. O usuário desenhava, salvava, pré-visualizava e não via
nada; só depois de **Publicar** a cena aparecia. Sem erro e sem console:
perda silenciosa entre dois consumidores do mesmo `draft_config`.

`compare()` já tinha `$pubState['scene_view']` e `$draftState['scene_view']`
em mãos, mas só os usava para tokens e para a miniatura Three.js.

## 2. A correção (4 arquivos de código)

| Arquivo | Mudança |
| --- | --- |
| `dev/system/library/template/twig.php` | `renderSandboxPreview()` ganha 3º argumento **opcional** `$sceneView`; novo `previewSceneView()` normaliza **fail-closed** (canvas 320–3840 × 240–2160, ≤48 camadas, `kind`/`role` por allowlist, `fill` `#rrggbb`, % 0–100, `radius` 0–64, `font` 8–72, texto ≤80) e publica `theme_scene_view` |
| `dev/admin/controller/design/theme.php` | `compare()` passa o `scene_view` **de cada painel**; `preview()` compila `kaz_scene` do rascunho e injeta o `<style>` da cena; cache `-ae` → `-af` |
| `dev/system/library/theme/theme_scene_css.php` | `previewCss()`: regras `.rms-scene*` **literais**, sem `url()`, `@import`, `expression` ou dado do usuário |
| `dev/system/library/theme/theme_compare_view.php` | um `<style>` estático no `<head>`, `scene_css` allowlistado no `style` de cada painel, e `sceneBlock()` como fallback quando um template customizado não inclui o partial |

`sceneBlock()` **não duplica** a cena quando o fragmento já traz `rms-scene`.
CSP (`default-src 'none'; img-src data:; style-src 'unsafe-inline'`), sandbox
do iframe, `autoescape` e proibição de tag script / `srcdoc` / `iframe` /
`form` permanecem intactos. Chamadores legados sem o 3º argumento continuam
válidos e recebem cena desabilitada.

## 3. Prova ponta a ponta

`tools/kaz-theme-preview-parity.php` (**43/0**, somente leitura) executa a
cadeia real: `ThemePackage::normalizeScene()` → `ThemeSceneCss::compile()` →
`Twig::previewSceneView()` → `home.twig` em **Twig 3.14 sandbox** →
`ThemeCompareView::render()` → extração das camadas. Verifica também que o
mesmo template **sem** `theme_scene_view` produz zero `.rms-scene` (a lacuna
da AE reproduzida), que entrada inválida é fail-closed, e que
`ThemeSnapshot::diff()` acusa `kaz_scene.layers.N.text`.

`tools/verify-kaz-preview.cjs` (**22/0**) abre os dois HTMLs em Chromium
real, em 1440×900 e 390×844, compara `getComputedStyle`, os 6 atributos
`style`, os 6 textos e a **cor computada** da camada `primary`, e prova que
**nenhum texto foi cortado**; salva `evidencia/*.png`.

> **Defeito visual corrigido após o primeiro harness.** Ele passava 18/18
> comparando atributos, mas as screenshots mostravam texto cortado e cor
> divergente (azul no painel, roxo no catálogo). Medido no Chromium: o painel
> tem 666px, acima do `@container (max-width:620px)` do `theme.css`, então o
> `font-size:32px` absoluto não escalava; e o HTML de evidência não injetava
> `--theme-primary` como `header.php`. `previewCss()` passou a espelhar
> `container-type:inline-size` e a estender a `@container` a 1200px **apenas
> na prévia**; o PROBE passou a publicar as variáveis do tema. É a mesma
> armadilha da frente AA: **contagem de atributos não é renderização**.

A única diferença visual restante é a tipografia responsiva (16px no painel
vs 32px no catálogo), deliberada e prevista no próprio `theme.css`.

**Rota de aceitação no servidor:** desenhar a cena → Salvar → Comparar (a
cena aparece nos 2 painéis, com as diferenças entre publicado e rascunho) →
Publicar/ativar → abrir a home do catálogo (a **mesma** cena). Se a prévia
aparecer e a home não, o problema está em `startup/theme.php`; se nenhuma
aparecer, cole `copy(__kazHealth()` da frente AE para descartar boot.

## 4. O que não foi feito

- **Nenhuma migration, coluna, tabela ou `schema.sql` alterado**: `kaz_scene`
  continua em `draft_config`/`published_config`. O hash congelado do schema
  foi conferido contra o `main` real.
- Nenhum manifesto, hash congelado, tradução, `PENDING_DECISION` ou
  `release_approved` tocado. F9-27 segue `BLOCKED_NOT_RUN`; BDREditor/F9-103
  pausado; P2/F9-18 não reaberto.
- Nenhuma escrita nossa no GitHub: o pacote é para **você** instalar.
- **Harnesses, PROBE e Chromium headless não são o seu Admin autenticado.**
  Não há nova avaliação visual do usuário desde `image-7.png`, nem veredito
  `__kazHealth()` colado do servidor de produção. Esta frente **não** alega
  aprovação de release; CI verde ou health local não significam release
  aprovada.
