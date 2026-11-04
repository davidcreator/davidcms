# KAZTheme-Editor — referência (Frentes U + V + X)

**Data:** 4 de novembro de 2026 (America/Sao_Paulo)  
**Estado:** fundação visual (U), cena persistente (V), correção do mapa de sites (W) e biblioteca de blocos + estado não salvo (X); não é release aprovada.  
**Nome próprio:** KAZTheme-Editor, o editor de temas do ReamurCMS — na mesma linha de identidade do BDREditor, mas com escopo, documentos e arquivos próprios.

## 1. O que é

KAZTheme-Editor é um **criador de temas visual** dentro do gerenciador de temas do Admin. A inspiração de interface é o Figma e o Penpot: canvas com grade, árvore de camadas à esquerda, inspector à direita, seleção, arrastar, redimensionar, duplicar, ocultar, bloquear, reordenar, desfazer/refazer e atalhos de teclado.

A implementação é **DOM + SVG puro**. Não há WebGL, Three.js, canvas 2D, bibliotecas externas, upload, rede ou código executável vindo do usuário. Isso mantém o editor fácil de editar: cada responsabilidade mora em um arquivo pequeno e legível.

### O que este editor NÃO é

- Não é o BDREditor. O BDREditor/F9-103 continua pausado e **nenhum arquivo dele foi alterado**.
- Não é F9-27, nem P2/F9-18, nem reabertura das frentes A–U. `release_approved` continua `false` e F9-27 continua `BLOCKED_NOT_RUN`.
- Não é renderização da cena no site público. A cena é **dado de edição e versionamento**: volta ao abrir o tema, viaja no pacote e é herdada por child-themes. O que o visitante vê continua vindo dos campos allowlistados (`primary`, `surface`, `sections`, `components`, tokens etc.) que o editor escreve. Renderizar a cena no Catalog exigiria evoluir `ThemeSnapshot`/runtime em frente posterior.
- Não é migration, schema, coluna nova, `custom_js`, WebGL, upload ou chave de tradução nova.
- CI verde ou health coerente **não significa release aprovada**.

## 2. Arquitetura e mapa de arquivos

| Caminho | Responsabilidade |
| --- | --- |
| `dev/admin/view/js/kaz-theme-editor.js` | Modelo da cena, validação fail-closed, render SVG, camadas, inspector, drag/resize, histórico, atalhos, export/import, sincronização do campo `kaz_scene_json` e conexão com o formulário. |
| `dev/admin/view/css/kaz-theme-editor.css` | Estilos escopados em `.kaz-*`, layout de 3 colunas, responsivo e `forced-colors`. |
| `dev/admin/view/template/design/kaz_theme_editor.twig` | Ponto de montagem incluído por `theme_form.twig`, dentro do `<form>` principal; o textarea é um campo real submetido. |
| `dev/admin/controller/design/theme.php` | `kazScene()` (fallback derivado do config), leitura da cena salva, validação/gravação no `buildDraftConfig()` e assets com cache key própria. |
| `dev/system/library/theme/theme_package.php` | `CONFIG_KEYS` com `kaz_scene`, `validateScene()`/`normalizeScene()` e validação no `validateConfig()`. |
| `dev/admin/view/template/design/theme_form.twig` | Inclui o editor antes de fechar o formulário principal. |
| `docs/KAZTheme-Editor.md` | Esta referência. |
| `docs/KAZTheme-Editor-Guia-Rapido.md` | Passo a passo de uso. |
| `docs/KAZTheme-Editor-Cena-Persistente.md` | Contrato da frente V e regras de migração zero. |
| `docs/KAZTheme-Editor-Biblioteca-de-Blocos.md` | Contrato dos 6 blocos da frente X e do selo de alterações não salvas. |

O padrão de separação (JS de modelo/UI + CSS escopado + init no controller + documento de referência) é o mesmo que o BDREditor usa, mas os arquivos, nomes, identidade e contrato são **exclusivos** do KAZTheme-Editor.

## 3. Contrato de dados `kaztheme-scene/v1`

Cena JSON, sem HTML, sem código executável, sem referência a arquivos:

```json
{
  "version": 1,
  "canvas": { "w": 960, "h": 540, "grid": 8 },
  "layers": [
    { "id": "kaz-primary", "kind": "rect", "role": "primary",
      "x": 40, "y": 40, "w": 200, "h": 120, "fill": "#2563eb",
      "radius": 12, "text": "Primary", "fontSize": 16,
      "visible": true, "locked": false, "bindId": "" }
  ]
}
```

Enums allowlistados: `kind` = `rect|text|button|card|section`; `role` = `none|primary|surface|section|component`.

Validação **fail-closed no cliente e no servidor**: `version === 1`; `canvas.w` 320..3840; `canvas.h` 240..2160; `grid === 8`; no máximo 48 camadas; JSON até 64 KiB; `id`/`bindId` em `[a-z0-9_-]{1,48}` (ou `bindId` vazio), IDs únicos; coordenadas `-2000..2000`; `w` `8..960`; `h` `8..540`; `radius` `0..64`; `fontSize` `8..72`; `fill` em `#[0-9a-fA-F]{6}`; `text` com até 80 caracteres e sem padrões executáveis/markup; `visible`/`locked` booleanos. Qualquer violação **rejeita o import e o POST integralmente**, sem alterar o estado. O erro do servidor usa a chave de tradução já existente `error_validation` — nenhuma chave nova foi criada.

`ThemePackage::normalizeScene()` devolve a cena **canônica** (somente as 14 chaves allowlistadas por camada, tipos garantidos) ou `null`. `validateScene()` devolve códigos estáveis (`kaz_scene_*`).

## 4. Persistência (frente V)

`ThemePackage::CONFIG_KEYS` passou de 13 para **14 chaves** com `kaz_scene`. Nenhum artefato congelado por hash, nenhum dos 14 manifestos `CHECKSUMS*.txt` e nenhuma coluna foi alterado: `theme_package.php` já era livre na triagem da U.

| Etapa | Comportamento |
| --- | --- |
| Abrir tema com `kaz_scene` válida | `edit()` usa `normalizeScene($config['kaz_scene'])` e marca `kaz_scene_saved=true`. |
| Abrir tema A–U sem a chave | `edit()` usa `kazScene($config)` derivado das cores/seções/componentes e marca `false`. **Zero migration**, comportamento idêntico ao da U. |
| Salvar formulário | `buildDraftConfig()` lê `kaz_scene_json`, chama `normalizeScene()`; inválido ⇒ `error_validation` e nada é gravado. Válido ⇒ grava `kaz_scene` no config. |
| Exportar/importar pacote | `export()` inclui a chave via `sanitizeConfig()`; `decode()` recalcula o checksum e chama `validateConfig()`, que valida a cena. |
| Child-theme | `kaz_scene` **não** está em `MAP_KEYS`: é substituída integralmente na cadeia, nunca mesclada pela metade. |
| Condições/rollout | `then` do `ConditionEngine` continua allowlistado e não conhece `kaz_scene`; a cena não participa de `decide()`. |

**Aplicação não destrutiva:** abrir o editor não executa nenhum binding; seções e componentes são marcados, nunca desmarcados, e `sections_order` preserva os IDs já salvos.

## 5. Biblioteca de blocos e estado não salvo (frente X)

A frente X acrescenta um painel **Biblioteca de blocos** com 6 presets
allowlistados (`hero`, `card`, `banner`, `titulo`, `galeria`, `cta`). Cada preset
é uma lista **declarativa** de 2–3 templates que geram camadas reais dentro do
contrato `kaztheme-scene/v1`: `kind` entre os 5 permitidos, `role='none'`,
`bindId=''`, cores hex, coordenadas com snap na grade de 8 px e limites
respeitados. Inserir um bloco:

- cria IDs únicos, seleciona a primeira camada, entra no histórico (desfazer) e
  **sincroniza o textarea** `kaz_scene_json` — portanto é persistido ao salvar;
- respeita `MAX_LAYERS` (48): se o bloco não couber, nada é alterado;
- **não** aplica bindings ao formulário e **não** roda no boot: só por clique.

O selo **Alterações não salvas** (`data-kaz-dirty`) compara a cena atual com o
`baseline` carregado do servidor. Abrir a tela não suja o estado; desfazer até o
estado inicial limpa o selo; o `submit` atualiza o `baseline`. É um indicador
local — a validação e a gravação continuam soberanas no servidor.

O contrato de dados, `CONFIG_KEYS` (14 chaves), `MAP_KEYS`, `validateScene()`,
`normalizeScene()`, `validateConfig()`, `decode()` e os 14 manifestos **não foram
alterados** pela X. Zero migration e zero chave de tradução nova: os rótulos são
literais estáticos no Twig, como os botões existentes desde a U. O cache key dos
assets passou de `-kaz-theme-editor-v` para `-kaz-theme-editor-x`.

## 6. Segurança

- Apenas `createElement`, `createElementNS`, `setAttribute`, `textContent`, `appendChild`, `removeChild`, `addEventListener`.
- Sem `innerHTML`, `outerHTML`, `document.write`, `eval`, `Function`, `fetch`, `XMLHttpRequest`, jQuery AJAX, `srcdoc`, tag `script` inline ou atributo `on*`.
- Sem `|raw` novo: o JSON da cena entra por `|e('html_attr')` e o textarea usa autoescape HTML.
- Texto do usuário vai para `textContent`; nunca é interpretado como markup.
- Import fail-closed; cena corrompida não executa nada e não derruba o formulário.
- `MAX_LAYERS`, `SCENE_MAX_BYTES` e limites numéricos evitam abuso de render e de payload.

## 7. Estado do repositório e validação

Base inspecionada: `main` em `cd92573c507b6a5a4189d7b1b574cb73ec17ad59` (pai `004a99cd…`, frente W aplicada **4/4** no raw). Os caminhos desta frente foram triados como **livres** nos 14 manifestos. Nenhum manifesto, hash de release gate, `schema.sql`, `F9-27-Base-Hashes.json`, `tools/decision-matrix.json` ou `release_approved` foi reescrito.

Validação executada nesta frente: A/B real com bootstraps separados HEAD/pacote, réplica A/B com guards, harnesses PHP (WASM 8.5.12), Twig 3.14, jsdom 29.1.1, `node --check`, `php -l`, CRC do ZIP e `sha256sum -c`. Os números exatos estão no `RELATORIO.md` da entrega.

**Indisponível neste ambiente:** PHP nativo, Composer, PHPStan, rsync, navegador real, MySQL, sessão autenticada, migration, staging, deploy, E2E e CI remoto verificado. PHP WASM/Twig/jsdom com mocks **não são um servidor**. Nenhum status `PENDING_DECISION`, `STATIC_ONLY`, `NOT_RUN`, `SKIP` ou `BLOCKED_NOT_RUN` foi convertido em `PASS`. Nenhuma escrita no GitHub foi feita por nós.
