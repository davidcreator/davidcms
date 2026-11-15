# ADR 0108 — KAZEditor-Theme: pendências fechadas (singular de "1 camada", aviso vermelho, marca de versão, rótulos do construtor de condições, regras sem uso)

| | |
| --- | --- |
| **Status** | ✅ aceita (KZ-15, 2026-11-15) |
| **Data** | 2026-11-15 |
| **Contexto** | As etapas KZ-10 a KZ-14 deixaram anotados achados pequenos, que não cabiam no assunto de cada uma: a contagem "1 camadas" ([ADR 0106](0106-kazeditor-paineis-e-mensagens-no-idioma-do-admin.md)); o aviso vermelho que manda rodar um arquivo que não existe, a marca de versão antiga em `ThemeReleaseProbe` e a falta de teste dessa classe ([ADR 0107](0107-kazeditor-avisos-modulos-e-avancado-no-idioma-do-admin.md)); o rótulo "ID" da regra de condição sem ligação com o campo e a regra `.kaz-btn--danger` sem uso ([ADR 0103](0103-kazeditor-pendencias-fechadas.md)). |

## Decisão

Esta ADR decide a **décima quinta etapa** do caminho B. Como a ADR 0103, ela não acrescenta função: fecha o que ficou anotado. Dois dos itens se mostraram maiores do que a anotação dizia; o que foi feito e o que ficou está escrito em cada parte.

### Parte 1 — "1 camada"

1. **Uma chave nova por idioma, `text_kaz_one_layer`** ("1 camada", "1 layer", "1 capa"), ao lado de `text_kaz_n_layers`. São 242 chaves `text_kaz_*` por idioma e 74 em `KAZ_FRAME_SCRIPT`.
2. **No script, só a função `layersText(n)` escreve a contagem.** Ela devolve o singular quando `n` é 1 e o plural nos outros casos (zero inclusive). Os seis lugares que montavam a frase passam a chamá-la: painel de camadas, histórico, mensagem de exportação, mensagem de bloco inserido, mensagem de abertura e detalhe do aviso "Aceitação".
3. **No Twig, o aviso "Liberação" escolhe a chave pelo número** de camadas que o servidor contou.
4. **Não foi criado um mecanismo geral de plural.** As outras contagens da tela não precisam: "%s grupo(s) recolhido(s)" já cobre os dois casos, o limite de camadas é sempre 48 e o número de módulos é fixo e maior que um.

### Parte 2 — aviso vermelho

5. **Sai a última frase do aviso** que aparece quando o editor não inicia: "Execute tools/kaz-theme-editor-health.js no console para o diagnóstico." O arquivo não existe no repositório. O resto do aviso continua dizendo o que conferir (o arquivo do script, o `header.twig`, o cache), e o aviso "Aceitação", no topo, repete o diagnóstico.
6. **O arquivo não foi criado.** Ele não aparece em nenhum ponto do histórico do repositório; o aviso "Aceitação" já mostra na tela o que um diagnóstico de console mostraria.
7. **Teste:** qualquer frase do editor que cite um caminho de `tools/` precisa apontar para um arquivo que exista. Hoje nenhuma cita.

### Parte 3 — marca de versão e teste de `ThemeReleaseProbe`

8. **`ThemeReleaseProbe::ASSET_MARK` passa a ser `-kaz-theme-editor-`**, o trecho da versão que não muda de etapa para etapa. Valia `-kaz-theme-editor-aj`; desde a KZ-11 a versão termina em `-kzNN`, e `asset_ok` (o atributo `data-kaz-release-asset` do aviso "Liberação") dava sempre 0. Agora dá 1.
9. **Nada na tela lê esse atributo.** A mudança não altera o que se vê; deixa o dado certo para quem inspeciona a página.
10. **`ThemeReleaseProbeTest` (14 testes)** cobre o que a classe decide: os três vereditos, a lista de chaves pendentes (ordem alfabética, no máximo 16), a comparação que não depende da ordem das chaves, as partes e o tamanho do CSS, a contagem de camadas até 48, a marca e o corte da versão. Fora a marca, o comportamento da classe não foi alterado.

### Parte 4 — rótulos do construtor de condições

11. **A anotação falava de um rótulo ("ID"); eram todos os do construtor.** A função `labelRow`, de `theme-condition-builder.js`, criava `<label>` sem `for`. O texto aparecia ao lado do campo, mas o leitor de tela não dizia o nome ao entrar no campo, e clicar no rótulo não levava o cursor para ele. São 8 rótulos na parte do simulador e 18 por regra.
12. **`labelRow` passa a ligar o rótulo ao que ele nomeia:**
    - com um campo só (`input`, `select`, `textarea`), o campo ganha um `id` e o rótulo, `for`;
    - com uma caixa de vários campos ("Variáveis", "Tokens", "Three.js"), não há um campo para apontar: o título vira `<span>` e a caixa vira um grupo com esse nome (`role="group"` e `aria-labelledby`).
13. **Os `id` são só de tela** (`tcb-f1`, `tcb-f2`, …, sem repetição mesmo depois de redesenhar as regras). Os campos continuam sem `name`: o que vai no `POST` é o campo `conditions`, como antes (ADR 0098).
14. **A tela não muda de lugar.** `admin.css` dá a todo `<label>` tamanho, peso e margem; o título de grupo, que deixou de ser `<label>`, recebe o mesmo por uma regra nova em `theme-condition-builder.css`. Um teste confere que as duas regras dizem a mesma coisa.
15. **Efeito para quem usa o mouse:** clicar no rótulo põe o cursor no campo; no caso de "Logado", marca e desmarca a caixa.
16. **Teste:** `tests/check_condition_builder_labels.js` roda o script sobre uma página de mentira e confere a árvore que ele monta, sem regra e com duas regras, antes e depois de redesenhar.
17. **Versão:** o endereço da folha e do script do construtor passa de `-theme-builder-b` para `-theme-builder-c`.

### Parte 5 — regras sem uso na folha do editor

18. **A anotação falava de uma regra (`.kaz-btn--danger`); eram seis.** Saem de `kaz-theme-editor.css` as regras de `.kaz-btn--danger`, `.kaz-tools`, `.kaz-blocks`, `.kaz-blocks__row`, `.kaz-block` (com a de `:hover`) e `.kaz-nojs .kaz-modules`. Nenhuma tela e nenhum script usam essas classes.
19. **Teste:** toda classe `kaz-*` ou `tcb-*` das três folhas do editor precisa aparecer num Twig, num script ou no controller. É o inverso do teste da ADR 0103 (toda classe da tela tem regra ou script). Duas famílias são montadas pelo script juntando um prefixo a um valor (`kaz-canvas__note--`, `tcb-group--`) e entram por uma lista curta.
20. **A cor `--kaz-danger` fica na paleta,** sem regra que a leia por enquanto.

### Versão dos arquivos

21. O endereço da folha e do script do editor passa a terminar em `-kaz-theme-editor-kz15`.

## O que muda na tela

- **Com uma camada só,** a tela diz "1 camada" ("1 layer", "1 capa") no painel, no histórico, nas mensagens e nos dois avisos do topo.
- **O aviso vermelho** de quando o editor não inicia termina na frase do cache; a menção ao arquivo que não existe saiu.
- **No módulo Condições,** clicar num rótulo leva ao campo, e o leitor de tela diz o nome do campo. O desenho é o mesmo: posição, tamanho e estilo de cada linha foram comparados antes e depois.
- **Fora isso, nada muda.**

## Alternativas consideradas

- **Mecanismo de plural em `KazEditorText`** (uma chave com as duas formas). Recusado: há uma contagem só que precisa, e o contrato da função é conferido por teste; uma chave a mais resolve.
- **"camada(s)".** Recusado: é o texto mais visto do painel; a forma com parênteses fica para onde o singular é raro.
- **Criar `tools/kaz-theme-editor-health.js`.** Recusado: seria um arquivo novo para manter, e o que ele mostraria já está no aviso "Aceitação".
- **Tirar `asset_ok` e o atributo.** Recusado: o resumo é um contrato de 12 campos; mudar a marca conserta o dado sem mexer no formato.
- **Apontar o rótulo de grupo para o primeiro campo da caixa.** Recusado: "Tokens" passaria a ser o nome do campo `color_text`, que já tem o próprio rótulo.
- **`<fieldset>` e `<legend>` para os grupos.** Dispensado: mudam o desenho das caixas; `role="group"` dá o mesmo nome ao leitor de tela sem mexer na tela.
- **Apagar `--kaz-danger` junto.** Dispensado: é uma cor da paleta, não uma regra.

## Limites conhecidos

- **Na parte "Quando" de cada regra, os campos continuam sem nome:** a lista do tipo de grupo, a do campo, a do operador e o valor não têm rótulo (no tema de prova, com cinco regras, são 20 campos). Nomeá-los pede textos novos nos três idiomas.
- **Nos outros módulos do editor há 98 rótulos sem ligação com o campo,** escritos no Twig: Geral (5), Tokens (22), Three.js (5), Mídia (24), Sites (16), Componentes (20) e Comparar (6). Há também campos sem rótulo algum: os 25 do módulo Templates e 11 campos numéricos do canvas de página. Nada disso foi tocado nesta etapa.
- **`ThemeReleaseProbe` compara os mapas de dentro pelo JSON:** `4` e `"4"` dentro de `tokens` contam como diferença, e a ordem das chaves só é ignorada nos dois primeiros níveis. Como publicar copia o rascunho inteiro, isso não aparece no uso; o teste registra o comportamento.
- **Conferido só no Chromium,** sem leitor de tela: o nome de cada campo foi lido pela árvore de acessibilidade do navegador.

## Achados, não corrigidos

- **O nome do editor aparece de dois jeitos na tela:** "KAZEditor-Theme" nos avisos do topo e "KAZTheme-Editor" no aviso vermelho e na mensagem de abertura.
- **No script, se o aviso "Liberação" não estiver na página, o detalhe do aviso "Aceitação" mostraria "indisponivel",** sem acento e fora dos arquivos de idioma. O aviso está sempre na página; o texto nunca aparece.
- **No controller, a variável que guarda a versão do editor ainda se chama `$kazAssetAJ`.**
