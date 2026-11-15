# ADR 0107 — KAZEditor-Theme: avisos, cabeçalhos dos módulos e seção "Avançado" no idioma do admin

| | |
| --- | --- |
| **Status** | ✅ aceita (KZ-14, 2026-11-15) |
| **Data** | 2026-11-15 |
| **Contexto** | A [ADR 0104](0104-kazeditor-moldura-no-idioma-do-admin.md) traduziu a moldura do editor de tema e a [ADR 0106](0106-kazeditor-paineis-e-mensagens-no-idioma-do-admin.md), os painéis laterais e as mensagens de ação. Sobravam em português fixo: os avisos "Liberação" e "Aceitação" do topo, o cabeçalho de cada um dos 14 módulos ("Voltar ao canvas" e o subtítulo), a seção "Avançado" e os textos de exemplo que um bloco pronto escreve nas camadas. Ao levantar o que faltava apareceu mais um: o aviso vermelho que o script mostra quando o editor não consegue iniciar. |

## Decisão

Esta ADR decide a **décima quarta etapa** do caminho B. Ela não acrescenta função nem mecanismo: usa o da ADR 0104 no que restava. Com ela, o arquivo `kaz_theme_editor.twig` inteiro deixa de ter frase fixa.

### Os textos

1. **57 chaves novas em cada idioma** (`pt-br`, `en-us`, `es-eu`), `text_kaz_*`, depois das 184 que já existiam. Cada arquivo passa a ter 241, as mesmas e na mesma ordem nos três.
2. **16 delas vão para o script** (`KAZ_FRAME_SCRIPT` passa de 57 para 73): o aviso "Aceitação", o aviso vermelho e os seis textos de exemplo dos blocos.
3. **49 trechos do Twig** trocam o texto fixo por `{{ text_kaz_<nome> }}`. A nota longa da seção "Avançado" tem dois destaques e dois nomes de código no meio; é cortada com `|split('%s')`, como as seis notas da ADR 0106. Continua sem `|raw`.

### Aviso "Liberação"

4. **O Twig escolhe a frase pelo veredito.** `ThemeReleaseProbe` devolve o veredito (`PUBLICADO`, `RASCUNHO_PENDENTE`, `SEM_TEMA_ATIVO`) e também um rótulo e uma orientação prontos, em português e sem acento. O Twig passa a ler só o veredito e a imprimir `text_kaz_rel_*` conforme o caso.
5. **`ThemeReleaseProbe` não muda.** A classe é pura e não tem acesso ao idioma. `verdict_label` e `hint` continuam no resultado; a tela é que deixou de usá-los.
6. **A linha de números** ("CSS: 6/6 partes · 12345 bytes · 5 camadas") usa `rel_parts` e reaproveita `n_layers`, da ADR 0106. "CSS" e "bytes" ficam iguais nos três idiomas.

### Aviso "Aceitação" e aviso vermelho

7. **O texto que o servidor escreve** ("Aguardando o editor" e a explicação, mais a frase do `<noscript>`) vem do idioma. É esse texto que fica na tela quando o script não chega.
8. **O que o script escreve** ("Editor pronto", "Editor não inicializou", o detalhe) usa `TXT(...)`. O detalhe é montado com sete chaves curtas, porque o mecanismo troca um `%s` por frase.
9. **O estado da liberação aparece no detalhe com o nome, não com o código:** "liberação: Publicado", e não "liberação PUBLICADO". O script lê o nome no aviso de cima, que já está no idioma do admin. Sem isso o detalhe em inglês diria "release PUBLICADO".
10. **O aviso vermelho** (quando o editor existe na página mas o script não conclui o início) também usa uma chave. Se nem o elemento do editor existir, o script não tem de onde ler as frases e usa o texto em português, que é o de reserva.
11. **Os atributos não são traduzidos:** `data-kaz-acceptance` continua valendo `aguardando`, `pronto` ou `falhou`, e `data-kaz-release-verdict`, o código do veredito. São valores lidos por script e por teste.

### Cabeçalhos dos módulos e seção "Avançado"

12. **O título de cada módulo reaproveita a chave do botão do módulo** (`text_kaz_mod_*`). Assim o cabeçalho e o botão não podem divergir. "Three.js" continua fixo.
13. **13 subtítulos novos** (`text_kaz_sub_*`) e uma chave para "Voltar ao canvas", usada nos 14 cabeçalhos. O subtítulo de "Three.js" é "Cena 3D", que já existia.
14. **Seção "Avançado":** título, as duas notas da cena (salva ou derivada), o rótulo e o nome do campo, "Exportar cena", "Importar cena", a nota longa e o título "Pacote, prévia isolada e revisões".

### Textos de exemplo dos blocos prontos

15. **Seis textos** ("Título do hero", "Saiba mais", "Cartão de conteúdo", "Aviso ou chamada", "Novo título", "Começar") saem no idioma do admin **no momento em que o bloco é inserido**. Depois disso são texto da camada, gravado com a cena; mudar o idioma do admin não os altera.

### O que muda em português

16. **Aviso "Liberação":** a orientação ganha os acentos ("Prévia e catálogo usam o mesmo tema.", "Há alterações não publicadas…", "…publicar no catálogo.").
17. **Aviso "Aceitação":** "liberação: Publicado" no lugar de "liberação PUBLICADO" (item 9).
18. **Blocos prontos:** os textos de exemplo ganham os acentos ("Título do hero", "Cartão de conteúdo", "Novo título", "Começar"). Vale para blocos inseridos daqui em diante; cenas já salvas não mudam.
19. **Aviso vermelho:** ganha os acentos.
20. **Nota longa da seção "Avançado":** a última frase termina em "upload ou `custom_js`." O trecho "ou chave de tradução nova" saiu, porque deixou de ser verdade para a tela.
21. **Fora isso, nada muda.** O HTML que o servidor entrega em português foi comparado antes e depois: a marcação é a mesma, e o texto difere só nos itens 16 e 20.

### Testes

22. **`tests/check_kaz_frame_text.js` passa a conferir o arquivo Twig inteiro,** não só as regiões. Fora das regiões antigas vale uma lista um pouco maior do que pode ficar fixo: "CSS:", "bytes", ".twig", "query.\*:", "site:" e três exemplos de preenchimento (`exemplo.com`, `loja.exemplo.com`, o exemplo de pacote). Dentro das regiões antigas continua valendo a lista curta.
23. **Conferências novas:** os 14 cabeçalhos têm título, subtítulo e botão vindos do idioma; o Twig não imprime `verdict_label` nem `hint` e escolhe a frase pelos três vereditos que `ThemeReleaseProbe` produz; o aviso "Aceitação" e o aviso vermelho não têm frase direta no script; toda camada de bloco pronto tem texto vazio ou uma chave `sample_*`. As frases cortadas com `split` passam de seis para sete.
24. **`KazFrameTextTest`** passa a exigir 241 chaves e 73 no script. As duas listas de palavras (só português; português e espanhol) ganharam as desta etapa. O número de textos em inglês que podem ser iguais ao português subiu de 16 para 20 (são 17: entraram "Boot OK", "Section builder", "Page models" e "Condition builder", que já eram em inglês).
25. **Versão dos arquivos:** o endereço da folha e do script do editor passa a terminar em `-kaz-theme-editor-kz14`.

## O que muda na tela

- **Admin em inglês ou espanhol:** os dois avisos do topo, o cabeçalho de cada módulo, a seção "Avançado" e o texto de exemplo dos blocos novos aparecem no idioma escolhido. Com isso a tela do editor não tem mais frase fixa em português.
- **Admin em português:** os itens 16 a 20.

## Alternativas consideradas

- **Dar o idioma a `ThemeReleaseProbe`.** Recusado: a classe é pura por decisão (sem banco, sessão ou pedido). Escolher a frase é trabalho da tela.
- **Trocar o código do veredito por um código em inglês.** Recusado: o código está em atributo lido por script e por teste, e ninguém o lê na tela depois do item 9.
- **Uma frase só para o detalhe do aviso "Aceitação", com vários `%s`.** Dispensada: o mecanismo troca um `%s` por chamada; juntar chaves curtas evita mudar `KazEditorText`, que tem contrato conferido por teste.
- **Deixar os textos de exemplo dos blocos em português.** Recusado: são a primeira coisa que aparece no canvas quando se insere um bloco, e em inglês a tela mostrava "Titulo do hero".
- **Traduzir também os exemplos de preenchimento** (`exemplo.com`). Dispensado nesta etapa: são endereços de exemplo, não frases. Ficam na lista do item 22.

## Limites conhecidos

- **Os exemplos de preenchimento** `exemplo.com` e `loja.exemplo.com` (módulo Sites) continuam iguais nos três idiomas.
- **O detalhe do aviso "Aceitação" e o aviso vermelho citam nomes técnicos** (`kaz-theme-editor.js`, `scripts_deferred`, `header.twig`, "asset", "cache/proxy"). São textos de diagnóstico para quem instala.
- **Texto de reserva em português:** se o script não conseguir ler as frases (elemento do editor ausente), os avisos saem em português, com acento.
- **Inglês e espanhol não passaram por revisão de um falante nativo,** como nas ADRs 0104 e 0106.
- **Quem mantém um quarto idioma** precisa copiar as 57 chaves. Sem uma chave, o texto que o Twig escreve fica vazio e o que o script escreve mostra o nome da chave.
- **Com uma camada só, a contagem continua "1 camadas",** também no aviso "Liberação".
- **Conferido só no Chromium,** sem leitor de tela.

## Correção de um registro

- A ADR 0106 e a entrada da KZ-13 no changelog listam, entre o que continuava em português, "as mensagens dos outros dois scripts da tela (`kaz-canvas.js`, `kaz-element-attrs.js`)". **Está errado.** Esses dois scripts não têm frase fixa. `kaz-canvas.js` recebe do servidor todos os textos que mostra, e `kaz-element-attrs.js` não escreve texto na tela. O texto antigo não foi reescrito.

## Achados, não corrigidos

- **O aviso vermelho manda rodar `tools/kaz-theme-editor-health.js`, e esse arquivo não existe no repositório.** A frase foi traduzida como está.
- **`ThemeReleaseProbe` não tem teste de unidade próprio.** O que confere os três vereditos é `check_kaz_frame_text.js`, lendo o arquivo da classe.
- **`data-kaz-release-asset` vale sempre 0.** `ThemeReleaseProbe::ASSET_MARK` ainda é `-kaz-theme-editor-aj`, e a versão dos arquivos mudou de nome desde a KZ-11. Nada lê esse atributo.
- **No script, `BLOCKS.<bloco>.label` e `modulesMap` têm nomes em português sem acento** ("Cartao", "Heranca"). O primeiro não é usado; o segundo só serviria se o botão do módulo viesse sem nome.
