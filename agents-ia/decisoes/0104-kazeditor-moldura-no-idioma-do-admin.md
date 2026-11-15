# ADR 0104 — KAZEditor-Theme: a moldura do editor no idioma do admin

| | |
| --- | --- |
| **Status** | ✅ aceita (KZ-11, 2026-11-15) |
| **Data** | 2026-11-15 |
| **Contexto** | A tela do editor de tema tinha textos escritos em português dentro do Twig e do script: "Publicar", "Tela cheia", "Salvo", "Camadas", os nomes dos módulos. Com o admin em inglês ou espanhol, a tela saía metade em cada idioma. O achado está registrado na [ADR 0103](0103-kazeditor-pendencias-fechadas.md). O levantamento desta etapa contou, em `kaz_theme_editor.twig`, 213 textos e 102 dicas (`title`, `aria-label`, `placeholder`, `data-kaz-tablabel`) fixos, e cerca de 120 frases em `kaz-theme-editor.js`. |

## Decisão

Esta ADR decide a **décima primeira etapa** do caminho B. Ela não acrescenta função ao editor: os textos da **moldura** passam a vir dos arquivos de idioma, como o resto do admin.

### O que é a moldura

1. **É o que fica sempre na tela, com qualquer módulo aberto:** a barra de cima (ferramentas, Publicar, tela cheia, desfazer, estado do arquivo), a barra da tela (tamanho, densidade, botões dos painéis), as abas dos dois painéis laterais, a navegação dos módulos (com o nome de cada um) e o rodapé (zoom, módulo aberto, tamanho da tela).
2. **O conteúdo dos painéis fica para outra etapa** (ver "Limites conhecidos"). O recorte foi escolhido por ser a parte que todo usuário vê o tempo todo, e por caber em uma etapa com conferência completa.

### Arquivos de idioma

3. **109 chaves novas, `text_kaz_<nome>`, em cada um dos três idiomas** (`pt-br`, `en-us`, `es-eu`), no fim de `language/<idioma>/design/theme.php`. Cada arquivo passa de 669 para 778 chaves.
4. **O texto em português é o que já estava na tela.** Uma correção de grafia: a dica do botão Publicar dizia "catalogo" e agora diz "catálogo".
5. **Teclas de atalho ficam no Twig**, fora da frase: `{{ text_kaz_tool_move }} (V)`. Nomes próprios e de aparelho também ficam como estão: "KAZEditor-Theme", "Three.js", "Desktop", "Laptop", "Tablet", "Mobile", "Widescreen".

### Controller e Twig

6. **O Twig já recebia todas as chaves do arquivo de idioma** (`edit()` junta `$this->language->all()` aos dados da tela), então as 109 chegam a ele sem código novo. `Design\Theme` ganha uma lista só, `KAZ_FRAME_SCRIPT`: as 24 chaves que o script também escreve na tela.
7. **No Twig, 93 trechos trocados.** Texto visível usa `{{ text_kaz_… }}`; valor de atributo usa `{{ text_kaz_…|e('html_attr') }}`, como os demais atributos da tela.
8. **Os 13 painéis de módulo com nome fixo** (`aria-label="Herança"` e os outros) usam a mesma chave do botão do módulo.

### Script

9. **As 24 frases do script viajam em um atributo**, `data-kaz-labels`, no elemento `#kaz-theme-editor`, em JSON. É o mesmo desenho que o canvas já usa para a configuração dele (`data-kaz-canvas-config`): sem `<script>` embutido e sem filtro `raw`.
10. **Uma função nova, `window.KazEditorText(chave, reserva, valor)`**, no começo de `kaz-theme-editor.js`. Devolve a frase do idioma; se a chave faltar, vier vazia ou o atributo não puder ser lido, devolve o texto de reserva, que é o português escrito no ponto de uso. Quando a frase tem `%s`, ele recebe o terceiro argumento.
11. **O atributo é lido no primeiro uso em que o elemento já existe**, e uma vez só. O arquivo é carregado no `<head>` e pode rodar antes do `<body>` (frente AD, descrita no próprio script).
12. **O que o script escreve com ela:** "Salvo" e "Não salvo"; "Tema" e "Personalizado" na etiqueta da tela; o nome do canvas; o controle de tamanho dos elementos, os três botões de recolher e o resumo ao lado deles; a dica das alças que redimensionam os painéis.
13. **O rodapé passa a mostrar o nome do módulo aberto lendo o botão do módulo.** Antes vinha de uma lista dentro do script, sem acento e com outro nome para a cena ("Heranca", "Condicoes", "Design"). Agora é o mesmo texto do botão ("Herança", "Condições", "Cena decorativa"), em qualquer idioma.
14. **O endereço do script e da folha de estilo do editor muda de versão** (`-kaz-theme-editor-kz11`), para o navegador não usar a cópia antiga.

### Testes

15. **`tests/check_kaz_frame_text.js`** lê o Twig, o script, o controller e o arquivo `pt-br`. Confere: nenhuma frase fixa nas seis regiões da moldura nem no nome dos painéis de módulo (texto e dicas, descontados nomes próprios e teclas); toda chave usada no Twig existe no arquivo de idioma e toda chave `text_kaz_*` do arquivo é usada, no Twig ou no script; o script só pede chaves que o controller manda, e o texto de reserva dele é igual ao do `pt-br`; a função `KazEditorText` se comporta como descrito no item 10.
16. **`KazFrameTextTest`** confere a parte do PHP: os três idiomas têm as mesmas 109 chaves, na mesma ordem, todas preenchidas, com a mesma quantidade de `%s`; nenhuma frase do inglês ou do espanhol ficou em português; a lista do controller só tem chaves que existem. Ele também roda o conferidor do item 15.

## O que muda na tela

- **Admin em português:** quase nada. O rodapé mostra o nome do módulo com acento e, para a cena, "Cena decorativa" no lugar de "Design"; a dica do botão Publicar ganha o acento; o nome do canvas para leitor de tela passa de "KAZTheme-Editor canvas" para "Canvas KAZEditor-Theme".
- **Admin em inglês ou espanhol:** a moldura inteira aparece no idioma escolhido.

## Alternativas consideradas

- **Traduzir a tela inteira nesta etapa.** São cerca de 260 textos no Twig e 120 no script. Recusado para esta etapa: a conferência no navegador, frase por frase e nos três idiomas, não caberia com o mesmo rigor. O teste novo já delimita as regiões; as próximas etapas acrescentam regiões.
- **Entregar as frases do script em uma tag `<script>` com JSON.** Recusado: a tela não tem script embutido (regra da frente AB, no cabeçalho do Twig).
- **Colocar as frases do script em `canvas_config_json`.** Recusado: aquele atributo pertence a `kaz-canvas.js`; o editor da cena é outro script e roda mesmo quando o canvas não inicia.
- **Tirar o texto de reserva do script.** Recusado: se o atributo faltar (tema de admin alterado, cópia antiga do Twig em cache), a tela mostraria botões vazios.
- **Traduzir os nomes de aparelho** ("Desktop", "Tablet"). Dispensado: são usados assim nos três idiomas, e o canvas já os trata à parte (`text_canvas_device_*`).

## Limites conhecidos

- **Só a moldura foi traduzida.** Continuam em português fixo: as mensagens que aparecem no rodapé depois de cada ação ("camada adicionada", "cena exportada"; cerca de 95 frases no script); o conteúdo dos painéis laterais (lista de camadas, "Ocultar", "Bloquear", títulos "SELEÇÃO" e "APARÊNCIA", blocos prontos); os dois avisos do topo da página ("Liberação" e "Aceitação"); a seção "Avançado"; e alguns rótulos dentro dos painéis de módulo. Depois desta etapa restam no Twig cerca de 160 textos e 10 dicas fixos.
- **As traduções para inglês e espanhol foram escritas nesta etapa e não passaram por revisão de um falante nativo.**
- **Um quarto idioma sem essas chaves mostra o nome da chave** (`text_kaz_saved`), como acontece com qualquer outro texto do admin: a biblioteca de idioma não tem idioma de reserva. Os três idiomas entregues são conferidos por teste.
- **O conferidor do Twig trabalha por região.** Texto fixo novo fora das seis regiões e dos nomes de painel não é acusado. No script, ele confere as chaves pedidas, não cada frase escrita na tela: uma frase fixa nova no script só é pega pelo roteiro de navegador.
- **A barra de cima já ocupava duas linhas em janela de 1500 px de largura; em espanhol, com textos mais longos, um botão a mais desce para a segunda linha.** A tela não foi redesenhada.
- **Conferido só no Chromium.**

## Achado desta etapa, não corrigido

- **O botão "Tela cheia em nova página" abre uma página em que o editor não funciona.** Com `kaz_fullscreen=1`, `theme_form.twig` deixa de incluir o cabeçalho do admin, e é o cabeçalho que carrega as folhas de estilo e os scripts. A página abre sem estilo, e o aviso "Aguardando o editor" não sai da tela. O defeito já existia antes desta etapa e não tem relação com idioma. "Tela cheia" (na mesma aba) funciona.
