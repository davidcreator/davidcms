# ADR 0102 — KAZEditor-Theme: link e botão sem nome

| | |
| --- | --- |
| **Status** | ✅ aceita (KZ-09, 2026-11-15) |
| **Data** | 2026-11-15 |
| **Contexto** | A ADR [0100](0100-kazeditor-descricao-de-imagens-e-desenhos.md) deu ao editor a lista das imagens e dos desenhos sem descrição. Ficou de fora o caso vizinho: o link ou o botão em que o leitor de tela não tem o que anunciar (um ícone sozinho, um "×"). Ali o leitor diz só "link" ou "botão", e quem não vê a página não sabe para onde vai. A ADR [0101](0101-kazeditor-alinhamento-e-espacamento-do-elemento.md) registrou as duas pendências desta etapa: esse aviso, e os 30 desenhos do pacote `David-Universe-3D` com marcas que se contradizem. Medido nos seis pacotes de `docs/temas`: 422 links e 3 botões no código; nenhum sem nome. |

## Decisão

Esta ADR decide a **nona etapa** do caminho B, em duas partes. O alcance foi escolhido pelo responsável pelo projeto: a lista da página passa a mostrar links e botões sem nome, e o painel oferece o campo do rótulo; os 30 selos são enfeite.

### Parte 1 — link e botão sem nome, na lista e no painel

1. **A lista da página passa a se chamar "Sem descrição ou sem nome"** e mostra, junto com as imagens e os desenhos, cada `<a href>` e cada `<button>` do código do tema que não tem nome para o leitor de tela. A linha diz **Link sem nome** com o destino escrito no código (`destino “/contato”`, cortado em 40 caracteres), ou **Botão sem nome** com o texto que está perto dele na página. Clicar leva até o elemento e põe o foco no campo do rótulo.

2. **De onde vem o nome**, nesta ordem: `aria-labelledby`; `aria-label`; o que há dentro (texto, imagem com `alt`, desenho com descrição, `aria-label` de um elemento de dentro); `title`. É a ordem que o navegador usa. O resultado foi comparado com o Chromium em um pacote de prova com 22 situações: dos dez elementos que o navegador entrega sem nome ao leitor de tela, o editor lista oito (dois deles pela imagem de dentro, item 5) e conta dois como não conferidos (item 3); nenhum dos que o navegador anuncia com nome é acusado.

3. **Pendente é só o que o editor consegue afirmar.** Se há Twig dentro do elemento (`{{ titulo }}`, `{% if %}`), o nome vem do conteúdo do site e o editor não acusa. Quando ele não consegue ler, o elemento é contado à parte, em **Não conferidos**: tag com Twig entre os atributos, link que abre em um trecho e fecha em outro, elemento de dentro que o editor não mapeia.

4. **O que está escondido do leitor de tela não dá nome.** Texto ou ícone dentro de um elemento com `aria-hidden="true"` não conta: `<a href="/x"><span aria-hidden="true">×</span></a>` é pendente. Já o link ou botão que está ele mesmo escondido (`aria-hidden="true"` nele ou em um elemento em volta) não é pendente; o painel avisa que o teclado ainda chega nele.

5. **Uma pendência, um conserto.** Link que só tem dentro uma imagem ou um desenho sem descrição não entra na lista: quem aparece é a imagem, como antes. Descrita a imagem, o link passa a ter nome. Se a imagem for marcada como enfeite, o link fica sem nome e entra na lista no lugar dela. O painel do link explica, nos dois casos.

6. **O conserto é o campo "Rótulo para leitor de tela"**, que já existia (ADR 0100): ele escreve `aria-label` logo depois do nome da tag. Nada novo é escrito no tema. Link e botão não têm o botão **É só enfeite**: esconder do leitor de tela um elemento em que o teclado para é outro erro. No elemento sem nome, a ajuda do campo deixa de dizer "Opcional".

7. **Nome só pela dica (`title`):** conta como nome, porque o navegador o usa. O painel sugere escrever o rótulo, já que nem todo leitor de tela anuncia a dica. O `title` é só lido; o painel não tem campo para ele.

8. **`<a>` sem `href`** não é link para o leitor de tela e não é conferido.

9. **A lista acompanha na hora.** Apagar pelo painel o texto de um link põe o link na lista; escrever o rótulo o tira. Nada é gravado até salvar o rascunho.

10. **O servidor manda um resumo, o navegador decide.** `Theme\ThemeSourceMap` passa a ler `title` em `<a>` e `<button>` e a entregar, para cada um deles, o que há dentro: se há texto solto, se há Twig, se está dentro de um trecho escondido, se há algo que ele não pôde ler, e quais elementos mapeados estão dentro. A regra fica em `admin/view/js/kaz-element-attrs.js` (`holder`, chamada por `a11y` e `pending`), sem página, rede ou armazenamento, porque a resposta muda a cada ação no painel (descrever a imagem de dentro, apagar um texto). O verificador novo é `tests/check_kaz_element_name.js`.

### Parte 2 — os 30 selos do pacote `David-Universe-3D`

11. **Os 30 desenhos ficam só como enfeite.** Cada um tinha `aria-hidden="true"` e, na mesma tag, `role="img"` com `aria-label="Selo do projeto"`. O leitor de tela obedecia ao `aria-hidden` e o rótulo nunca era lido. Saíram o `role` e o `aria-label`; ficou o `aria-hidden="true"`. Para quem usa leitor de tela nada muda; o código deixa de dizer duas coisas. O `checksum` do pacote foi recalculado.
12. **Um teste impede a volta:** o pacote não pode ter desenho escondido com `role="img"` ou com rótulo, e o `checksum` é conferido.
13. **Site que já importou o pacote não muda sozinho.** Importar o arquivo de novo grava a correção como rascunho do tema; ela vale no site depois de publicar.

## Alternativas consideradas

- **Decidir tudo no servidor:** o servidor leria o código e devolveria "pendente" ou não. A resposta depende do estado da imagem de dentro, que muda no painel sem ida ao servidor; a lista ficaria atrasada até o redesenho.
- **Perguntar ao navegador, na página desenhada:** é a resposta exata para aquela página, mas só para o que foi desenhado. Elemento dentro de um `{% if %}` falso não aparece, e o resultado muda com o conteúdo do site. A lista é do código do tema. O navegador ficou como referência nos testes.
- **Listar o link e a imagem de dentro:** duas linhas para um problema só, e a segunda some sozinha quando a primeira é resolvida.
- **Oferecer "É só enfeite" em link e botão:** o elemento continuaria recebendo o foco do teclado, sem nada a anunciar.
- **Tratar `title` como "sem nome":** o editor acusaria um elemento que o navegador entrega com nome. A sugestão no painel diz o que há de fraco nele sem contradizer o navegador.
- **Acusar link cujo conteúdo vem de Twig:** todo menu montado com dados do site entraria na lista, sem o editor ter como saber se o dado está vazio.
- **Selos como conteúdo (retirar o `aria-hidden`):** o leitor anunciaria "Selo do projeto" trinta vezes, sem dizer qual projeto. O responsável escolheu enfeite.

## Limites conhecidos

- **Só `<a href>` e `<button>` do código do tema.** Ficam de fora: bloco com `role="button"` ou `role="link"`, `<input type="submit">` e `<input type="image">`, `<summary>`, `<area>`, e links que vêm do conteúdo do site.
- **O editor confere se há nome, não se o nome é bom.** "Clique aqui" e "Saiba mais" passam.
- **Texto posto por CSS não conta.** Ícone desenhado com `content:` em `::before` não é lido pelo editor; o link só com esse ícone é acusado. Texto escondido por CSS (`display: none`) conta como texto.
- **`aria-labelledby` é aceito sem conferir** se o `id` apontado existe na página.
- **Texto que vem de Twig pode chegar vazio** no site; o editor não acusa.
- **`aria-label` em elemento de dentro conta em qualquer tag.** Em um `<span>` sem `role`, nem todo leitor de tela o usa.
- **Tag de fechamento opcional escondida dentro do link** (`<p aria-hidden="true">`, `<li aria-hidden="true">`): o editor não sabe onde o trecho escondido termina e conta o link como não conferido.
- **Trecho com mais de 4000 elementos:** o que está depois do limite não é mapeado; um link que tenha esses elementos dentro fica como não conferido.
- **Elemento em laço (`{% for %}`)** é uma linha só, e o rótulo vale para todas as repetições: os links de um laço ficam todos com o mesmo nome.
- **A linha do botão mostra o texto que está perto**, que pode ser o de um bloco vizinho. O botão não tem destino para mostrar.
- **O rótulo não aparece na página.** A prévia recebe o `aria-label` quando o servidor redesenha a página; não há mudança visível a esperar.
- A lista mostra até 60 linhas (ADR 0100).
- **O verificador em Node roda pelo PHPUnit** (`KazElementNameTest`). Sem `node`, os casos que o chamam são pulados; o CI tem `node`.
- Conferido em Chromium, pela árvore de acessibilidade do navegador. Não foi testado com leitor de tela nem em outros navegadores.
- Continuam valendo os limites das ADRs 0083 a 0086 e 0098 a 0101.

## Achados registrados, não corrigidos

- Os mesmos das ADRs 0098 a 0101 (desenho quebrado no pacote `David-Universe-3D`; a tela de edição preenche trecho ausente com o arquivo padrão; `pattern` em `theme-condition-builder.js`; três classes sem regra de CSS).
- **A pasta `tema/` tem duas cópias antigas do `David-Universe-3D`** (`tema/david_universe_3d_bo.reamurtheme.json` e `tema/bo_config.json`), cada uma ainda com os 30 selos nas duas marcas. Nenhum documento cita esses arquivos e eles não foram alterados.

## Consequências

- Arquivos novos: `tests/check_kaz_element_name.js`, `tests/Unit/Library/KazElementNameTest.php`. Alterados: `system/library/theme/theme_source_map.php`, `admin/view/js/kaz-element-attrs.js`, `admin/view/js/kaz-canvas.js`, `admin/controller/design/theme.php`, os três arquivos de idioma `admin/language/*/design/theme.php`, o pacote `docs/temas/David-Universe-3D.reamurtheme.json`, `tests/check_kaz_element_a11y.js` e os testes `KazElementA11yTest`, `KazElementAttrsTest`, `KazElementSpaceTest` e `KazElementStyleTest` (conferências que acompanham a versão do script, a lista de atributos lidos e a contagem dos pacotes).
- Versão do arquivo do canvas no endereço: `-kaz-canvas-kz09`. O navegador busca o arquivo novo sem limpar cache.
- Sem tabela, coluna, migration, rota, dependência ou campo novo no `POST` de salvar. Nenhum controle novo tem `name`. Nenhuma regra de CSS nova.
- O servidor muda em um ponto: o mapa que `ThemeSourceMap::describe()` entrega ao editor ganha `title` na lista de atributos de `<a>` e `<button>` e um nono campo nesses dois (o resumo do que há dentro). O código do tema não é alterado por isso; a validação do tema (`ThemeSandbox`) não mudou. O `aria-label` que o painel escreve passa por ela como antes; há teste.
- O site público não muda. O que muda nele é o que o autor salvar e publicar.
- **Mudança de comportamento no admin:**
  - a lista "Imagens e desenhos sem descrição" muda de nome e pode mostrar mais linhas em um tema que já existe (os links e botões sem nome dele);
  - no link ou botão sem nome, o painel mostra um aviso e a ajuda do campo do rótulo muda;
  - a frase de "nada pendente" e a de "não conferidos" foram reescritas para falar também de links e botões.
- **Mudança no pacote:** `David-Universe-3D` tem `checksum` novo. No repositório, nenhum outro arquivo o cita.
- Testes: `KazElementNameTest` (13 casos) e o verificador em Node (152 conferências; com os mapas do servidor, 158). Suíte inteira em PHP 8.2 e 8.3: 1606 testes, 33 698 conferências; PHPStan sem erros.
- O comportamento no navegador foi conferido em Chromium, fora do PHPUnit: um pacote de prova com 22 situações (104 conferências, incluindo o que o Chrome anuncia antes e depois de cada conserto, salvar o rascunho e reabrir) e os seis pacotes de `docs/temas` (76 conferências: cada link e botão da página comparado com o que o Chrome anuncia e, em quatro pacotes, apagar o texto de um link, escrever o rótulo, salvar, reabrir e desfazer). Os roteiros das etapas KZ-05 a KZ-08 foram rodados de novo.
- **Roteiro antigo ajustado:** o de KZ-07 tem um botão vazio no pacote de prova, que agora é pendência. As contas dele subiram em um (17 para 18), o link do ícone passou a ser acompanhado quando o ícone vira enfeite, e os textos novos da lista foram trocados. Os de KZ-05, KZ-06 e KZ-08 passaram sem mudança.
- Verificação por trocas (um erro posto de propósito no código, para ver se alguma conferência acusa): 121 de 121 acusadas pelos testes. A primeira rodada acusou 113 de 123: oito mostraram conferências que faltavam, e elas foram acrescentadas; duas eram trocas que não mudam o resultado e saíram da lista. No navegador: a primeira rodada acusou 35 de 41 trocas. Das seis que passaram, uma não muda o resultado e saiu da lista. Quatro mostraram situações que faltavam no pacote de prova (destino com `$&`, texto solto ao lado de um ícone, tag de nome montado com Twig, trecho escondido de fim incerto); elas foram acrescentadas e as quatro trocas passaram a ser acusadas. Uma (link que não fecha no próprio trecho) não tem situação no pacote de prova e só é vista pelo PHPUnit. A lista do navegador, agora com 40 trocas, não foi rodada inteira de novo depois dos acréscimos.

## Próximas etapas (fora desta ADR)

1. **Outros elementos que recebem foco:** bloco com `role="button"`, `<input type="image">`, `<summary>`.
2. **As cópias do pacote em `tema/`:** corrigir como em `docs/temas`, ou retirar a pasta.
3. **Largura máxima e borda por elemento**, se o uso mostrar que faltam.
4. **Os quatro comportamentos de pagamento mantidos na ADR 0097.**
