# KAZTheme-Editor — Estado efetivo na prévia (Frente AG)

**Data:** 2026-11-05 · **Base:** `2494669eda6e` (AF aplicada, auditoria 18/18
byte a byte no `main`) · **Status:** entregue para instalação, **não** aprovado
em produção.

## A lacuna

A AF fechou a **cena**: prévia e publicado passaram a desenhar as mesmas
camadas. Mas o editor edita muito mais do que a cena. `ThemeSnapshot::resolve()`
calcula o estado efetivo do tema, e `common/header.php` publica no `style` do
body — nesta ordem:

```
token_css + component_css + schedule_css + media_css + site_css + scene_css
```

A prévia isolada (`preview()`) injetava **zero** dessas partes e usava
`theme.primary` fixo `#2563eb`; os painéis de comparação injetavam **duas**
(`token_css` e `scene_css`). Então alterar cor, fonte, densidade, radius,
componentes, agenda ou sites não mudava nada antes de Publicar. Sem erro, sem
console: o usuário editava e a prévia mentia.

A causa é estrutural, não um bug pontual: **dois consumidores diferentes do
mesmo estado**, cada um com sua própria lista de partes. É o mesmo padrão que
produziu a AF.

## O que a AG entrega

| Consumidor | Antes (AF) | Depois (AG) |
| --- | --- | --- |
| `preview()` isolado | sem tokens, `theme` fixo | estado via `ThemeSnapshot`, `theme.css` real, 6 partes no body |
| `compare()` — publicado | cena + `token_css` | estado completo de `$pubState` |
| `compare()` — rascunho | cena + `token_css` | estado completo de `$draftState` |
| `renderSandboxPreview()` | `theme.primary=#2563eb`, globais vazios | globais allowlistados do estado |
| `ThemeCompareView::panel()` | 2 de 6 partes | 6 de 6, na ordem do `header.php` |

`ThemePreviewState` é a **única** tradutora. `css()` é fail-closed por parte:
uma parte com `<`, `>`, `{`, `}`, `\`, `javascript:`, `@import`,
`expression(` ou `behavior:` é descartada, nunca relaxada. `globals()` só
emite chaves `theme`/`theme_*` planas com escalares ≤256 bytes — `header` e
`footer` do sandbox não podem ser sobrescritos. `catalogCss()` lê o `theme.css`
real com limite de 131.072 bytes e validação; sem ele as variáveis existiriam
mas nenhuma regra as consumiria.

**Sem** `|raw` novo, tag script, `srcdoc`, `iframe`, `form`, `eval`,
`innerHTML`, `localStorage`, rede, migration ou rota Admin nova. CSP e sandbox
do iframe permanecem como na AE/AF.

## Como verificar

1. `php tools/kaz-theme-preview-state.php` → PROBE somente leitura. Constrói
   dois estados A/B, compara `css()`, prova a paridade com a concatenação do
   `header.php`, testa fail-closed e renderiza `home.twig` em Twig sandbox
   com `theme.primary` real. Gera `/tmp/ag_site/state_a.html` e
   `/tmp/ag_site/state_b.html`.
2. Sirva os dois HTMLs e rode
   `node tools/verify-kaz-preview-state.cjs <url-a> <url-b>` → Chromium real
   mede `getComputedStyle` e confirma que **radius, fonte e cor mudam entre
   A e B**.
3. No Admin de verdade: altere a cor primária / densidade / radius / um
   componente → Salvar → Comparar (os dois painéis devem differir) →
   Pré-visualizar → Publicar → home do catálogo (o mesmo resultado).

## Limites

Harnesses, PROBE e Chromium headless não substituem a sessão Admin
autenticada, o banco ou o servidor de produção. Não há nova avaliação visual
do usuário desde `image-7.png`, nem veredito `copy(__kazHealth())` colado do
servidor. `release_approved` continua `false`; F9-27 `BLOCKED_NOT_RUN`,
BDREditor/F9-103 pausado, P2/F9-18 não reaberto. **CI verde ou health local
não significam release aprovada.**
