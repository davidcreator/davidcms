# Fase 9 · Tarefa 18 — Revisão estática e prontidão

A revisão consolidada de F9-12 a F9-17 identificou **2 itens P1 e 2 itens P2 abertos**. O estado atual é **não liberar ainda**. O relatório completo e recomendações estão em `docs/F9-18-Relatorio-Revisao-Estatica.md`; o registro estruturado fica em `docs/F9-18-Risk-Register.json`.

Os arquivos de código críticos listados no Source Hashes foram comparados byte a byte com o HEAD de `main`, sem clone do projeto.

## Verificador

`tools/e2e/e2e_f918_preflight.cjs <checkout>` é uma inspeção estática heurística. Ele apenas lê texto de fonte e aponta os padrões dos riscos; não executa aplicação/PHP/SQL/browser nem acessa banco. O exit code 1 significa riscos estáticos detectados, não falha de teste de runtime.

## Limites

Nenhuma alteração no código de produção, migration, PHP, SQL, browser, app, banco, deploy ou GitHub write foi feita. A migration F9-12 permanece sem confirmação de aplicação. Nenhum teste funcional foi executado.
