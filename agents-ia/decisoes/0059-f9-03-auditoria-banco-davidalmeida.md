# ADR 0059 — F9-03: Auditoria segura do dump `bd-davidalmeida.sql`

**Data:** 2026-10-23 · **Agente:** install / orquestrador.
**Status:** aceita para auditoria estática; nenhuma importação executada.
**Base verificada:** `davidcreator/davidcms` @ `9b570ef4828ce128674a501cc523800b624d609e`.

## Contexto

A F9-02 já publicou o desenho físico proposto para campos personalizados. Em seguida, o mantenedor informou que o banco real prioritário é o do **davidalmeida.xyz**, que o arquivo será `bd-davidalmeida.sql` em `install/sql`, e que `davidcreator.com` não será importado nesta etapa.

O arquivo já se encontra no repositório em `dev/install/sql/bd-davidalmeida.sql`. Antes de propor importação, era necessário verificar integridade, dimensão, forma técnica e classes de dados sensíveis sem copiar valores.

## Decisão

1. **Prioridade de dados:** o único escopo de origem aprovado é `davidalmeida.xyz`; nenhuma rotina, mapa ou inventário para importar `davidcreator.com` entra nesta tarefa.
2. **Credenciais:** o usuário autorizou leitura porque serão alteradas. Ainda assim, a decisão é tratar o dump como sensível e **não copiar nenhum valor/credencial** para documentação, relatório, logs ou Tool.
3. **Auditoria sem execução:** o SQL é somente parseado. Nenhum `CREATE`, `INSERT`, `ALTER`, conexão, transação ou rollback foi executado.
4. **Inventário sanitizado:** publicar apenas metadados, nomes/tipos de colunas, contagens e flags sensíveis, conforme `docs/F9-03-Inventario-Banco-DavidAlmeida.json`.
5. **Importação adiada:** nenhum mapeamento é autoridade de importação ainda. A migração real exigirá plano/staging/dry-run e aprovação posterior.

## Resultados técnicos confirmados

- 18 tabelas no dump, todas `InnoDB`/`utf8mb4`, sem constraints de FK.
- 14 tabelas com dados, 16 instruções `INSERT` e 909 linhas estimadas.
- 0 `DROP TABLE` e 0 `TRUNCATE`.
- Nenhuma tabela com prefixo `rms_` e nenhuma tabela `custom_field*` — portanto o dump não toca a estrutura proposta na F9-02.
- 4 referências textuais a `davidcreator.com`; permanecem fora do escopo de importação.
- Tabelas sensíveis identificadas por categorias de coluna: contatos, contas, auditoria, cliques/telemetria e configurações.
- O banco legado usa nomes sem prefixo; qualquer importação será transformação, não cópia direta por renomeação.

## Alternativas consideradas

- **Importar tudo via script imediato:** rejeitado. Há dados de pessoas, hash de credenciais e estruturas sem destino no CMS atual.
- **Ignorar o dump até a fase de instalador final:** rejeitado parcialmente; a auditoria agora evita surpresa de escopo, mas não executa migração.
- **Copiar credenciais para outro arquivo/documento:** rejeitado sempre. O pacote registra somente hash e metadados.

## Consequências

- A próxima tarefa deve desenhar **staging + mapeamento + dry-run**, com contagens antes/depois, log sem valores e caminho de rollback/backup.
- Curadoria de ferramentas e contato precisa de aprovação de destino; cursos/estudantes não têm destino atual.
- O dump antecede a F9-02 e não conflita com as tabelas de campos personalizados.
- Dumps antigos não devem ficar expostos em diretório público HTTP; rotação das credenciais permanece obrigatória antes de produção.

## Validação

`tools/e2e/e2e_f903.sh --check` faz leitura estática do fixture; `--sql dev/install/sql/bd-davidalmeida.sql` confere o arquivo real publicado contra o inventário sanitizado. A suíte não abre conexão nem executa SQL.
