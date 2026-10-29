# F9-36 — Localizar e substituir no BDREditor

## Escopo entregue

A busca e substituição está disponível como botão com ícone de lupa na faixa **Início** e é compartilhada pelo componente do editor; Page e Blog continuam com formulários, controladores, contexto e salvamento independentes.

- Campo **Localizar**, campo **Substituir por**, opção de diferenciar maiúsculas/minúsculas, navegação para ocorrência anterior/próxima, substituição única e substituição total.
- Contagem/estado acessível com `role="status"` e `aria-live="polite"`; entradas têm rótulos e o botão da lupa expõe `aria-expanded`.
- Pesquisa exclusivamente o texto dos blocos BDREditor (incluindo filhos, como células); não varre metadados, atributos, HTML legado ou conteúdo fora do documento estruturado. A extensão F9-38 permite que o termo atravesse trechos com formatações diferentes dentro do mesmo bloco; a busca não cruza limites entre blocos. A limitação é exibida junto dos controles.
- O texto substituído herda marcas e link do trecho original; os demais trechos formatados permanecem inalterados. Substituição única ou total é uma única operação do histórico, permitindo desfazer/refazer pela pilha normal do editor.
- Substituir tudo é limitado a 1.000 ocorrências por ação. Se exceder, nada é modificado e a interface informa que a busca deve ser refinada.
- Strings adicionadas em PT-BR, EN-US e ES-EU para Pages e Blog. Nenhuma ação de busca salva automaticamente: a semântica explícita de Salvar permanece inalterada.

## Verificações

Executado `node --check` no JavaScript do editor. Verificações estáticas confirmam o botão/ícone, ações, acessibilidade, configuração de strings nos dois controladores e presença das traduções nos seis arquivos de idioma. O PHP CLI não está instalado neste ambiente, então os controladores e arquivos PHP de idioma não foram lintados. Não foram executados navegador, banco de dados, PHP runtime, staging ou E2E; esses resultados não são alegados.

## Nota de comportamento

A busca percorre segmentos textuais individualmente para preservar a formatação inline e seu link. Portanto, o termo precisa estar inteiramente dentro de um único segmento; a busca através de mudança de marca/link está explicitamente fora deste escopo.
