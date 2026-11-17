# RELATORIO-FINAL-KZ24 — KAZEditor-Theme: texto do tema com quebra de linha

**Etapa:** KZ-24 (ADR 0117) · **Data:** 2026-11-17 · **Base:** HEAD pós-KZ-23 (`235271c`)
**Pacote:** `ReamurCMS-Frente-KZ24-Texto-do-Tema-Quebra-de-Linha.zip` · **Não é release**

## O que foi entregue
Fecha a segunda pendência da KZ-21 (ADR 0114): o servidor (`ThemeStrings`) **já aceitava** `\n`, mas o editor recusava (`theme_lines`) e colava a quebra em espaço. Agora o texto do tema aceita quebra de linha.

- **`themeClean` preserva `\n`:** CRLF/CR → `\n`, tab → espaço, demais controles removidos, teto de 2000 caracteres (igual ao servidor).
- **Painel:** o campo "Texto do tema" grava quebras no JSON (`strings_json`), no idioma do canvas, pelo mesmo ponto único da KZ-21 (`themeCommit`).
- **Caixa na página:** texto do tema com `\n` abre em caixa própria (modo `box`); se a quebra entra durante a edição, a caixa vira `box` na hora. **Enter quebra linha, Ctrl/Cmd+Enter conclui**, Esc devolve o JSON byte a byte.
- **Filtro `nl2br` reconhecido** em `THEME_REF` (com `upper`/`lower`): no site a quebra aparece onde a fonte usar `|nl2br`; na prévia do quadro, `setElementText` aplica `white-space: pre-line` enquanto houver `\n`.
- **Idiomas:** chave nova `text_canvas_theme_lines_help` em `en-us`, `pt-br`, `es-eu` (sem revisão de nativo). A chave antiga `theme_lines` segue definida, sem uso.

## Arquivos neste pacote
| Caminho | Ação |
| --- | --- |
| `dev/admin/view/js/kaz-canvas.js` | alterado (KZ-24: THEME_REF, themeClean, themeWhy, setElementText, inlineKey, inlineInput, inlinePlace) |
| `dev/admin/language/{pt-br,en-us,es-eu}/design/theme.php` | alterado (+1 chave cada) |
| `tests/fixtures/kaz_theme_lines.json` | novo |
| `tests/check_kaz_theme_lines.js` | novo (40 conferências Node) |
| `tests/Unit/Library/KazThemeLinesTest.php` | novo (servidor + contrato estático PHP) |
| `agents-ia/decisoes/0117-kazeditor-texto-do-tema-com-quebra-de-linha.md` | novo (ADR 0117) |
| `docs/CHANGELOG.md` | alterado (entrada KZ-24) |

## Validação
- `node tests/check_kaz_theme_lines.js` → **40 conferências OK** (com estrago proposital acusado).
- Conferidores anteriores continuam verdes: KZ-23 (47 OK).
- `node --check` OK em `kaz-canvas.js`.
- Servidor não muda: `ThemeStrings` continua aceitando `\n` e recusando controle; sem migration.

## Limites / pendências
- Caixa "sobre o texto" (modo `place`) não comporta multilinha: texto do tema com `\n` usa sempre caixa própria.
- Sem `nl2br` na fonte, o site mostra o texto em uma linha (HTML padrão); o editor não força quebra na renderização real.
- Traduções en-us/es-eu sem revisão de falante nativo.

## Como aplicar
Descompactar o ZIP sobre a raiz do projeto (caminhos preservados) e recarregar a tela do editor de tema.
