# Guia — Biblioteca de Componentes (Frente G)

A **G** entrega um catálogo fechado de componentes reutilizáveis para os temas.
Não é o BDREditor (F9-25/F9-103): não há documento de blocos, tabela, upload,
`custom_js`, WebGL ou HTML livre. Cada componente é uma **constante allowlistada**
no `ComponentRegistry`, com opções enum, miniatura SVG determinística, variáveis
CSS e um snippet Twig fixo.

## Catálogo (10)

| ID | Grupo | Variantes | Tons |
|---|---|---|---|
| `button` | Ação | solid / outline / ghost | primary / neutral / danger |
| `badge` | Exibição | pill / square | primary / neutral / success |
| `card` | Exibição | flat / elevated / bordered | primary / neutral |
| `callout` | Feedback | info / warning / success | primary / neutral / danger |
| `stat` | Exibição | compact / wide | primary / neutral / success |
| `quote` | Exibição | plain / bordered | primary / neutral |
| `divider` | Exibição | solid / dashed | primary / neutral |
| `feature` | Exibição | icon / number | primary / neutral / success |
| `cta` | Ação | inline / block | primary / neutral |
| `notice` | Feedback | bar / box | primary / neutral / danger |

Tons são hex fixos (`primary #2563eb`, `neutral #475569`, `success #15803d`,
`danger #b91c1c`). Nenhum valor de cor vem do usuário.

## Como usar

1. **Design → Temas → Editar**, card **Biblioteca de componentes**.
2. Marque **Habilitado**, escolha **Variante** e **Tom**. A miniatura SVG
   atualiza na hora (endpoint `design/theme.componentThumb`, GET, ACL `access`,
   `no-store`, `nosniff`, CSP própria, zero escrita).
3. Selecione o **template de destino** (uma das 25 chaves allowlistadas) e
   clique em **Inserir snippet**. O JS acrescenta o trecho Twig ao `textarea`
   daquele template — sem `innerHTML`, `fetch`, `eval` ou upload. Sem
   JavaScript, a galeria e os selects continuam funcionando; copie o snippet
   deste guia se precisar.
4. **Salvar rascunho** grava `components` no `draft_config`. **Comparar
   publicado × rascunho** mostra os componentes efetivos nos dois painéis e o
   diff (`components.button.variant`, …). Publique quando quiser com **Ativar**.

## O que chega ao Catalog

`ThemeSnapshot` publica três globais **planos** (compatíveis com
`Twig::setThemeGlobals`, que descarta arrays aninhados):

- `theme_components` — `id => variant`
- `theme_component_tones` — `id => tone`
- `theme_component_enabled` — `id => 1`

Os snippets consomem exatamente esses globais. Além disso, `startup/theme`
publica `catalog_theme_component_css`, que `common/header.php` concatena ao
`theme_token_css` já existente no `style` do `<body>`:

```css
--rms-cmp-button-variant: solid; --rms-cmp-button-tone: #2563eb;
```

Ou seja: **todas as telas** enxergam as variáveis, sem alterar `header.twig`,
`home.twig`, `PageLayout`, `cms/slot.php` ou qualquer parcial.

## Pacotes, herança e prévia

`components` é uma chave do JSON do config (sem migration). Vai no `checksum`
SHA-256 da frente E, é validada fail-closed em `decode()` (`components_invalid`)
e é **mesclada por ID** na cadeia child-theme: um filho herda os componentes
do pai e sobrescreve só os que declarar. Config sem `components` = nenhum
componente (idêntico às frentes A–F).

## Segurança

- `normalize()` recusa IDs fora do catálogo, variantes/tons fora do enum, mais
  de 10 entradas e `enabled` não booleano. Lista inválida => nenhum componente.
- Snippets são **constantes**: sem `|raw`, `<script>`, event handlers,
  `javascript:`, `srcdoc`, `formaction` ou `autoescape`. Passam pela mesma
  validação de fontes Twig do `ThemePackage` quando inseridos num template.
- Miniaturas são SVG determinístico sem texto, URL ou script do usuário,
  servidas via `<img>`/endpoint com CSP `default-src 'none'`.
- A fita da prévia C é gerada no servidor com `htmlspecialchars`.

## Arquivos

`component_registry.php` e `theme-component-library.js` (novos),
`theme_package.php`, `theme_snapshot.php`, `theme_compare_view.php`,
`framework.php`, `startup/theme.php`, `common/header.php`, controller e
formulário do Admin, CSS, 3 idiomas e este guia.

Intocados: `schema.sql`, `autoloader.php`, `twig.php`, `common/home.twig`,
`PageLayout`, `cms/slot.php`, models, runtime Three.js, BDREditor, manifestos,
contratos congelados e `release_approved`.
