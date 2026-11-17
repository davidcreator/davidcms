# ADR 0114 — KAZEditor-Theme: o texto do tema é editado na página, no idioma do canvas (o que se digita vai para `config.strings`, não para a fonte)

| | |
| --- | --- |
| **Status** | ✅ aceita (KZ-21, 2026-11-16) |
| **Data** | 2026-11-16 |
| **Contexto** | A ADR 0111 deixou editar na página o texto **fixo** de um tema. A ADR 0112 criou os textos do tema por idioma (`config.strings`, impressos com `{{ theme_text.chave }}`) e a ADR 0113 converteu o David Universe 3D para eles. O resultado foi um passo atrás no editor: depois da conversão, quase todo título, rótulo e botão do tema virou "texto que vem de dado". Dois cliques nele só mostravam o aviso "não é editado aqui", e a única forma de trocar a frase era abrir o bloco "Textos do tema por idioma", achar a chave no JSON e editar à mão. |
| **Relacionadas** | ADR 0111 (texto editado direto na página), ADR 0112 (textos do tema por idioma), ADR 0113 (David Universe 3D acompanha o idioma), ADR 0083 (canvas do editor) |

## Decisão

Um elemento que só imprime um texto do tema passa a ser editado como um texto fixo: no campo do painel e com dois cliques na própria página. O que é digitado é gravado no dicionário do tema, no idioma que o canvas está mostrando. A fonte do tema não muda.

### Que texto abre

1. **Abre o elemento cujo conteúdo é exatamente `{{ theme_text.chave }}`.** O mapa de elementos (ADR 0083) já marca esse elemento como `variable` e entrega a expressão. O script reconhece a forma `theme_text.<chave>`, com a gramática de chave do servidor, e mais nada.
2. **Um filtro de caixa é aceito: `|upper` ou `|lower`.** O tema usa `{{ theme_text.control_mouse|upper }}`. O campo mostra o texto guardado ("Mouse"), não o que a página mostra ("MOUSE").
3. **Qualquer outra expressão continua recusada**, com o aviso de antes: variável de conteúdo (`project.title`), texto de idioma do sistema, concatenação, outro filtro, `theme_text` junto de texto fixo no mesmo elemento.
4. **A expressão cortada pelo servidor nunca é lida como outra chave.** A maior expressão aceita tem 83 caracteres; o mapa corta em 120. Uma expressão cortada tem exatamente 120 e não casa.

### Onde o texto é gravado

5. **No campo `strings_json` do formulário do tema**, o mesmo do bloco "Textos do tema por idioma". O script lê o JSON, troca o texto de uma chave em um idioma e escreve o JSON de volta no campo, com o evento `input`. Daí em diante vale o caminho que já existia: o formulário fica marcado como alterado, a prévia é pedida de novo ao servidor e "Salvar rascunho" grava.
6. **Só aquela chave e aquele idioma mudam.** As outras chaves, os outros idiomas e a ordem ficam como estavam. O JSON é escrito no mesmo formato em que o servidor o mostra (quatro espaços), então no campo muda uma linha.
7. **Um só ponto escreve no campo** (`themeCommit`), chamado em dois lugares: o texto digitado e a devolução do `Esc`.
8. **O servidor continua sendo quem valida.** Nada mudou no controller nem em `ThemeStrings`. O script aplica os mesmos limites antes de escrever, para avisar na hora: 2000 caracteres por texto, 600 chaves, 12 idiomas por chave, 262.144 bytes no campo (contados como o formulário envia: cada quebra de linha vale dois bytes).

### O idioma

9. **O idioma gravado é o do seletor "Idioma" do canvas.** O rótulo do campo diz qual é: "Texto do tema em pt-br".
10. **O campo mostra o texto que o site mostra**, pela mesma ordem do servidor: o do idioma; sem ele, o de `en-us`; sem esse, o primeiro preenchido. Quando o texto vem de outro idioma, uma nota diz de qual, e digitar cria a tradução que faltava. O texto emprestado não é alterado.
11. **Só se grava no idioma que a página está mostrando.** Ao trocar o seletor, o campo fica em modo leitura até a página nova chegar, e a caixa aberta sobre um texto do tema fecha como está. Sem isso, o texto de um idioma poderia ser gravado em outro.

### No painel

12. **O elemento escolhido ganha o campo "Texto do tema em <idioma>"**, com a chave ao lado e a mesma ajuda dos dois cliques. As outras variáveis continuam mostrando só a expressão.
13. **Sem elemento escolhido, o painel lista os "Textos do tema neste trecho"**, no idioma do canvas, um por elemento. Escolher uma linha leva ao elemento. A lista acompanha a troca de idioma.
14. **Uma chave usada em vários lugares muda em todos.** "Sobre" aparece no menu e no rótulo da seção; editar um troca os dois, na hora e depois da prévia do servidor. A ajuda do campo avisa disso.

### Na página

15. **Dois cliques (ou `Enter` / `F2` com o elemento escolhido) abrem a caixa da ADR 0111**, com o texto guardado. `Enter` conclui. `Esc` devolve o JSON dos textos como estava ao abrir, byte a byte.
16. **Elemento com filtro de caixa abre em caixa própria**, ao lado do elemento, porque o que se vê ("MOUSE") não é o que está guardado ("Mouse").
17. **A prévia imediata troca só o texto** (`textContent`) dos elementos daquela chave, como no texto fixo. O que é digitado nunca é lido como HTML: na página publicada, sai escapado pelo Twig.

### O que não se grava

18. **Texto vazio.** O dicionário não aceita texto vazio. O campo e a caixa podem ser esvaziados para digitar de novo; enquanto estão vazios, vale o último texto, e um aviso diz isso.
19. **Texto com quebra de linha.** A caixa da página é de uma linha. Um texto que já tem quebra de linha fica em modo leitura, com o aviso de editar no bloco do JSON.
20. **Nada, quando o JSON do bloco está inválido.** O campo fica em modo leitura e o aviso cita o bloco pelo nome. O que a pessoa digitou no bloco não é tocado.
21. **Nada, quando o trecho ainda usa o padrão do sistema** ou o mapa está defasado: valem os avisos da ADR 0111.

### Textos e versão

22. Nove rótulos novos em `pt-br`, `en-us` e `es-eu` (`text_canvas_theme_*`). Os avisos citam o bloco "Textos do tema por idioma" pelo nome que ele tem em cada idioma.
23. Versão dos arquivos do canvas: `-kaz-canvas-kz21`.

### Testes

24. **`tests/fixtures/kaz_theme_text.json`** é uma tabela de casos única: leitura com recuo, textos aceitos e recusados, expressões, escritas e as bordas de cada limite. Os dois lados a rodam: `tests/check_kaz_theme_text.js` sobre as funções do script, e `KazThemeTextTest.php` sobre `ThemeStrings` e sobre o que o controller faz com o campo. O JSON esperado de cada escrita foi gerado por uma terceira implementação (Python).
25. **`tests/check_kaz_theme_text.js`** recorta do script as nove funções que decidem, roda os casos, lê os pontos fixos da fonte e dos três idiomas, e por fim estraga o recorte de 17 formas, uma por vez, exigindo que os casos acusem cada uma.
26. **`KazThemeTextTest.php`** confere que os limites e as gramáticas do script são os de `ThemeStrings`, que o mapa descreve o elemento como o script espera, que o JSON escrito é aceito e tem o formato do servidor, e as frases dos três idiomas, escritas no teste.
27. Dois testes da ADR 0111 fixavam a linha por onde a caixa escreve. A linha agora escolhe entre dois caminhos (texto fixo ou texto do tema) e os dois testes foram atualizados para ela.

## O que muda na tela

- No David Universe 3D, dois cliques em "Bem-vindo ao universo do design", num item do menu ou num rótulo do cartão de controles abrem a edição. Antes, mostravam "Este texto vem dos dados do site".
- Com o seletor "Idioma" em `es-eu`, o que se digita vale para o espanhol. Para traduzir uma frase nos três idiomas, troca-se o seletor três vezes.
- O painel do trecho passa a listar os textos do tema, além dos textos fixos e dos links.
- O bloco "Textos do tema por idioma" continua existindo e mostra, no JSON, o que foi digitado na página.

## Alternativas consideradas

- **Trocar `{{ theme_text.chave }}` por texto fixo na fonte ao editar.** O elemento deixaria de acompanhar o idioma, que é o que a ADR 0113 construiu.
- **Um quadro com os idiomas lado a lado para cada chave.** É mais tela para o mesmo dado. O canvas já tem o seletor de idioma, e editar o que se está vendo é mais direto. Pode vir depois, sobre a mesma escrita.
- **Campos escondidos por chave e idioma, em vez do JSON.** Seriam duas fontes de verdade no formulário. O contrato do servidor é o campo `strings_json`.
- **Aceitar qualquer filtro ou expressão que comece por `theme_text`.** A prévia imediata não saberia mostrar o resultado, e a página mostraria uma coisa enquanto o campo guarda outra.
- **Aceitar quebra de linha na caixa.** A caixa da ADR 0111 é de uma linha por decisão. O bloco do JSON continua aceitando.
- **Gravar texto vazio como "apagar a tradução".** Um toque de tecla apagaria um dado. Apagar uma tradução continua sendo um ato no bloco do JSON.

## Limites conhecidos

- **Só o elemento que imprime um texto do tema e mais nada.** Texto do tema dentro de atributo (`aria-label`, `alt`, `placeholder`, os `data-du-*` que a cena lê) ou junto de texto fixo continua sendo editado no bloco do JSON. No David Universe 3D isso inclui os avisos da cena ("Orbe coletado") e a palavra "Fase" do HUD.
- **Um idioma por vez**, o do canvas.
- **A contagem "Textos que faltam por idioma"**, no bloco do JSON, é calculada pelo servidor e só se atualiza ao salvar.
- **Um idioma criado pela edição entra no fim da chave** no JSON. Ao salvar, o servidor reordena chaves e idiomas, como já fazia.
- **Depois de concluir, não há desfazer.** `Esc` desfaz enquanto a caixa está aberta. Sair da tela sem salvar descarta tudo.
- **Só o David Universe 3D usa textos do tema.** Nos outros temas nada muda.
- **Visto na prova que salva, e anterior a esta etapa:** salvar o tema pelo formulário regrava as 25 fontes com fim de linha `CR LF` (é como o navegador envia um `<textarea>`). O conteúdo é o mesmo; o pacote exportado fica alguns bytes maior. Não foi alterado aqui. *(Corrigido na KZ-22: pela ADR 0115, a fonte é guardada com `\n` e salvar sem mexer não muda um byte.)*
- **Os roteiros de navegador das etapas KZ-05 a KZ-18** continuam rodando contra o pacote antigo do David Universe, mantido como peça de teste da bancada.

## Verificação

- PHPUnit em PHP 8.2 e 8.3: 1720 testes, 44905 conferências (eram 1710 e 44541). PHPStan sem erro. Os onze verificadores em Node passam; o novo, `check_kaz_theme_text.js`, faz 311 conferências, entre elas 17 estragos de propósito, todos acusados.
- Bancada, Chromium, editor (`prova.cjs`, 175 conferências, tema "David Universe 3D — Cena Real", painel em `pt-br`, `es-eu` e `en-us`; nada é salvo): lista dos textos do tema do trecho; campo do painel com o texto do idioma do canvas; digitação lenta com a prévia do servidor chegando no meio; só a chave e o idioma mudam no JSON e a fonte do trecho não muda; formulário marcado como alterado; texto vazio; chave usada em dois lugares; dois cliques, `F2`, `Enter` e `Esc` (JSON de volta byte a byte); elemento com `|upper` (a página mostra "RATO", o JSON guarda "Rato"); troca de idioma com e sem a caixa aberta; idioma sem tradução; texto com quebra de linha; JSON inválido e corrigido; JSON mudado no bloco com a caixa aberta; variável de conteúdo continua recusada. Nenhum erro no console.
- Bancada, o mesmo roteiro com o script estragado de sete formas, uma por vez, só na cópia da bancada (`mutacoes.py`): as sete foram acusadas. A sétima passou na primeira rodada; uma conferência foi acrescentada ao roteiro e ela passou a ser acusada.
- Bancada, salvando de verdade (`salvar.sh`, 20 conferências): texto digitado na página em `pt-br` (`Olá & "aspas" <b>não</b> — KZ-21`) e no painel em `es-eu`, rascunho salvo, tela reaberta, pacote exportado e tema ligado no site. O JSON salvo traz os dois textos e o resto igual; as 25 fontes têm o mesmo conteúdo; a home publicada mostra cada texto no seu idioma, com a tag digitada escapada. Ao fim, a tabela de temas voltou a ser igual à de antes (soma conferida).
- Bancada, `editor.cjs` da KZ-20 (39 conferências em cada pacote novo do David Universe 3D): passa.
- Bancada, regressão: os 79 roteiros de navegador das etapas KZ-05 a KZ-19 passam com o código novo, rodando com o pacote antigo do David Universe importado; depois o pacote novo voltou e a soma dos temas ficou igual à de antes. Três não passaram na rodada em lote e passaram em seguida: os dois da KZ-18 conferiam a versão `kz18` dos arquivos do canvas (a conferência passou a aceitar `kz18` em diante), e um da KZ-06 parou uma vez ao procurar um elemento que ainda não tinha sido redesenhado (rodado sozinho quatro vezes, passou nas quatro).
- Guardas do repositório (`verify-checksums`, `verify-ci-workflow`, `verify-deps-layout`, `verify-build-prod`) e links dos `.md` sem erro.
