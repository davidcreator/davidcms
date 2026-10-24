# Fase 9 · Tarefa 17 — Preparação E2E Blog Blocks

Esta entrega prepara a validação integrada dos fluxos F9-12 a F9-16, com matriz de casos, fixtures exclusivamente sintéticas, checklist operacional futuro e um auditor estático de contratos/fontes.

**Não é um E2E de runtime.** A verificação estática foi executada sobre fontes pontuais existentes no workspace (F9-16 admin/model/renderer e F9-15 catálogo público): 11 arquivos, 3 idiomas e 8 fixtures foram inspecionados com resultado PASS. Não executamos PHP, SQL, migration, browser ou aplicação; não acessamos banco nem fizemos GitHub write.

## Uso

- Ver o plano: `tools/e2e/e2e_f917.sh --plan`
- Auditar um checkout completo apenas como texto: `tools/e2e/e2e_f917.sh --static /caminho/para/davidcms`

O modo `--static` apenas lê arquivos; não inicia o sistema.

## Base

HEAD de `main` verificado: `1e3f15873436accbe21003c41720054eaeb0b09a` — integração F9-16. A migration F9-12 não é presumida aplicada.
