# F9-22 — verificação e plano E2E futuro

## Gate estático incluído

`tools/e2e/e2e_f922_static.cjs [F9-22-root]` lê os arquivos de fonte como texto e o JSON de fixtures. Não inicia PHP, SQL, Twig, browser, CMS, servidor ou banco. PASS comprova apenas marcadores/shape estáticos.

## E2E funcional futuro (não executado)

1. Carregar documento v1: canvas exibe texto; abrir sem submit não altera storage.
2. Selecionar trecho em texto e aplicar/remover cada mark; seleção/foco permanece utilizável.
3. Criar, editar e remover link; unsafe scheme/credentials são bloqueados no client e servidor.
4. Colar HTML com scripts/atributos; apenas texto puro entra e é escaped no renderer.
5. Salvar v2, reabrir e comparar runs, links e output; incluir headings/list items/quote/button label.
6. Undo/redo com edição inline; limites de runs, conteúdo e documento rejeitam sem sobrescrever fonte.
7. Testar texto Unicode, links mistos, seleção reversa, IME, teclado e leitores de tela.

Somente executar após autorização explícita, em staging descartável, com F9-12 aplicada manualmente pelo responsável. Nunca usar dados reais/dumps ou produção. Estas verificações ainda precisam ocorrer.
