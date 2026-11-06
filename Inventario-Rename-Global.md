# Inventário do rename global (R2)

**HEAD:** `481b690209ec1a1caf4d9f334cef6012d4597653` · **CI:** 7/7 success · **Leitura apenas.**

Padrão considerado: `BDREditor`, `KAZTheme-Editor`, `KAZTheme`,
`kaz-theme-editor`, `kaz_theme_editor`, `blog-block-editor-kaz`, `kaz-bdre`.

## Totais

| Métrica | Valor |
|---|---|
| Arquivos afetados | **179** |
| Ocorrências de texto | **762** |
| Arquivos com o termo **no próprio path** | **57** |
| Manifestos `CHECKSUMS*.txt` a regenerar | **13 de 14** |
| Contratos congelados afetados | **0 de 3** |

## Por categoria

| Categoria | Arquivos | Ocorrências |
|---|---|---|
| Docs | 99 | 231 |
| CSS | 8 | 190 |
| Doc/contrato | 19 | 107 |
| Tools (probe/smoke/guard) | 14 | 68 |
| Traducao | 6 | 55 |
| JS runtime | 8 | 42 |
| PHP runtime | 9 | 34 |
| Template Twig | 5 | 14 |
| ADR/decisoes | 4 | 9 |
| Preview | 2 | 4 |
| Replica tree/ | 3 | 4 |
| Shell (guard) | 1 | 3 |
| Tests | 1 | 1 |

## Achados de governança

- `tools/decision-matrix.json` → `frozen_artifacts_sha256` protege 3 artefatos:
  `docs/F9-14-Contrato-Editor-MVP.json`, `docs/F9-15-Contrato-Renderer-Publico.json`,
  `docs/F9-25-Contrato-Editor-Editorial.json`. **Nenhum cita os nomes**, logo o
  rename não precisa alterá-los e `tools/verify-contract-freeze.py` segue coerente.
- Policy literal: *"Nenhuma decisao tomada por inferencia. Contratos congelados
  ate registro explicito de status=DECIDED."* — 10 `PENDING_DECISION` / 0 `DECIDED`.
- `docs/BDREditor-Table-Contract.json`, `-Verification.json`, `-Base-Hashes.json`
  **não** estão entre os 3 artefatos congelados, mas são contratos por nome e
  podem ser referenciados por outros checkers.
- **13 dos 14 manifestos** listam arquivos que mudariam de conteúdo (28 entradas
  cruzadas). Regenerar é obrigatório — foi exatamente esse tipo de divergência
  que deixou o CI vermelho na Frente AV.

## Arquivos sensíveis (amostra ordenada)

| Arquivo | Ocorrências | Nome no path |
|---|---|---|
| `agents-ia/Agent-BDREditor/README.md` | 3 | sim |
| `agents-ia/README.md` | 1 | — |
| `dev/admin/controller/blog/blog.php` | 8 | — |
| `dev/admin/controller/cms/page.php` | 8 | — |
| `dev/admin/language/en-us/blog/blog.php` | 14 | — |
| `dev/admin/language/en-us/cms/page.php` | 4 | — |
| `dev/admin/language/es-eu/blog/blog.php` | 14 | — |
| `dev/admin/language/es-eu/cms/page.php` | 4 | — |
| `dev/admin/language/pt-br/blog/blog.php` | 14 | — |
| `dev/admin/language/pt-br/cms/page.php` | 5 | — |
| `dev/admin/view/css/blog-block-editor-kaz-total.css` | 4 | sim |
| `dev/admin/view/css/blog-block-editor-kaz.css` | 104 | sim |
| `dev/admin/view/css/kaz-theme-editor-ajuste.css` | 9 | sim |
| `dev/admin/view/css/kaz-theme-editor-ergonomia.css` | 58 | sim |
| `dev/admin/view/css/kaz-theme-editor.css` | 2 | sim |
| `dev/admin/view/js/blog-block-editor-kaz.js` | 19 | sim |
| `dev/admin/view/js/kaz-theme-editor-ajuste.js` | 1 | sim |
| `dev/admin/view/js/kaz-theme-editor-ergonomia.js` | 3 | sim |
| `dev/admin/view/js/kaz-theme-editor.js` | 12 | sim |
| `dev/admin/view/template/design/kaz_theme_editor.twig` | 9 | sim |
| `dev/system/library/theme/theme_preview_state.php` | 1 | — |
| `dev/system/library/theme/theme_release_probe.php` | 1 | — |
| `docs/BDREditor-Table-Base-Hashes.json` | 0 | sim |
| `docs/BDREditor-Table-Contract.json` | 2 | sim |
| `docs/BDREditor-Table-Verification.json` | 2 | sim |
| `docs/CHANGELOG.md` | 1 | — |
| `docs/README.md` | 6 | — |
| `tools/e2e/README.md` | 1 | — |
| `tools/kaz-bdre-total-probe.php` | 22 | sim |
| `tools/kaz-bdre-visual-probe.php` | 18 | sim |
| `tree/dev/admin/language/pt-br/cms/page.php` | 1 | — |

## Arquivos com o termo no próprio path (renomear = atualizar links)

Foram encontrados **57**. Renomeá-los exige atualizar `docs/README.md`, os
manifestos e todos os links relativos — sob pena de `docs-links` falhar no CI.

Lista completa em `evidencia/inventario-rename.json`.

## O que NÃO foi feito

Nenhuma substituição, nenhum rename de arquivo, nenhuma regeneração de
manifesto, nenhuma escrita no GitHub. Este é um levantamento.
