# F9-23 — checker estático e plano de E2E

## Checker disponível

Execute `node tools/e2e/e2e_f923_static.cjs [F9-23-root]`. Ele só lê JavaScript/CSS/PHP como texto, traduções e fixtures JSON. Não carrega o CMS nem executa PHP, SQL, browser, app ou banco. PASS é uma checagem estática, não prova interação/integração runtime.

## Plano funcional futuro (não executado)

1. Inserter pesquisável por categoria; adicionar no root, após seleção e dentro de group/column; confirmar restrições em list e columns.
2. Slash no início/após espaço, pesquisa pelo teclado, Enter seleciona, Escape preserva o caractere literal.
3. Toolbar contextual; mover/duplicar/remover; transformar parágrafo/título/citação/botão mantendo apenas estado compatível.
4. Duplicação de árvore profunda com IDs únicos; undo/redo (capacidade 50), foco e status ARIA em cada ação.
5. Outline aninhado seleciona o bloco correspondente; testar documento vazio/longo e nomes de tipos.
6. Drag/drop de irmãos no root e em níveis nested; rejeitar cross-parent e garantir alternativas de teclado.
7. Acessibilidade com teclado/leitor de tela, mobile, reduced motion, seleção de rich text e idioma PT-BR/EN-US/ES-EU.

Executar somente com autorização explícita em staging descartável. Nunca usar produção, dump privado ou dados reais. F9-12 continua não presumida/aplicada.
