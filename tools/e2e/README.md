# Plano de E2E — editor avançado (futuro)

Este arquivo define critérios para os recursos planejados; não é um runner e não foi executado. A F9-21 não implementa o editor. Nenhum teste PHP/SQL/browser/app é permitido/executado nesta etapa.

## Cenários para F9-22

1. Abrir documento v1 não o regrava; edição e envio convertem para v2 apenas após POST válido.
2. Formatar seleção com cada mark permitido, salvar/reabrir e confirmar ordem/merge canônico de runs.
3. Adicionar/remover link; rejeitar esquemas/hosts não permitidos no servidor e impedir fallback HTML raw.
4. Colar texto com markup perigoso; resultado fica texto, sem tags/atributos executáveis.
5. Desfazer/refazer operações de caracteres, marks, link e estrutura; estado e seleção coerentes.
6. Limites de comprimento/runs/complexidade; falha bloqueia salvamento sem substituir documento original.
7. Documento v2 inválido/desconhecido; renderer fail-closed e conteúdo legado/snapshot preservado.
8. Teclado/foco/leitor de tela; toolbar não captura seleção/foco de forma irrecuperável.

## Pré-condições futuras

- Aprovação do contrato v2 e implementação server-first (validador/renderer) antes da UI produzir v2.
- Ambiente descartável e autorização expressa para PHP/SQL/browser/app.
- F9-12 aplicada explicitamente pelo responsável ao banco de teste; nunca presumir.
- Fixtures sintéticas somente; não capturar dados, tokens, cookies, dumps ou logs sensíveis.
