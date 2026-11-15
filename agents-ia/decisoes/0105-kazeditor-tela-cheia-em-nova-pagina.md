# ADR 0105 — KAZEditor-Theme: a tela cheia do editor volta a funcionar

| | |
| --- | --- |
| **Status** | ✅ aceita (KZ-12, 2026-11-15) |
| **Data** | 2026-11-15 |
| **Contexto** | O editor de tema tem duas telas cheias: o botão "Tela cheia" (na mesma aba, tecla F) e o link "Tela cheia em nova página", que abre a mesma tela com `kaz_fullscreen=1`. A [ADR 0104](0104-kazeditor-moldura-no-idioma-do-admin.md) registrou que a segunda abria uma página sem folhas de estilo e sem scripts, em que o editor não iniciava. Ao reproduzir o defeito, esta etapa encontrou mais dois, nas duas telas cheias: sobrava no pé da janela uma faixa de 104 px com o Admin, e o menu, a barra e o rodapé do Admin continuavam atrás do editor, no caminho da tecla Tab. |

## Decisão

Esta ADR decide a **décima segunda etapa** do caminho B. Ela não acrescenta função ao editor: conserta as duas telas cheias que já existiam.

### A página cheia carrega o cabeçalho e o rodapé do Admin

1. **`theme_form.twig` inclui o cabeçalho e o rodapé sempre.** Antes, com `kaz_fullscreen=1`, os dois ficavam de fora. É o cabeçalho que abre o documento (`<!doctype>`, `<head>`) e carrega as folhas de estilo e os scripts registrados pela tela; é o rodapé que carrega `bootstrap.bundle.min.js` e `admin.js` e fecha o documento. Sem eles o navegador recebia só o miolo da tela.
2. **O `<body>` da página cheia recebe a classe `kaz-page-fullscreen`.** `Design\Theme::edit()` passa a classe para `adminChrome()`, que já tinha esse terceiro parâmetro. Na tela normal o valor é `null` e o `<body>` sai como sempre.
3. **O controller não ganha rota, parâmetro nem dado novo.** `kaz_fullscreen=1` continua sendo o único sinal, e o atributo `data-kaz-page-mode` do editor continua igual.

### A tela cheia ocupa a janela inteira

4. **Regra de altura com peso maior.** A folha já dizia que o editor em tela cheia tem `height: 100vh`, mas a regra do editor comum (`.kaz-shell.kaz-card { height: calc(100dvh - 104px) }`) tem o mesmo peso e vem depois na folha, então ganhava. Duas regras novas, `.kaz-shell.kaz-card[data-kaz-fullscreen="1"]` e `.kaz-shell.kaz-card[data-kaz-page-mode="fullscreen"]`, têm um seletor a mais e ganham em qualquer ordem e em qualquer largura de janela.
5. **As regras antigas ficam como estão.** Elas continuam dando posição fixa, margem, borda e largura; só a altura passou a ser decidida pelas novas.

### O Admin atrás do editor sai da tela

6. **Menu lateral, barra de cima, rodapé e o fundo do menu do Admin recebem `display: none`** enquanto o editor cobre a janela. Assim a tecla Tab não passa por eles e o leitor de tela não os anuncia.
7. **Duas classes no `<body>`, uma para cada tela cheia.** `kaz-page-fullscreen` vem do servidor (item 2). `kaz-tab-fullscreen` é posta e retirada pelo script, em `setFullscreenLocal()`, junto com o atributo `data-kaz-fullscreen` que já existia. As regras ficam na folha do editor, que só é carregada nesta tela.
8. **A folha não usa `:has()` para isso.** A regra antiga `body:has(.kaz-shell[data-kaz-page-mode="fullscreen"])` continua na folha, mas as novas dependem só de classe: funcionam também em navegador sem `:has()`.
9. **Versão dos arquivos:** o endereço da folha e do script do editor passa a terminar em `-kaz-theme-editor-kz12`.

### Testes

10. **`KazFullscreenTest` (3 testes).** O primeiro confere que `theme_form.twig` inclui cabeçalho e rodapé sem condição e que o controller entrega a classe só na página cheia. O segundo lê a folha, calcula o peso de cada seletor que dá altura ao editor e exige que a altura de tela cheia pese **mais** que a do editor comum (peso igual não basta: foi assim que a faixa apareceu). O terceiro confere que as quatro partes do Admin somem com cada uma das duas classes, que nenhuma regra as esconde fora da tela cheia, e que o script liga e desliga a classe da aba.
11. **`KazFrameTextTest`** aceitava só a versão `kz11` no endereço dos arquivos; passa a aceitar `kz11` em diante.

## O que muda na tela

- **"Tela cheia em nova página":** abre o editor funcionando, ocupando a janela inteira, sem o menu do Admin. Antes abria uma página sem estilo, com o aviso "Aguardando o editor" parado.
- **"Tela cheia" (mesma aba, tecla F):** o editor passa a ocupar a janela inteira. Antes sobrava uma faixa de 104 px no pé, com o fim do menu e o rodapé do Admin.
- **Tela normal:** nada muda.

## Alternativas consideradas

- **Um cabeçalho próprio para a página cheia, só com o `<head>`.** Recusado: seria uma segunda cópia do que `common/header.twig` faz (folhas, scripts, CSRF, nonce da política de conteúdo), e as duas cópias precisariam andar juntas a cada mudança do Admin.
- **Um parâmetro novo em `common/header` para não imprimir menu e barra.** Recusado: `header.twig` e `footer.twig` abrem e fecham as mesmas `<div>`; os dois controllers precisariam do mesmo sinal, e a tela cheia na mesma aba continuaria precisando esconder o Admin por CSS. Uma solução só, por classe, serve às duas.
- **Trocar a ordem das regras na folha** (pôr a de tela cheia depois da do editor comum). Recusado: voltaria a quebrar na próxima vez que alguém acrescentasse uma regra de altura no fim da folha; a regra dentro de `@media (max-width: 980px)` já é um segundo caso. O peso maior não depende de ordem.
- **`!important` na altura.** Dispensado: o seletor a mais resolve.
- **Esconder o Admin com `visibility: hidden` ou `inert`.** Dispensado: `display: none` tira do Tab e do leitor de tela e não depende de script; para a página cheia, o servidor já entrega a classe.

## Limites conhecidos

- **Os avisos "Liberação" e "Aceitação" ficam atrás do editor nas duas telas cheias.** Eles são irmãos do editor, acima dele, e o editor em tela cheia cobre a janela. Já era assim na tela cheia da mesma aba. Se o script do editor não carregar, quem está na página cheia não vê o aviso "Aguardando o editor"; na tela normal ele continua visível.
- **Salvar leva para a lista de temas,** na página cheia como na tela normal. Quem abriu a página cheia em outra aba termina com duas abas do Admin.
- **O menu do Admin continua no HTML da página cheia,** só fora da tela. O servidor monta o menu mesmo sem ele aparecer.
- **Os textos "Tela cheia", "Tela cheia em nova página" e "Sair da página cheia" não mudaram.**
- **Conferido só no Chromium,** em janelas de 1500×900, 1024×700 e 390×844.
