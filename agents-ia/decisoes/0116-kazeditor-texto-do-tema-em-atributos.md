# ADR 0116 — KAZEditor-Theme: texto do tema dentro de atributo editável no painel

| | |
| --- | --- |
| **Status** | ✅ aceita (KZ-23, 2026-11-17) |
| **Data** | 2026-11-17 |
| **Contexto** | A KZ-21 (ADR 0114) deixou uma pendência anotada: texto do tema dentro de atributo (`aria-label`, `alt`, `placeholder` e os `data-du-*` que a cena 3D lê — avisos como "Orbe coletado" e a palavra "Fase" do HUD no David Universe 3D) continuava editável somente no bloco JSON "Textos do tema por idioma". Quem edita o tema não conseguia trocar esses textos vendo a página. |
| **Relacionadas** | ADR 0112 (textos do tema por idioma), ADR 0114 (edição por idioma no canvas), ADR 0098 (atributos do elemento, KZ-05) |

## Decisão

Esta ADR decide a **vigésima terceira etapa** do caminho B. A mudança é toda no editor; o servidor não muda.

1. **Um atributo cujo valor é só `{{ theme_text.chave }}`** (com, no máximo, um filtro de caixa `upper`/`lower`) passa a ser reconhecido como texto do tema editável, nos atributos `alt`, `aria-label`, `placeholder` e qualquer `data-du-*` (`/^data-du-[a-z0-9-]+$/`). A regra pura fica em `kaz-element-attrs.js` (`themeAttrName` / `themeAttr`), exportada em `KazElementAttrs`.
2. **O painel "Elemento" mostra um campo de texto do tema** para cada um desses atributos (input com o texto atual no idioma do canvas), igual ao campo da KZ-21: mesmo idioma gravado (o do seletor "Idioma"), mesmo aviso de fallback ("Sem tradução neste idioma…"), mesmos limites (`themeWhy`/`themeWrite` de KZ-21). `alt` e `aria-label` que já tenían campo próprio (KZ-05/KZ-07) usam o novo caminho quando são `theme_text`; `placeholder` e `data-du-*` são varridos no mapa do elemento.
3. **A escrita vai para o JSON dos textos (`strings_json`), no idioma do canvas.** A fonte do tema não muda — `onThemeAttrInput` chama `themeWrite` + `themeCommit`, o mesmo ponto único de escrita da KZ-21. O campo do formulário real recebe o JSON novo e o formulário é marcado como alterado.
4. **Prévia imediata:** `setThemeAttr` troca só aquele atributo (`setAttribute`), com o filtro de caixa aplicado (`themeShown`), em todos os quadros e em todos os elementos que usam aquela chave. Nada mais no quadro muda.
5. **Atributo com outra expressão variável** (ex.: `{{ project.title }}`) continua como antes: aviso "vem de dado do site", sem campo. Texto com quebra de linha continua recusado (`theme_lines`), como na KZ-21.
6. **Segurança:** nenhuma barreira nova é necessária — a escrita não toca a fonte do tema (que passa pelo sandbox no servidor ao salvar); o valor digitado é texto simples gravado como dado em `strings`, escapado na saída pela fonte. O quadro continua sem script.

## Limites conhecidos

- Só atributo cujo valor inteiro é a expressão; texto fixo misturado com `theme_text` continua no bloco JSON.
- Um idioma por vez (o do canvas), como na KZ-21.
- A edição inline na página (duplo clique, KZ-18/KZ-21) não se estende a atributos nesta etapa: só o painel.

## Consequências

- Arquivos: `dev/admin/view/js/kaz-element-attrs.js` (regras puras + export), `dev/admin/view/js/kaz-canvas.js` (bloco KZ-23: `attrThemeRefs`, `setThemeAttr`, `onThemeAttrInput`, `paintThemeAttrField`; integração em `valueField`/`paintAttrs`; delegação de input), 3 arquivos de idioma (`text_canvas_theme_attr`, `text_canvas_theme_attr_help`).
- Testes: `tests/fixtures/kaz_theme_attr.json` + `tests/check_kaz_theme_attr.js` (Node, 47 conferências) + `tests/Unit/Library/KazThemeAttrTest.php` (contrato estático PHP).
- Nenhuma tabela, coluna, rota ou chave de configuração nova; nenhuma migration.
