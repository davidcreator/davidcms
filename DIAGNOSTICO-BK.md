# Diagnóstico — BK: stage do KAZEditor-Theme vazando no Catálogo

**HEAD auditado:** `ab8b76588010a5349930c6326e947e5af36fcb49` (BJ aplicada)
**Evidência:** `image-12.png` + o trecho HTML colado na mensagem.
**Veredito:** **falha confirmada, de produto — não é erro de CSS do tema.**

## 1. O que você viu

O `<section class="rms-scene" aria-label="Composição visual do tema">` com as
3 camadas (`DAVID`, `Creative Developer`, `Get in touch`) é o **stage de
composição do editor**. Ele está sendo renderizado **no topo do `<main>` do
site público**, antes do hero, com fundo branco — exatamente como na imagem.
Os valores `4.167% / 7.407% / 43.750%` saem de `x=40,y=40,w=420` sobre o
canvas `960×540` do `kaz_scene` do tema David Creator. Confere 100% com o
pacote BJ que você aplicou.

## 2. Cadeia causal (linhas reais do HEAD)

| # | Arquivo | Linha | O que faz |
|---|---|---|---|
| 1 | `dev/system/library/theme/theme_snapshot.php` | 120 | `$sceneView = $this->sceneCss->compile($config['kaz_scene'] ?? null)` |
| 2 | `dev/system/library/theme/theme_scene_css.php` | 57 | devolve `['enabled' => $layers !== [], 'layers' => …]` — **qualquer camada visível liga `enabled`** |
| 3 | `dev/system/library/theme/theme_snapshot.php` | 123 | publica `scene_view` dentro do snapshot |
| 4 | `dev/catalog/controller/startup/theme.php` | 155 | `$this->config->set('catalog_theme_scene_view', $snapshot['scene_view'])` — **config global do Catálogo** |
| 5 | `dev/catalog/controller/common/home.php` | 369 | `$data['theme_scene_view'] = (array)$this->config->get('catalog_theme_scene_view', [])` |
| 6 | `dev/catalog/view/template/common/home.twig` | 4 | `{% include 'partial/theme-scene-block.twig' %}` **no topo do `<main>`** |
| 7 | `dev/catalog/view/template/partial/theme-scene-block.twig` | 3 | `{% if theme_scene_view.enabled|default(false) %}` → `<section class="rms-scene">` |
| 8 | `dev/catalog/view/css/theme.css` / `ThemeSceneCss::previewCss()` | — | `.rms-scene{background:var(--bg,#fff)}` → **o fundo branco da sua imagem** |

Ou seja: **`kaz_scene` (dado de edição) está sendo publicado como bloco
visível no front**. Não depende do tema ser o David Creator: qualquer tema
com `kaz_scene.layers` visíveis dispara. Confirmamos em todos os 4 pacotes
entregues (BG, BH, BI, BJ), que também incluíam o partial — o BH em **12
pontos**.

## 3. A garantia que foi quebrada

O próprio editor documenta, em
`dev/admin/view/template/design/kaz_theme_editor.twig:566`:

> *"O KAZEditor-Theme edita a **cena do tema**, não um protótipo navegável.
> Não há interações, fluxos ou View mode: a cena é dado de edição e
> versionamento (`kaz_scene`), **não renderização no site público**."*

A "Frente AB" (`partial/theme-scene-block.twig`, comentário no próprio
arquivo: *"a cena desenhada no KAZTheme-Editor, publicada no Catalogo"*)
**contradiz essa garantia**. Há ainda a "Frente AF" em
`dev/system/library/template/twig.php:230-233`, que injeta
`theme_scene_view` no contexto da **prévia sandbox do Admin** — esse uso é
legítimo e deve ser mantido.

**Conclusão:** o partial é correto no Admin (prévia + `ThemeCompareView`),
mas **não deve ser chamado nem alimentado no Catálogo**.

## 4. Sobre "o KAZEditor-Theme não está alterando coisas dos temas inseridos"

O diagnóstico confirma a sua observação, e explica o mecanismo: o editor
grava `kaz_scene` no `config` do tema (`theme.php:651-667`), mas esse dado
**não altera os `templates`, os tokens, nem o layout renderizado do tema**.
O único efeito observável no site é justamente o bloco avulso do item 2 —
um painel solto, global, que aparece antes do hero em qualquer tema.
Editar a cena, portanto, não edita o site: ela fica presa num dado de
composição que só o Admin consome (e que agora vazava para o front).

Isso é uma **decisão de produto**, não um ajuste de CSS: para a ferramenta
passar a alterar de fato os temas inseridos, é preciso definir explicitamente
qual é o contrato — `kaz_scene` continua sendo apenas dado de edição, ou
passa a gerar `templates`/tokens no config do tema (o que muda allowlist,
validação e renderer, e depende de autorização do **Recorte B**). **Não
decidimos isso aqui.** A BK fecha o vazamento sem mexer nesse contrato.

## 5. Correção em duas camadas

### Camada A — pacotes (entregues e aplicáveis já)

Removemos **toda** linha `{% include 'partial/theme-scene-block.twig' %}`
das 25 fontes dos 4 temas: 2 no David Creator, 2 no Atlas Studio, 2 no
Reamur Reference e **12** no ThreeJS Reference. Mantivemos:

- `kaz_scene` intacto (3/3/5/24 camadas) — continua sendo o dado de edição
  do Admin e continua na prévia sandbox;
- `partial/theme-scene.twig` intacto — é o **slot declarativo ThreeJS**,
  emite apenas `data-*` e não renderiza o stage.

Os 4 pacotes foram **reexportados com `ThemePackage::export()` real** e
revalidados com `decode()`/`validateConfig()`; os checksums mudaram porque
o `config` mudou. **Sem migration, schema, contrato, manifesto ou renderer
alterado.** Reimportar cada tema substitui o `common/home` e remove o bloco
do front imediatamente — mesmo sem aplicar o patch de runtime.

### Camada B — patch de runtime (PROPOSTA, **não aplicado**)

`docs/correcao/correcao-runtime.patch` remove **4 linhas em 3 arquivos do
Catálogo**:

1. `common/home.twig` — remove o `include` (defesa em profundidade);
2. `common/home.php` — deixa de injetar `theme_scene_view` no front;
3. `startup/theme.php` — deixa de publicar `catalog_theme_scene_view`.

**Nada muda no Admin**: `twig.php` (`renderSandboxPreview`),
`theme_compare_view.php`, `theme.php` e o próprio
`partial/theme-scene-block.twig` permanecem intactos — a cena continua
aparecendo na prévia e na comparação de temas, como documentado.
`catalog_theme_scene_css` (variáveis `--rms-scene-*`) foi mantido para
minimizar o patch; fica sem consumidor e pode ser removido numa etapa
posterior, se você quiser.

Os 3 arquivos **não constam em `CHECKSUMS.txt`**, então o patch não exige
atualização de manifesto. Aplicação sugerida: `git apply docs/correcao/correcao-runtime.patch`.
**O agente não escreveu nada no GitHub.**

## 6. Prova

`evidencia/antes-depois.png` justapõe a **sua captura real** (`image-12.png`)
com a prévia corrigida. `evidencia/layout-check.json` mede estruturalmente:

| | `rms-scene__stage` | `data-rms-layer` | hero | seções | CTA | footer |
|---|---|---|---|---|---|---|
| antes | 1 | 3 | ✓ | 10 | ✓ | ✓ |
| depois | **0** | **0** | ✓ | 10 | ✓ | ✓ |

`tools/verify-no-scene-leak.cjs` varre os 4 pacotes, valida os checksums
canônicos, falha se qualquer tema voltar a incluir o partial e confere que
o patch não toca no Admin nem em contratos.

## 7. Limites

`release_approved: false` · F9-27 `BLOCKED_NOT_RUN` · F9-18
`release_ready: false` · 10 `PENDING_DECISION`. Nenhuma escrita do agente no
GitHub, nenhum runtime/MySQL/deploy, nenhuma conversão de `PENDING`/
`NOT_RUN`/`BLOCKED_NOT_RUN` em `PASS`, nenhuma decisão sobre o Recorte B.
CI verde, PHP WASM, PIL e guardas estáticos **não significam release
aprovada**; o aceite visual no Admin real continua pendente.
