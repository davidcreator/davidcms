# BDREditor — escopo corrigido (Frente AP)

> **Este documento SUBSTITUI a versão da Frente AO no mesmo caminho.**
> A AO foi aplicada no GitHub (`9c809050ff908b66b6f3bf97c1fc5a1aed9b3160`) e
> **reprovada visualmente**: `image-9.png` mostra que a edição passou dos limites
> e pintou a barra de navegação lateral do ReamurCMS. `image-10.png` mostra o
> KAZTheme-Editor: a sidebar do Admin continua **azul**, e a identidade KAZ fica
> **dentro do editor** (`#kaz-theme-editor`).

## 1. O que estava errado

O `reamurcms.css` define:

```css
#menu > li.active > a { background-color: var(--bs-primary); }
```

e `themes/light.css` define `--rms-accent: #0d6efd`. A AO sobrescrevia
`--bs-primary: #31efb8`, `--bs-body-bg`, `--bs-border-color` e dezenas de
classes Bootstrap **no `body`/`html`**. Resultado medido no A/B/C:

| Estado | item ativo da sidebar | `--bs-primary` | página Admin |
|---|---|---|---|
| Admin nativo + AN | `rgb(13, 110, 253)` azul | `#0d6efd` | clara |
| **AO (reprovada)** | `rgb(49, 239, 184)` verde | `#31efb8` | escura |
| **AP (corrigida)** | `rgb(13, 110, 253)` azul | `#0d6efd` | clara |

Ou seja: `image-9.png` não era impressão — era o `--bs-primary` vazando.

## 2. O que a AP faz

**Recolhe a camada ao BDREditor.** A folha
`dev/admin/view/css/blog-block-editor-kaz-total.css` é **substituída** no mesmo
caminho (nome mantido por compatibilidade; não é mais "total"). Agora ela
contém **apenas**:

- `[data-blog-block-editor]` e seus descendentes — o host e os estados do editor
  (biblioteca de padrões, inserter, previews, tabelas, rail, inspector, outline,
  histórico, fullscreen, arraste, seleção);
- `body:has([data-blog-block-editor]) .rb-*` — os overlays/dialogs que o editor
  cria em `document.body`. O `:has()` é **somente condição de presença**: nunca
  é o alvo da regra, e sempre exige um descendente `rb-*`;
- tokens **locais** `--kaz-ap-*` no host (nunca `:root`), scrollbars e foco
  dentro do host/overlays;
- restauração do **canvas branco** e do foco **F9-26** `#174ea6` / `3px` /
  `offset 2px`.

**Nenhuma regra** tem como alvo `html`, `body`, `:root`, `--bs-*`, `--rms-*`,
`#menu`, `.sidebar`, `.navbar`, `.page-titlebar`, `.breadcrumb`,
`.container-fluid` ou qualquer classe genérica do Admin. O verificador varre
**todos os elementos fora do editor** e exige **zero divergências** de estilo
contra o Admin nativo.

## 3. O que NÃO muda

- A barra de navegação lateral, a topbar, o breadcrumb, os cards, os campos,
  as abas de idioma, os botões, os alertas e os badges do **ReamurCMS** voltam
  exatamente ao estado anterior à AO.
- O catálogo público, o renderer, o documento de blocos, o JS de ~600 KB, as
  Twigs, `schema.sql`, traduções, manifestos e os contratos
  F9-14 / F9-25 / F9-26 / F9-27 / F9-18 permanecem intactos.
- Não há JS novo, DOM novo, `|raw`, migração, fonte externa ou CDN.
- Não se clona o layout do KAZTheme-Editor (screenbar / 3 colunas): a decisão
  permanece **somente identidade visual dentro do BDREditor**.
- `release_approved` continua `false`; F9-27 segue `BLOCKED_NOT_RUN`;
  F9-18 segue `release_ready: false`.

## 4. Aplicação

O pacote **substitui 6 arquivos** da AO no mesmo caminho e **acrescenta 1
documento**. Não é necessário remover nada e não fica arquivo órfão:

| Arquivo | Ação |
|---|---|
| `dev/admin/controller/blog/blog.php` | marcador `-kaz-bdre-ao` → `-kaz-bdre-ap` |
| `dev/admin/controller/cms/page.php` | marcador `-kaz-bdre-ao` → `-kaz-bdre-ap` |
| `dev/admin/view/css/blog-block-editor-kaz-total.css` | conteúdo recolhido ao BDREditor |
| `docs/KAZ-BDREditor-Tema-Total.md` | este documento corrigido |
| `tools/kaz-bdre-total-probe.php` | PROBE de **não-vazamento** |
| `tools/verify-kaz-bdre-total.cjs` | verificador A/B/C |
| `docs/Registro-Visual-AP.md` | **novo** — critério visual |

O marcador novo existe apenas para invalidar o cache do navegador; a ordem de
carregamento (AN antes da AP) e os demais assets não mudam.

## 5. Validação e limites

`tools/kaz-bdre-total-probe.php` (PHP WASM, somente leitura) e
`tools/verify-kaz-bdre-total.cjs` (Chromium headless) verificam o A/B/C:
`admin_nativo.html`, `admin_ao.html`, `admin_ap.html`. O estado intermediário
com a AO antiga existe para provar que a asserção **detecta** o defeito — a AO
diverge em 11 superfícies do Admin, a AP em **zero**.

**Limites:** PHP 8.5 via WASM, Twig/jsdom e Chromium headless não são produção;
a prévia reconstrói as classes e os tokens do Admin, não o layout completo da
sidebar. CI remoto, `copy(__kazHealth())` em produção e o ciclo
salvar–Publicar não foram executados. **Os resultados locais não significam release aprovada** —
o veredito continua sendo o visual do usuário no Admin real, com `image-9.png`
como reprovação e `image-10.png` como referência.

## 6. Estado

Correção de escopo entregue para aplicação e **nova avaliação visual**.
Sem escrita no GitHub, sem deploy e sem declaração de release pelo agente.
