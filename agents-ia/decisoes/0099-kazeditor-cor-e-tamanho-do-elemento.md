# ADR 0099 — KAZEditor-Theme: cor e tamanho por elemento

| | |
| --- | --- |
| **Status** | ✅ aceita (KZ-06, 2026-11-14) |
| **Data** | 2026-11-14 |
| **Contexto** | Com as ADRs [0086](0086-kazeditor-selecionar-elemento.md) e [0098](0098-kazeditor-atributos-do-elemento.md), clicar em um elemento da página do rascunho abre o painel **Elemento**, que edita o texto, o destino do link, o texto alternativo e o rótulo. Mudar a **cor** ou o **tamanho do texto** de um elemento só ainda exigia achar a tag no código e escrever CSS. O painel **Design** (ADR 0085) muda as cores do tema inteiro, não de um elemento. Os seis pacotes de `docs/temas/` já usam `style="…"` em 106 tags (`Atlas` 26 cada, `David-Creator` 23, `David-Universe-3D` 31, os dois de referência nenhuma). |

## Decisão

Esta ADR decide a **sexta etapa** do caminho B: **o painel Elemento ganha a seção "Cor e tamanho", com cor do texto, cor de fundo e tamanho do texto do elemento selecionado**. O alcance foi escolhido pelo responsável pelo projeto: só tamanho do texto (sem largura, altura, margem ou posição); cor do texto e do fundo; cores do tema e cor livre.

1. **O valor fica no atributo `style` da própria tag.** O painel escreve `color`, `background-color` e `font-size` no `style` do elemento, dentro de `templates[chave]`. Não há folha de estilo gerada, classe nova, tabela nem campo novo no envio: o código do trecho continua sendo a única fonte, e quem abre o código vê exatamente o que o painel fez.

2. **O mapa de elementos passa a localizar o `style`.** `Theme\ThemeSourceMap` acrescenta `style` à lista de atributos lidos em qualquer tag, com as mesmas três naturezas da ADR 0098 (`fixed`, `variable`, `bare`). O servidor só diz **onde** o atributo está; ler e escrever as declarações é trabalho do módulo de regras no navegador.

3. **Regras no módulo sem página.** `admin/view/js/kaz-element-attrs.js` ganha `styleOf` (o que o código tem), `setStyle` (escrever ou retirar uma das três propriedades), `colorOk`, `sizeOk` e `styleParse`. Continua sem tocar em página, rede ou armazenamento; o verificador novo é `tests/check_kaz_element_style.js`.

4. **Lista fechada do que pode ser escrito.**
   - propriedades: `color`, `background-color`, `font-size`. Qualquer outro nome é recusado;
   - cor: `#rgb` ou `#rrggbb`, ou `var(--nome)` com nome em minúsculas, dígitos e hífen (até 48 caracteres);
   - tamanho: número em `px`, de 6 a 400, com até duas casas.
   Nada além disso chega ao código pelo painel: sem `url(`, sem `expression`, sem `;` para emendar outra declaração, sem `!important`. A ADR 0098 recusou "campo livre para qualquer atributo" porque abriria o `style` inteiro; aqui ele é aberto só para essas três propriedades e esses formatos.

5. **O que já existe no `style` é preservado.** Trocar uma propriedade muda só o valor dela; as outras declarações (`margin`, `display`, …) ficam byte a byte iguais. Propriedade nova entra no fim, depois de `;`. Tag sem `style` recebe ` style="propriedade:valor"` logo depois do nome da tag.

6. **Retirar devolve o código ao que era.** Escolher "Como o tema define" (ou apagar o tamanho) retira só aquela declaração. Se não sobrar nenhuma, o atributo `style` sai inteiro, com o espaço dele: desfazer as três mudanças devolve a fonte original, byte a byte. Retirar uma propriedade que não está escrita não muda nada.

7. **Quando o painel não oferece os campos.**
   - `style` com Twig (`style="color: {{ cor }}"`): mostra a expressão e explica que o valor vem do site;
   - `style` que o módulo não lê com segurança (a mesma propriedade duas vezes, entidade, aspa, chave, comentário, parêntese sem par, valor sem aspas): mostra uma frase mandando editar no código;
   - imagem, vídeo, `iframe`, `br` e semelhantes: cor de texto e tamanho de letra não têm efeito neles;
   - tag que o leitor do servidor não entende (regra 2 da ADR 0098).
   Nos seis pacotes, todo `style` fixo é lido sem cair no segundo caso (há teste).

8. **Valor já escrito em outro formato não é perdido.** Um `color:rgba(…)` ou `font-size:1.2rem` do autor aparece como "Escrito no código: …". Só é trocado se o autor escolher outro valor.

9. **Cores do tema.** A lista mostra as variáveis de cor que a página do rascunho declara (`--dj-primary`, `--at-accent`, …), com as do tema antes das do sistema, mais as do painel Design; cada linha traz o nome e a cor atual. Escolher uma escreve `var(--nome)`: se o tema mudar aquela cor depois, o elemento acompanha. "Cor própria" abre o seletor de cor do navegador e escreve o código `#rrggbb`. O seletor abre na cor que a página mostra naquele elemento (para fundo transparente, a cor que está por trás).

10. **O resultado aparece na hora.** A mesma declaração é aplicada nas ocorrências do elemento nos três quadros enquanto o servidor redesenha a página. Diferente dos campos da ADR 0098, aqui há efeito visível, e esperar o redesenho a cada passo do seletor de cor tornaria a escolha impraticável. A prévia só toca as três propriedades, com o valor lido de volta do código já validado.

11. **Aviso quando o tema vence.** Uma regra do tema marcada `!important` ganha de um `style` na tag. Depois de cada redesenho, o painel compara o que está escrito com o que a página mostra e, se forem diferentes, avisa: "A página mostra outro valor neste elemento". O código é escrito mesmo assim; o painel não escreve `!important`.

12. **Trecho padrão do sistema e mapa defasado.** Como nos outros campos: somente leitura até **Personalizar este trecho**; se o código mudou e a página ainda não foi redesenhada, nada é escrito e o painel pede para esperar.

## Correção incluída: o limite de tamanho do código era contado de outro jeito no navegador

Achado ao provar esta etapa; o defeito é anterior a ela (KZ-03).

O servidor aceita até 131 072 **bytes** por trecho, medidos como o formulário chega: letra acentuada vale dois bytes (UTF-8) e cada quebra de linha vale dois (CR LF). O editor comparava o número de **caracteres**. Um trecho com acentos ou muitas linhas, perto do limite, passava na conta do editor e era recusado pelo servidor logo depois ("a fonte passa do tamanho permitido"), com a página voltando ao último rascunho salvo. *(Mudou na KZ-22: pela ADR 0115, o servidor guarda a fonte com `\n` e mede depois disso; cada quebra de linha vale um byte, no servidor e no editor. A conta em bytes continua.)*

A conta passa a ser feita em um lugar só (`KazElementAttrs.tooLong`), do jeito do servidor, e vale para os campos de atributo, de cor e tamanho, o campo Texto e os campos de cor e medida do painel Design. Medido em Chromium: com o trecho a 3 bytes do limite, a mudança que não cabe é recusada no painel com o aviso "esta mudança não foi gravada", e a que cabe é aceita pelo servidor.

## Alternativas consideradas

- **Gerar uma folha de estilo com um seletor por elemento:** pediria um identificador estável em cada tag (classe ou `id` escritos pelo editor), um lugar novo para guardar a folha e uma regra para quando o autor reordena o código. O `style` na tag não precisa de nada disso e some junto com a tag.
- **Guardar as mudanças fora do código (JSON por elemento):** muda o contrato do pacote e pede migration; o código deixaria de dizer como a página fica.
- **Campo de texto livre para o `style`:** é a porta que a ADR 0098 recusou. Lista fechada de propriedades e formatos.
- **Aceitar qualquer cor que o navegador entenda** (`red`, `rgb()`, `hsl()`, `color-mix()`): a validação teria de acompanhar a gramática de cor do CSS, que cresce a cada ano. `#rrggbb` e `var(--nome)` cobrem a cor livre e a cor do tema; os outros formatos continuam podendo ser escritos no código e são preservados.
- **Tamanho em `rem`, `em` ou `%`:** a régua do painel (o tamanho que a página mostra) é em `px`; misturar unidades pediria explicar cada uma. Fica só `px`; outras unidades escritas no código são preservadas.
- **Escrever `!important` para sempre vencer o tema:** esconderia o conflito e dificultaria qualquer ajuste posterior no CSS do tema. O painel avisa e deixa a decisão com o autor.
- **Esperar o servidor para mostrar a mudança** (como nos campos da ADR 0098): ver item 10.
- **Para o limite de tamanho: contar no servidor em caracteres.** Mudaria uma regra de validação do servidor, que vale para todo caminho por onde o código do tema entra. O navegador é que estava contando diferente.

## Limites conhecidos

- **Só três propriedades.** Largura, altura, margem, espaçamento, borda, fonte, alinhamento e posição não entram nesta etapa.
- **Uma regra `!important` do tema vence o que o painel escreve.** O painel avisa (item 11) e não resolve.
- **O aviso de "outro valor" compara o que dá para comparar:** código `#rrggbb`, variável que vale um código `#rrggbb` e tamanho em `px`. Para variável com outro formato de cor (`rgb()`, nome), não há aviso.
- **Vale para todas as ocorrências.** Um elemento dentro de um laço (`{% for %}`) é uma tag só no código: a cor muda em todas as repetições. O painel já diz quantas são.
- **Vale para todas as larguras de tela.** O `style` na tag não muda entre celular e computador. Um tamanho grande escolhido no quadro largo fica igual no quadro estreito.
- **Cor própria é fixa.** Um código `#rrggbb` não acompanha mudanças posteriores nas cores do tema. Uma cor do tema (`var(--nome)`) acompanha.
- **O painel não confere contraste** entre texto e fundo.
- **A lista de cores do tema** mostra até 60 variáveis, das que a página do rascunho declara nas folhas de estilo que o navegador deixa ler. Variável declarada só em um `style` de tag não aparece.
- **Cor de fundo com transparência** não pode ser escolhida pelo painel (o seletor do navegador não tem transparência); pode ser escrita no código e é preservada.
- **Valor escrito em outro formato é substituído inteiro** quando o autor escolhe outro: `font-size:clamp(…)` vira `font-size:NNpx`.
- **Elemento cujo texto vem do site** (`{{ titulo }}`) também recebe cor e tamanho: o `style` é da tag, não do texto.
- **O limite de tamanho do código** agora é contado como o servidor conta. Um trecho que já estava acima do limite em bytes continua sendo recusado pelo servidor, como antes; a diferença é que o painel deixa de escrever nele.
- **O verificador em Node roda pelo PHPUnit** (`KazElementStyleTest::testNodeCheckerOfTheStyleRulesPasses`). Sem `node`, o caso é pulado; o CI tem `node`.
- Conferido em Chromium. Outros navegadores não foram testados; o seletor de cor (`<input type="color">`) tem aparência diferente em cada um.
- Continuam valendo os limites das ADRs 0083 a 0086 e 0098.

## Achados registrados, não corrigidos

- Os mesmos da ADR 0098 (desenho quebrado no pacote `David-Universe-3D`; a tela de edição preenche trecho ausente com o arquivo padrão; `pattern` em `theme-condition-builder.js`).
- **Três classes usadas pelo editor não têm regra de CSS:** `kaz-canvas__hiddenlist`, `kaz-btn--publish` e `kaz-asset__thumb--hero`. Não quebram nada; os elementos ficam com a aparência padrão.

## Consequências

- Arquivos novos: `tests/check_kaz_element_style.js`, `tests/Unit/Library/KazElementStyleTest.php`. Alterados: `system/library/theme/theme_source_map.php` (uma palavra na lista de atributos), `admin/view/js/kaz-element-attrs.js`, `admin/view/js/kaz-canvas.js`, `admin/view/css/kaz-canvas.css`, `admin/controller/design/theme.php`, os três arquivos de idioma (14 chaves `text_canvas_el_*` novas em cada), `tests/check_kaz_element_attrs.js` e `tests/Unit/Library/KazElementAttrsTest.php`.
- Versão do arquivo do canvas no endereço: `-kaz-canvas-kz06`. O navegador busca o arquivo novo sem limpar cache.
- Sem tabela, coluna, migration, rota, dependência ou campo novo no `POST` de salvar. Nenhum controle novo tem `name`.
- O `style` escrito pelo painel passa pela validação do tema (`ThemeSandbox`) como qualquer outro código do trecho; há teste com tudo o que o painel consegue escrever.
- O site público não muda. O que muda nele é o que o autor salvar: `color`, `background-color` e `font-size` nas tags editadas. A política de conteúdo do site já permitia `style` nas tags (os pacotes usam).
- **Mudança de comportamento no admin:** perto do limite de tamanho do código, o editor passa a recusar no painel o que o servidor recusaria (ver a correção acima).
- Testes: `KazElementStyleTest` (12 casos) e os verificadores em Node (425 conferências de cor e tamanho; 293 de atributos). Suíte inteira em PHP 8.2 e 8.3: 1576 testes, 31 627 conferências; PHPStan sem erros.
- O comportamento no navegador foi conferido em Chromium, fora do PHPUnit: um pacote de prova com doze situações (88 conferências) e os seis pacotes de `docs/temas`, incluindo salvar o rascunho, reabrir o editor, desfazer e salvar de novo.
- Verificação por trocas (um erro posto de propósito no código, para ver se alguma conferência acusa): 80 de 80 acusadas pelos testes e 47 de 47 pelo roteiro no navegador. Sete trocas ficaram fora da conta porque outra trava dá o mesmo resultado (por exemplo: com o código mudado por fora, a escrita é barrada no painel e de novo no módulo de regras, e retirar só uma das duas barreiras não muda nada); estão anotadas, com o motivo, no roteiro de verificação. As trocas da etapa anterior (ADR 0098) foram rodadas de novo contra os testes: 92 de 92.

## Próximas etapas (fora desta ADR)

1. **Criar `alt` e `aria-label` quando faltam**, com a lista das imagens sem texto alternativo.
2. **Espaçamento e alinhamento por elemento** (margem, espaço interno, alinhamento do texto), se o uso mostrar que faltam.
3. **Os quatro comportamentos de pagamento mantidos na ADR 0097.**
