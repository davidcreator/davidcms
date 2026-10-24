# Agentes IA — F9-17

## Objetivo autorizado

Preparar a validação integrada/E2E do fluxo de blocos Blog após F9-12–F9-16: contrato, cenários sintéticos, checklist seguro e auditoria estática de código-fonte. Não executar PHP/SQL/browser/app, migration, banco, deploy ou GitHub write.

## Estado de referência

- Repositório correto: `https://github.com/davidcreator/davidcms`
- Branch `main`, HEAD consultado: `1e3f15873436accbe21003c41720054eaeb0b09a` — integração F9-16.
- A verificação de HEAD é somente leitura. Não clonar/baixar o projeto completo; use apenas os arquivos pontuais necessários.
- F9-12 não é presumida aplicada em nenhum ambiente.

## Instruções de segurança

1. Use somente as fixtures inventadas em `tests/fixtures/blog_block_workflows.json`.
2. Não acesse `bd-davidalmeida.sql`, dados reais, credenciais, logs runtime ou conteúdo pessoal; não copie nenhum para pacote, artefatos ou memória.
3. O comando `--static` lê fontes como texto, sem carregar código executável. `--plan` apenas exibe o procedimento manual futuro.
4. Não introduza endpoints, migrations ou código de produção nesta tarefa. Qualquer E2E de runtime exige autorização e ambiente descartável.
5. Ao coletar evidências futuras, registre apenas status e contagens agregadas; nunca valores de campo, tokens, cookies ou linhas de log potencialmente sensíveis.

Veja `docs/F9-17-Checklist-E2E-Blog-Blocos.md` e o contrato JSON antes de alterar o plano.
