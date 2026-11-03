# Prévia real: rascunho × publicado (Frente C)

Complementa `docs/GUIA-BUILDER-CONDICOES-TEMA.md` e
`docs/GUIA-DESIGN-TOKENS.md`. A ADR 0080 já exigia prévia isolada e que o
Catalog aplicasse **somente o payload explicitamente publicado**.

## O que faltava

`design/theme.preview` renderizava **um único template já salvo** em
`draft_config`, com dados de amostra fixos. Não havia:

- prévia do que está **sendo editado** (era preciso salvar antes de ver);
- comparação com o **publicado**;
- aplicação de cores, design tokens, condições casadas e Three.js na prévia;
- indicação de que existem alterações não publicadas;
- separação real entre rascunho e público: `save()` gravava o rascunho em
  `config` **e** `draft_config`, mas o Catalog lê `published_config ?: config`.
  Se `published_config` estivesse vazio, um rascunho recém-salvo vazaria para
  o site — violação direta da ADR 0080.

## O que passa a existir

### Correção de impacto

`dev/admin/model/design/theme.php`: `save()` passa a atualizar **apenas**
`draft_config`; `config` e `published_config` ficam intactos. `activate()`
copia `draft_config` para `published_config` **e** para `config`, mantendo o
fallback legado coerente. Nenhuma migration, nenhuma coluna nova.

### Uma única fonte de verdade

`dev/system/library/theme/theme_snapshot.php` (`ThemeSnapshot`) resolve o
**estado efetivo** de um config num contexto allowlistado: cores, cena,
Three.js, tokens (com revalidação de contraste AA), CSS `--rms-*`, vars, flags,
classes, regras casadas e globais Twig. `startup/theme.php` e a prévia do Admin
usam **a mesma classe**, portanto não podem divergir. O `ConditionEngine`, o
`TokenRegistry` e o runtime Three.js continuam intactos.

`ThemeSnapshot::diff()` produz um diff estruturado e limitado (60 itens,
valores truncados a 120 caracteres) entre publicado e rascunho.

### Endpoint `design/theme.compare`

- **POST**, ACL `access`, CSRF, `no-store`, `nosniff`, `no-referrer`,
  `Content-Type: text/html; charset=utf-8`, **zero escrita** (só SELECT).
- Reconstrói o rascunho com `buildDraftConfig()` — o **mesmo** helper usado
  pelo `edit()`/`save()`, com as mesmas validações de nome, slug, hex, fontes
  Twig, condições, Three.js e tokens. A prévia não pode aprovar algo que o save
  rejeitaria (e vice-versa).
- Lê o publicado com `getPublishedConfig()`.
- Avalia os dois lados no **mesmo contexto** (rota, idioma, dispositivo,
  variante, hora, login, UTM — todos allowlistados por `simulateContext()`).
- Renderiza dois painéis com `ThemeCompareView`: amostra visual que consome as
  variáveis reais (`var(--rms-*)`), badges de cores/flags/regras casadas/tokens,
  o fragmento do template via `renderSandboxPreview()` e o resumo do diff.
- Rascunho inválido: exibe o **último rascunho salvo**, mostra o código de erro
  e **não grava nada**.

### Segurança

O HTML da comparação é gerado no servidor, com todo dado dinâmico escapado por
`htmlspecialchars`. CSP `default-src 'none'; img-src data:; style-src
'unsafe-inline'; base-uri 'none'; form-action 'none'` — **mais restritiva** que
a prévia legada. Sem `<script>`, sem `<style>` (apenas atributos `style`), sem
`|raw` novo, sem `srcdoc`, sem `<form>`, sem iframe aninhado, sem rede e sem
fontes remotas. O único HTML não escapado é o fragmento já produzido pelo
Twig Sandbox com autoescape e `SecurityPolicy` restrita. O iframe do formulário
continua `sandbox="" referrerpolicy="no-referrer"`.

### Interface

Cartão "Prévia: rascunho × publicado" com badge de estado (publicado / ainda
não publicado), badge de alterações pendentes, 5 seletores de contexto e o botão
`Comparar agora`. O botão usa `formaction` + `formtarget` — **funciona sem
JavaScript** (progressive enhancement), e o `textarea[name="conditions"]`,
os 22 campos de tokens e o builder das frentes A/B permanecem intactos.
As prévias antigas por template (`design/theme.preview`) continuam funcionando.

13 chaves de UI + 26 rótulos da view ×3 idiomas; estilos no CSS do builder já
existente (nenhum arquivo novo de ferramenta/checker).

## Arquivos

`theme_snapshot.php` e `theme_compare_view.php` (novos), `framework.php`,
`startup/theme.php`, `admin/model/design/theme.php`,
`admin/controller/design/theme.php`, `design/theme_form.twig`,
`theme-condition-builder.css`, 3 idiomas e este guia.

Nenhum `CHECKSUMS*.txt`, contrato congelado, `schema.sql`, workflow, checker,
`release_approved` ou runtime Three.js foi modificado. Os 14 manifestos não
cobrem nenhum destes arquivos e não foram reemitidos.

## Limitações

PHP 8.5 WASM + Twig 3.14 + jsdom, com mocks de DB/sessão/response. Sem CMS
completo, MySQL real, sessão Admin autenticada, browser com rede, WebGL, E2E,
migration, staging, deploy ou escrita no GitHub. A conferência visual final é
do usuário. **CI verde e health verde ≠ release aprovada.**
