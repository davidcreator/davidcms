# ADR 0111 — KAZEditor-Theme: texto editado direto na página (dois cliques num título, parágrafo, link ou botão do canvas abrem o texto para digitar no lugar)

| | |
| --- | --- |
| **Status** | ✅ aceita (KZ-18, 2026-11-16) |
| **Data** | 2026-11-16 |
| **Contexto** | Desde a [ADR 0098](0098-kazeditor-atributos-do-elemento.md) o texto de um elemento da página é trocado no painel "Elemento", à direita do canvas: a pessoa clica no elemento, procura o campo "Texto" no painel e digita lá, olhando para um lugar e escrevendo em outro. A [ADR 0083](0083-kazeditor-canvas-pagina-real.md) lista o texto editado no lugar entre as ferramentas de desenho da quarta etapa do canvas, e nenhuma delas existia. O duplo clique no canvas só enquadrava um quadro. |

## Decisão

Esta ADR decide a **décima oitava etapa** do caminho B. É a primeira ferramenta de desenho sobre o canvas de página: **dois cliques num texto abrem esse texto para digitar no lugar em que ele aparece.** As regras de escrita são as do painel "Elemento"; o formato do tema salvo não muda.

### O gesto

1. **Dois cliques num texto fixo do canvas abrem uma caixa de edição sobre ele,** com o cursor no fim. Vale para título, parágrafo, link, botão, item de lista: qualquer elemento que o painel já deixava editar pelo campo "Texto".
2. **Pelo teclado:** com um elemento escolhido, `Enter` ou `F2` abrem a caixa com o texto todo selecionado.
3. **`Enter` conclui. `Esc` desfaz** tudo o que foi digitado desde que a caixa abriu, e a fonte do trecho volta byte por byte ao que era. **Clicar fora ou sair com `Tab` conclui** (fica o que foi digitado).
4. **Fora de um texto, o duplo clique faz o que fazia:** enquadra o quadro clicado; no fundo do canvas, mostra todos.
5. **Quando o texto não pode ser editado ali, um aviso curto diz por quê** e a caixa não abre. O aviso aparece no alto do canvas por 5 segundos, com `role="status"`:

   | Caso | Aviso (pt-br) |
   | --- | --- |
   | O texto vem de um dado do site (conteúdo ou idioma) | "Este texto vem dos dados do site (conteúdo ou idioma) e não é editado aqui." (chave nova `inline_variable`) |
   | O texto mistura palavras fixas e dados | o aviso que o painel já mostrava (`el_mixed`) |
   | O trecho ainda usa o padrão do sistema | "Este trecho ainda usa o padrão do sistema. Use "Personalizar este trecho", no painel, para editar o texto." (chave nova `inline_need_customize`) |
   | O código do trecho mudou por outro caminho e o mapa ficou defasado | o aviso que o painel já mostrava (`el_stale`) |

6. **Um elemento que tem outros elementos dentro** (um parágrafo com um trecho em negrito, por exemplo) **não abre e não avisa:** o duplo clique enquadra o quadro, como antes. O painel também não oferece o campo "Texto" para ele.

### A caixa

7. **A caixa é um `<textarea>` do próprio admin,** posto por cima do quadro (`.kaz-canvas__inline`, dentro de `.kaz-canvas__framebody`). **O quadro continua sem script:** o `iframe` segue com `sandbox="allow-same-origin"`, e nada dentro dele recebe foco ou tecla.
8. **Modo "sobre o texto" (o comum):** a caixa é transparente e copia a letra do elemento (família, tamanho, peso, estilo, espaçamento entre letras e entre palavras, altura da linha, caixa alta, alinhamento, recuo e cor, com a opacidade dos elementos de fora multiplicada na cor). O texto digitado aparece no lugar exato do texto da página, com as mesmas quebras de linha.
9. **Enquanto a caixa está aberta, o elemento da prévia deixa de pintar o próprio texto,** para as letras não aparecerem em dobro. Isso é feito com duas propriedades, e só com elas: `-webkit-text-fill-color: transparent` e `text-shadow: none`, escritas por `style.setProperty` e devolvidas ao valor anterior quando a caixa fecha. Fundo, borda e tamanho do elemento continuam os da página. Nada disso entra na fonte do tema.
10. **A posição vem de uma linha medida, não de uma conta.** O canvas mede no documento do admin uma linha com a letra da caixa (`inlineLine`) e alinha o topo da primeira linha da caixa com o topo do texto da página. Quando a linha é mais baixa que a letra, a sobra vira margem interna da caixa, para a letra não ser cortada.
11. **Modo "caixa própria":** quando não dá para imitar o texto, a caixa abre logo abaixo do elemento (acima, se não couber), escura, com letra de tela, e o texto da página continua visível e acompanha o que é digitado. Acontece em seis casos: a letra é uma fonte que só o tema carrega (`@font-face`); o elemento ou um de fora está girado ou transformado; o texto é vertical; é da direita para a esquerda; a letra é vazada (preenchimento transparente); ou é um link (elemento `inline`) que quebra de linha, ou um texto de várias linhas com conteúdo posto pelo CSS (`::before`, `::after`).
12. **Letra pequena demais aproxima a câmera.** Se a letra do elemento ficaria com menos de 12 px na tela, o canvas aproxima até 12 px. Ao fechar com `Enter` ou `Esc`, se a pessoa não mexeu no zoom, a câmera volta para onde estava. Ao fechar clicando fora, a câmera fica.
13. **A câmera acompanha o cursor:** se o texto sai da área visível, o canvas desloca a página o necessário.
14. **A caixa tem nome para o leitor de tela** (`aria-label`, chave nova `inline_label`): "Texto do elemento, editado na página. Enter conclui; Esc desfaz." O contorno de foco é o do canvas (lilás), e o contorno do elemento escolhido some enquanto a caixa está aberta.
15. **Dentro da caixa, as teclas são texto.** Os atalhos do canvas e da cena (`1`, `0`, `R`, `+`, `-`, setas, `Home`, `Delete`, espaço) não disparam. O mouse dentro da caixa também é dela: clicar põe o cursor, dois cliques selecionam a palavra, arrastar seleciona.

### A escrita

16. **Cada tecla passa pela mesma função do campo "Texto" do painel** (`onTextInput`). A caixa não tem um segundo caminho de escrita: os sinais especiais são codificados (`&` vira `&amp;`, `<` vira `&lt;`), só o texto daquele elemento muda na fonte, e os limites são os do painel.
17. **Uma linha só.** Quebras de linha e tabulações, digitadas ou coladas, viram um espaço. No máximo 2000 caracteres por texto.
18. **Trecho no tamanho máximo (131072 caracteres):** a letra que não cabe não entra, a caixa volta ao último texto aceito e o aviso do painel (`el_full`) aparece no alto do canvas.
19. **Os três quadros e o painel acompanham.** O que é digitado aparece na hora nos quadros de computador, tablet e celular e no campo "Texto" do painel. 700 ms depois da última tecla o servidor redesenha a página, como já fazia; **a caixa continua aberta**, com o cursor onde estava, e se reposiciona sobre o elemento novo.
20. **Nada é gravado até salvar.** A edição muda o campo do trecho no formulário, como qualquer outra edição do canvas. Recarregar a página sem salvar devolve o texto antigo.
21. **Um elemento que se repete por um laço** tem um texto só na fonte: editar uma ocorrência muda todas.

### Textos e versão

22. **Quatro textos novos por idioma** (`pt-br`, `en-us`, `es-eu`), em `design/theme.php`: `text_canvas_inline_help`, `text_canvas_inline_label`, `text_canvas_inline_variable`, `text_canvas_inline_need_customize`. O primeiro é uma linha nova no painel, sob o campo "Texto": "Dois cliques no texto, na própria página, também abrem a edição. Enter conclui; Esc desfaz."
23. **Dois textos reescritos:** o rótulo do quadro (`text_canvas_frame_hint`) passa a dizer "Duplo clique para focar o quadro; em um texto, para editar", e a linha de ajuda sob o canvas (`text_canvas_hint`) troca "Duplo clique foca um quadro" por "Duplo clique edita um texto ou foca um quadro". Para a linha de ajuda continuar em uma linha nas mesmas larguras de janela, dois itens dela foram encurtados ("Clique para selecionar", "Ctrl + rolagem ou pinça: zoom"); nos três idiomas o texto novo não é mais comprido que o anterior.
24. **Os endereços de `kaz-canvas.js`, `kaz-canvas.css` e `kaz-element-attrs.js` passam a terminar em `-kaz-canvas-kz18`.** O terceiro usa a mesma versão e muda de endereço sem mudar de conteúdo.

### Testes

25. **`tests/check_kaz_inline_text.js` (182 conferências)** recorta do script as seis funções puras (`inlineWhy`, `inlineClean`, `inlineMode`, `inlineGeometry`, `inlineInk`, `inlineShift`) e roda casos em cada uma; confere pontos fixos do script, da folha de estilo e dos textos; e no fim estraga o recorte de oito formas, uma por vez, e exige que os casos acusem cada uma.
26. **`KazInlineTextTest` (7 testes)** fixa: a caixa é um `textarea` do admin e o quadro continua sem script; a digitação passa por `onTextInput` e por mais nenhum caminho; só as duas propriedades de pintura são escritas no elemento da página, e as duas são devolvidas; o duplo clique abre texto e mantém o gesto antigo no resto; as regras de estilo da caixa e do aviso; os textos nos três idiomas, escritos no teste; e roda o conferidor do item 25.

## O que muda na tela

- **Canvas de página:** dois cliques num texto abrem a edição no lugar. Um aviso curto aparece no alto do canvas quando o texto não pode ser editado ali.
- **Painel "Elemento":** uma linha a mais sob o campo "Texto", contando que o texto também se edita na página.
- **Linha de ajuda sob o canvas e rótulo do quadro:** falam dos dois usos do duplo clique.
- **O resto do desenho é o mesmo.** Os 15 painéis comparados antes e depois estão iguais nos dois temas de teste (722 e 490 peças).

## Alternativas consideradas

- **`contenteditable` dentro do quadro.** Recusado: o quadro é um `iframe` em sandbox sem script (ADR 0083), e é isso que impede o código do tema de rodar dentro do admin. Editar dentro dele pediria foco e eventos lá dentro, e o navegador escreveria no elemento marcação que não é do tema.
- **Um segundo caminho de escrita, só da caixa.** Recusado: as regras de codificar o texto, de limite e de mapa defasado já existem em `onTextInput`. Uma tentativa de separar a escrita em outra função quebrou cinco testes que fixam esse caminho, e foi desfeita.
- **Calcular a posição da caixa pelo `line-height` do elemento.** Recusado depois de medido: o texto da caixa ficava cerca de 1 px fora do texto da página (de 14% a 79% dos pontos de tinta diferentes). Medir uma linha de verdade com a letra da caixa resolveu.
- **Sempre a caixa própria** (escura, abaixo do elemento). Dispensado: funciona em qualquer caso, mas a pessoa volta a olhar para um lugar e escrever em outro. Ficou como reserva para os seis casos do item 11.
- **Aceitar várias linhas** (`Enter` como quebra). Fora do escopo: uma quebra dentro do texto pede `<br>` ou outro parágrafo na fonte, e isso é mexer na estrutura, não no texto.
- **Esconder o texto da prévia antes de a página redesenhada terminar de carregar.** Dispensado: para decidir o modo da caixa o canvas precisa ler a letra e a cor do elemento novo antes de escondê-lo, e antes do fim do carregamento o estilo ainda pode não estar aplicado. Fica o limite anotado abaixo.

## Limites conhecidos

- **Só texto fixo de elemento sem outros elementos dentro** (item 6). Parágrafo com negrito ou link no meio continua pelo código.
- **Uma linha só** (item 17). Não dá para criar parágrafo novo nem quebra de linha.
- **Por uma fração de segundo depois de cada redesenho** (700 ms depois da última tecla), o texto da página nova aparece sob o texto da caixa, no mesmo lugar, até o canvas medir a página. Medido: de 50 a 300 ms. As letras ficam um pouco mais fortes nesse instante.
- **Fechar clicando fora ou com `Tab` deixa a câmera onde está.** Se a caixa abriu aproximando (item 12), a página continua de perto; `1` mostra tudo de novo.
- **Com a câmera muito de perto, um título pode ficar mais largo que a área visível.** A caixa abre, e a câmera mostra a parte onde está o cursor.
- **Um elemento da prévia que não tinha o atributo `style` fica com ele vazio (`style=""`) depois de editado.** É só na prévia; a fonte do tema e a página publicada não recebem nada.
- **O limite de 131072 é contado em caracteres na tela e, pelo que foi observado, em bytes no servidor.** Um trecho com acentos a 2 caracteres do limite foi aceito pela tela e o servidor respondeu com a versão salva. Já era assim no painel; não foi mexido nesta etapa.
- **A fonte carregada pelo tema, o texto girado, o vertical, o da direita para a esquerda, a letra vazada e o link de várias linhas foram provados mudando o estilo de um elemento na prévia,** porque os dois temas de teste não têm esses casos. O texto de várias linhas com `::before` ou `::after` e o texto com elementos dentro só têm prova nas funções, não na tela.
- **Não foi provado:** toque (dois toques num tablet), leitor de tela de verdade, acentos por composição (IME) e `Ctrl + Z` dentro da caixa.
- **Conferido só no Chromium.** As traduções para inglês e espanhol não passaram por revisão de um falante nativo.

## Verificação

- 1686 testes (46341 conferências) em PHP 8.2 e em PHP 8.3; PHPStan sem erro; os dez conferidores em node passam.
- No Chromium, 613 conferências no tema "Cena Real" e 588 no David Creator (`pt-br` completo; `es-eu` e `en-us` resumidos): abertura sobre o texto em uma amostra de elementos, com a comparação ponto a ponto entre o texto da caixa e o da página; `Esc`, `Enter`, `Tab` e clique fora; digitar devagar atravessando redesenhos; sinais especiais; colar com quebras; os dois limites; os três quadros; os doze casos especiais do item 11 e vizinhos; os avisos; página cheia; e nenhum erro no console.
- 16 conferências de salvar de verdade: tema novo, texto editado na página, salvo, reaberto, fonte igual byte por byte, exportação com as mesmas chaves e sem nada da caixa.
- Trocas de propósito (cada uma desfaz um ponto da etapa): os testes acusam 64 de 64; o navegador acusa 63 de 63. Na primeira rodada do navegador foram 59 de 62: duas trocas não mudavam nada na tela e foram substituídas, uma prova de linha baixa foi apertada, e uma troca que deixava o script com erro de sintaxe foi reescrita.
- Os roteiros de navegador das etapas KZ-05 a KZ-17 continuam passando (71 execuções com as desta etapa).
