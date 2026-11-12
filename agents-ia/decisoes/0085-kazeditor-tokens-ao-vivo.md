# ADR 0085 — KAZEditor-Theme: tokens ao vivo

| | |
| --- | --- |
| **Status** | ✅ aceita (KZ-03, 2026-11-12) |
| **Data** | 2026-11-12 |
| **Contexto** | Com a [ADR 0084](0084-kazeditor-apontar-e-editar.md) a página do rascunho ficou clicável, mas cores, tipografia e espaçamentos continuavam em outra tela (módulos Geral e Tokens): mudar um valor, voltar para Página e esperar o render. Além disso, quatro dos seis pacotes entregues (`Atlas-Studio`, `Atlas-Studio-3D`, `David-Creator`, `David-Universe-3D`) não leem os 22 tokens do sistema: declaram variáveis próprias (`--at-*`, `--dj-*`, `--du-*`) em um `<style>` dentro de `partial/site_header`. Um painel só com os tokens pareceria quebrado nesses temas. O tema escuro também não tinha como ser visto no editor. |

## Decisão

Esta ADR decide a **terceira etapa** do caminho B: **os valores de design são editados ao lado da página, com resultado imediato**.

1. **Aba Design no painel direito.** O painel da ADR 0084 ganha duas abas: **Trecho** (o que já existia) e **Design**. Nenhum controle da aba tem `name`: cada um escreve no campo que já existe no formulário (`primary`, `surface`, `tokens[chave]`, `templates[chave]`). O que é salvo e validado não muda.

2. **Mapa token → variável CSS, derivado e não copiado.** A nova classe pura `Theme\ThemeLiveTokens::spec()` descobre, sondando `TokenRegistry::css()`, qual variável `--rms-*` cada token gera e como o valor é formado (cor, número com sufixo, ou lista fechada de opções). O editor recebe esse mapa em `canvas_config_json.tokens`. Um teste garante que o valor que a prévia aplica é igual ao que o servidor gera para o mesmo token.

3. **Prévia imediata e confirmação do servidor.** Ao mexer em um controle, o editor (a) grava o campo real, (b) aplica a variável na hora dentro de cada quadro e (c) dispara o mesmo render de 700 ms da ADR 0083. O que fica na tela depois é sempre a resposta do servidor.

4. **Variáveis do próprio tema.** `ThemeLiveTokens::styleVars()` lê os blocos `<style>` das fontes allowlistadas e devolve as variáveis declaradas com valor simples: cor `#rrggbb` ou tamanho em `px`. Cada uma vira um controle em **Variáveis deste tema**. Mudar um controle reescreve **só aquele valor** dentro do `<textarea name="templates[chave]">`; o resto do código fica byte a byte igual. O navegador aplica a mesma gramática do servidor para achar a declaração (N-ésima ocorrência do nome naquela fonte); se o código mudou nesse meio-tempo e a declaração não está mais lá, nada é escrito e o editor avisa.

5. **Aviso "não usado".** Depois de cada render, o editor troca uma variável por vez no quadro principal e compara o estilo calculado dos elementos da página. Se nada muda, a linha recebe o selo **não usado** — o controle continua funcionando, só fica claro que este tema, nesta página, não lê aquele valor. A medição é desfeita antes de terminar.

6. **Claro / Escuro.** A barra do canvas ganha o seletor de esquema. `design/theme.canvas` aceita `canvas_scheme=light|dark`; em `dark`, a página sai com `data-bs-theme="dark"` no `<html>`, o mesmo atributo que o Catalog usa. A escolha fica nas preferências locais do editor e não é salva no tema.

7. **Contraste e motivo do erro à vista.** A resposta do canvas traz `contrast` (os sete pares de `TokenRegistry::contrastPairs()`, com razão, mínimo e aprovado/reprovado) calculado com as cores do formulário, mesmo quando o formulário está inválido, e `form_error` com o motivo da recusa. Antes, um contraste abaixo de 4,5:1 fazia o canvas voltar ao rascunho salvo sem dizer por quê.

## Desvio documentado da ADR 0084

O item 3 da ADR 0084 dizia que o editor **não escreve nada no documento do quadro**. A partir desta ADR ele escreve duas coisas, e só estas:

- propriedades personalizadas no atributo `style` do `<body>` (variáveis `--rms-*`, `--theme-primary`, `--theme-surface`);
- o valor de uma propriedade personalizada em uma regra já existente de um `<style>` do tema, pelo CSSOM.

Nomes passam por `^--[a-z][a-z0-9-]{0,47}$`; valores são cor `#rrggbb`, número dentro da faixa do token, tamanho de 0 a 4000 px ou um item da lista fechada vinda do servidor. Nada de HTML, seletor, regra nova ou atributo novo. O `sandbox="allow-same-origin"` (sem `allow-scripts`) dos quadros não muda, e o próximo render do servidor substitui o documento inteiro.

## Alternativas consideradas

- **Só os 22 tokens do sistema:** mais simples, mas sem efeito visível em quatro dos seis pacotes. Recusado.
- **Esperar só o render do servidor (sem prévia imediata):** mantém a ADR 0084 intacta, mas arrastar um seletor de cor com 700 ms de atraso não dá retorno. Recusado.
- **Converter as variáveis do tema em tokens novos no banco:** exigiria mudar o contrato do pacote e migration. Fora do escopo; os valores continuam morando no código do tema.
- **Detectar uso lendo o CSS em busca de `var(--nome)`:** não enxerga herança, `calc()` nem valor padrão do `var()`. Medir o resultado é mais confiável.

## Limites conhecidos

- **"Não usado" vale para a página, o esquema e o quadro medidos** (o primeiro quadro visível). Um token sem efeito na página inicial pode ter efeito em outra rota. `focus_ring` e as cores do esquema que não está na tela não são medidos. As variáveis do próprio tema não são medidas.
- **Variáveis do tema: só cor `#rrggbb` e tamanho em `px`.** `rgba()`, fontes, `clamp()`, `var()` e cores de 3 dígitos não viram controle; continuam editáveis no código. Limite de 96 variáveis e de 131 072 bytes por fonte.
- Se duas fontes diferentes declaram a mesma variável, a prévia imediata pode acertar a regra errada por até 700 ms; o render do servidor corrige. Nenhum pacote entregue faz isso.
- **O seletor Escuro não muda temas que não têm estilo escuro.** Os quatro pacotes desenhados têm cor fixa e ficam iguais nos dois esquemas.
- O contraste mostrado é o dos tokens do sistema. Um tema com variáveis próprias não tem o contraste das suas cores medido.
- Número fora da faixa digitado em um controle é ignorado; ao sair do campo ele volta a mostrar o valor aceito.
- **Achado fora do escopo, não corrigido:** em `catalog/view/css/theme.css`, `--accent` é declarado em `:root` como `var(--theme-primary, #6d28d9)`, mas `--theme-primary` é definido no `<body>`. Uma variável do `<body>` não é vista pelo `:root`, então as 22 regras que usam `--accent` ficam sempre com `#6d28d9` e a cor **Primária** do módulo Geral não as altera. O selo "não usado" mostra isso corretamente. Corrigir muda a aparência de sites publicados e pede decisão própria.
- Continuam valendo os limites das ADRs 0083 e 0084.

## Consequências

- Arquivo novo: `system/library/theme/theme_live_tokens.php`. Alterados: `admin/controller/design/theme.php`, `admin/view/template/design/kaz_theme_editor.twig`, `admin/view/js/kaz-canvas.js`, `admin/view/css/kaz-canvas.css` e os três arquivos de idioma (26 chaves `text_canvas_*` novas em cada).
- `design/theme.canvas` continua somente leitura (`POST` + CSRF + ACL `access`). Entrada nova: `canvas_scheme`. Saída nova: `scheme`, `form_error`, `style_vars`, `contrast`.
- Sem tabela, coluna, migration, rota, dependência ou campo novo no `POST` de salvar. O Catalog, a prévia isolada e o site publicado não mudam.
- Testes: `tests/Unit/Library/KazLiveTokensTest.php` (9 casos). A ação do controller e o comportamento no navegador foram conferidos em Chromium headless nos seis pacotes, fora do PHPUnit.

## Próximas etapas (fora desta ADR)

1. **Contrato de cena v2:** selecionar um elemento dentro de uma fonte (um título, um botão), auto layout, restrições e componentes.
2. **Passada de segurança** (S1–S12 do relatório de análise).
3. **Ferramentas de desenho** sobre o canvas e modo código como visão avançada do mesmo documento.
