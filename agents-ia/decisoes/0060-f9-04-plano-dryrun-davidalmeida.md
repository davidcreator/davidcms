# ADR 0060 — F9-04: Fonte privada e dry-run para o legado davidalmeida.xyz

**Data:** 2026-10-23 · **Agente:** install / orquestrador.
**Status:** aceita como contrato/plano; nenhuma importação autorizada nesta tarefa.
**Base verificada:** `davidcreator/davidcms` @ `0d00e150103da983df6908400959bef8da96898d`.

## Contexto

A F9-03 publicou inventário sanitizado do dump `bd-davidalmeida.sql`. Em seguida, o mantenedor removeu o dump do GitHub por segurança. Isso é correto: dumps reais contêm PII/credenciais e não devem ficar versionados.

A análise F9-03 mapeou 18 tabelas, mas não é autoridade de importação. Antes de novos scripts, foi necessário confrontar o inventário com decisões já aceitas no repositório, especialmente ADR 0032/F4-08.

## Decisão

1. **Dump real fora do repositório:** `bd-davidalmeida.sql` torna-se artefato privado, fornecido localmente pelo operador e removido do GitHub. Somente hash, bytes, estrutura e inventário sanitizado podem permanecer na base.
2. **Ciclo de dados legado observado está fechado:** o dump analisado já teve constatações aceitas em ADR 0032 — sem pedidos, contatos spam não migram, ferramentas já migradas, portfólio sem linhas e cursos fora de escopo. Logo, não importar novamente.
3. **Mapa de decisão obrigatório:** `docs/F9-04-Mapa-Migracao-DavidAlmeida.json` decide cada tabela como `archive_only`, `already_migrated_f3`, `excluded_prior_decision`, `out_of_scope_course_module`, `sensitive_out_of_scope`, `whitelist_future_review` ou `no_rows_already_curated_f3`; todas com `action_now: none`.
4. **Dry-run é gate, não execução:** se um dump novo aparecer, a única ação permitida automaticamente é auditoria estática/validação. Nova estrutura ou conteúdo só entra com plano novo, backup, laboratório, contagem antes/depois e log sem valores.
5. **Não importar davidcreator.com:** qualquer referência textual legada ao site externo permanece fora do escopo; blog/importação não entra nesta etapa.

## Alternativas consideradas

- **Criar novo staging SQL para as 18 tabelas:** rejeitado agora; a tarefa já existe em parte em F4-08 para contatos legítimos futuros e o inventário atual não aponta dado novo a migrar.
- **Reimportar ferramentas do dump:** rejeitado; já houve curadoria F3 e duplicaria destino.
- **Apagar o inventário sanitizado F9-03 junto com o dump:** rejeitado; ele contém apenas estrutura/contagens e permite auditoria sem valores.

## Consequências

- O repositório fica mais seguro: não há dump real versionado, mas ainda é possível conferir forma e integridade do que foi observado.
- Um futuro dump diferente exigirá rerodar o inventário e atualizar o contrato antes de qualquer transformação.
- A Fase 9 pode retomar a trilha de campos personalizados sem carregar uma falsa dívida de migração legada.

## Validação

`tools/e2e/e2e_f904.sh --check` cruza o mapa F9-04 com o inventário sanitizado F9-03, verifica ausência do dump no repositório e bloqueia ações de importação direta. O Tool lê apenas JSON/arquivo local; não executa SQL.
