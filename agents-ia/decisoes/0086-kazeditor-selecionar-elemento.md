# ADR 0086 — KAZEditor-Theme: selecionar um elemento dentro do trecho

| | |
| --- | --- |
| **Status** | ✅ aceita (KZ-04, 2026-11-12) |
| **Data** | 2026-11-12 |
| **Contexto** | Com as ADRs [0084](0084-kazeditor-apontar-e-editar.md) e [0085](0085-kazeditor-tokens-ao-vivo.md), clicar na página seleciona um **trecho** (uma fonte Twig inteira). Para trocar a palavra de um título ainda era preciso achar a linha certa dentro do código daquele trecho. Nos pacotes desenhados isso pesa: `David-Creator` tem 381 textos escritos direto no código, `David-Universe-3D` tem 485, `Atlas-Studio` 305. O editor não sabia apontar "este título" nem dizer se o texto dele é fixo ou vem de um dado do site. |

## Decisão

Esta ADR decide a **quarta etapa** do caminho B: **clicar em um elemento da página seleciona aquele elemento, e o texto fixo dele é editado no painel**. Escopo fechado em texto; cor, tamanho, posição, `href` e `alt` por elemento ficam fora.

1. **Mapa de elementos por fonte.** A nova classe pura `Theme\ThemeSourceMap` lê uma fonte Twig e lista cada tag de abertura escrita nela, com a posição no código e a natureza do conteúdo: `fixed` (texto escrito no código), `variable` (só um `{{ … }}`), `mixed` (texto com Twig), `empty`, `void` (tag sem conteúdo) e `group` (contém outros elementos). Ela reconhece os delimitadores do Twig (`{{ }}`, `{% %}`, `{# #}`, `verbatim`) para nunca olhar dentro deles, e não entra em comentários, `<style>`, `<script>`, `<textarea>` nem no conteúdo de `<svg>`. Limite de 4000 elementos por fonte.

2. **Âncora no canvas, e só nele.** Com `$annotate = true`, `Twig::renderThemePage()` escreve ` data-kz-el="CHAVE:N"` logo depois do nome de cada tag listada, **na fonte**, antes de compilar. O formato é fechado: chave allowlistada e número inteiro; nenhum texto do tema entra no atributo. Antes de usar a fonte anotada, os tokens Twig dela são comparados com os da original; se houver qualquer diferença (ou a fonte não puder ser lida), a fonte segue sem âncoras e o clique volta a selecionar o trecho, como antes. Catalog, prévia isolada e site publicado continuam chamando sem `$annotate` e não recebem nada.

3. **O mapa viaja com a página.** `design/theme.canvas` devolve `elements`: por chave, a assinatura da fonte (`crc32` + tamanho) e a lista `[tag, tipo, início da tag, fim da tag, início do texto, fim do texto, expressão]`, com posições contadas como o JavaScript conta (UTF-16) sobre a fonte com quebras `\n`, igual ao que o `<textarea>` entrega. Vale para fontes do tema e para as fontes padrão que aparecem na página.

4. **Selecionar.** Clicar no canvas seleciona o elemento sob o ponteiro e o trecho que o contém. O painel **Trecho** ganha o bloco **Elemento**, com o caminho até ele (clicável, para subir de nível) e os botões **Ver no código** e **Selecionar o trecho inteiro**. O painel **Estrutura** continua selecionando trechos. `Esc` solta primeiro o elemento, depois o trecho. Com um trecho inteiro selecionado, o painel lista os **textos deste trecho** (até 80), que é o caminho por teclado até cada texto.

5. **Editar texto fixo.** Para elementos `fixed` ou `empty`, o campo **Texto** reescreve **apenas aquele intervalo** dentro de `<textarea name="templates[CHAVE]">`; o resto do código fica byte a byte igual, e o recuo original das pontas é mantido. O que é digitado nunca vira HTML nem Twig: `&`, `<`, `>` e `{` viram entidades, e os padrões que a validação do tema recusa (`on…=`, `srcdoc=`, `formaction=`, `|raw`, `javascript:`) são quebrados com entidades. A escrita só acontece se a assinatura do mapa for igual à da fonte que está no formulário; se o código mudou por outro caminho, nada é escrito, o painel avisa e a seleção do elemento é solta no render seguinte (os números dos elementos podem ter mudado).

6. **Texto que vem de dado.** Para `variable`, o painel mostra a expressão (`{{ heading_title|default('…') }}`) e explica que o texto muda com o conteúdo ou o idioma. Não há campo de edição. `mixed`, `group` e `void` recebem uma frase própria.

7. **Trecho padrão do sistema.** O elemento pode ser selecionado e visto no código, mas o campo **Texto** fica somente leitura até **Personalizar este trecho** (o mesmo botão da ADR 0084).

8. **Elemento repetido.** Uma tag dentro de `{% for %}` aparece várias vezes na página e tem um ponto só no código. O painel diz quantas vezes ela aparece e que editar muda todas.

## Revisão da ADR 0084

A ADR 0084 recusou "atributo `data-*` no primeiro elemento de cada fonte" porque exigiria analisar e reescrever a **saída** do tema e falharia em fontes sem elemento raiz único. Esta ADR adota um atributo `data-*`, por um caminho diferente:

- a anotação é feita na **fonte**, não na saída, e é descartada se os tokens Twig mudarem;
- os comentários `<!--kz:b:CHAVE-->` da ADR 0084 continuam sendo a única marcação de trecho; o atributo serve só para achar o elemento dentro dele;
- um atributo a mais não muda o fluxo nem seletores estruturais (`:first-child`, `:nth-child`). Só um seletor do tema escrito para `[data-kz-el]` seria afetado, e apenas no canvas. Nenhum template padrão nem pacote tem isso.

O item 3 da ADR 0084 ("o editor não escreve no documento do quadro"), já ampliado pela ADR 0085, ganha mais uma exceção: enquanto o campo **Texto** é digitado, o editor troca o `textContent` das ocorrências daquele elemento, e só de elementos sem filhos. É texto puro; nenhuma marcação é criada. O render seguinte do servidor substitui o documento inteiro.

Como nos marcadores de trecho, a âncora é **informativa e não uma barreira**: o autor do tema pode escrever um `data-kz-el` no próprio código. O atributo do editor vem primeiro na tag (o navegador usa o primeiro), e o editor confere chave, índice e nome da tag antes de aceitar. Dado da página não consegue forjar a âncora (passa pelo autoescape; há teste).

## Correção incluída: `--accent` passa a seguir a cor Primária

Achado registrado na ADR 0085. Em `catalog/view/css/theme.css`, `--accent: var(--theme-primary, #6d28d9)` era declarado em `:root`, mas `--theme-primary` é definido no `style` do `<body>`. Uma variável resolvida no `:root` não enxerga um valor do `<body>`, então `--accent` ficava sempre em `#6d28d9`.

A correção redeclara `--accent` no `body`. Medido no canvas com o tema todo no padrão do sistema e Primária `#ff0066`: antes, 65 usos de cor em `#6d28d9` e 1 na Primária; depois, 66 na Primária e nenhum no roxo fixo.

**Isto muda sites publicados**: quem usa os templates padrão com uma cor Primária diferente de `#6d28d9` passa a ver essa cor em botões, links e destaques. Sem cor definida, nada muda. Decisão tomada com o responsável pelo projeto.

Não corrigido, de propósito: `--bg: var(--theme-surface, #ffffff)` tem o mesmo defeito. A cor do texto (`--ink`) é fixa e escura, então fazer a Superfície valer poderia deixar texto ilegível em sites que hoje têm uma Superfície escura sem efeito. Também `--accent-ink` é branco fixo: uma Primária clara dá pouco contraste nos botões.

## Alternativas consideradas

- **Achar o elemento só pelo navegador (comparar o HTML da página com o código):** não distingue texto fixo de dado, e erra quando o Twig gera ou remove tags. Recusado.
- **Analisar a árvore de nós do Twig:** o Twig trata o HTML como texto opaco; a árvore não tem elementos. Seria preciso o mesmo leitor de tags de qualquer forma.
- **Edição direta na página (`contenteditable`):** exige scripts ou escrita livre no quadro, contra a ADR 0080, e o resultado teria de ser traduzido de volta para o código. Recusado.
- **Guardar os textos fora do código (tabela ou JSON de textos):** mudaria o contrato do pacote e pediria migration. Fora do escopo.

## Limites conhecidos

- **Só texto de elemento sem filhos.** Em `<h1>Olá, <b>mundo</b></h1>`, "mundo" é editável no painel; "Olá, " só no código.
- **Sem âncoras, sem seleção de elemento:** fontes com `{% extends %}` (já sem marcadores desde a ADR 0084), fontes em que a anotação mudaria os tokens Twig, tags com nome dinâmico (`<h{{ n }}>`) e o interior de `<svg>` (o `<svg>` inteiro é um elemento).
- **O clique acha o elemento de cima.** Um elemento coberto por outro, ou com `pointer-events: none`, não é alcançado pelo clique; use o caminho ou a lista de textos.
- **Ao editar, o texto é regravado como texto simples.** Entidades como `&nbsp;` viram o caractere correspondente, e espaços e quebras de linha repetidos no meio do texto viram um espaço. Texto não editado não é tocado.
- Limites: 2000 caracteres por texto, 131 072 por fonte (o mesmo do formulário), 80 linhas na lista de textos.
- **Custo:** o render anotado do canvas levou cerca de 4× o render sem âncoras nas medições locais (de ~8 ms para ~34 ms por página, 35 páginas). Só o canvas paga.
- Mexer em uma **variável do tema** (ADR 0085) ou no código reescreve a fonte; se havia um elemento selecionado nela, a seleção é solta no render seguinte.
- Continuam valendo os limites das ADRs 0083, 0084 e 0085.

## Consequências

- Arquivo novo: `system/library/theme/theme_source_map.php`. Alterados: `system/library/template/twig.php` (só o caminho `$annotate`), `admin/controller/design/theme.php`, `admin/view/js/kaz-canvas.js`, `admin/view/css/kaz-canvas.css`, os três arquivos de idioma (18 chaves `text_canvas_el_*` novas e 2 textos reescritos em cada) e `catalog/view/css/theme.css` (a correção acima).
- `design/theme.canvas` continua somente leitura (`POST` + CSRF + ACL `access`). Saída nova: `elements`. Nenhuma entrada nova.
- Sem tabela, coluna, migration, rota, dependência ou campo novo no `POST` de salvar: o painel escreve em `templates[chave]`, que já existia e continua passando pela mesma validação.
- Testes: `tests/Unit/Library/KazElementSelectTest.php` (13 casos); três casos de `KazPointEditTest` foram ajustados para a página anotada conter as âncoras. A ação do controller e o comportamento no navegador foram conferidos em Chromium headless nos seis pacotes, fora do PHPUnit.

## Próximas etapas (fora desta ADR)

1. **Mais do elemento:** `href` de links, `alt` de imagens, e depois cor e tamanho por elemento.
2. **Passada de segurança** (S1–S12 do relatório de análise).
3. **Contrato de cena v2:** auto layout, restrições e componentes.
