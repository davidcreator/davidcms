# Fase 9 · Tarefa 27 — E2E de staging e release gate

**Status: preparação local; release gate BLOQUEADO.** Base `main` verificada: `448dd61f467e8ce2b4a0d5bce481345dace4ca77` (F9-26). A F9-27 do roadmap pede testes em servidor/browser/DB em instância descartável, mas a autorização não foi concedida e seguem vigentes as restrições de não executar PHP/SQL/browser/app/E2E runtime.

Esta entrega organiza o plano, casos sintéticos, critérios de parada e verificação estática. **Nenhum teste funcional, banco, migration ou deploy foi realizado.** O estado de F9-12 segue desconhecido/não confirmado; não se presume aplicada.

- Plano: `docs/F9-27-Release-Gate.md`
- Matriz de gate: `docs/F9-27-Status.json`
- Dados sintéticos: `tests/fixtures/blog_editor_f927_synthetic.json`
- Checker estático apenas: `tools/e2e/e2e_f927_static.cjs`
- HEAD/blob hashes: `docs/F9-27-Base-Hashes.json`
