# Fase 9 · Tarefa 23 — Block UX Gutenberg-like

Implementação local de inserter pesquisável e contextual, comando `/`, toolbar de ações de bloco, duplicação com novos IDs, transformação textual compatível, outline aninhado e reordenação acessível em filhos. Continuidade da base F9-22; nenhum clone integral foi criado.

**Base `main` verificada:** `184bff0c52b57bfbdbd43922093d21240f3fc836`. O pacote não foi enviado ao GitHub.

- Escopo/guardrails: `docs/F9-23-Block-UX.md`
- Hashes dos oito arquivos-base: `docs/F9-23-Base-Hashes.json`
- Plano de E2E (pendente): `tools/e2e/README.md`
- Fixtures sintéticas: `tests/fixtures/block_ux_f923.json`
- Checker estático: `tools/e2e/e2e_f923_static.cjs`

Não executamos PHP/SQL/browser/app/E2E runtime, nem acessamos banco ou aplicamos migration. Verificações estáticas não substituem validação em browser/staging.
