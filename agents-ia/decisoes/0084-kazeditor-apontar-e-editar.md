# ADR 0084 — KAZEditor-Theme: apontar e editar

| | |
| --- | --- |
| **Status** | ✅ aceita (KZ-02, 2026-11-12) |
| **Data** | 2026-11-12 |
| **Contexto** | A [ADR 0083](0083-kazeditor-canvas-pagina-real.md) colocou a página real do rascunho no editor, mas a página era só para olhar: para mudar um trecho era preciso adivinhar qual das 25 fontes Twig o gerava, abrir o módulo Templates, achar o campo e voltar para conferir. Ordem e visibilidade das seções ficavam em outro módulo. A barra tinha 16 botões de módulo com o mesmo peso. |

## Decisão

Esta ADR decide a **segunda etapa** do caminho B: **clicar em um trecho da página seleciona a fonte que o gerou e permite editá-la ali mesmo**.

1. **Marcadores de origem no servidor.** `Twig::renderThemePage()` ganha o sexto parâmetro `bool $annotate = false`. Com `true`, a saída de cada fonte allowlistada (do tema ou padrão) sai cercada por dois comentários HTML: `<!--kz:b:CHAVE-->` e `<!--kz:e:CHAVE-->`. `CHAVE` é sempre uma constante de `THEME_TEMPLATE_KEYS`; nenhum dado do tema ou da página entra no marcador. Sem `$annotate`, a saída é idêntica à da ADR 0083. Só `design/theme.canvas` passa `true`: o Catalog, a prévia isolada e o site publicado nunca recebem marcadores.

2. **Fonte padrão como ponto de partida.** O retorno ganha `defaults`: a fonte padrão (`catalog/view/template/<chave>.twig`, lida por caminho fixo) das chaves que aparecem na página e que o tema não redefine. A resposta JSON de `design/theme.canvas` repassa esse mapa. É o que alimenta "Personalizar este trecho": o tema passa a ter uma cópia exata do padrão, e a página continua igual até alguém editar.

3. **Leitura no navegador, sem escrever no quadro.** `kaz-canvas.js` percorre os comentários do documento de cada quadro (mesma origem, já prevista na ADR 0083), mede cada trecho com `Range.getBoundingClientRect()` e guarda os retângulos. O contorno do trecho sob o ponteiro e o da seleção são desenhados em uma camada do editor **por cima** do quadro. O documento do quadro não recebe atributo, classe, estilo nem script.

4. **Clique seleciona, arrasto move.** Um clique sem arrasto (até 4 px) seleciona o trecho mais interno sob o ponteiro; arrastar continua movendo o canvas; `Esc` ou clique fora dos quadros limpa a seleção. Toque funciona do mesmo jeito.

5. **Dois painéis ao lado da página**, ambos sem nenhum campo com `name`:
   - **Estrutura** (esquerda): os trechos da página na ordem em que aparecem, com a origem de cada um ("Deste tema" / "Padrão do sistema"). Selecionar na lista leva a página até o trecho. Na página inicial, as seções ocultas aparecem embaixo com o botão "Mostrar".
   - **Trecho selecionado** (direita): nome, arquivo, origem, tamanho do trecho em cada quadro, e o código. Para uma seção da página inicial: subir, descer e ocultar.

6. **Os dados salvos não mudam.** O código editado no painel é escrito no `<textarea name="templates[chave]">` que já existe no módulo Templates; subir, descer, ocultar e mostrar acionam os controles que já existem no módulo Seções (`sections[]` e `sections_order`). O `POST` do tema tem exatamente os mesmos campos de antes. "Voltar ao padrão do sistema" esvazia o campo e guarda o texto anterior para "Desfazer" enquanto a tela estiver aberta.

7. **Barra de módulos por uso.** Ficam à vista Página, Geral, Tokens, Seções, Componentes, Templates e Cena decorativa. Herança, Three.js, Cena 3D, Mídia, Modelos, Agendamento, Sites, Condições e Prévia abrem em **Configurações**. Os botões, os painéis e o comportamento de cada módulo são os mesmos; só mudaram a ordem e o agrupamento. Sem JavaScript o grupo aparece aberto.

8. **Aviso do PHP durante o render vira erro de template.** No Catalog, `system/framework.php` converte avisos em exceção; no canvas, um aviso (por exemplo, uma lista impressa como texto) vazava para dentro da página. `renderThemePage()` passa a fazer a mesma conversão **somente durante a própria execução** (`set_error_handler` + `finally restore_error_handler`): a fonte com problema cai no padrão e o erro aparece com template e linha, como o site publicado se comportaria.

## Alternativas consideradas

- **Atributo `data-*` no primeiro elemento de cada fonte:** exigiria alterar o HTML do tema (analisar e reescrever a saída), falha em fontes sem elemento raiz único e muda seletores CSS como `:first-child`. Comentários não entram no fluxo nem na cascata.
- **Mapear por `id`/classe das seções:** só funciona para temas que seguem a convenção dos templates padrão; os pacotes entregues usam marcação própria.
- **Script dentro do quadro avisando o clique:** exigiria `allow-scripts` junto de `allow-same-origin`, o que anula o isolamento. Recusado.
- **Editor de código novo (realce, autocompletar):** fica para a etapa do modo código. Aqui o campo é um `<textarea>` simples, espelho do campo real.

## Limites conhecidos

- **Granularidade = fonte Twig.** O menor trecho selecionável é uma das 25 chaves (rota ou parte). Elementos dentro de uma fonte (um título, um botão) ainda não são selecionáveis; isso depende do contrato de cena v2.
- **Fonte com `{% extends %}` fica sem marcador** (Twig não aceita conteúdo fora de blocos em template filho) e não aparece na Estrutura. Nenhum template padrão nem pacote entregue usa herança.
- **Os marcadores são informativos, não uma barreira de segurança.** O autor do tema pode escrever um comentário `<!--kz:…-->` no próprio código; o efeito é só o editor contornar um trecho com o nome errado, dentro do rascunho do próprio autor. Dados da página passam pelo autoescape e não viram marcador (teste `testMarkerTextComingFromPageDataIsEscaped`).
- Uma fonte incluída **dentro** de `<style>` ou de um atributo receberia o comentário nesse contexto, apenas no canvas. Nenhum template padrão nem pacote faz isso.
- Subir/descer/ocultar valem para as seções do construtor (`sections[]`) na rota `common/home`. Um tema que redefine `common/home` e ignora `home_sections` (caso de `David-Universe-3D`) mostra os botões apenas para as partes que de fato incluir.
- "Desfazer" de "Voltar ao padrão" vale até recarregar a tela; depois disso vale o histórico de revisões do tema.
- Continuam valendo os limites da ADR 0083 (dados de amostra, Three.js sem execução, `vh`).

## Consequências

- Arquivos alterados: `system/library/template/twig.php`, `admin/controller/design/theme.php`, `admin/view/template/design/kaz_theme_editor.twig`, `admin/view/js/kaz-canvas.js`, `admin/view/css/kaz-canvas.css`, `admin/view/js/kaz-theme-editor.js` e os 3 arquivos de idioma. 1 teste novo (`KazPointEditTest`, 7 casos).
- 38 chaves de idioma novas (`text_canvas_*`) em `en-us`, `pt-br` e `es-eu`; `text_canvas_hint` atualizada.
- Sem tabela, coluna, migration, rota nova ou dependência. O contrato de `design/theme.canvas` só ganhou a chave `defaults` na resposta.
- Correção incidental no preset `docs/temas/Reamur-Reference.reamurtheme.json`: `catalog/project_info` imprimia `{{ img }}` para itens que são listas (`src`, `alt`), o que derruba a página de projeto no Catalog. Passa a usar `{{ img.src }}`; o `checksum` do pacote foi reemitido pelo próprio `ThemePackage::checksum()`.

## Próximas etapas (fora desta ADR)

1. **Tokens ao vivo:** cores, tipografia e espaçamentos editados ao lado da página, com resultado imediato.
2. **Contrato de cena v2:** árvore de quadros com auto layout, restrições e componentes, com compilador determinístico para Twig + CSS e ligação declarativa a dados.
3. **Ferramentas de desenho** sobre o canvas e modo código como visão avançada do mesmo documento.
