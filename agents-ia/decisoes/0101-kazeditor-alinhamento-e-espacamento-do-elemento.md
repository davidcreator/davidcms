# ADR 0101 — KAZEditor-Theme: alinhamento e espaçamento por elemento

| | |
| --- | --- |
| **Status** | ✅ aceita (KZ-08, 2026-11-14) |
| **Data** | 2026-11-14 |
| **Contexto** | Com as ADRs [0098](0098-kazeditor-atributos-do-elemento.md) a [0100](0100-kazeditor-descricao-de-imagens-e-desenhos.md), o painel **Elemento** edita link, texto alternativo, rótulo, cor e tamanho do texto. Afastar um título do parágrafo de baixo, dar respiro dentro de um cartão ou centralizar um texto ainda pedia abrir o código do trecho. Os pacotes de `docs/temas` já usam esse recurso direto na tag: 75 lados de margem ou de espaço interno escritos em `style` (`margin-top:40px`, `padding-bottom:84px`), 9 declarações gerais (`margin:0 auto 26px`) e 3 alinhamentos. Ficou também da ADR 0100 uma pendência de conteúdo: 38 desenhos (`<svg>`) sem descrição em três pacotes. |

## Decisão

Esta ADR decide a **oitava etapa** do caminho B, em duas partes. O alcance foi escolhido pelo responsável pelo projeto: limpar os pacotes; margem e espaço interno com os quatro lados cada; número livre em px de 0 a 400; alinhamento do texto.

### Parte 1 — os pacotes saem sem desenho pendente

1. **Os 38 desenhos sem descrição foram marcados como enfeite** (`aria-hidden="true"`) nos arquivos `Atlas-Studio`, `Atlas-Studio-3D` e `David-Creator` de `docs/temas` (13, 13 e 12). Todos são ícones de cartão ou de passo, ao lado de um título que já diz o mesmo; nenhum está dentro de link ou botão. A marca foi escrita como o botão **É só enfeite** escreve: logo depois do nome da tag. O `checksum` de cada pacote foi recalculado.
2. **Um teste impede a volta:** os seis pacotes são lidos pelo mesmo leitor que o editor usa, e qualquer imagem ou desenho pendente faz o teste falhar. O `checksum` de cada pacote também é conferido.
3. **Site que já importou um pacote não muda.** O tema importado fica no banco; a correção só chega com uma nova importação do arquivo, ou marcando os desenhos no editor.

### Parte 2 — alinhamento e espaçamento no painel Elemento

4. **Nove campos novos, na seção "Alinhamento e espaçamento":** **Alinhamento do texto** (à esquerda, centralizado, à direita, justificado) e dois grupos de quatro números, **Margem: espaço por fora** e **Espaço interno: por dentro**, cada um com **Acima**, **Direita**, **Abaixo** e **Esquerda**. O painel escreve `text-align`, `margin-top` … `margin-left` e `padding-top` … `padding-left` no atributo `style` da própria tag, em `templates[chave]`. Nada é gravado até salvar o rascunho.

5. **Valores aceitos.** Lado: número de 0 a 400, com até duas casas decimais, gravado em px (`24` vira `24px`; `08.50` vira `8.5px`). Alinhamento: só os quatro valores da lista. O resto é recusado com aviso e o código continua com o valor anterior. Um número digitado pela metade (`1e`) não apaga o que estava escrito.

6. **Campo vazio quer dizer "como o tema define".** Apagar o número (ou escolher "Como o tema define" na lista) retira só aquela declaração. Se não sobrar nenhuma, o atributo `style` sai e o código volta ao que era, byte a byte. Zero é um valor: `0` escreve `0px`. Em cada lado, o número em cinza é o que a página usa hoje naquele elemento, medido no navegador.

7. **O editor nunca escreve nem reescreve uma declaração geral.** Quando a tag já tem `margin: 0 auto 26px` (ou `padding`, `margin-inline`, `padding-block` e as outras formas que valem para mais de um lado), ela fica como está. O lado preenchido entra **no fim** do `style`, onde vence a declaração geral só naquele lado; os outros lados continuam com ela. O painel mostra a declaração geral que a tag tem, para o número em cinza fazer sentido. Apagar o lado devolve o código original.

8. **Lado que o código anula fica só para leitura.** Se a tag escreve um lado **antes** de uma declaração geral do mesmo grupo (`margin-top:5px; margin:0`), o navegador ignora o lado. Trocar o número não mudaria a página, então o campo fica travado, com a explicação, e a regra de escrita recusa a mudança mesmo se a trava do campo for retirada.

9. **O que o autor escreveu de outro jeito é mantido e mostrado.** Valor negativo, `auto`, outra unidade (`1.5em`, `2rem`), `calc(…)`, valor com `!important` ou alinhamento fora da lista (`start`, `end`): o campo fica vazio e a nota **Escrito no código** mostra a declaração como está. Ela só muda se outro valor for preenchido no campo.

10. **A mudança aparece na hora** nos três quadros (celular, tablet, computador) e o contorno do elemento escolhido acompanha a caixa nova, enquanto o servidor redesenha a página. A prévia passa a copiar para a página o `style` inteiro da tag, lido de volta do código, e não mais uma propriedade por vez: assim, retirar um lado faz a declaração geral voltar a valer na prévia como vai valer no site. Vale também para cor e tamanho (ADR 0099).

11. **Onde cada campo aparece.**
    - Os oito lados: em qualquer tag, menos as que não têm caixa (`br`, `wbr`, `source`, `track`, `param`, `area`, `map`, `col`).
    - O alinhamento: nas tags de texto e de bloco; não em imagem, vídeo, áudio, `canvas`, outros conteúdos embutidos, desenho (`svg`) e linha (`hr`), onde ele não tem efeito sobre o próprio elemento.
    - **Elemento que corre dentro da linha do texto** (`span`, `strong`, `a` com `display: inline`): margem acima, margem abaixo e alinhamento ficam travados, com uma frase dizendo para escolher o bloco em volta. Os lados que funcionam ali (margem à esquerda e à direita, espaço interno) continuam abertos. Se um desses já estiver escrito no código, o campo fica aberto para poder ser retirado.

12. **Quando o editor não escreve**, com a explicação no painel: `style` que vem do conteúdo do site (`style="margin-top: {{ folga }}"`); `style` que o editor não lê com segurança (aspas, entidade, comentário); um dos nove campos escrito duas vezes na mesma tag; tag que o leitor do servidor não entende (Twig entre os atributos). Trecho padrão do sistema pede **Personalizar este trecho** antes. Código mudado por fora e ainda não lido de novo pelo servidor: a escrita é recusada com aviso.

13. **Cada família se trava sozinha.** `color` repetido na tag fecha os campos de cor, e não os de espaçamento; `margin-top` repetido fecha os de espaçamento, e não os de cor. Antes desta etapa, qualquer repetição fechava tudo.

14. **Aviso quando o tema vence.** Se uma regra do tema marcada `!important` ganha do que foi escrito, o painel diz "A página mostra outro valor neste elemento", sob o alinhamento ou sob o grupo. O painel não escreve `!important`.

15. **O servidor não muda.** `Theme\ThemeSourceMap` já localiza `style` em qualquer tag (ADR 0099). Ler, validar e escrever fica em `admin/view/js/kaz-element-attrs.js` (`layoutOf`, `pxOf`, `spaceOk`, e `setStyle` estendido), sem página, rede ou armazenamento. O verificador novo é `tests/check_kaz_element_space.js`.

## Alternativas consideradas

- **Um número só por grupo (os quatro lados iguais):** mais simples, mas o uso mais comum é afastar para um lado só (título do parágrafo de baixo). O responsável escolheu os quatro lados.
- **Lista de passos fixos (0, 8, 16, 24 …):** evita valores quebrados, mas não deixa repetir um valor que o tema já usa (os pacotes têm 18, 26, 44, 84). O responsável escolheu número livre, como no tamanho do texto.
- **Reescrever a declaração geral (`margin: 0 auto 26px` → `margin: 0 auto 40px`):** exigiria interpretar e remontar as formas de um a quatro valores, com `auto`, `calc()` e variáveis, e mudaria um texto que o autor escreveu. Pôr o lado no fim dá o mesmo resultado na página, mexe em um ponto só e se desfaz sem deixar marca.
- **Aceitar valor negativo, `auto` e outras unidades no campo:** `auto` centraliza, negativo sobrepõe elementos, `em` depende do tamanho do texto: cada um pede uma explicação própria no painel. Continuam possíveis pelo código, e o painel mostra o que está escrito.
- **Alinhamento por classe do tema em vez de `style`:** cada tema dá um nome diferente à classe (ou não tem), e o editor teria de conhecer o CSS de cada pacote. O `style` da tag funciona em qualquer tema e é onde cor e tamanho já são escritos.
- **Valores lógicos (`start` / `end`, `margin-inline-start`):** são os corretos para sites em idioma escrito da direita para a esquerda. Os três idiomas do sistema são da esquerda para a direita, e os pacotes usam `left` / `center`. Valor lógico escrito pelo autor é mantido e mostrado.
- **Travar todos os campos em elemento dentro da linha:** esconderia os lados que funcionam ali (margem à esquerda e à direita). Travar só os três que não têm efeito, com a frase, mostra por quê.
- **Deixar os 38 desenhos para quem usa o pacote:** foi o que a ADR 0100 fez. Um pacote de exemplo que abre com 13 pendências ensina o hábito errado; a marca é uma linha por desenho e o teste impede que voltem.

## Limites conhecidos

- **Só essas nove propriedades.** Largura, altura, borda, canto arredondado, sombra, fonte, posição e espaço entre itens (`gap`) ficam de fora.
- **Vale para todas as larguras de tela.** O `style` da tag não tem como dizer "só no celular"; margem de 80 px vale nos três quadros. Para valores por largura, o caminho continua sendo o CSS do tema.
- **Elemento em laço (`{% for %}`)** é uma tag só no código: a mudança vale para todas as repetições.
- **O painel não confere o resultado visual.** Espaço interno grande em um botão pequeno, ou margem que empurra o conteúdo para fora do quadro, são aceitos se o número estiver entre 0 e 400.
- **Margens que se juntam:** entre dois blocos, a margem de baixo de um e a de cima do outro não se somam; vale a maior (regra do CSS). O número em cinza mostra a margem do elemento, não a distância que se vê até o vizinho.
- **Alinhamento em elemento que é item de grade ou de `flex`** alinha o texto dentro dele, não o elemento dentro do pai.
- **"Dentro da linha" é medido na página, no primeiro quadro visível** (o do computador, com os três abertos). Um elemento que o tema mostra como bloco em uma largura e dentro da linha em outra é tratado como aparece nesse quadro.
- **Lado anulado por declaração geral** aparece travado junto com o aviso "A página mostra outro valor": as duas frases dizem a mesma coisa de dois jeitos. Não foi unificado.
- **Declaração geral com `!important`** (`margin: 0 !important`) vence o lado que o editor escreve; o painel avisa depois de o servidor redesenhar a página.
- **Valor do autor com `!important`** (`margin-top: 8px !important`): preencher o campo troca o valor inteiro, e o `!important` sai junto. O painel não o escreve de volta.
- **Maiúsculas:** `MARGIN-TOP: 12PX` é lido e mostrado; ao trocar o número, só o valor é reescrito, em minúsculas (`MARGIN-TOP: 20px`).
- **O verificador em Node roda pelo PHPUnit** (`KazElementSpaceTest::testNodeCheckerOfTheSpaceRulesPasses`). Sem `node`, o caso é pulado; o CI tem `node`.
- Conferido em Chromium. Outros navegadores não foram testados.
- Continuam valendo os limites das ADRs 0083 a 0086 e 0098 a 0100.

## Achados registrados, não corrigidos

- Os mesmos das ADRs 0098 a 0100 (desenho quebrado no pacote `David-Universe-3D`; a tela de edição preenche trecho ausente com o arquivo padrão; `pattern` em `theme-condition-builder.js`; três classes sem regra de CSS).
- **`David-Universe-3D`: 30 desenhos com marcas que se contradizem.** Cada um tem `aria-hidden="true"` e, na mesma tag, `role="img"` com `aria-label="Selo do projeto"`. O leitor de tela obedece ao `aria-hidden` e pula o desenho; o rótulo nunca é lido. Não é pendência (o desenho está escondido), e o pacote não foi alterado: decidir se o selo deve ser anunciado é decisão de conteúdo.
- **Link ou botão sem nome** continua sem aviso próprio. Medido nesta etapa: nenhum dos 72 desenhos escondidos dos pacotes está dentro de link ou botão, então a limpeza da Parte 1 não deixou nenhum link sem nome.

## Consequências

- Arquivos novos: `tests/check_kaz_element_space.js`, `tests/Unit/Library/KazElementSpaceTest.php`. Alterados: `admin/view/js/kaz-element-attrs.js`, `admin/view/js/kaz-canvas.js`, `admin/view/css/kaz-canvas.css`, `admin/controller/design/theme.php`, os três arquivos de idioma `admin/language/*/design/theme.php`, três pacotes de `docs/temas`, `tests/check_kaz_element_style.js` (um caso) e os testes `KazElementA11yTest` e `KazElementStyleTest` (conferências que acompanham a versão do script, a prévia e a contagem dos pacotes).
- Versão do arquivo do canvas no endereço: `-kaz-canvas-kz08`. O navegador busca o arquivo novo sem limpar cache.
- Sem tabela, coluna, migration, rota, dependência ou campo novo no `POST` de salvar. Nenhum controle novo tem `name`. Nenhum arquivo do servidor que lê ou valida o tema mudou.
- O que o painel escreve (`margin-top:24px`, `text-align:center`) passa pela validação do tema (`ThemeSandbox`) como qualquer outro código do trecho; há teste.
- O site público não muda. O que muda nele é o que o autor salvar.
- **Mudança de comportamento no admin:**
  - `style` com um dos nove campos repetido deixa de fechar cor e tamanho; `style` com cor repetida deixa de fechar o que não é cor (item 13);
  - a prévia imediata de cor e tamanho copia o `style` inteiro da tag (item 10). O resultado na página é o mesmo de antes;
  - imagem (que não tinha nenhum campo de estilo), desenho e linha (`hr`) ganham os oito lados.
- **Mudança nos pacotes:** `Atlas-Studio`, `Atlas-Studio-3D` e `David-Creator` têm `checksum` novo. Quem guarda o `checksum` antigo desses arquivos em algum lugar precisa atualizar; no repositório, nenhum outro arquivo o cita.
- Testes: `KazElementSpaceTest` (6 casos) e o verificador em Node (1010 conferências). Suíte inteira em PHP 8.2 e 8.3: 1593 testes, 33 223 conferências; PHPStan sem erros.
- O comportamento no navegador foi conferido em Chromium, fora do PHPUnit: um pacote de prova com dezenove situações (95 conferências) e os seis pacotes de `docs/temas` (119 conferências), incluindo preencher, salvar o rascunho, reabrir o editor, desfazer e salvar de novo. Os roteiros das etapas KZ-05 a KZ-07 foram rodados de novo; dois deles foram ajustados (ver abaixo).
- **Roteiros antigos ajustados:** o de KZ-06 contava os títulos do painel e passou a olhar só a parte de cor e tamanho; o de KZ-07 esperava 13, 13 e 12 pendências nos três pacotes e passou a esperar zero. O caminho "descrever um desenho pendente pela lista" deixou de ser exercitado nos pacotes (não há mais pendência neles) e continua coberto pelo pacote de prova de KZ-07.
- Verificação por trocas (um erro posto de propósito no código, para ver se alguma conferência acusa): 93 de 93 acusadas pelos testes. No navegador, a primeira rodada acusou 82 de 89 trocas. Das sete que passaram, cinco mostraram conferências que faltavam no roteiro (parágrafo com Twig na tag, imagem dentro da linha, evento na opção "Escrito no código", alinhamento `inherit`, valor negativo sem aviso de valor forçado); elas foram acrescentadas e as cinco trocas passaram a ser acusadas. Uma (os campos novos contam para a frase "personalize este trecho") só é vista por um texto fixo no PHPUnit, porque nenhum trecho padrão do sistema tem uma tag que receba só os campos de espaçamento; ela está entre as 93. A última (a versão no endereço do script) já era conferida pelo PHPUnit. Uma troca a mais, sobre o passo do campo numérico, entrou depois, com a conferência dela. **A lista do navegador, hoje com 88 trocas, não foi rodada inteira de novo depois desses acréscimos.** Seis trocas ficaram fora da conta porque outra regra dá o mesmo resultado; as quatro do painel foram rodadas e nenhuma muda o que os roteiros veem.

## Próximas etapas (fora desta ADR)

1. **Link e botão sem nome:** avisar quando um link ou botão não tem texto, rótulo nem imagem descrita.
2. **Os 30 selos do pacote `David-Universe-3D`:** decidir se são enfeite (retirar `role` e `aria-label`) ou conteúdo (retirar `aria-hidden`).
3. **Largura máxima e borda por elemento**, se o uso mostrar que faltam.
4. **Os quatro comportamentos de pagamento mantidos na ADR 0097.**
