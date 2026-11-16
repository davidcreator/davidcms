# ADR 0110 — KAZEditor-Theme: pendências do editor fechadas (listas da parte "Quando" no idioma do admin, salvar sem sair do editor, nomes de bloco com acento, nome do editor no blog)

| | |
| --- | --- |
| **Status** | ✅ aceita (KZ-17, 2026-11-16) |
| **Data** | 2026-11-16 |
| **Contexto** | As etapas anteriores deixaram cinco pendências anotadas na tela do editor de tema. Nas listas da parte "Quando" de cada regra de condição, o nome da lista foi traduzido na [ADR 0109](0109-kazeditor-nome-para-cada-campo-do-editor.md), mas as opções continuaram com o valor técnico (`all`, `eq`, `route`). Salvar levava para a lista de temas (limite anotado na [ADR 0105](0105-kazeditor-tela-cheia-em-nova-pagina.md)). O rótulo de zoom do canvas de página mostrava 36% na tela normal e 30% na página cheia. Dois nomes de bloco estavam sem acento no script. E o editor de blocos do blog ainda dizia o nome antigo do editor de tema. |

## Decisão

Esta ADR decide a **décima sétima etapa** do caminho B. É uma etapa pequena, como a KZ-10 e a KZ-15: não há função nova de desenho. Quatro pendências mudam o código; a do zoom foi medida e fica como está.

### Parte 1 — listas da parte "Quando"

1. **Cada opção das três listas mostra um nome no idioma do admin.** São 3 tipos de grupo, 16 campos e 16 operadores, mais uma opção por parâmetro de URL conhecido (`query.<chave>`).
2. **O valor de cada opção continua o técnico.** O que a regra guarda, o que o formulário envia e o que o motor lê são os mesmos de antes (`{"field":"hour","op":"between",…}`). Só o texto visível muda.
3. **Os nomes dos tipos de grupo já existiam** (`text_builder_group_all`, `_any`, `_not`) e apareciam num selo ao lado da lista, que por sua vez mostrava `all`, `any`, `not`. A lista passa a usar esses textos, e **o selo sai**: repetia a mesma informação.

   | Valor | pt-br | en-us | es-eu |
   | --- | --- | --- | --- |
   | `all` | E (todas) | AND (all) | Y (todas) |
   | `any` | OU (qualquer) | OR (any) | O (cualquiera) |
   | `not` | NÃO (nenhuma) | NOT (none) | NO (ninguna) |

4. **Dezesseis nomes de campo novos por idioma,** chaves `text_builder_field_<campo>`:

   | Valor | pt-br | en-us | es-eu |
   | --- | --- | --- | --- |
   | `route` | Rota | Route | Ruta |
   | `path` | Caminho da URL | URL path | Dirección de la URL |
   | `language` | Idioma | Language | Idioma |
   | `device` | Dispositivo | Device | Dispositivo |
   | `mobile` | Celular ou tablet | Phone or tablet | Móvil o tableta |
   | `logged_in` | Usuário logado | Logged-in user | Usuario con sesión |
   | `user_group` | Grupo do usuário | User group | Grupo de usuario |
   | `hour` | Hora (0 a 23) | Hour (0 to 23) | Hora (0 a 23) |
   | `weekday` | Dia da semana (1 = segunda) | Weekday (1 = Monday) | Día de la semana (1 = lunes) |
   | `date` | Data (AAAA-MM-DD) | Date (YYYY-MM-DD) | Fecha (AAAA-MM-DD) |
   | `month` | Mês (1 a 12) | Month (1 to 12) | Mes (1 a 12) |
   | `variant` | Variante | Variant | Variante |
   | `https` | Conexão HTTPS | HTTPS connection | Conexión HTTPS |
   | `ajax` | Requisição AJAX | AJAX request | Petición AJAX |
   | `query` | Parâmetros da URL | URL parameters | Parámetros de la URL |
   | `site` | Site | Site | Sitio |

5. **Um texto para os parâmetros de URL,** chave `text_builder_field_query_key`, com `%s` no lugar da chave: "Parâmetro da URL: %s", "URL parameter: %s", "Parámetro de la URL: %s". A opção `query.utm_source` aparece como "Parâmetro da URL: utm_source".
6. **Dezesseis nomes de operador novos por idioma,** chaves `text_builder_op_<operador>`:

   | Valor | pt-br | en-us | es-eu |
   | --- | --- | --- | --- |
   | `eq` | é igual a | is equal to | es igual a |
   | `neq` | é diferente de | is not equal to | es distinto de |
   | `in` | está na lista | is in the list | está en la lista |
   | `not_in` | não está na lista | is not in the list | no está en la lista |
   | `contains` | contém | contains | contiene |
   | `starts_with` | começa com | starts with | empieza por |
   | `ends_with` | termina com | ends with | termina en |
   | `gt` | é maior que | is greater than | es mayor que |
   | `gte` | é maior ou igual a | is greater than or equal to | es mayor o igual que |
   | `lt` | é menor que | is less than | es menor que |
   | `lte` | é menor ou igual a | is less than or equal to | es menor o igual que |
   | `between` | está entre | is between | está entre |
   | `exists` | existe | exists | existe |
   | `empty` | está vazio | is empty | está vacío |
   | `truthy` | é verdadeiro | is true | es verdadero |
   | `falsy` | é falso | is false | es falso |

7. **A lista de campos e de operadores vem do motor,** não do arquivo de idioma. O controller percorre `ConditionEngine::spec()` e procura o texto de cada item. Um campo ou operador novo no motor aparece na lista sem mais código; enquanto não tiver texto, aparece com o valor técnico.
8. **Sem texto, a opção mostra o valor técnico, nunca o nome da chave.** O controller só envia ao script o texto que o idioma tem (o carregador de idioma devolve a própria chave quando falta o texto, e essa resposta é descartada). No script, a reserva de cada opção é o valor.
9. **As listas ficam da largura do texto.** A linha da condição era uma grade de colunas com largura fixa, e os nomes novos não cabiam na coluna do campo. A linha passa a ser `flex` com quebra: cada lista tem a largura do nome mais comprido, o valor ocupa o resto e desce para a linha de baixo quando não cabe. Abaixo de 768 px cada lista ocupa a linha inteira, como antes.

### Parte 2 — salvar continua no editor

10. **Depois de salvar, a tela volta ao editor do mesmo tema,** no mesmo módulo e na mesma tela (normal ou página cheia), com o aviso verde "Rascunho salvo. Publique quando quiser levar as mudanças ao site." Antes ia para a lista de temas. Resolve o limite "Salvar leva para a lista de temas" da ADR 0105.
11. **Um tema novo, depois do primeiro salvar, abre no editor dele** (o endereço passa a ter o número do tema criado).
12. **O endereço de volta é montado no servidor.** Do pedido só entram duas coisas: o módulo, se for um nome simples (`/^[a-z]{1,24}$/`), e o sinal de página cheia, se for exatamente `1`. Nada do pedido vira destino: não há parâmetro de retorno.
13. **A ordem no endereço é fixa:** `design/theme.edit|<id>`, `saved=1`, `kaz_module=<módulo>`, `kaz_fullscreen=1`.
14. **O aviso "Rascunho salvo" só aparece em `GET`.** Um `POST` recusado (slug repetido, por exemplo) redesenha o formulário com o erro, no mesmo módulo e na mesma tela, sem dizer "salvo". O aviso também não passa por cima do aviso ou do erro de importação.
15. **O módulo viaja no endereço do formulário.** Ao enviar, o script acrescenta `kaz_module=<módulo aberto>` ao `action`; na página cheia o Twig já escreve `kaz_fullscreen=1` nele. O servidor devolve o módulo em `data-kaz-start-module`, e o script abre nele; sem módulo, ou com um nome que não existe, abre em "Página", como antes.
16. **"Publicar", "Comparar" e "Voltar" não mudam.** Os dois primeiros têm `formaction` próprio; "Voltar" continua levando à lista.
17. **Um texto novo por idioma,** chave `text_saved_ok`: "Rascunho salvo. Publique quando quiser levar as mudanças ao site." / "Draft saved. Publish when you want the changes on the site." / "Borrador guardado. Publique cuando quiera llevar los cambios al sitio."

### Parte 3 — rótulo de zoom

18. **Nada muda no código. O rótulo diz a verdade nas duas telas.** Medido nos temas "Cena Real" e David Creator, numa janela de 1500×1000, com a largura do quadro na tela dividida pela largura própria do quadro:

    | Tela | Ao abrir | Depois de "Ajustar" |
    | --- | --- | --- |
    | Canvas de página, tela normal | 36% | 19% |
    | Canvas de página, página cheia | 30% | 30% |
    | Cena, nas duas telas | 75% | (não tem "Ajustar" medido aqui) |

19. **A diferença vem de duas regras de `kaz-canvas.js` que já existiam.** "Ajustar" (`fitAll`) enquadra todos os quadros visíveis. Ao abrir (`fitInitial`), se enquadrar todos deixaria a escala abaixo de 28%, o canvas enquadra só o primeiro quadro, para a página não nascer ilegível. Na tela normal o painel é estreito: todos os quadros caberiam a 19%, então a abertura mostra o primeiro a 36%. Na página cheia cabem todos a 30%, e as duas regras dão o mesmo número.
20. **A prova de navegador passa a medir isso:** em cada tela, sete leituras do rótulo do canvas de página e quatro do rótulo da cena, cada uma comparada com a escala medida.

### Parte 4 — nomes de bloco e nome do editor no blog

21. **Os rótulos dos blocos "Cartão" e "Título" ganham acento** na tabela `BLOCKS` de `kaz-theme-editor.js` (estavam "Cartao" e "Titulo"). Esse rótulo não aparece na tela hoje: desde a ADR 0106 a mensagem "bloco … inserido" usa o texto do botão. A correção é na fonte, para o dia em que o rótulo for usado.
22. **O editor de blocos do blog e das páginas passa a dizer "Padrão KAZEditor-Theme ativo"** (dois pontos de `blog-block-editor-kaz.js`). Era o último texto de tela com o nome antigo anotado na ADR 0109. A frase continua fixa em português, como o resto desse editor.

### Versão dos arquivos

23. Os endereços passam a terminar em `-theme-builder-e`, `-kaz-theme-editor-kz17` e `-kaz-bdre-kz17`. A versão `-theme-builder-e` é compartilhada por `theme-section-builder.js`, `theme-scene-editor.js` e `theme-component-library.js`, que mudam de endereço sem mudar de conteúdo. `kaz-canvas.js` não mudou e continua em `-kaz-canvas-kz16`.

### Testes

24. **`KazSaveAndWhenNamesTest` (27 testes)** roda o controller de verdade com modelo e carregador de mentira e exige: o destino exato depois de salvar, em cada combinação de módulo e tela; que nada do pedido além do módulo simples e do sinal de página cheia entre no destino; o aviso só em `GET` e só com `saved=1`; os 33 nomes de cada idioma, escritos no teste; que a chave não chegue à tela quando falta o texto; as regras de largura da folha de estilo; e as versões dos arquivos.
25. **`tests/check_condition_builder_labels.js`** monta o construtor com textos e sem textos e confere, nas três listas, valor técnico em cada opção, texto do idioma (ou o valor, na falta dele) e ausência do selo.
26. **`ThemeSandboxPublishedTest`** tinha uma conferência do destino antigo (a lista de temas); passa a conferir o novo.

## O que muda na tela

- **Condições:** as listas dizem "Rota", "é igual a", "E (todas)" em vez de `route`, `eq`, `all`. O selo com o tipo do grupo, ao lado da lista, saiu.
- **Salvar:** a tela continua no editor, no módulo em que a pessoa estava, com um aviso verde em cima. Quem trabalha na página cheia continua nela.
- **Blog e páginas:** a linha de estado do editor de blocos diz "Padrão KAZEditor-Theme ativo".
- **O resto do desenho é o mesmo.** Dos 15 painéis comparados antes e depois, só o de condições mudou (as listas ficaram mais largas e os botões ao lado andaram).

## Alternativas consideradas

- **Voltar ao endereço que o navegador informar** (`Referer` ou um parâmetro `return`). Recusado: destino vindo do pedido é a porta do redirecionamento aberto. O servidor monta o endereço com o número do tema que acabou de gravar.
- **Salvar por `fetch`, sem recarregar a tela.** Dispensado nesta etapa: o servidor refaz na resposta o que depende do que foi salvo (prévia, cena derivada, avisos de liberação), e manter isso em dia sem recarregar pede outro desenho. Fica para quando houver salvamento automático.
- **Guardar o módulo aberto no navegador** (`sessionStorage`). Dispensado: o endereço resolve, funciona com duas abas e pode ser conferido no servidor.
- **Escrever os nomes de campo e operador numa lista fixa do controller.** Recusado: a lista do motor muda sem aviso para a tela. Percorrer `spec()` mantém uma fonte só (item 7).
- **Manter a grade e alargar as colunas.** Dispensado: com coluna fixa, ou sobra espaço em inglês ou falta em espanhol. A largura pelo texto serve aos três idiomas.
- **Igualar a abertura e o "Ajustar" do canvas de página.** Recusado: abrir a 19% deixa a página ilegível na tela normal, e foi para isso que `fitInitial` foi escrita.
- **Traduzir a frase do editor de blocos do blog.** Fora do escopo: todo o texto desse editor está fixo em português; traduzi-lo é uma etapa do blog, não do editor de tema.

## Limites conhecidos

- **Na tela normal, com a janela entre 992 e cerca de 1090 px de largura, os nomes mais compridos da lista de campos aparecem cortados** com a lista fechada (9 de 28 em português, 12 em espanhol e 5 em inglês, numa janela de 1024 px). A coluna central do editor tem 294 px nessa largura, menos que o nome mais comprido. Com a lista aberta o nome aparece inteiro, e na página cheia não há corte (conferido em 1024 e 900 px). Em 1100, 1280 e 1500 px, e abaixo de 768 px, nada é cortado.
- **Continuam técnicos, de propósito:** os valores que a pessoa digita (rotas, slugs, ids de cena e de site), as listas do contexto de simulação, as listas da parte "Então" e a referência de campos e operadores, recolhida no fim do módulo de condições.
- **Salvar recarrega a tela.** O histórico de desfazer da cena e a posição da rolagem dentro do módulo não sobrevivem; o módulo e a tela, sim.
- **O endereço fica com `saved=1` depois de salvar.** Recarregar a página mostra o aviso de novo.
- **O rótulo dos blocos (item 21) não tem prova de navegador,** porque não aparece na tela; é conferido na fonte.
- **O editor de blocos foi aberto só na tela de nova postagem do blog.** O das páginas usa o mesmo arquivo; a versão dele é conferida no teste.
- **Conferido só no Chromium.** As traduções para inglês e espanhol não passaram por revisão de um falante nativo.

## Achados, não corrigidos

- **O nome antigo "KAZTheme-Editor" continua no texto da camada de exemplo da cena inicial** (ADR 0109, item 15) e em comentários do código.
- **No controller, a variável que guarda a versão do editor ainda se chama `$kazAssetAJ`** (já anotado nas ADRs 0108 e 0109).
- **Os avisos "Liberação" e "Aceitação" continuam atrás do editor nas telas cheias,** e o menu do Admin continua no HTML da página cheia (ADR 0105).
