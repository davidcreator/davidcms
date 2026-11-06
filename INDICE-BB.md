# Índice — Frente BB (rename R2 · Fase B1)

Pacote: `ReamurCMS-Frente-BB-Rename-B1-UI.zip` · HEAD `700d104c…` · CI 7/7 ✅
**9 arquivos de UI do Admin substituídos** · **66 → 0** ocorrências dos nomes antigos.

| # | Entrada | Conteúdo |
|---|---|---|
| 1 | `RELATORIO-FINAL-BB.md` | Decisão, invariantes, o que foi preservado, validação, pendências |
| 2 | `INDICE-BB.md` | Este índice |
| 3–11 | **9 arquivos de UI** | `design/kaz_theme_editor.twig`, `cms/page_form.twig`, `cms/page_content_form.twig`, `language/{en-us,pt-br,es-eu}/{cms/page,blog/blog}.php` |
| 12 | `tools/verify-rename-b1.cjs` | Verificador Node puro |
| 13 | `evidencia/invariantes.json` | Contagens originais de chaves/classes/ids/`|raw` por arquivo |
| 14 | `evidencia/validacao.txt` | `php -l` 6/6 + guardas |

## Mapeamento

| De | Para |
|---|---|
| `BDREditor` | `BDREditor-Content` |
| `KAZTheme-Editor` | `KAZEditor-Theme` |
| `KAZTheme` (isolado) | `KAZEditor-Theme` |

## Como aplicar

**9 substituições.** Nenhum manifesto, contrato, hash, `schema.sql`, workflow
ou arquivo de `dev/install/` precisa ser tocado.

```bash
node tools/verify-rename-b1.cjs <raiz>
bash tools/repo-health.sh
```

## Preservado (B2/B3 não rodaram)

Documentos históricos F9/F11/ADRs · contratos `BDREditor-Table-*` · nomes de
arquivos e identificadores (`blog-block-editor-kaz.js`, `kaz-theme-editor.js`)
· 14 manifestos · `decision-matrix.json` · catálogo público · schema e
workflows. Restam **113 ocorrências** para B2+B3.

## Validação medida

`php -l` **6/6** · Twig balanceado **3/3** · invariantes **9/9** ·
`verify-checksums` **142 arquivos / 0 divergentes** · `repo-health`
**6 PASS / 0 FAIL / COERENTE** · `verify-contract-freeze` **COERENTE**.

## O que NÃO faz

Não decide B2/B3, não faz migration, não altera contrato/hash, não decide
D01–D10, não escreve no GitHub pelo agente e **não significa release
aprovada**. Aceite visual no Admin real segue pendente.

`release_approved: false` · F9-27 `BLOCKED_NOT_RUN` · F9-18
`release_ready: false` · 10 `PENDING_DECISION`.
