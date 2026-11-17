# RELATORIO-FINAL-KZ23 — KAZEditor-Theme: texto do tema dentro de atributo

**Etapa:** KZ-23 (ADR 0116) · **Data:** 2026-11-17 · **Base:** HEAD `fc5339f` (KZ-22, ADR 0115)
**Pacote:** `ReamurCMS-Frente-KZ23-Texto-do-Tema-em-Atributos.zip` · **Não é release**

## O que foi entregue
Fecha a pendência anotada na KZ-21 (ADR 0114): atributo cujo valor é só `{{ theme_text.chave }}` (com filtro de caixa opcional) passa a ser editável no painel "Elemento", gravando no JSON dos textos (`strings_json`) no idioma do canvas. A fonte do tema **não muda**.

- **Atributos cobertos:** `alt`, `aria-label`, `placeholder` e qualquer `data-du-*` (avisos da cena e a palavra "Fase" do HUD no David Universe 3D).
- **Regras puras:** `themeAttrName` / `themeAttr` em `dev/admin/view/js/kaz-element-attrs.js` (exportadas em `KazElementAttrs`).
- **Painel:** `paintThemeAttrField` + `onThemeAttrInput` + `setThemeAttr` em `dev/admin/view/js/kaz-canvas.js` (bloco KZ-23); integração em `valueField`/`paintAttrs`; delegação de input; slot de aviso `themeattr`.
- **Prévia imediata:** só o atributo troca no quadro (`setAttribute` com filtro de caixa), em todos os elementos com aquela chave, nos três quadros.
- **Idiomas:** `text_canvas_theme_attr`, `text_canvas_theme_attr_help` em `en-us`, `pt-br`, `es-eu` (traduções sem revisão de nativo).
- **Documentação:** ADR 0116 (`agents-ia/decisoes/0116-kazeditor-texto-do-tema-em-atributos.md`) e entrada KZ-23 no `docs/CHANGELOG.md`.

## Arquivos alterados/novos neste pacote
| Caminho | Ação |
| --- | --- |
| `dev/admin/view/js/kaz-canvas.js` | alterado (bloco KZ-23 + integração no painel + delegação) |
| `dev/admin/view/js/kaz-element-attrs.js` | alterado (regras puras + export) |
| `dev/admin/language/{pt-br,en-us,es-eu}/design/theme.php` | alterado (+2 chaves cada) |
| `tests/fixtures/kaz_theme_attr.json` | novo |
| `tests/check_kaz_theme_attr.js` | novo (47 conferências Node) |
| `tests/Unit/Library/KazThemeAttrTest.php` | novo (contrato estático PHP) |
| `agents-ia/decisoes/0116-kazeditor-texto-do-tema-em-atributos.md` | novo (ADR 0116) |
| `docs/CHANGELOG.md` | alterado (entrada KZ-23) |

## Validação
- `node tests/check_kaz_theme_attr.js` → **47 conferências OK** (inclui 2 estragos propositalais acusados).
- `node --check` OK em `kaz-canvas.js`, `kaz-element-attrs.js`, `check_kaz_theme_attr.js`.
- Lado servidor não muda: `ThemeStrings` e o controller continuam os de KZ-19/KZ-21/KZ-22.

## Limites / pendências
- Só atributo cujo valor inteiro é a expressão; texto fixo misturado com `theme_text` fica no bloco JSON.
- Um idioma por vez (o do canvas); texto com quebra de linha continua recusado (`theme_lines`).
- Edição inline na página (duplo clique) não se estende a atributos nesta etapa — só o painel.
- Traduções en-us/es-eu sem revisão de falante nativo.
- Sem migration, sem tabela/coluna/rota nova.

## Como aplicar
Copiar os arquivos do ZIP sobre a instalação (caminhos preservados a partir da raiz do projeto) e recarregar a tela do editor de tema. Limpar cache de CDN/proxy se houver.
