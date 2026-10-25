# Agentes IA — F9-22

## Objetivo

Primeiro recurso de autoria rich-text do editor Blog: formato de runs JSON v2 compatível com v1, edição inline por `contenteditable`, toolbar localizada e renderer allowlisted. Base `main` HEAD `54748166dc5b474edac1a8e28e436bd333a926c2`.

## Regras de integração

- Rever apenas os dez arquivos pontuais do pacote contra o HEAD vigente; o pacote não é clone.
- V1 permanece legível. A conversão v1→v2 ocorre em memória e só persiste no POST explícito.
- Não persistir `innerHTML`; não usar `execCommand`; nunca relaxar a validação/renderer para aceitar HTML livre.
- Renderer e validator PHP são fonte de segurança. Hrefs vêm da allowlist; `rel` fixo; sem target do cliente.
- Mantém o workflow existente: ACL, CSRF, transação e snapshots da F9-16/F9-19. Não adicionar autosave.
- Não alterar/aplicar migration F9-12 nem presumir que está aplicada.
- Não executar PHP, SQL, browser, app, E2E runtime, deploy ou GitHub write.
- Fixtures são sintéticas; sem dados reais, dump privado, credenciais ou logs runtime.

## Verificação

Somente parsing JS (`node --check`), leitura estática e parse de JSON foram realizados. E2E funcional segue pendente e exige autorização e staging descartável.
