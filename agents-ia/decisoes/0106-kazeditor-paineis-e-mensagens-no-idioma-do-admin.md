# ADR 0106 — KAZEditor-Theme: painéis laterais e mensagens de ação no idioma do admin

| | |
| --- | --- |
| **Status** | ✅ aceita (KZ-13, 2026-11-15) |
| **Data** | 2026-11-15 |
| **Contexto** | A [ADR 0104](0104-kazeditor-moldura-no-idioma-do-admin.md) traduziu a moldura do editor de tema e deixou dois grupos de texto em português fixo: o conteúdo dos dois painéis laterais (camadas, blocos prontos, cores, tokens, histórico, seleção, aparência, protótipo, inspeção) e as mensagens que o rodapé mostra depois de cada ação ("cena importada", "bloco inserido"). Com o admin em inglês ou espanhol, a tela ficava metade em um idioma e metade em outro. Duas mensagens tinham ainda um defeito próprio: "painel left oculto" misturava inglês no português, e "alteracoes da cena descartadas" e "painel de paginas aberto" estavam sem acento. |

## Decisão

Esta ADR decide a **décima terceira etapa** do caminho B. Ela não acrescenta função ao editor e não cria mecanismo novo: usa o da ADR 0104 para os textos que ela deixou de fora.

### Os textos

1. **75 chaves novas em cada idioma** (`pt-br`, `en-us`, `es-eu`), `text_kaz_*`, depois das 109 da ADR 0104, no fim de `language/<idioma>/design/theme.php`. Cada arquivo passa a ter 184 chaves `text_kaz_*`, as mesmas e na mesma ordem nos três.
2. **Painéis laterais no Twig:** 52 trechos de `kaz_theme_editor.twig` trocam o texto fixo por `{{ text_kaz_<nome> }}` (em atributo, com `|e('html_attr')`).
3. **Frase com um pedaço marcado continua sem `|raw`.** Seis notas têm um nome de código ou um destaque no meio (`<code>kaztheme-scene/v1</code>`, `<strong>`, `<em>`). A frase do idioma leva `%s` nesse lugar, e o Twig a corta com `|split('%s')` e escreve a marcação entre as partes. O texto do idioma nunca é impresso como HTML.
4. **Mensagens no script:** 25 trechos de `kaz-theme-editor.js` passam a usar `TXT('<nome>', '<texto em português>'[, valor])`. São 33 chaves novas em `KAZ_FRAME_SCRIPT` (de 24 para 57), que é a lista do que o servidor entrega ao script em `data-kaz-labels`.
5. **Contagem de camadas em uma chave só** (`n_layers`, "%s camadas"). Ela aparece no rodapé do painel, no histórico e dentro de três mensagens; a frase de cada mensagem recebe o resultado pronto.

### O que muda em português

6. **"painel left oculto" passa a "painel esquerdo oculto"** (e "direito", "visível"). O script usava o nome interno do lado.
7. **A mensagem de bloco inserido usa o nome do bloco como está no botão:** "bloco Hero inserido", não "bloco hero inserido". Assim o nome sai no idioma do admin sem uma segunda lista de nomes no script.
8. **Dois acentos corrigidos:** "alterações da cena descartadas" e "painel de páginas aberto".
9. **Nenhum outro texto muda em português.** O HTML que o servidor entrega foi comparado antes e depois: o texto visível e as dicas são iguais, letra por letra.

### O que não é traduzido, de propósito

10. **Nomes técnicos ficam iguais nos três idiomas e levam `translate="no"`,** para que o tradutor automático do navegador não mexa neles: os eixos `X`, `Y`, `W`, `H`, a amostra "Aa", os tokens `primary` e `surface`, e as opções dos campos Tipo (`rect`, `text`, `button`, `card`, `section`) e Papel (`none`, `primary`, `surface`, `section`, `component`). Esses valores são os que ficam gravados na cena; traduzir a opção faria a tela mostrar uma palavra e o arquivo guardar outra.
11. **O que está em `<code>` também fica:** `kaztheme-scene/v1`, `primary`, `surface`.
12. **"CTA" e `system-ui`** entram na lista do que é igual em qualquer idioma, junto com "KAZEditor-Theme" e "Three.js".

### Testes

13. **`tests/check_kaz_frame_text.js` passa a cobrir os dois painéis laterais** (duas regiões novas) e ganha quatro conferências: só os 18 nomes técnicos do item 10 podem levar `translate="no"` (a marca não serve para esconder texto comum); as seis frases cortadas escrevem todas as partes, na ordem; não há `|raw`; toda chamada `say(...)` do script usa uma chave, e nenhuma frase de painel é escrita direto pelo script. A única mensagem sem chave é `zoom NN%`.
14. **`KazFrameTextTest`** passa a exigir 184 chaves e 57 no script. A lista de palavras que só existem em português, que não podem sobrar nos outros dois idiomas, ganhou as dos painéis e das mensagens. Há agora uma segunda lista, de palavras que o espanhol também usa ("ocultar", "bloquear", "aplicar", "tema"): essas só não podem sobrar no inglês. O número de textos em inglês que podem ser iguais ao português subiu de 12 para 16 (são 13: "Hero", "Design tokens", "layout %s" e os que já eram).
15. **`KazLooseEndsTest` e `KazFullscreenTest`** conferiam um trecho literal que esta etapa mudou (o botão do bloco Hero e a versão `kz12` no endereço dos arquivos); passam a conferir a forma nova.
16. **Versão dos arquivos:** o endereço da folha e do script do editor passa a terminar em `-kaz-theme-editor-kz13`.

## O que muda na tela

- **Admin em inglês ou espanhol:** os painéis "Páginas", "Camadas", "Ativos", "Tokens", "Histórico", "Design", "Protótipo" e "Inspecionar" e as mensagens do rodapé aparecem no idioma escolhido.
- **Admin em português:** as três mudanças dos itens 6 a 8. O resto é igual.

## Alternativas consideradas

- **Entregar ao script o arquivo de idioma inteiro.** Recusado: o script usa 57 das 184 frases; a lista `KAZ_FRAME_SCRIPT` diz quais são, e o verificador acusa a frase que falta ou sobra.
- **Imprimir a frase com `|raw` e pôr o `<code>` dentro do arquivo de idioma.** Recusado: o arquivo de idioma passaria a ser HTML, e um `<` digitado numa tradução quebraria a tela. Com `|split('%s')` o teste garante que nenhuma tradução tem `<`, `>` ou aspas.
- **Traduzir as opções de Tipo e Papel.** Recusado pelo motivo do item 10. O rótulo do campo ("Tipo", "Papel no tema") é traduzido; o valor não.
- **Uma lista de nomes de bloco no script, só para a mensagem.** Dispensada: o botão já tem o nome no idioma certo.
- **Uma chave por mensagem inteira, com a contagem dentro** ("bloco %s inserido · %s camadas"). Dispensada: o mecanismo troca um `%s` por frase; juntar duas chaves evita mudar o mecanismo e repete menos texto.

## Limites conhecidos

- **Ainda em português fixo:** os avisos "Liberação" e "Aceitação" do topo (e o trecho "liberação" da mensagem de estado), os cabeçalhos dos módulos ("Voltar ao canvas" e os subtítulos), a seção "Avançado" (botões "Exportar cena" e "Importar cena"), os textos de exemplo que um bloco pronto escreve nas camadas ("Titulo do hero", "Saiba mais") e as mensagens dos outros dois scripts da tela (`kaz-canvas.js`, `kaz-element-attrs.js`).
- **A mensagem `zoom NN%`** não tem chave: "zoom" é igual nos três idiomas.
- **Inglês e espanhol não passaram por revisão de um falante nativo,** como na ADR 0104. Corrigir é mudar a linha no arquivo de idioma.
- **Quem mantém um quarto idioma** precisa copiar as 75 chaves. Sem uma chave, o texto que o Twig escreve fica **vazio** (um rótulo sem nome, um botão em branco) e o texto que o script escreve mostra o nome da chave (`text_kaz_n_layers`).
- **Correção de um registro:** a ADR 0104 diz que um idioma sem as chaves "mostra o nome da chave". Isso vale só para as frases entregues ao script. As que o Twig escreve ficam vazias, porque o controller entrega o idioma inteiro de uma vez (`language->all()`) e o Twig imprime vazio para uma variável que não existe. Foi conferido nesta etapa, tirando uma chave de cada tipo do arquivo em inglês. O texto antigo não foi reescrito.
- **A mensagem de abertura ("KAZTheme-Editor pronto · N camadas · cena salva no tema") é trocada na hora** pela do zoom ajustado; foi traduzida, mas ninguém chega a ler. Já era assim.
- **Gênero e número:** as frases com `%s` foram escritas para o valor que recebem hoje ("%s camadas" sempre recebe número; com uma camada só, a frase fica "1 camadas", como já era).
- **Conferido só no Chromium,** sem leitor de tela.
