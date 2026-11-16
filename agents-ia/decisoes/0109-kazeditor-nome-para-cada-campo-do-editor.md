# ADR 0109 — KAZEditor-Theme: todo campo da tela tem nome (rótulos ligados, parte "Quando" das condições, Templates, canvas de página) e o editor é chamado por um nome só

| | |
| --- | --- |
| **Status** | ✅ aceita (KZ-16, 2026-11-15) |
| **Data** | 2026-11-15 |
| **Contexto** | A [ADR 0108](0108-kazeditor-pendencias-da-traducao-e-rotulos-de-condicoes.md) ligou os rótulos do construtor de condições e deixou anotado o resto: 98 rótulos escritos no Twig ao lado do campo, sem ligação com ele; os campos da parte "Quando" de cada regra, os 25 do módulo Templates e os campos de número do canvas de página, sem nome nenhum; e o nome do editor escrito de dois jeitos na tela. Um levantamento com a tela aberta achou, no tema de prova com cinco regras, 154 campos e 2 botões sem nome para o leitor de tela. |

## Decisão

Esta ADR decide a **décima sexta etapa** do caminho B. Não há função nova nem mudança de desenho: cada campo, botão e lista da tela do editor passa a ter um nome que o leitor de tela diz, e cada rótulo visível passa a levar ao campo dele.

### Parte 1 — rótulos escritos no Twig

1. **Os 98 rótulos soltos ganham `for`, e o campo ao lado ganha `id`.** Na fonte são 23 pares; os laços (tokens, mídias, sites, componentes) fazem os 98 da página.
2. **Os `id` seguem o padrão que a tela já usava** (`med-<item>`, `site-host-<item>`, `cmp-<item>`):

   | Módulo | `id` do campo |
   | --- | --- |
   | Geral | `theme-field-name`, `theme-field-slug`, `theme-field-primary`, `theme-field-surface`, `theme-field-scene` |
   | Tokens | `token-<chave>` |
   | Three.js | `three-scene`, `three-slot`, `three-count`, `three-color`, `three-speed` |
   | Mídia | `med-variant-<item>`, `med-tone-<item>` |
   | Sites | `site-slug-<item>`, `site-language-<item>` |
   | Componentes | `cmp-variant-<item>`, `cmp-tone-<item>` |
   | Comparar | `preview-route`, `preview-language`, `preview-device`, `preview-variant`, `preview-template` (`preview-site` já existia) |

3. **Os `name` não mudam.** O que o formulário envia é o mesmo; `id` e `for` são só de tela.
4. **Cada cartão de mídia, de site e de componente vira um grupo com o nome do item** (`role="group"` e `aria-labelledby` apontando para o título do cartão). Dentro do cartão os nomes se repetem ("Variante", "Tom"); o grupo diz de qual item são.
5. **Templates: o nome de cada campo é o nome do arquivo que ele edita** (`aria-label="common/home.twig"`). O texto visível já era esse, dentro do `<summary>`; não há tradução a fazer.
6. **As duas setas do painel de camadas ("↑" e "↓") ganham `title` e `aria-label`,** com os mesmos textos dos botões da barra de ferramentas que fazem a mesma ação (`text_kaz_tool_front`, `text_kaz_tool_back`). Nenhuma chave nova.

### Parte 2 — canvas de página

7. **O campo de número ao lado de cada barra recebe o nome da linha.** No painel "Design", um valor com faixa aparece duas vezes: a barra e o número. O rótulo apontava só para a barra. Em `kaz-canvas.js`, `row()` dá um `id` ao rótulo (`kaz-canvas-lbl-<n>`) e o número cita esse `id` em `aria-labelledby`.
8. **Um campo só na linha continua como estava:** é o campo do rótulo, sem segundo nome.

### Parte 3 — construtor de condições

9. **Os campos da parte "Quando" não têm rótulo visível e não ganham um:** a linha de uma condição é compacta (campo, operador, valor), e um rótulo por campo mudaria o desenho. O nome vai em `aria-label`.
10. **Oito textos novos por idioma, chaves `text_builder_*`:**

    | Chave | pt-br | en-us | es-eu |
    | --- | --- | --- | --- |
    | `cond_group` | Tipo de grupo | Group type | Tipo de grupo |
    | `cond_field` | Campo | Field | Campo |
    | `cond_op` | Operador | Operator | Operador |
    | `cond_value` | Valor | Value | Valor |
    | `cond_from` | Valor inicial | Start value | Valor inicial |
    | `cond_to` | Valor final | End value | Valor final |
    | `var_name` | Nome da variável | Variable name | Nombre de la variable |
    | `var_value` | Valor da variável | Variable value | Valor de la variable |

11. **Onde cada um entra:** a lista do tipo de grupo, a lista do campo, a lista do operador, o valor (campo de texto, lista de valores ou lista de sites), os dois números do operador `between` e o par nome + valor de cada variável.
12. **O botão "×" que tira uma variável passa a se chamar "Remover"** (`text_builder_remove`, que já existia). Era o único "×" do construtor cujo nome era o próprio símbolo.
13. **Os textos chegam ao script pelo mesmo caminho dos outros** (`data-labels`, montado no controller); a reserva em português fica no script, sem acento, como as demais.

### Parte 4 — um nome só para o editor

14. **A tela passa a dizer "KAZEditor-Theme" em todo lugar.** É o nome registrado em `admin/model/tool/internal-tools.json`; "KAZTheme-Editor" é o nome antigo (`legacy_name`). Mudam dois textos, nos três idiomas: a mensagem de abertura (`text_kaz_msg_ready`) e o aviso vermelho de quando o editor não inicia (`text_kaz_boot_warning`).
15. **Não muda o texto da camada de exemplo** da cena inicial ("KAZTheme-Editor", escrito na cena que o servidor monta para um tema sem cena salva): é conteúdo da cena, que o usuário edita, e trocá-lo mudaria a cena derivada de temas já existentes.

### Versão dos arquivos

16. Os endereços passam a terminar em `-kaz-theme-editor-kz16`, `-kaz-canvas-kz16` e `-theme-builder-d`. Esta última versão é compartilhada por `theme-section-builder.js`, `theme-scene-editor.js` e `theme-component-library.js`, que mudam de endereço sem mudar de conteúdo.

### Testes

17. **`KazFieldNamesTest` (9 testes)** lê a fonte do Twig e exige: todo `<label>` aponta para um campo que existe ou tem o campo dentro; todo campo tem rótulo, `aria-label`, `aria-labelledby` ou `title`; nenhum `id` escrito duas vezes (fora os três ramos do campo de token); todo botão só com símbolo tem `aria-label`; os três tipos de cartão são grupos com nome; os oito textos existem, com o valor exato, nos três idiomas; a tela não usa o nome antigo do editor.
18. **`tests/check_kaz_canvas_field_names.js`** recorta `control` e `row` de `kaz-canvas.js`, roda as duas sobre nós de mentira e confere o nome de cada campo de cada linha.
19. **`tests/check_condition_builder_labels.js`** (da KZ-15) passa a exigir nome em todo campo da árvore do construtor e nome com letras em todo botão só com símbolo.

## O que muda na tela

- **Para quem usa leitor de tela:** ao entrar num campo, o leitor diz o nome dele em todos os módulos; nos cartões de mídia, sites e componentes diz também o nome do item.
- **Para quem usa o mouse:** clicar no rótulo leva o cursor ao campo, nos módulos Geral, Tokens, Three.js, Mídia, Sites, Componentes e Comparar. Parar o mouse sobre as setas do painel de camadas mostra "Trazer para frente" e "Enviar para trás".
- **O desenho é o mesmo.** Posição, tamanho e estilo de cada rótulo, campo e botão de cada módulo foram comparados antes e depois.
- **O nome do editor** na mensagem de abertura e no aviso vermelho passa a "KAZEditor-Theme".

## Alternativas consideradas

- **Pôr o campo dentro do `<label>`** em vez de `for` + `id`. Recusado: muda a árvore em que as folhas de estilo e os scripts se apoiam (`.form-label` seguido do campo).
- **`aria-label` em todos os campos, sem tocar nos rótulos.** Recusado: o leitor de tela teria o nome, mas o clique no rótulo continuaria sem efeito, e o nome ficaria escrito em dois lugares.
- **Rótulo visível para os campos da parte "Quando".** Recusado nesta etapa: pede outro desenho para a linha da condição.
- **Um grupo por condição** (`role="group"` em cada linha de "Quando"). Dispensado: a linha não tem um título para dar nome ao grupo.
- **`<fieldset>` e `<legend>` nos cartões.** Dispensado, como na ADR 0108: muda o desenho; `role="group"` dá o mesmo nome sem mexer na tela.
- **Trocar também o texto da camada de exemplo.** Recusado (item 15).

## Limites conhecidos

- **O levantamento olhou a tela em nove estados:** aberta; com um link, um título, um parágrafo, um botão, um desenho e um bloco selecionados no canvas de página; com uma camada selecionada na cena; e com uma condição `between` e uma variável nova. Em todos, nenhum campo, botão, link ou quadro ficou sem nome. Nenhuma das duas prévias usadas tem imagem; o painel de uma imagem selecionada não entrou no levantamento (os campos dele vêm das etapas KZ-05 e KZ-07, que têm conferidor próprio).
- **Nas listas da parte "Quando", as opções continuam com o valor técnico** (`all`, `any`, `not`; `eq`, `between`; `route`, `site`). O nome da lista foi traduzido; as opções, não.
- **Com o site desligado, os campos do cartão ficam travados;** o rótulo está ligado, mas não há clique para conferir.
- **Conferido só no Chromium,** pela árvore de acessibilidade do navegador, sem leitor de tela.
- **As traduções para inglês e espanhol não passaram por revisão de um falante nativo.**

## Achados, não corrigidos

- **O nome antigo continua em três lugares fora dos textos da tela do editor:** no texto da camada de exemplo da cena inicial (item 15); na frase "Padrão KAZTheme-Editor ativo", fixa em português, do editor de blocos do blog (`blog-block-editor-kaz.js`); e em comentários do código.
- **No controller, a variável que guarda a versão do editor ainda se chama `$kazAssetAJ`** (já anotado na ADR 0108).
