# F9-39 — Opção “Somente palavras inteiras”

## Escopo entregue

Adicionada uma caixa de seleção **Somente palavras inteiras** ao painel Localizar e substituir. Com a opção desligada, a busca mantém o comportamento anterior. Ligada, só aceita ocorrências quando o primeiro/último caractere alfanumérico do termo está delimitado por início/fim do texto ou por caractere que não seja letra, marca Unicode, número ou `_`. Hífens e pontuação delimitam palavras; `_` e letras/dígitos (incluindo letras acentuadas) pertencem à palavra.

A regra é aplicada ao texto agregado do bloco, portanto funciona também para termos que atravessam trechos com marcas ou links diferentes. A opção combina com busca sensível ou insensível a caixa; a substituição segue preservando formatação/link, histórico undo/redo e limite de 1.000 substituições. As seis traduções de Page/Blog foram adicionadas em PT-BR, EN-US e ES-EU e os controladores entregam a string ao componente.

## Verificações

- `node --check` nos scripts JavaScript afetados.
- Sete casos unitários isolados: correspondência parcial com/sem a opção, palavras adjacentes em caixa mista, acentos e marca combinante, underscore, pontuação/hífen, sensibilidade a caixa e termo inteiro atravessando runs formatadas.
- Verificações estáticas do mapeamento nos controladores/idiomas e integridade do ZIP.

Não foram executados testes de navegador, leitor de tela, PHP runtime, banco de dados, staging ou E2E. A comparação é literal em relação aos acentos: a busca não transforma formas Unicode compostas e decompostas em equivalentes, mas trata marcas combinantes como parte de palavra ao determinar limites.
