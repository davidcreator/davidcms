# Frente B — Design tokens completos

Série A→J: **A** (builder de condições) já está aplicada no `main` 71923f849;
**esta é a B**. Próxima: **C — prévia real rascunho × publicado**.
Um pacote instalável por frente, sem frente de auditoria nem dossiê.

## Diagnóstico

O tema só tinha `primary` e `surface`. Tipografia, escala, espaçamento, raios,
sombras, densidade, largura e o tema escuro exigiam reescrever Twig/CSS, e não
havia **nenhuma** verificação de contraste. O `header.twig` já emitia duas
variáveis CSS no `style` do `<body>` — o caminho natural para ampliar sem
`<style>` inline (que exigiria `|raw`, proibido).

## Entregue (17 arquivos)

- **`TokenRegistry`** novo (`dev/system/library/theme/token_registry.php`):
  **22 tokens allowlistados** em 4 grupos — tipografia (família entre 4 stacks
  system, tamanho 14–20, escala 1.0–1.5, line-height, pesos), espaçamento/forma
  (unidade, densidade, largura 800–1600, raios, borda, 5 sombras pré-definidas),
  cores claras e cores escuras. `spec()`, `normalize()`, `validate()`,
  `contrast()`, `contrastPairs()`, `css()`, `formFields()`.
- **Contraste WCAG medido e exigido no save**: 7 pares, mínimo **AA 4,5:1**
  (texto/superfície, secundário/superfície, primária/superfície, branco/primária
  no claro; texto, secundário e primária sobre superfície no escuro). O
  formulário mostra cada razão com badge verde/vermelho; valor que reprova
  **não é salvo**, com código de erro estável.
- **Efeito visual real**: `startup` publica `catalog_theme_tokens` +
  `theme.tokens` (global Twig); `header.php` gera as declarações `--rms-*` no
  `style` do `<body>` com `|e('html_attr')`; `theme.css` **consome** as variáveis
  em `body`, títulos, botões, cards, seções, container e foco. O modo escuro
  alterna por `[data-bs-theme="dark"] body` entre `--rms-light-*` e
  `--rms-dark-*` — sem `<style>` inline e sem `|raw`.
- **Integração com a frente A**: `ConditionEngine` ganhou a chave **aditiva**
  `tokens` em `then` (patch parcial, validado pelo mesmo `TokenRegistry`), com
  merge acumulado por regra. O builder visual ganhou 4 campos de patch no
  `then`. No startup, o merge final é **revalidado**: um patch que derrube o
  contraste é ignorado (fail-closed) e a base permanece.
- **Admin**: card "Design tokens" com 22 campos agrupados (gerados por
  `formFields()` — sem HTML repetido), 7 badges de contraste e validação no POST.
  22 rótulos de campo + 9 chaves de UI por idioma (×3 idiomas). Guia `docs/GUIA-DESIGN-TOKENS.md`.
- **Compatibilidade**: tema sem `tokens` recebe os defaults e continua idêntico.
  Nenhuma mudança em `primary`, `surface`, `scene`, `templates`, revisões ou
  publicação.

## Segurança

`css()` só emite valores derivados de enums, inteiros/floats formatados, hex
validado e tabelas fixas (stacks de fonte e sombras). `safeCssValue()` ainda
rejeita `;`, `{}`, `<`, `>`, aspas, `url(`, `@import` e `expression(` —
testado com bypass direto do `normalize()`. Sem fontes remotas, sem `eval`,
sem IO, sem `|raw` novo e sem `<script>`/`<style>` inline.

O PHP real capturou e corrigiu um bug de integração: `normalize()` do registry
é método de instância, não estático — a chamada no motor foi ajustada para
`(new TokenRegistry())->normalize(...)`.

## Validação executada

| Verificação | Resultado |
|---|---|
| `php -l` PHP 8.5 real nos 9 arquivos PHP | 9 OK / 0 FAIL |
| `node --check` no builder JS | OK |
| `TokenRegistry` (defaults, faixas, contraste, CSS seguro, injeção, spec/form) | 42 PASS / 0 FAIL |
| Motor + `tokens` (regressão das chaves A/F12, patch, merge, fail-closed) | 21 PASS / 0 FAIL |
| `startup/theme.php` com mocks (legado, base, patch casa/não casa, contraste) | 19 PASS / 0 FAIL |
| Render Twig 3.14 (header com tokens + formulário com 22 campos/7 pares) | 25 PASS / 0 FAIL |
| **jsdom 29** (builder A + patch de tokens, abas, reordenação, `fetch`) | 20 PASS / 0 FAIL |
| Cruzado: JSON produzido pelo JS validado no motor PHP | 7 PASS / 0 FAIL |
| `verify-checksums` (A e B) | 14 manifestos / 142 arquivos / 0 divergentes |
| `repo-health` (A e B) | 6 PASS / 0 FAIL / 0 SKIP |
| `verify-ci-workflow` | 31 PASS / 0 FAIL |
| `verify-contract-freeze` | exit 0 |

**Total: 134 asserções, 0 falhas.** Os 14 `CHECKSUMS*.txt` permanecem
**idênticos** (nenhum arquivo da frente é coberto por eles) e não foram
reemitidos. Nenhum contrato congelado, `schema.sql`, `release_approved`,
workflow, checker, runtime Three.js ou migration foi alterado. Nenhuma escrita
remota. F9-27 segue `BLOCKED_NOT_RUN`; BDREditor pausado; P2 do F9-18 não
reaberto.

## Limitações

PHP 8.5 via WASM + Twig 3.14 + jsdom para lint/lógica/DOM. **Não** houve CMS
completo, banco, sessão Admin autenticada real, browser com rede, WebGL, E2E,
migration, staging, deploy ou escrita no GitHub. A conferência visual dos temas
claro/escuro é do usuário. **CI verde e health verde ≠ release aprovada.**

Aplicar: substituir pelos arquivos completos e rodar
`bash tools/verify-checksums.sh && bash tools/repo-health.sh`.
