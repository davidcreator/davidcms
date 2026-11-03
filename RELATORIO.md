# Frente C — Prévia real: rascunho × publicado

Série A→J: **A** (builder de condições) e **B** (design tokens) já estão
aplicadas no `main` f4725612e; **esta é a C**. Próxima: **D — editor de cena com
miniatura/presets**. Um pacote instalável por frente.

## Diagnóstico

O `design/theme.preview` renderizava **um único template já salvo**, com dados
de amostra fixos. Para ver uma alteração era preciso **salvar antes**; não havia
comparação com o publicado, nem aplicação de cores, tokens, condições casadas
ou Three.js. A ADR 0080 já exigia que o Catalog aplicasse **somente o payload
explicitamente publicado**.

## Dois impactos corrigidos

1. **Contaminação do público pelo rascunho.** `save()` gravava o rascunho em
   `config` **e** `draft_config`, mas o Catalog lê `published_config ?: config`.
   Com `published_config` vazio, uma edição não publicada vazaria para o site.
   Agora `save()` atualiza **apenas `draft_config`**; `activate()` copia
   `draft_config` para `published_config` **e** `config` (fallback legado
   coerente). Sem migration, sem coluna nova.
2. **13 rótulos do formulário chegavam vazios.** O `Loader::view()` deste
   projeto **não** mescla o idioma (confirmado em
   `dev/system/engine/loader.php`), e o controller não publicava
   `text_builder_visual`, `text_builder_json`, `text_builder_add_rule`,
   `text_builder_context`, `text_builder_simulate`, `text_template_editor`,
   `text_preview`, `text_history` etc. O Twig renderizava sem erro, mas as abas,
   botões e ajudas ficavam em branco. `edit()` agora publica todas as chaves
   carregadas com `array_replace($this->language->all(), $data)`. Teste cobre
   as **46** chaves `text_*` usadas pelo template.

## Entregue (14 arquivos)

- **`ThemeSnapshot`** novo (`theme_snapshot.php`): uma única fonte de verdade
  para o estado efetivo do tema — cores, cena, Three.js, tokens (com contraste
  AA revalidado no merge), CSS `--rms-*`, vars, flags, classes, regras casadas
  e globais Twig. **`startup/theme.php` passa a usá-lo**, e a prévia do Admin
  usa a **mesma classe**: Catalog e prévia não podem divergir. Também traz
  `diff()` (60 itens, valores truncados a 120 caracteres) e `flatten()`.
- **`ThemeCompareView`** novo: HTML isolado com dois painéis (publicado e
  rascunho), amostra visual que consome `var(--rms-*)`, badges de cores, flags,
  regras casadas e tokens, fragmento do template via `renderSandboxPreview()`
  e resumo do diff.
- **Endpoint `design/theme.compare`**: POST, ACL `access`, CSRF, `no-store`,
  `nosniff`, `no-referrer`, **zero escrita**. Reconstrói o rascunho com
  `buildDraftConfig()` — o **mesmo** helper agora usado por `edit()`/`save()`,
  com as mesmas validações de nome, slug, hex, fontes Twig, condições, Three.js
  e tokens. A prévia não aprova algo que o save rejeitaria.
- **Rascunho inválido**: exibe o último rascunho salvo, mostra o código de erro
  e **não grava nada**.
- **Interface**: cartão "Prévia: rascunho × publicado" com badge de estado,
  badge de alterações pendentes, 5 seletores de contexto (rota, idioma,
  dispositivo, variante, template) e botão `Comparar agora`. Usa `formaction` +
  `formtarget` — **funciona sem JavaScript**. O iframe continua
  `sandbox="" referrerpolicy="no-referrer"`. As prévias antigas por template,
  o `textarea[name="conditions"]`, os 22 campos de tokens e o builder A/B
  permanecem intactos.
- **13 chaves de UI + 26 rótulos da view ×3 idiomas**; estilos no CSS do
  builder já existente (nenhuma ferramenta/checker novo). Guia
  `docs/GUIA-PREVIA-RASCUNHO-PUBLICADO.md`.

## Segurança

CSP **mais restritiva** que a prévia legada: `default-src 'none'; img-src data:;
style-src 'unsafe-inline'; base-uri 'none'; form-action 'none'`. Todo dado
dinâmico escapado com `htmlspecialchars`. Sem `<script>`, sem `<style>` (apenas
atributos `style`), sem `|raw` novo, sem `srcdoc`, sem `<form>`, sem iframe
aninhado, sem rede e sem fontes remotas. O único HTML não escapado é o
fragmento já produzido pelo Twig Sandbox com autoescape e `SecurityPolicy`
restrita. Contexto allowlistado por `simulateContext()` (sem A/B por inferência:
`variant` só `''`/`a`/`b`).

## Validação executada

| Verificação | Resultado |
|---|---|
| `php -l` PHP 8.5 real nos 9 arquivos PHP | 9 OK / 0 FAIL |
| `node --check` no builder JS (inalterado) | OK |
| Núcleo: `ThemeSnapshot`, paridade startup↔snapshot, diff/flatten, model DB | 64 PASS / 0 FAIL |
| Controller Admin com mocks: `compare()`, ACL/CSRF/método, `edit()`/`save()`, regressão A | 61 PASS / 0 FAIL |
| Render Twig 3.14 + `ThemeCompareView` (46 rótulos, escape, CSP) | 29 PASS / 0 FAIL |
| **jsdom 29**: card C, `formaction`/`formtarget`, regressão builder A/B | 30 PASS / 0 FAIL |
| `verify-checksums` (A e B) | 14 manifestos / 142 arquivos / 0 divergentes |
| `repo-health` (A e B) | 6 PASS / 0 FAIL / 0 SKIP |
| `verify-ci-workflow` | 31 PASS / 0 FAIL |
| `verify-contract-freeze` | exit 0 |

**Total: 184 asserções, 0 falhas.** Os 14 `CHECKSUMS*.txt` permanecem
**idênticos** (nenhum arquivo da frente é coberto por eles) e não foram
reemitidos. Nenhum contrato congelado, `schema.sql`, `release_approved`,
workflow, checker, `ConditionEngine`, `TokenRegistry` ou runtime Three.js foi
modificado. Nenhuma escrita remota. F9-27 segue `BLOCKED_NOT_RUN`; BDREditor
pausado; P2 do F9-18 não reaberto.

## Limitações

PHP 8.5 via WASM + Twig 3.14 + jsdom, com mocks de DB/sessão/response. **Não**
houve CMS completo, MySQL real, sessão Admin autenticada, browser com rede,
WebGL, E2E, migration, staging, deploy ou escrita no GitHub. A conferência
visual final é do usuário. **CI verde e health verde ≠ release aprovada.**

Aplicar: substituir pelos arquivos completos e rodar
`bash tools/verify-checksums.sh && bash tools/repo-health.sh`.
