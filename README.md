# Fase 9 · Tarefa 19 — Remediação estática F918-01…04

Esta entrega contém um conjunto de arquivos pontuais para endereçar os quatro riscos registrados na F9-18:

- **F918-01:** detecção/leitura do sidecar no catálogo agora distingue tabela realmente ausente de erro; erro de metadata/leitura sinaliza `query_error` e mantém o ramo público fail-closed.
- **F918-02:** renderer recebe base de assets configurada pelo servidor e resolve paths allowlisted de imagem; sem base, emite path root-relative.
- **F918-03:** seleção do sidecar tenta o idioma ativo primeiro; só usa idioma fallback depois da ausência confirmada da linha ativa.
- **F918-04:** exclusão do post, sidecar, descrições e SEO usa transação/rollback.

O checker de fonte estática passou, com oito fixtures sintéticas. Isso não valida execução PHP/SQL, consultas reais, Twig, browser ou comportamento do app. **Nenhum teste PHP/SQL/browser/app foi executado; nenhum banco/migration/GitHub foi acessado ou alterado.**

## Integração

Compare os cinco arquivos em `dev/` contra o HEAD base e aplique/revise individualmente. Este ZIP não é um commit e não contém o projeto inteiro. F9-12 não é presumida aplicada. Antes de qualquer merge/deploy, é necessária validação autorizada em staging descartável, incluindo resolução de asset-base, fallback multilíngue e rollback.
