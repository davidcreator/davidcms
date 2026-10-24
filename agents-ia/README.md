# Agentes IA — F9-19

## Objetivo

Preparar correções locais para os achados F918-01 a F918-04, sem GitHub write ou alteração de ambiente. Fonte base: `davidcreator/davidcms`, branch `main`, HEAD `b4ef8cc2f6cc398eea6e9a80f8ea81c7d3b0f1f6`.

## Arquivos

Os cinco arquivos sob `dev/` são versões pontuais para revisão/integração. Não são cópia do projeto completo. Compare os diffs com o HEAD imediatamente antes de aplicar.

## Restrições

- Não executar PHP, SQL, migrations, browser ou aplicação; não acessar bancos nem fazer deploy.
- Não presumir migration F9-12; não usar dados reais, dump privado, credenciais ou logs runtime.
- O preflight roda apenas leitura estática. PASS significa marcadores presentes, não teste funcional.
- Erros no sidecar público devem continuar fail-closed. Não restaurar fallback para `description|raw` em erro de metadata/query.
- A base de assets deve vir somente de configuração confiável; nunca do documento de blocos.
- O fallback por idioma exige ausência confirmada do sidecar ativo; query_error deve bloquear, não cair para HTML legado/inglês.
- Todas as escritas de exclusão precisam permanecer dentro da transação/rollback.

Consulte `docs/F9-19-Correcoes-Readiness-Blog-Blocks.md` e o conjunto de cenários sintéticos antes de alterar os arquivos.
