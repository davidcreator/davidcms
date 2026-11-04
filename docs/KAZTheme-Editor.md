# KAZTheme-Editor — referência da fundação visual (Frente U)

**Data:** 4 de novembro de 2026 (America/Sao_Paulo)  
**Estado:** fundação visual entregue para aplicação; não é release aprovada.  
**Nome próprio:** KAZTheme-Editor, o editor de temas do ReamurCMS — na mesma linha de identidade do BDREditor, mas com escopo, documentos e arquivos próprios.

## 1. O que é

KAZTheme-Editor é um **criador de temas visual** dentro do gerenciador de temas do Admin. A inspiração de interface é o Figma e o Penpot: um canvas com grade, uma árvore de camadas à esquerda, um inspector à direita, seleção, arrastar, redimensionar, duplicar, ocultar, bloquear, reordenar, desfazer/refazer e atalhos de teclado.

A implementação é **DOM + SVG puro**. Não há WebGL, Three.js, canvas 2D, bibliotecas externas, upload, rede ou código executável vindo do usuário. Isso mantém o editor fácil de editar: cada responsabilidade mora em um arquivo pequeno e legível.

### O que esta frente NÃO é

- Não é o BDREditor. O BDREditor/F9-103 continua pausado e **nenhum arquivo dele foi alterado**.
- Não é F9-27, nem P2/F9-18, nem reabertura das frentes A–T. `release_approved` continua `false` e F9-27 continua `BLOCKED_NOT_RUN`.
- Não é persistência de cena no servidor. A cena é um artefato de trabalho **browser-local**, exatamente como os *style presets* locais descritos na referência do BDREditor: "browser-local, not published content, server state, a database feature or a substitute for the BDRE document".
- Não é migration, schema, coluna nova, `custom_js`, WebGL, upload ou chave de tradução nova.
- CI verde ou health coerente **não significa release aprovada**.

## 2. Arquitetura e mapa de arquivos

| Caminho | Responsabilidade |
| --- | --- |
| `dev/admin/view/js/kaz-theme-editor.js` | Modelo da cena, validação fail-closed, render SVG, camadas, inspector, drag/resize, histórico, atalhos, export/import e conexão com o formulário. |
| `dev/admin/view/css/kaz-theme-editor.css` | Estilos escopados em `.kaz-*`, layout de 3 colunas, responsivo e `forced-colors`. |
| `dev/admin/view/template/design/kaz_theme_editor.twig` | Ponto de montagem incluído por `theme_form.twig`, dentro do `<form>` principal. |
| `dev/admin/controller/design/theme.php` | Fornece `kaz_scene_json` derivado do config efetivo e registra os dois assets com cache key própria. |
| `dev/admin/view/template/design/theme_form.twig` | Inclui o editor antes de fechar o formulário principal. |
| `docs/KAZTheme-Editor.md` | Esta referência: identidade, contrato, limites e segurança. |
| `docs/KAZTheme-Editor-Guia-Rapido.md` | Passo a passo de uso e aplicação. |

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

Enums allowlistados:

- `kind`: `rect`, `text`, `button`, `card`, `section`.
- `role`: `none`, `primary`, `surface`, `section`, `component`.

Validação fail-closed no import: `version === 1`; `canvas.w` entre 320 e 3840; `canvas.h` entre 240 e 2160; no máximo 48 camadas; `id` e `bindId` em `[a-z0-9_-]{1,48}` (ou `bindId` vazio); coordenadas `-2000..2000`; `w` `8..960`; `h` `8..540`; `radius` `0..64`; `fontSize` `8..72`; `fill` em `#[0-9a-fA-F]{6}`; `text` com até 80 caracteres; `visible`/`locked` booleanos; IDs únicos. Qualquer violação **rejeita o import integralmente**, sem alterar o estado atual. O JSON inválido também cai na cena derivada do config em vez de quebrar a tela.

A grade é de 8 px com snap em posição e dimensão. Desfazer/refazer guarda até 60 snapshots em memória.

## 4. Conexão com o tema (campos já allowlistados)

`ThemePackage::CONFIG_KEYS` continua **intocado**: `primary`, `surface`, `scene`, `templates`, `conditions`, `three`, `tokens`, `parent`, `sections`, `components`, `schedule`, `media`, `sites`.

| Papel da camada | Campo do formulário | Efeito ao salvar o tema |
| --- | --- | --- |
| `primary` | `name="primary"` | Cor primária validada pelo `validateConfig()`. |
| `surface` | `name="surface"` | Cor de superfície validada pelo `validateConfig()`. |
| `section` | `name="sections[]"` e `name="sections_order"` | Seções habilitadas e ordem, via `SectionRegistry::formOrder()`. |
| `component` | `name="components[<id>][enabled]"` | Componentes habilitados, via `ComponentRegistry::normalize()`. |
| `none` | nenhum | Apenas composição visual. |

O botão **Aplicar ao tema** (e cada edição explícita de cor/papel) propaga esses valores. O `POST` continua passando pelo `edit()`/`save()` existente, com a validação do lado servidor que já existia. O editor não cria, não remove e não renomeia chaves do config.

**Aplicação não destrutiva:** abrir o editor **não altera o formulário** — nenhum binding é executado no carregamento. Seções e componentes são **marcados**, nunca desmarcados, e `sections_order` preserva os IDs já salvos, acrescentando apenas os novos. Assim a configuração existente do tema não é apagada por uma visita à tela.

**Limitação honesta:** a cena em si (posições, texto, tipos de camada) não é gravada como chave nova do config nesta frente — ela sobrevive pelo botão **Exportar cena** (JSON no textarea, copiável) e pelo **Importar cena**. Salvar a cena dentro do pacote do tema exigiria evoluir `ThemePackage`, `validateConfig()`, `export()`, `decode()` e os manifestos em uma frente posterior, com decisão explícita. Nenhuma dessas peças foi tocada aqui.

## 5. Segurança

- Apenas `createElement`, `createElementNS`, `setAttribute`, `textContent`, `appendChild`, `removeChild`, `addEventListener`.
- Sem `innerHTML`, `outerHTML`, `document.write`, `eval`, `Function`, `fetch`, `XMLHttpRequest`, jQuery AJAX, `srcdoc`, `<script>` inline ou atributo `on*`.
- Sem `|raw` novo: o JSON da cena entra por `|e('html_attr')` e é lido com `getAttribute`.
- Texto do usuário vai para `textContent`; nunca é interpretado como markup.
- Sem leitura/escrita de arquivos, sem `localStorage`, sem sessão, sem banco, sem rede.
- Import fail-closed; cena corrompida não executa nada e não derruba o formulário.
- `MAX_LAYERS` e limites numéricos evitam abuso de render.

## 6. Estado do repositório e validação

Base inspecionada: `main` em `018cafb3846445d7e2e0f541554d662ce79be67c` (pai `f993fe3…`), 3.166 entradas e 2.584 blobs, 14 manifestos `CHECKSUMS*.txt`. O ZIP da frente T foi conferido **11/11 byte-idêntico** ao raw desse HEAD. Os sete caminhos desta frente foram triados como **livres** nos 14 manifestos. Nenhum manifesto, hash de release gate, `schema.sql`, `F9-27-Base-Hashes.json` ou `release_approved` foi reescrito.

Validação executada nesta frente: A/B real com bootstraps separados HEAD/pacote, réplica A/B com guards, harnesses PHP (WASM 8.5.12), Twig 3.14, jsdom 29.1.1, `node --check`, `php -l`, CRC do ZIP e `sha256sum -c`. Os números exatos estão no `RELATORIO.md` da entrega.

**Indisponível neste ambiente:** PHP nativo, Composer, PHPStan, rsync, navegador real, MySQL, sessão autenticada, migration, staging, deploy, E2E e CI remoto verificado. PHP WASM/Twig/jsdom com mocks **não são um servidor**. Nenhum status `PENDING_DECISION`, `STATIC_ONLY`, `NOT_RUN`, `SKIP` ou `BLOCKED_NOT_RUN` foi convertido em `PASS`. Nenhuma escrita no GitHub foi feita por nós.
