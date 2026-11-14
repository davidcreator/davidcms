# ADR 0100 — KAZEditor-Theme: descrever imagens e desenhos que estão sem texto

| | |
| --- | --- |
| **Status** | ✅ aceita (KZ-07, 2026-11-14) |
| **Data** | 2026-11-14 |
| **Contexto** | Com a ADR [0098](0098-kazeditor-atributos-do-elemento.md), o painel **Elemento** edita o texto alternativo (`alt`) e o rótulo (`aria-label`) de um elemento, mas só quando o atributo **já existe** no código. Imagem sem `alt` recebia um aviso mandando abrir o código. Não havia como saber, sem clicar elemento por elemento, quais imagens da página estavam sem descrição. Medido nos seis pacotes de `docs/temas`: nenhuma imagem (`<img>`) sem `alt`, mas **38 desenhos (`<svg>`) sem nenhuma descrição e sem marca de enfeite** (13 em Atlas-Studio, 13 em Atlas-Studio-3D, 12 em David-Creator). Um leitor de tela ou pula esses desenhos sem avisar, ou anuncia "imagem" sem dizer o quê. |

## Decisão

Esta ADR decide a **sétima etapa** do caminho B: **o editor lista as imagens e os desenhos da página que estão sem descrição, e o painel Elemento passa a criar o texto que falta ou a marcar o elemento como enfeite**. O alcance foi escolhido pelo responsável pelo projeto: imagens e desenhos (`<img>` e `<svg>`); lista da página inteira; rótulo novo só onde ele é anunciado; botão "É só enfeite".

1. **O que conta como pendente.** Uma `<img>` ou um `<svg>` que o código não descreve nem esconde. As perguntas são feitas nesta ordem, e a primeira que responde decide:
   - `aria-hidden="true"`, ou `role` com `presentation` ou `none` → **escondido** do leitor de tela (não é pendente);
   - `aria-label` ou `aria-labelledby` com texto → **tem nome**;
   - na imagem: `alt` com texto → tem nome; `alt` vazio → **enfeite**; sem `alt` → **pendente**;
   - no desenho: `<title>` dentro dele → tem nome; senão → **pendente**.
   Quando o valor que decide vem do conteúdo do site (`alt="{{ titulo }}"`, `aria-hidden="{{ … }}"`), o elemento não é pendente: o editor não tem como saber o valor e não pede nada.

2. **Lista da página.** O painel da esquerda ganha a seção **"Imagens e desenhos sem descrição"**, com a contagem, agrupada por trecho, na ordem da página. Cada linha diz se é imagem ou desenho e mostra o texto que está perto dele na página ("perto de …"), ou os atributos da tag quando não há texto por perto. Passar o mouse ou chegar pelo teclado destaca o elemento; clicar abre o elemento no painel e leva o foco para o campo a preencher. A lista é refeita a cada mudança no código, sem esperar o servidor.

3. **O campo cria o atributo.** Em imagem pendente aparece **Texto alternativo**; em desenho pendente, **Descrição do desenho**. A primeira letra digitada escreve o atributo logo depois do nome da tag (` alt="…"` ou ` aria-label="…"`); apagar tudo deixa o atributo vazio, e os botões **Não é enfeite** (imagem) e **Retirar a descrição** (desenho) o removem. No desenho sem `role`, o editor escreve `role="img"` junto com o rótulo, para o leitor de tela tratar o desenho como uma imagem só.

4. **"É só enfeite".** Marca o elemento para o leitor de tela pular: `alt=""` na imagem, `aria-hidden="true"` no desenho. **"Não é enfeite"** retira a marca. O botão só aparece em elemento pendente.

5. **Rótulo novo só onde ele é anunciado.** O campo **Rótulo para leitor de tela** passa a poder criar o `aria-label` em links, botões, desenhos e blocos de navegação ou de seção (`a`, `button`, `svg`, `nav`, `section`, `aside`, `form`, `header`, `footer`, `main`), e em elemento com `role` fixo de uma lista fechada (`button`, `link`, `img`, `navigation`, `region`, …). Em parágrafo, título, `div` ou `span` sem `role` o campo não é oferecido: um rótulo ali seria ignorado ou esconderia o texto visível. Rótulo que já existe em qualquer tag continua editável (ADR 0098).

6. **Retirar devolve o código ao que era.** Criar e retirar, ou marcar e desmarcar, devolve a fonte original byte a byte, incluindo o `role="img"` que o editor escreveu. Um `role` do autor com outro valor fica.

7. **O servidor só lê.** `Theme\ThemeSourceMap` passa a localizar `aria-hidden`, `aria-labelledby` e `role` em qualquer tag, e cada `<svg>` do mapa diz se há um `<title>` dentro dele (nono campo do item; só o `<svg>` tem). Decidir a situação e escrever continua em `admin/view/js/kaz-element-attrs.js` (`a11y`, `pending`, `canLabel`, `putAttr`, `decorate`, `dropLabel`), sem página, rede ou armazenamento. O verificador novo é `tests/check_kaz_element_a11y.js`.

8. **Valor sem aspas é lido como o navegador lê.** `aria-hidden=true` esconde; `alt` sem valor é enfeite; `aria-hidden` sem valor não esconde. Quando o editor precisa trocar um atributo assim, reescreve com aspas (`aria-hidden="true"`), sem repetir o nome.

9. **Quando o editor não decide.**
   - tag que o leitor do servidor não entende (Twig entre os atributos, como `<img {% if x %}alt="…"{% endif %}>`; atributo repetido; atributos colados um no outro, como `a="x"b="y"`): não é contada como pendente; a lista mostra quantas ficaram **sem conferir**, e o painel diz que a tag precisa ser editada no código;
   - trecho cujo código mudou e ainda não foi lido de novo pelo servidor: fica fora da lista até o mapa voltar;
   - trecho padrão do sistema: os elementos aparecem na lista, mas os botões ficam travados até **Personalizar este trecho**.

10. **O texto digitado é gravado como texto.** `&`, `<`, `>`, aspas e `{` viram entidades, como na ADR 0098; `=` também, para a validação do tema não tomar um texto como `Clique: onde=aqui` por um atributo de evento. Máximo de 300 caracteres. O limite de 131 072 bytes do trecho vale como nos outros campos: o que não cabe não é escrito.

## Alternativas consideradas

- **Só imagens (`<img>`):** nos seis pacotes não há nenhuma imagem sem `alt`; as 38 pendências são desenhos. A etapa não teria efeito nos temas que existem.
- **Lista só do trecho selecionado:** obrigaria a abrir trecho por trecho para saber se a página está resolvida. A lista da página responde de uma vez.
- **Criar `aria-label` em qualquer elemento:** em `div`, `span` e parágrafo sem `role` o rótulo é ignorado por parte dos leitores de tela, e onde é aceito substitui o texto visível. A lista fechada evita os dois problemas.
- **Descrever o desenho com `<title>` em vez de `aria-label`:** pediria inserir uma tag dentro do desenho, e o `<title>` também vira dica ao passar o mouse. `role="img"` com `aria-label` é um atributo na tag, do mesmo tipo que o painel já escreve. Um `<title>` do autor é reconhecido e respeitado.
- **Marcar o desenho como enfeite com `role="presentation"`:** não esconde o que está dentro dele. `aria-hidden="true"` esconde o desenho inteiro e é o que o pacote David-Universe-3D já usa.
- **Decidir a situação no servidor e mandar pronta:** a lista teria de esperar o servidor a cada letra digitada. Com as regras no navegador, ela acompanha o código na hora, e o servidor continua só dizendo onde cada atributo está.
- **Marcar automaticamente como enfeite o desenho dentro de link com texto:** é o caso mais comum, mas é uma suposição sobre o conteúdo. A decisão fica com quem edita; o botão resolve em um clique.

## Limites conhecidos

- **O editor confere se há descrição, não se ela é boa.** Um `alt="imagem"` conta como resolvido.
- **Só `<img>` e `<svg>`.** Imagem de fundo em CSS, `<picture>`, `<video>`, `<canvas>`, `<object>` e fonte de ícones não entram na lista.
- **Só o código do tema.** Imagem que vem do conteúdo do site (dentro de `{{ descricao }}`, por exemplo) não está no código do trecho e não aparece.
- **Desenho dentro de outro desenho** não é um elemento do mapa: só o `<svg>` de fora é conferido.
- **Link ou botão que só tem um desenho pendente** aparece pela pendência do desenho. O editor não confere se o link, depois de o desenho ser marcado como enfeite, ficou sem nenhum nome. O campo de rótulo do link está disponível para isso.
- **`aria-labelledby` não é seguido:** se tem texto, o editor aceita que o nome vem de outro elemento, sem conferir se o `id` existe.
- **`role` com valor que vem do site, ou `aria-hidden` que vem do site,** deixam o elemento fora da lista.
- **Elemento em laço (`{% for %}`)** é uma tag só no código: uma linha na lista, e a descrição vale para todas as repetições. Para descrições diferentes por item, o texto precisa vir de um dado (`alt="{{ item.nome }}"`), no código.
- **A lista mostra até 60 linhas** e diz quantas ficaram de fora.
- **"Perto de"** usa o texto visível do elemento em volta (até três níveis acima), cortado em 40 caracteres. Em laço, é o texto da primeira repetição.
- **O verificador em Node roda pelo PHPUnit** (`KazElementA11yTest::testNodeCheckerOfTheA11yRulesPasses`). Sem `node`, o caso é pulado; o CI tem `node`.
- Conferido em Chromium, sem leitor de tela: o que foi medido é o código gravado, não o que um leitor anuncia. Outros navegadores não foram testados.
- Continuam valendo os limites das ADRs 0083 a 0086, 0098 e 0099.

## Achados registrados, não corrigidos

- Os mesmos das ADRs 0098 e 0099 (desenho quebrado no pacote `David-Universe-3D`; a tela de edição preenche trecho ausente com o arquivo padrão; `pattern` em `theme-condition-builder.js`; três classes sem regra de CSS).
- **Os 38 desenhos sem descrição dos pacotes Atlas-Studio, Atlas-Studio-3D e David-Creator continuam como estão nos arquivos de `docs/temas`.** Esta etapa entrega a ferramenta; decidir, desenho por desenho, se é enfeite ou o que ele mostra é trabalho de conteúdo, feito no editor.

## Consequências

- Arquivos novos: `tests/check_kaz_element_a11y.js`, `tests/Unit/Library/KazElementA11yTest.php`. Alterados: `system/library/theme/theme_source_map.php`, `admin/view/js/kaz-element-attrs.js`, `admin/view/js/kaz-canvas.js`, `admin/view/css/kaz-canvas.css`, `admin/view/template/design/kaz_theme_editor.twig`, `admin/controller/design/theme.php`, os três arquivos de idioma `admin/language/*/design/theme.php` e `tests/Unit/Library/KazElementStyleTest.php` (três conferências acompanham a lista de atributos e a versão do script).
- Versão do arquivo do canvas no endereço: `-kaz-canvas-kz07`. O navegador busca o arquivo novo sem limpar cache.
- Sem tabela, coluna, migration, rota, dependência ou campo novo no `POST` de salvar. Nenhum controle novo tem `name`.
- No módulo de regras, retirar um atributo passa a ser recusado quando ele está colado no seguinte. Com o mapa que o servidor manda isso não acontece (a tag inteira já fica fechada para o editor); é uma segunda trava, coberta pelo verificador em Node.
- O que o painel escreve (`alt`, `aria-label`, `role="img"`, `aria-hidden="true"`) passa pela validação do tema (`ThemeSandbox`) como qualquer outro código do trecho; há teste com textos difíceis (aspas, `<`, `{{`, `onde=`).
- O site público não muda. O que muda nele é o que o autor salvar.
- **Mudança de comportamento no admin:**
  - o aviso da imagem sem `alt` deixa de mandar abrir o código e passa a oferecer o campo e o botão;
  - `aria-hidden=true`, `role=presentation` e `aria-label=Nome`, escritos sem aspas, são lidos para decidir se o elemento está pendente. Os campos da ADR 0098 continuam editando só valores entre aspas.
- Testes: `KazElementA11yTest` (11 casos) e o verificador em Node (487 conferências). Suíte inteira em PHP 8.2 e 8.3: 1587 testes, 32 250 conferências; PHPStan sem erros.
- O comportamento no navegador foi conferido em Chromium, fora do PHPUnit: um pacote de prova com vinte e quatro situações (115 conferências) e os seis pacotes de `docs/temas` (77 conferências), incluindo descrever um desenho, salvar o rascunho, reabrir o editor, desfazer e salvar de novo.
- Verificação por trocas (um erro posto de propósito no código, para ver se alguma conferência acusa): 122 de 122 acusadas pelos testes e 67 de 67 pelo roteiro no navegador. Dez trocas ficaram fora da conta porque outra trava dá o mesmo resultado (por exemplo: com o código mudado por fora, o botão "É só enfeite" é barrado no painel e, sem essa barreira, pelas regras); as seis do painel foram rodadas e nenhuma muda o que os roteiros veem.

## Próximas etapas (fora desta ADR)

1. **Descrever os 38 desenhos dos três pacotes** (ou marcá-los como enfeite) e atualizar os arquivos de `docs/temas`.
2. **Link e botão sem nome:** avisar quando um link ou botão não tem texto, rótulo nem imagem descrita.
3. **Espaçamento e alinhamento por elemento**, se o uso mostrar que faltam.
4. **Os quatro comportamentos de pagamento mantidos na ADR 0097.**
