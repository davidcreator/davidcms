# Pacotes versionáveis e child-theme (Frente E)

Complementa `docs/GUIA-PREVIA-RASCUNHO-PUBLICADO.md` e
`docs/GUIA-EDITOR-CENA-PRESETS.md`.

## Recorte e restrições

**Sem migration e sem schema.** A tabela `rms_design_theme` tem `config`,
`draft_config`, `published_config`, `slug` e `status`; o parent é apenas uma
chave allowlistada (`parent`) dentro do JSON. Nenhum arquivo é lido ou escrito
no servidor: export devolve JSON na resposta HTTP, import recebe JSON no POST.
Sem upload, `eval`, `call_user_func`, regex do usuário, HTML, `|raw` novo ou
código executável.

## O que faltava

Temas só existiam no banco, um a um. Não havia como transportar um tema entre
instalações, versioná-lo, nem criar uma variação que herdasse de outro tema —
cada cópia exigia duplicar cores, tokens, condições, cena e 25 fontes Twig.

## Entregue

- **`ThemePackage`** (`dev/system/library/theme/theme_package.php`), registrado
  no framework. `FORMAT = reamurcms-theme`, `VERSION = 1`, limite de 512 KiB,
  cadeia de herança limitada a 5 níveis.
  - `export()` / `encode()`: pacote com `format`, `version`, `name`, `slug`,
    `parent`, `config`, `meta` e **`checksum` SHA-256 do config canônico**
    (chaves ordenadas recursivamente).
  - `decode()`: fail-closed em tamanho, JSON, formato, versão, identidade,
    **checksum** (`hash_equals`) e `validateConfig()`. Códigos de erro estáveis
    (`checksum_invalid`, `template_source_invalid`, `tokens_invalid`, ...).
    Um byte alterado no config invalida o pacote.
  - `validateConfig()`: reusa `ConditionEngine`, `TokenRegistry` e a **mesma**
    regex de fontes Twig do editor; valida hex, cena, `parent`, chaves de
    topo e cada template da allowlist.
  - `resolveChain()`: mescla pai → filho. Mapas (`tokens`, `templates`,
    `three`) são mesclados **por chave**; escalares e `conditions` são
    substituídos pelo filho. Ciclo, profundidade ou pai ausente **param** a
    caminhada (fail-closed), sem loop.
  - `TEMPLATE_KEYS` passa a ser a **fonte única** da allowlist: o controller
    Admin deixou de ter uma constante privada duplicada.
- **Herança resolvida em runtime** no `ThemeSnapshot::resolve()` (a mesma
  classe já usada pelo Catalog e pela prévia da frente C), com `themeMap`
  opcional. `startup/theme.php` passa o mapa **publicado**; `compare()` passa
  o mapa publicado e o de rascunho. Assim mudanças no pai propagam sem
  regravar o filho, e a prévia mostra o estado efetivo correto.
- **Dois models** ganham `getThemeMap('draft'|'published')` (SELECT simples,
  sem coluna nova). `getDraftConfig()`/`getPublishedConfig()` continuam
  devolvendo o config **próprio**, necessário para export e para o diff.
- **Admin**: card "Tema pai (child-theme)" com `<select name="parent_theme">`
  (nenhum / outros temas por slug; auto-herança recusada) e card "Pacote de
  tema e herança" com links de **export** (rascunho ou publicado), textarea de
  **import**, arquivo `.json` opcional por `theme-package.js` (FileReader, sem
  upload/fetch) e mensagens de sucesso/erro.
- **`design/theme.export`**: GET, ACL `access`, `no-store`, `nosniff`,
  `application/json`, `Content-Disposition: attachment`, zero escrita.
- **`design/theme.import`**: POST, ACL `modify`, CSRF, aplica o pacote como
  **rascunho** via `save()` — nunca publica, nunca ativa. Pacote inválido não
  grava nada e retorna com código de erro allowlistado (sem refletir texto
  livre). `parent` igual ao próprio `slug` é removido.
- 16 chaves de UI ×3 idiomas; estilos no CSS do builder já existente.

## Formato do pacote

```json
{
  "format": "reamurcms-theme",
  "version": 1,
  "name": "Loja azul",
  "slug": "loja-azul",
  "parent": "tema-base",
  "config": { "primary": "#0a58ca", "surface": "#ffffff", "scene": "",
              "templates": {}, "conditions": {"version": 1, "rules": []},
              "three": {}, "tokens": {}, "parent": "tema-base" },
  "meta": { "generator": "ReamurCMS", "schema": 1, "source": "draft" },
  "checksum": "<sha256 do config canonico>"
}
```

O `checksum` cobre **somente** `config`. Metadados podem mudar sem invalidar o
conteúdo; qualquer alteração no config invalida o pacote.

## Compatibilidade e regressão

Temas sem `parent` e sem a chave no JSON continuam idênticos (`themeMap` vazio
ou cadeia vazia → mesmo resultado das frentes A–D). O `buildDraftConfig()`
agora grava `parent: ''`, que é aceito por todas as allowlists. Builder de
condições, tokens, prévia rascunho×publicado, presets de cena, `simulate()` e
`activate()` permanecem intactos.

## Arquivos

`theme_package.php` e `theme-package.js` (novos), `framework.php`,
`theme_snapshot.php`, `startup/theme.php`,
`admin/model/design/theme.php`, `catalog/model/common/theme.php`,
`admin/controller/design/theme.php`, `design/theme_form.twig`,
`theme-condition-builder.css`, 3 idiomas e este guia.

Nenhum `CHECKSUMS*.txt`, contrato congelado, `schema.sql`, workflow, checker,
`ConditionEngine`, `TokenRegistry`, `release_approved` ou runtime Three.js foi
modificado. Os 14 manifestos não cobrem estes alvos e não foram reemitidos.

## Limitações

PHP 8.5 WASM + Twig 3.14 + jsdom, com mocks de DB/sessão/response. **Não**
houve CMS completo, MySQL real, sessão Admin autenticada, browser com rede,
WebGL, E2E, migration, staging, deploy ou escrita no GitHub. Import entre
instalações diferentes deve ser conferido pelo usuário. **CI verde e health
verde ≠ release aprovada.**
