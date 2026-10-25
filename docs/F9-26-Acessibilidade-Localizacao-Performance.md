# Fase 9 · Tarefa 26 — Acessibilidade, localização e polimento

**Status:** incremento de código e análise estática local; não é auditoria acessível certificada. F9-27 browser/staging e o benchmark real continuam pendentes/autorização-required.

**Base verificada:** `fe7de816f6dc27658e909e33d54ad735c7cb873b` (F9-25). Commits F9-24/F9-25 verificados nos GitHub APIs e mudanças por SHA de blob nos sete arquivos-base pontuais; nenhum clone integral.

## Melhorias locais desta tarefa

### Acessibilidade e teclado

- Removidos roles ARIA `list/listitem` aplicados em botões nativos do inserter; botões voltam a anunciar nome, função e estado nativos. Busca declara `aria-controls`; botão de disclosure declara `aria-expanded`, e estado sem resultados usa `role=status`/`aria-live`.
- Enter no campo de busca ativa corretamente primeiro item filtrado, inclusive padrões; Escape continua fechando e restaurando foco; drag tem alternativa Alt+↑/↓.
- Canvas usa região nomeada em vez de `<main>` interno. Erro inicial usa `role=alert`; status e erros de submissão anunciam mensagem; host publica `aria-invalid`.
- Indicadores de foco aumentados para outline sólido de 3 px com offset. Texto placeholder passa a `#5e6b7f`; a verificação local de contraste para essa combinação no fundo branco dá cerca de 5,4:1, e cor do foco `#174ea6` sobre branco cerca de 7,85:1. Isso não substitui auditoria dos estados/tema reais.

### Localização

- Erro de documento inicial inválido e limite de payload de 4 MiB passaram às strings de language já consumidas pelo controller em PT-BR/EN-US/ES-EU.
- O checker compara os conjuntos de chaves completos das três línguas e verifica strings recém-usadas.

### Performance e limites de payload

- Digitação não clona a árvore toda nem serializa JSON a cada tecla. Atualiza o nó de texto ativo e armazena um snapshot de undo por burst de edição com debounce de 700 ms.
- Histórico mantém até 50 entradas e limita cada stack por até 8.388.608 caracteres serializados (aproximação de memória, não medição exata).
- Serialização no submit revalida e impõe 4.194.304 bytes UTF-8 no cliente; o servidor continua sendo autoridade e rejeição fail-closed.
- Fixtures documentam limites sintéticos para texto, total de blocos/nós, profundidade, payload e strings UTF-8. O checker apenas verifica marcadores/valores/bytes; não chama validação JS/PHP do CMS.

## Limitações e pendências

- Não rodamos leitor de tela, teclado/browser, zoom/reflow real, mobile, Axe, teste de contraste em todas as cores/estados, nem benchmark de documentos longos.
- Render estrutural ainda constrói a árvore DOM completa; virtualização não foi introduzida por risco a navegação/leitores de tela.
- O guard de 4 MiB no client é avaliado em serialize/submit, não em cada tecla para evitar tornar digitação O(documento) por evento. O servidor preserva a validação autoritativa.
- PHP/SQL/browser/app/E2E runtime permanecem proibidos nesta tarefa. Fixtures e checks estáticos não autorizam afirmar conformidade WCAG ou performance medida.
