# F9-25 — verificação estática e plano pendente

## Checker

`node tools/e2e/e2e_f925_static.cjs [F9-25-root]` verifica marcadores de contrato no PHP/JS/controller/traduções, conteúdo do contrato JSON e fixtures sintéticas. Não executa PHP nem testa browser, editor, endpoint, preview real, SQL, app ou ACL.

## E2E funcional (não executado)

1. Abrir documento v1 e confirmar que funciona sem `outline_label`; rejeitar o campo em v1 sem alterar/persistir ao abrir.
2. V2: salvar/editar/limpar rótulo; conferir outline aninhado, limites 160 UTF-8 bytes, XSS literal tratado como texto; verificar acessibilidade/teclado.
3. Editar um bloco e outros campos, navegar/fechar e conferir o aviso; submit inválido não deve desarmar a confirmação; submit válido mantém o fluxo explícito já existente.
4. Verificar formulários com múltiplas abas de idioma/hosts, CSRF, falhas de validação do servidor e postback com dados preservados.
5. Revisar padrão salvo privado por usuário após decisão explícita de ACL, CSRF, validação de JSON e schema aprovado; teste não deve começar até migration instalada em staging descartável.
6. Preview autenticado de draft depois de aprovado: usuário sem acesso negado, token/CSRF/origin/expiração, renderer allowlisted, sem persistência nem URL pública para draft.
7. Testar dispositivos/teclado/leitor de tela e locale PT-BR/EN-US/ES-EU.

Browser/app/E2E runtime, PHP e SQL continuam proibidos sem autorização explícita. Nenhuma persistência ou preview de catálogo foi implementada nesta entrega.
