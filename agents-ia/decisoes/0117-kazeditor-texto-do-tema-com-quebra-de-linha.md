# ADR 0117 — KAZEditor-Theme: texto do tema com quebra de linha

| | |
| --- | --- |
| **Status** | ✅ aceita (KZ-24, 2026-11-17) |
| **Data** | 2026-11-17 |
| **Contexto** | A KZ-21 (ADR 0114) deixou anotado: texto do tema com quebra de linha continuava editável somente no bloco JSON. Porém o servidor (`ThemeStrings::isText`) **já aceitava** `\n` (controle, menos a quebra de linha) — só o editor recusava (`theme_lines`) e colava a quebra em espaço (`themeClean` herdava `inlineClean`). |
| **Relacionadas** | ADR 0112 (textos por idioma), ADR 0114 (edição por idioma), ADR 0111 (texto na página, KZ-18) |

## Decisão

Esta ADR decide a **vigésima quarta etapa** do caminho B. A mudança é toda no editor (`kaz-canvas.js`); o servidor não muda.

1. **O texto do tema aceita quebra de linha.** `themeClean` passa a normalizar `CRLF`/`CR` para `\n`, trocar tabulação por espaço, remover os demais caracteres de controle e manter o teto de 2000 caracteres — exatamente o que o servidor aceita.
2. **`themeWhy` não recusa mais `theme_lines`**: um texto do tema com `\n` abre para edição no painel e na página.
3. **Painel:** o campo "Texto do tema" (já um `<textarea>`) grava quebras de linha no JSON dos textos, no idioma do canvas, pelo mesmo ponto único da KZ-21.
4. **Caixa na página:** texto do tema com quebra abre em **caixa própria** (modo `box`, como já acontecia com filtro de caixa); se a quebra entra durante a edição, a caixa vira `box` na hora. Na caixa, **Enter quebra linha** e **Ctrl/Cmd+Enter conclui**; Esc continua desfazendo (devolve o JSON byte a byte).
5. **Filtro `nl2br` reconhecido:** `THEME_REF` aceita `upper`, `lower` e `nl2br`. No site, a quebra aparece onde a fonte usar `|nl2br`; na prévia do quadro, `setElementText` aplica `white-space: pre-line` no elemento enquanto o texto tiver `\n` (escrita leve só na prévia, como os tokens da KZ-03).
6. **Ajuda nova:** chave `text_canvas_theme_lines_help` nos três idiomas explica que a quebra aparece no site onde a fonte usar `nl2br`. A chave antiga `theme_lines` permanece definida nos idiomas (uso zero), sem quebrar quem mantém idioma próprio.

## Limites conhecidos
- A caixa "sobre o texto" (modo `place`) não comporta multilinha: texto do tema com `\n` sempre usa caixa própria.
- Sem `nl2br` na fonte, o site mostra o texto em uma linha (comportamento do HTML); o editor não força quebra na renderização real.
- Traduções en-us/es-eu sem revisão de nativo.

## Consequências

- Arquivos: `dev/admin/view/js/kaz-canvas.js` (THEME_REF, themeClean, themeWhy, setElementText, inlineKey, inlineInput, inlinePlace, cabeçalho), 3 arquivos de idioma (`text_canvas_theme_lines_help`).
- Testes: `tests/fixtures/kaz_theme_lines.json` + `tests/check_kaz_theme_lines.js` (40 conferências Node, com estrago proposital) + `tests/Unit/Library/KazThemeLinesTest.php` (servidor + contrato estático PHP).
- Nenhuma tabela, coluna, rota ou chave de configuração nova; nenhuma migration; o servidor não muda.
