# F9-18 source preflight

`e2e_f918_preflight.cjs <checkout>` lê quatro fontes do checkout como texto e procura padrões ligados aos achados F918-01…04. Não carrega a aplicação nem inicia PHP, SQL, browser, servidor ou banco.

O comando termina com código 1 quando identifica riscos ainda abertos (estado esperado para a base analisada); isto não é teste E2E nem aprovação automática de release. O scanner é heurístico e complementar ao relatório, não substitui code review.

Exemplo futuro, após as correções e em checkout completo:

```sh
node tools/e2e/e2e_f918_preflight.cjs /caminho/para/davidcms
```

Nesta tarefa, o scanner foi executado sobre snapshots pontuais do workspace e apontou F918-01…04, coerente com o relatório. Nenhum teste de runtime foi executado.
