# F9-38 — Busca através de trechos formatados

## Entregue

Estendeu-se a busca e substituição do F9-36 para localizar um termo mesmo quando ele atravessa runs adjacentes com formatos ou links diferentes, desde que esteja no mesmo bloco textual. A busca continua a percorrer os blocos BDREditor e filhos textuais; não cruza fronteiras entre blocos.

Ao substituir um match que começa e termina em estilos diferentes, o texto substituto herda as marcas e o link do primeiro caractere selecionado. O prefixo restante mantém a formatação original da run inicial e o sufixo mantém a formatação/link da run final; as runs intermediárias selecionadas são removidas. Substituição única e total seguem como uma ação cada na pilha de desfazer/refazer. As traduções do aviso de escopo foram atualizadas em PT-BR, EN-US e ES-EU para Page e Blog.

## Verificações

- `node --check` no editor.
- Testes unitários isolados das funções de busca e substituição: match em quatro runs com estilos diferentes, preservação de formatação/link de prefixo e sufixo, substituição dentro de uma run, busca sem diferenciar caixa e limite de 1.001 matches para acionar a proteção de 1.000 substituições.
- Verificações estáticas das traduções e integridade do ZIP.

Não foram executados testes de navegador, PHP, banco de dados, staging ou E2E. A busca não cruza limites entre blocos; ela opera sobre o texto agregado das runs de cada bloco, sem analisar atributos/metadados.
