# Agentes IA — F9-20

## Objetivo

Confirmar, por fonte estática, que os fixes F918-01…04 enviados na tarefa F9-19 estão no HEAD atual e que o checker estático F9-19 passa sobre cópias pontuais obtidas de `raw` no HEAD.

## Referência

- Repositório correto: `https://github.com/davidcreator/davidcms`
- Branch `main`, HEAD: `60ee6cc7579cfde9b372f44c92f64ba45c1a176f`.
- Parent: `b4ef8cc2f6cc398eea6e9a80f8ea81c7d3b0f1f6`.
- Cópias em `reference/` são sete arquivos pontuais; não são clone do projeto.

## Restrições permanentes

- Não executar PHP, SQL, migration, browser, aplicação, query, banco ou deploy.
- Nenhuma escrita no GitHub. Hashes e downloads `raw` somente leitura.
- Não presumir F9-12 aplicada; não acessar dump privado, dados reais, credenciais ou logs.
- Checker PASS é estrutural, não prova comportamento nem certifica produção.

Consulte relatório e hashes em `docs/` antes de avançar para operação. E2E real exige autorização explícita e ambiente descartável.
