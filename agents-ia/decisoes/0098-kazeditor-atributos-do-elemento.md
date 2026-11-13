# ADR 0098 — KAZEditor-Theme: editar atributos do elemento (link, texto alternativo, rótulo)

| | |
| --- | --- |
| **Status** | ✅ aceita (KZ-05, 2026-11-13) |
| **Data** | 2026-11-13 |
| **Contexto** | Com a ADR [0086](0086-kazeditor-selecionar-elemento.md), clicar em um elemento da página do rascunho seleciona aquele elemento e o **texto** fixo dele é editado no painel. Trocar o **destino** de um link, o texto alternativo de uma imagem ou o rótulo lido por leitores de tela ainda exigia achar a tag no código do trecho. Nos seis pacotes de `docs/temas/` há 174 destinos de link escritos direto no código (`David-Creator` 95, `David-Universe-3D` 45, os dois `Atlas` 17 cada) e 83 rótulos `aria-label` fixos. |

## Decisão

Esta ADR decide a **quinta etapa** do caminho B: **o painel Elemento edita `href` de links, `alt` de imagens e `aria-label` de qualquer elemento, e liga ou desliga "Abrir em nova aba"**. Cor, tamanho e posição por elemento ficam para a etapa seguinte.

1. **O mapa de elementos passa a dizer onde fica cada atributo.** `Theme\ThemeSourceMap` lê, em cada tag de abertura, uma lista fechada de nomes: `href`, `target` e `rel` em `<a>`; `alt` em `<img>`; `aria-label` em qualquer tag. Para cada um devolve a posição do valor (sem as aspas), a posição do atributo inteiro e a natureza:
   - `fixed`: valor entre aspas, sem Twig. É o único tipo que o painel troca;
   - `variable`: valor entre aspas com Twig. Se for um `{{ … }}` só, a expressão vai junto, para o painel mostrar de onde o valor vem;
   - `bare`: atributo sem valor ou com valor sem aspas. Não é editado.

2. **Tag que o leitor não entende fica fechada para o editor.** Twig solto entre atributos (`<a href="/x" {% if on %}class="a"{% endif %}>`), nome repetido, aspa fora do lugar ou atributo colado no anterior: nesses casos o que está escrito não diz com certeza qual atributo a página vai ter. A tag viaja com `null` no lugar da lista e o painel mostra uma frase mandando editar no código. Nos seis pacotes entregues nenhuma tag cai nesse caso (há teste).

3. **O mapa viaja no mesmo lugar.** O item de `elements` em `design/theme.canvas` ganha um oitavo campo: `null` ou a lista `[nome, tipo, início do valor, fim do valor, início do atributo, fim do atributo, expressão]`, com posições contadas como o JavaScript conta (UTF-16). Nenhuma entrada nova; a ação continua somente leitura (`POST` + CSRF + ACL `access`).

4. **Regras de escrita num módulo sem página.** `admin/view/js/kaz-element-attrs.js` (`window.KazElementAttrs`) guarda as regras e não toca em página, rede nem armazenamento; por isso roda igual no navegador e no verificador `tests/check_kaz_element_attrs.js`. `kaz-canvas.js` monta os campos e chama esse módulo; sem ele, os campos não aparecem e nada é escrito.

5. **Trocar um valor muda só o que está entre as aspas.** O resto do código fica byte a byte igual. Antes de escrever, o módulo confere na fonte o nome do atributo, a aspa que abre e a que fecha; se não baterem (o mapa é de outra versão do código), nada é escrito e o painel pede para esperar a página atualizar. O que é digitado é sempre texto: `&`, `<`, `>`, as duas aspas e `{` viram entidades, e os padrões que a validação do tema recusa (`on…=`, `srcdoc=`, `formaction=`, `|raw`, `javascript:`) são quebrados com entidades, como já acontecia no campo Texto.

6. **Destino de link: lista fechada.** Aceitos: caminho do site (`/contato`), âncora (`#secao`), consulta na mesma página (`?pagina=2`), endereço com `https://` ou `http://`, `mailto:` e `tel:`. Recusados, com aviso e sem tocar no código: qualquer outro protocolo (`javascript:`, `data:`, `ftp:`…), `//site` sem protocolo, barra invertida, espaço, caractere de controle e endereço com `usuario@` antes do site (esconde o site verdadeiro). Enquanto o que foi digitado está incompleto, o código continua com o destino anterior e o painel diz isso.

7. **Texto alternativo e rótulo.** Até 300 caracteres. `alt=""` é aceito (imagem decorativa). Imagem **sem** `alt` no código mostra uma frase dizendo que o atributo falta; o painel não cria o atributo.

8. **Abrir em nova aba.** É a única escrita que acrescenta ou retira atributos:
   - marcar: `target` passa a `_blank` (troca o valor que houver, ou escreve ` target="_blank"` logo depois do `href`); `rel` ganha a palavra `noopener` (as outras palavras ficam), ou é escrito ` rel="noopener"` logo depois do `target`;
   - desmarcar: `target` sai inteiro; `rel` sai só quando é exatamente `noopener`, que é o que o próprio editor escreve. Um `rel="noopener me"` do autor fica.
   - a caixa fica travada, com a frase explicando, quando `target` ou `rel` vêm de dado, quando o `href` não tem aspas, ou quando o `href` tem Twig que usa a mesma aspa do atributo.
   Vale também para link cujo destino vem do site (`href="{{ … }}"`): a expressão não é tocada.

9. **Valor que vem de dado.** O painel mostra a expressão (`{{ home|default('/') }}`) e explica que o valor muda com o conteúdo ou o idioma. Não há campo.

10. **Trecho padrão do sistema.** Os campos aparecem somente para leitura até **Personalizar este trecho**, como no campo Texto.

11. **Chegar ao link.** O clique costuma cair no texto ou no ícone *dentro* do link. Nesse caso o painel mostra "Este elemento está dentro de um link" com o botão **Editar o link**. Com o trecho inteiro selecionado, a lista **Links deste trecho** leva a cada link de destino fixo pelo teclado, com o foco já no campo.

12. **Nada é escrito no documento do quadro.** Diferente do campo Texto (ADR 0086), os campos de atributo não mexem na página enquanto são digitados: o destino de um link e o rótulo de leitor de tela não têm efeito visível, e o render seguinte do servidor traz o resultado.

## Correção incluída: o botão Salvar não enviava o formulário

Achado ao provar esta etapa em Chromium; o defeito é anterior a ela e foi corrigido aqui por decisão do responsável pelo projeto.

O formulário do editor contém controles **só de tela**, sem `name`, que nunca vão ao servidor: altura e largura da tela de trabalho, X/Y/W/H do objeto selecionado, números da cena 3D. Vários têm `step`, e o valor que o próprio editor põe neles nem sempre cai no passo: a altura de fábrica é 540, e com `min="240"` e `step="8"` os valores aceitos são 536 e 544. A validação nativa do navegador considerava o campo inválido; como ele estava em um módulo escondido, o navegador barrava o envio **sem mostrar nada**. Medido nos seis pacotes e no pacote de prova: em todos, **Salvar** não enviava o formulário (de 1 a 8 controles fora do passo por tema). **Publicar** usa o mesmo formulário.

A correção, em `admin/view/js/kaz-theme-editor.js`: o formulário passa a `noValidate` e a conferência antes do envio é feita pelo próprio editor, **só nos campos com `name`** (os que o servidor recebe). Se um deles estiver inválido, o envio é barrado como antes, e o editor abre o módulo onde o campo está para o navegador apontar o motivo. As regras do servidor não mudaram.

Junto: o `pattern` do campo `slug` era `[a-z0-9_-]{1,48}`, que os navegadores atuais recusam como expressão (hífen sem escape dentro de classe) e por isso **ignoravam**. Passa a `[a-z0-9_\-]{1,48}`, igual à regra do servidor. Efeito: um slug fora do padrão agora é apontado no campo antes do envio, em vez de voltar como erro do servidor.

## Alternativas consideradas

- **Trocar o atributo no HTML já renderizado:** não distingue valor fixo de dado e não tem como voltar ao código. Mesma recusa da ADR 0086.
- **Campo livre para qualquer atributo:** abriria `on…=`, `style`, `srcdoc` e todo o resto pela mesma porta. Lista fechada de nomes por tag.
- **Aceitar qualquer destino e deixar a validação do tema recusar ao salvar:** a recusa viria tarde, sobre o código inteiro, sem dizer qual campo. A lista de destinos aceitos responde na hora.
- **Guardar "nova aba" fora do código (tabela ou JSON):** mudaria o contrato do pacote e pediria migration.
- **Criar `alt` quando falta:** obrigaria o editor a escolher onde escrever dentro da tag de uma imagem cujo `src` quase sempre vem de dado. Fica para depois; hoje o painel avisa que falta.
- **Para o botão Salvar: `novalidate` no formulário e nada mais.** Resolveria o envio, mas nome vazio e slug inválido deixariam de ser apontados antes do envio. Recusado.
- **Para o botão Salvar: tirar o `step` dos controles.** As setas deixariam de andar de 8 em 8, e controles novos criados pelos outros scripts do editor voltariam a travar o envio. A regra "sem `name` não trava" cobre todos.

## Limites conhecidos

- **Só valor fixo entre aspas.** Destino, `alt` ou rótulo que vêm de dado, ou misturam texto e dado (`href="/p/{{ id }}"`), são mostrados e não editados.
- **O painel não cria nem apaga `href`, `alt` ou `aria-label`.** Só troca o valor de um que já existe. A única criação é a de `target` e `rel` pela caixa de nova aba.
- **Nenhum dos seis pacotes tem `alt` fixo** (as imagens deles vêm do conteúdo). O campo "Texto alternativo" foi provado com um pacote de teste, que não entra no repositório.
- **Desmarcar "nova aba" não devolve o que havia antes.** Um `target="_self"` vira `_blank` ao marcar e sai ao desmarcar; um `rel` que ganhou `noopener` continua com ele.
- **O destino aceito não é conferido contra o site.** `/contato` é aceito mesmo que a página não exista.
- **`mailto:` e `tel:`** têm conferência de formato simples (um `@`; de 3 a 31 dígitos e sinais), não de existência.
- **Ao editar, o valor é regravado como texto simples.** Entidades como `&nbsp;` viram o caractere correspondente. Valor não editado não é tocado.
- Limites: 2000 caracteres por destino, 300 por `alt` e rótulo, 131 072 por fonte (o mesmo do formulário), 80 linhas na lista de links. Fonte no limite avisa "esta mudança não foi gravada" (antes, no campo Texto, a tecla era ignorada em silêncio).
- **O verificador em Node roda pelo PHPUnit** (`KazElementAttrsTest::testNodeCheckerOfTheWritingRulesPasses`). Em uma máquina sem `node` esse caso é pulado; o CI tem `node`.
- **Botão Salvar:** a correção cobre o formulário do editor de tema. Outros formulários do admin não foram revistos atrás do mesmo defeito.
- Continuam valendo os limites das ADRs 0083 a 0086.

## Achados registrados, não corrigidos

- **Desenho quebrado dentro do pacote `David-Universe-3D`:** quatro trechos (`common/home` ×2, `partial/portfolio`, `partial/news`) têm `<path d="M40 24h80v84#eb767e40z">` (um código de cor no meio do desenho; o esperado seria `H40`). O navegador avisa no console e o selo não é desenhado. Corrigir muda o pacote e a soma de verificação dele.
- **Erro `localStorage` anotado na KZ-03:** não se repetiu em nenhuma das execuções desta etapa (sete temas, Chromium). Os dois acessos do canvas a `localStorage` estão em `try/catch`. Origem ainda desconhecida.
- **A tela de edição preenche todo trecho ausente com o arquivo padrão.** Um tema que não traz `partial/site_header` abre com o código padrão já no campo, e o painel o trata como "deste tema". O estado "padrão do sistema" só aparece depois de **Voltar ao padrão**. É o comportamento anterior a esta etapa.
- **`pattern` do identificador de regra em Condições** (`theme-condition-builder.js`) tem o mesmo hífen sem escape. O campo não tem `name` e não trava nada; o padrão é ignorado pelo navegador.

## Consequências

- Arquivos novos: `admin/view/js/kaz-element-attrs.js`, `tests/check_kaz_element_attrs.js`, `tests/Unit/Library/KazElementAttrsTest.php`. Alterados: `system/library/theme/theme_source_map.php`, `admin/view/js/kaz-canvas.js`, `admin/view/css/kaz-canvas.css`, `admin/view/js/kaz-theme-editor.js`, `admin/view/template/design/kaz_theme_editor.twig` (só o `pattern` do slug), `admin/controller/design/theme.php` e os três arquivos de idioma (23 chaves `text_canvas_el_*` novas em cada).
- Versão dos arquivos do editor no endereço: `-kaz-canvas-kz05` e `-kaz-theme-editor-kz05`. O navegador busca os arquivos novos sem limpar cache.
- Sem tabela, coluna, migration, rota, dependência ou campo novo no `POST` de salvar: o painel escreve em `templates[chave]`, que já existia e continua passando pela mesma validação. Nenhum controle novo tem `name`.
- O site público não muda. O que muda nele é o que o autor salvar: destino, `alt`, rótulo, `target` e `rel` dos elementos editados.
- **Mudança de comportamento no admin:** Salvar e Publicar voltam a enviar o formulário; slug fora do padrão é apontado antes do envio.
- Testes: `KazElementAttrsTest` (14 casos) e o verificador em Node (293 conferências). Suíte inteira em PHP 8.2 e 8.3: 1564 testes, 15 365 conferências; PHPStan sem erros.
- O comportamento no navegador foi conferido em Chromium, fora do PHPUnit: nos seis pacotes de `docs/temas` e em um pacote de prova, incluindo salvar o rascunho e reabrir o editor nos quatro pacotes que têm link de destino fixo.
- Verificação por trocas (um erro posto de propósito no código, para ver se alguma conferência acusa): 92 de 92 acusadas pelos testes e 22 de 22 pelo roteiro no navegador. Seis trocas ficaram fora da conta porque não mudam o resultado; estão anotadas, com o motivo, no roteiro de verificação.

## Próximas etapas (fora desta ADR)

1. **KZ-06 — cor e tamanho por elemento.**
2. **Criar `alt` e `aria-label` quando faltam**, com a lista das imagens sem texto alternativo.
3. **Os quatro comportamentos de pagamento mantidos na ADR 0097.**
