# Fase 9 · Tarefa 26 — Acessibilidade, localização e polimento

Incremento local estático sobre F9-25. Base remota verificada: `fe7de816f6dc27658e909e33d54ad735c7cb873b` (F9-25).

- Navegação por teclado e semântica acessível no inserter/outline/editor.
- Foco mais visível e placeholder com contraste melhorado.
- Mensagens localizadas e verificação de paridade PT-BR/EN-US/ES-EU.
- Digitação de texto não clona/serializa o documento inteiro em cada input; snapshots de undo e limites explícitos para payload.
- Fixtures sintéticas de limites de texto, nós, profundidade e bytes.

Detalhes: `docs/F9-26-Acessibilidade-Localizacao-Performance.md`; contrato: `docs/F9-26-Contrato-A11y-Performance.json`; checker: `tools/e2e/e2e_f926_static.cjs`.

**Limitação importante:** auditoria com browser/leitor de tela, Axe, benchmark e testes PHP/SQL/app/E2E runtime não foram executados e continuam pendentes/autorização-required. Checker estático não certifica WCAG nem runtime.
