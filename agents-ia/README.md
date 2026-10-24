# Agentes IA — F9-18

## Objetivo

Revisão estática de prontidão das tarefas F9-12–F9-17. O produto não foi editado nesta tarefa. O resultado não substitui E2E de runtime e não autoriza release/deploy.

## Referência

- Repositório: `https://github.com/davidcreator/davidcms`
- Branch: `main`
- HEAD lido: `72a8b927c0d2b96b7d3df41114969aa620bb9602`
- A alteração mais recente F9-17 adiciona contrato/fixtures/tooling; não altera código de runtime.

## Restrições

- Não clonar/baixar o projeto inteiro; trabalhar com arquivos pontuais e contratos já entregues.
- Não executar PHP, SQL, migration, browser, app, acesso a banco, deploy ou GitHub write.
- Não presumir F9-12 aplicada. Não acessar dump privado, dados reais ou logs runtime.
- Não remover `description|raw` nem relaxar a allowlist: resolver o F918-01 com fail-closed explícito.
- O relatório identifica riscos por leitura de fonte; confirmar cada achado com reprodução apenas em ambiente descartável autorizado.

Leia `docs/F9-18-Relatorio-Revisao-Estatica.md`, `docs/F9-18-Risk-Register.json` e o plano de release antes de modificar qualquer código.
