# Agentes IA — F9-25

## Objetivo

Prosseguir do F9-24 com title/outline, fluxo de alterações não salvas, avaliação de padrões próprios e preview contextual de catálogo.

## Regras da implementação local

- `core/group.outline_label` é metadata v2, plain UTF-8 limitado a 160 bytes; não aceitar em v1. JS e servidor precisam validar igual.
- Outline usa APIs DOM/texto, sem `innerHTML`/HTML arbitrário; renderer público ignora o label.
- Guard beforeunload não salva conteúdo. Submit é o fluxo explícito existente; não introduzir autosave.
- Não inferir que `modify blog/blog` permite criar uma biblioteca compartilhada de padrões. ACL/escopo pendentes; aguardar decisão do usuário e revisão install/schema antes de storage/API.
- Não expor rascunhos por URL pública ou token bearer. Preview precisa de ACL/CSRF/origin, renderer allowlisted e ausência de gravação.
- Sem SQL/PHP/browser/app/E2E runtime, banco, deploy ou GitHub write.
