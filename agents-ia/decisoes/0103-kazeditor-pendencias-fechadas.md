# ADR 0103 — KAZEditor-Theme: pendências fechadas (desenho do selo, tema "Cena Real", `pattern`, classes sem regra)

| | |
| --- | --- |
| **Status** | ✅ aceita (KZ-10, 2026-11-15) |
| **Data** | 2026-11-15 |
| **Contexto** | As ADRs [0098](0098-kazeditor-atributos-do-elemento.md) a [0102](0102-kazeditor-link-e-botao-sem-nome.md) registraram defeitos vistos durante o trabalho e deixados para depois: um desenho quebrado no pacote `David-Universe-3D`, um `pattern` que o navegador recusa em `theme-condition-builder.js`, três classes do editor sem regra de CSS, e os 30 selos da pasta `tema/` ainda com duas marcas que se contradizem. Nenhum deles impede o uso. Juntos, enchem o console de avisos e deixam um campo sem a conferência que o código diz ter. O responsável pelo projeto escolheu fechar os quatro nesta etapa. |

## Decisão

Esta ADR decide a **décima etapa** do caminho B. Ela não acrescenta função ao editor: corrige quatro pendências e põe um teste em cada uma, para que o mesmo defeito não volte em outro arquivo.

### Parte 1 — o desenho quebrado de quatro selos do `David-Universe-3D`

1. **O defeito.** Em quatro selos do pacote (o do envelope: dois em `common/home`, um em `partial/portfolio`, um em `partial/news`), o traçado do retângulo estava escrito `M40 24h80v84#eb767e40z` ou `M40 24h80v84#a7b3b540z`. No lugar do comando `H` havia uma cor, a mesma do contorno do selo. O navegador desenha até o ponto em que não entende e para: o retângulo aparecia só com o lado de cima e o lado direito, e cada selo deixava um aviso no console (18 avisos ao abrir o tema no editor).
2. **A correção.** O traçado volta a ser `M40 24h80v84H40z`. O valor não foi deduzido: é o que está nos arquivos de `tema/`, que trazem os mesmos 30 selos e nunca tiveram o defeito. Depois da correção, os 30 selos dos dois temas são iguais byte a byte, e há teste para isso.
3. **O `checksum` do pacote foi recalculado.**
4. **Um teste confere todo `<path>` de todo arquivo de tema entregue** (os seis pacotes de `docs/temas` e os dois arquivos de `tema/`, 133 traçados): o `d` só pode ter comandos de desenho e números.

### Parte 2 — os 30 selos do tema "Cena Real" (`tema/`)

5. **Mesma correção da ADR 0102, nos dois arquivos de `tema/`.** Cada um dos 30 desenhos tinha `aria-hidden="true"` e, na mesma tag, `role="img"` com `aria-label="Selo do projeto"`. O leitor de tela obedece ao `aria-hidden`; o rótulo nunca era lido. Saíram o `role` e o `aria-label`. Para quem usa leitor de tela nada muda.
6. **`tema/david_universe_3d_bo.reamurtheme.json`:** `checksum` recalculado; o arquivo passa de 91.535 para 90.215 bytes.
7. **`tema/bo_config.json`:** não tem `checksum`. Está formatado à mão, então a troca foi feita no texto do arquivo e o resto ficou como era. Passa de 84.074 para 82.754 bytes. Um teste confere que ele tem o mesmo conteúdo do pacote.
8. **`docs/historico/GUIA-BO.md`** manda enviar o pacote e cita o tamanho dele. O guia dizia 91.504 bytes, número que já não batia com o arquivo; agora diz 90.215, e um teste compara o número do guia com o tamanho real.
9. **Correção de um registro anterior.** A ADR 0102 e a entrada da KZ-09 no changelog chamam os arquivos de `tema/` de "duas cópias antigas do `David-Universe-3D`" que "nenhum documento cita". As duas afirmações estão erradas. É outro tema, **"David Universe 3D — Cena Real"** (slug `david_universe_3d_bo`), com 25 modelos, dos quais 7 diferem do `David-Universe-3D`, além da cena 3D, das condições e da configuração do Three.js. E `docs/historico/GUIA-BO.md` manda importá-lo. O texto antigo não foi reescrito; a correção fica registrada aqui.

### Parte 3 — o `pattern` do ID de regra de condição

10. **O defeito.** No módulo **Condições**, o campo **ID** de cada regra recebia `pattern="[a-z0-9_-]{1,48}"`. O navegador de hoje compila o `pattern` com uma regra mais rígida (a flag `v`), em que hífen solto dentro de colchetes é erro. O valor era recusado, o navegador escrevia um aviso no console e **descartava o atributo**: o campo aceitava qualquer coisa, e o erro só aparecia depois, na resposta do servidor.
11. **A correção.** O valor passa a ser `[a-z0-9_\-]{1,48}`, com o hífen escapado. É a mesma regra do servidor (`ConditionEngine`: minúsculas, números, `_` e `-`, até 48 caracteres).
12. **O campo passa a avisar.** Com um ID fora do formato, a borda do campo fica vermelha, com o cursor dentro ou fora dele. É uma regra de CSS nova, em `theme-condition-builder.css`.
13. **Não trava o Salvar.** O campo não tem `name`: é controle de tela, e a conferência do editor ao salvar só olha os campos que o servidor recebe (ADR 0098). Quem recusa um ID errado continua sendo o servidor, com mensagem.
14. **Um verificador confere todos os `pattern` do produto** (`tests/check_form_patterns.js`): acha cada `pattern` escrito nos `.twig` e cada `setAttribute('pattern', …)` nos `.js`, e compila do jeito que o navegador compila. Hoje são 12, todos aceitos. Nos dois campos que repetem a regra do servidor (este e o slug do tema), confere também que aceitam e recusam os mesmos exemplos que ela.

### Parte 4 — classes do editor sem regra de CSS

15. **Quatro classes saíram de `kaz_theme_editor.twig`:** `kaz-btn--publish` (botão **Publicar**), `kaz-asset__thumb--hero` (miniatura do bloco Hero), `kaz-canvas__hiddenlist` (caixa das seções escondidas) e `kaz-note--state` (nota do estado da cena). Nenhuma folha de estilo tinha regra para elas e nenhum script as procurava. As três primeiras estavam anotadas desde a ADR 0099; a quarta foi achada pelo teste novo.
16. **Nada muda na tela.** O botão Publicar segue igual ao vizinho **Voltar**; a miniatura do Hero é o desenho base das miniaturas; a caixa das seções escondidas é encontrada pelo atributo `data-kaz-canvas-hidden`, como já era. Medido no navegador antes e depois.
17. **Por que retirar em vez de criar regra:** criar regra seria inventar um desenho que ninguém pediu (um botão Publicar de outra cor, por exemplo). Se um dia houver esse pedido, a classe volta junto com a regra.
18. **Um teste impede a volta:** toda classe `kaz-…` escrita na tela do editor precisa ter regra em uma das folhas que a tela carrega ou ser usada por um dos scripts dela.

## Alternativas consideradas

- **Deduzir o traçado certo pelo desenho vizinho.** Dispensado: o original estava em `tema/`.
- **Retirar a pasta `tema/`** (hipótese da ADR 0102). Descartada: é um tema diferente e um guia manda importá-lo.
- **Reescrever a ADR 0102 e o changelog da KZ-09.** Recusado: registro antigo não é editado; a correção entra no registro novo.
- **Retirar o `pattern` do ID**, já que o servidor confere. Recusado: o aviso na hora poupa uma ida ao servidor, e o atributo já estava lá com essa intenção.
- **Dar `name` ao campo para o navegador barrar o envio.** Recusado: o campo iria no `POST`, e a ADR 0098 separa controle de tela de campo enviado.
- **Mensagem de erro escrita ao lado do campo.** Fica para depois: pede texto novo nos três idiomas.
- **Criar regra de CSS para as quatro classes.** Ver item 17.

## Limites conhecidos

- **O teste dos traçados confere os caracteres, não a gramática inteira.** Um `d` com um número a menos passa. Pega o defeito que houve (texto estranho no meio do traçado).
- **O verificador de `pattern` só lê valor escrito por extenso:** atributo `pattern="…"` em `.twig` e `setAttribute('pattern', '…')` em `.js`. Valor montado por Twig faz o verificador falhar, de propósito, para alguém olhar; valor montado por JavaScript em tempo de execução não é visto.
- **O verificador precisa de Node 20 ou mais novo** (é onde existe a flag `v`). Em Node mais antigo ele avisa e é pulado; o CI usa 20 e 22.
- **O aviso do ID é só a cor da borda.** Não há texto, e o leitor de tela não é avisado. Campo vazio não é marcado: quem cobra o ID é o servidor.
- **O teste das classes olha as classes escritas no Twig.** As que os scripts criam em tempo de execução ficam de fora.
- **`docs/historico/RELATORIO-GERAL.md` cita o `checksum` antigo do tema "Cena Real"** (`e2b935d8…`). É um relatório datado e não foi alterado; o `checksum` de hoje é o que está no arquivo.
- **Site que já importou um dos dois temas não muda sozinho.** Importar o arquivo de novo grava a correção como rascunho; ela vale no site depois de publicar.
- Conferido em Chromium. Não foi testado em outros navegadores nem com leitor de tela.
- Continuam valendo os limites das ADRs 0083 a 0086 e 0098 a 0102.

## Achados registrados, não corrigidos

- **A tela de edição preenche trecho ausente com o arquivo padrão** (ADR 0098). Continua.
- **Textos fixos em português na tela do editor.** Com o admin em inglês, a barra de cima mostra "Save draft" e "Back" ao lado de "Publicar", "Tela cheia" e "Salvo": esses textos estão escritos direto no `.twig`, fora dos arquivos de idioma.
- **O rótulo "ID" da regra de condição não está ligado ao campo** (`<label>` sem `for`). O leitor de tela não anuncia o nome do campo.
- **`docs/historico/GUIA-BO.md` cita `tools/verify-bo.cjs` e `patch/PATCH-BO.diff`**, que não estão no repositório (vieram em um ZIP da época).
- **`kaz-theme-editor.css` tem a regra `.kaz-btn--danger`**, que nenhuma tela e nenhum script usam. É o caso inverso do tratado na Parte 4, e o teste novo não o procura.

## Consequências

- Arquivos novos: `tests/check_form_patterns.js`, `tests/Unit/Library/KazLooseEndsTest.php`. Alterados: `admin/view/js/theme-condition-builder.js`, `admin/view/css/theme-condition-builder.css`, `admin/controller/design/theme.php`, `admin/view/template/design/kaz_theme_editor.twig`, `docs/temas/David-Universe-3D.reamurtheme.json`, `tema/david_universe_3d_bo.reamurtheme.json`, `tema/bo_config.json`, `docs/historico/GUIA-BO.md`.
- Versão no endereço dos arquivos do construtor de condições: `-theme-builder-b` (era `-a`). Vale também para os outros três scripts que usam a mesma versão (`theme-scene-editor.js`, `theme-section-builder.js`, `theme-component-library.js`): o navegador busca os quatro de novo, sem limpar cache. A versão do canvas não mudou (`-kaz-canvas-kz09`): `kaz-canvas.js` e `kaz-canvas.css` não foram alterados.
- Sem tabela, coluna, migration, rota, dependência ou campo novo no `POST` de salvar. Uma regra de CSS nova (item 12). Nenhum texto novo nos arquivos de idioma.
- O servidor não muda de comportamento: a única linha de PHP alterada é a da versão no endereço.
- O site público não muda. Os pacotes corrigidos só chegam a um site quando alguém os importa e publica.
- **Mudança de comportamento no admin:** no módulo Condições, o campo ID fica com a borda vermelha quando o texto não segue o formato. Antes não havia aviso nenhum.
- **Mudança nos pacotes:** `David-Universe-3D` e "Cena Real" têm `checksum` novo.
- Testes: `KazLooseEndsTest` (10 casos) e o verificador em Node (93 conferências). Suíte inteira em PHP 8.2 e 8.3: 1616 testes, 35 376 conferências; PHPStan sem erros.
- No navegador (Chromium, fora do PHPUnit): o campo ID com 12 textos, entre aceitos, recusados e o campo vazio (27 conferências); a tela do editor em sete temas, incluindo o "Cena Real" importado pelo guia (97 conferências: aparência do botão Publicar e da miniatura, esconder e mostrar uma seção, os selos desenhados, nenhum aviso de desenho no console). Com o pacote antigo no lugar, o mesmo roteiro acusa os 18 avisos e o retângulo pela metade. Os roteiros das etapas KZ-05 a KZ-09 foram rodados de novo e passaram sem ajuste.
- Verificação por trocas (um erro posto de propósito, para ver se alguma conferência acusa): 44 de 44 acusadas pelos testes. A primeira rodada acusou 42 de 43: a que passou retirava uma regra de CSS que nenhuma tela usa (`.kaz-btn--danger`), e por isso não havia o que acusar; ela foi trocada por duas regras de classes em uso. No navegador: 12 de 12 acusadas.

## Próximas etapas (fora desta ADR)

1. **Textos fixos em português na tela do editor:** levar para os arquivos de idioma.
2. **Campo de formulário sem rótulo** na lista da página (os pacotes têm 9 campos, todos com rótulo).
3. **Outros elementos que recebem foco:** bloco com `role="button"`, `<input type="image">`, `<summary>`.
4. **Largura máxima e borda por elemento**, se o uso mostrar que faltam.
5. **Os quatro comportamentos de pagamento mantidos na ADR 0097.**
