# Fase 9 · Tarefa 04 — Contrato de fonte privada e plano de dry-run para `davidalmeida.xyz`

**Data:** 2026-10-23 · **Base verificada:** `davidcreator/davidcms` no `main`
**HEAD:** `0d00e150103da983df6908400959bef8da96898d`
**Estado:** plano/contrato aprovado para revisão local; **nenhum dump, banco, produção ou SQL foi executado**.

## 1. Gatilho desta tarefa

A F9-03 foi publicada e conferida. Depois disso, `dev/install/sql/bd-davidalmeida.sql` foi removido do repositório por segurança — estado correto: um dump real de banco não deve permanecer público.

A auditoria F9-03 permanece válida porque não depende do arquivo vivo: ela preserva hash, inventário sanitizado, estrutura e contagens, sem valores/credenciais.

## 2. Decisão sobre o ciclo `davidalmeida.xyz`

Como o arquivo foi removido, a fonte passa a ser um artefato **privado e efêmero**:

- nome esperado quando fornecido localmente: `bd-davidalmeida.sql`;
- política: **nunca commitar no GitHub**;
- local recomendado no operador: pasta fora do web root e fora do repositório, com permissão restrita;
- se um novo dump for gerado, primeiro recalcular hash/bytes e rerodar o Tool F9-03 para atualizar o inventário sanitizado;
- relatórios futuros devem trazer somente contagens, nomes de tabelas/colunas, motivos de rejeição e IDs estáveis — nunca linhas do banco.

## 3. Confronto obrigatório com decisões anteriores

Antes de propor qualquer migração nova, o plano lê ADR 0032/F4-08:

- o dump de 20/10/2026 já foi classificado: não havia tabela de pedidos;
- as 9 mensagens legadas de contato foram tratadas como spam e **não devem virar pedidos**;
- `rms_import_contact` já existe como maquinaria idempotente, só para uso local futuro com dados legítimos;
- `external_resources` já teve curadoria/migração para `rms_tool` em F3;
- `portfolio_items` estava vazio e o portfólio já tem seed curada;
- tabelas `course_*`, `class_*` e `student_*` representam outro produto e seguem fora do escopo.

Portanto, **a F9-04 não autoriza nem cria importação nova de dados legados**. O resultado é um contrato de dry-run e um mapa de decisão por tabela para impedir reimportação indevida.

## 4. Mapa de decisão por grupo

| Grupo | Tabelas | Decisão |
| --- | --- | --- |
| Ferramentas | `external_resources` | já migrado/curado em F3; não reimportar |
| Telemetria | `external_resource_clicks` | archive/analytics apenas; não vira catálogo |
| Contato | `contact_messages`, eventos e templates | decisão ADR 0032 mantida: não importar o lote spam; maquinaria futura fica em F4-08 |
| Portfólio | `portfolio_items` | tabela vazia; seed curada F3 permanece fonte atual |
| Cursos / aulas | `course_*`, `class_*`, `student_*` | fora de escopo do ReamurCMS atual; exige decisão de módulo própria |
| Configurações | `site_settings` | somente whitelist futura e credenciais rotacionadas |
| Auditoria interna | `admin_audit_logs` | preservação/arquivo, não runtime público |

Detalhe machine-readable: `docs/F9-04-Mapa-Migracao-DavidAlmeida.json`.

## 5. Protocolo se um dump real reaparecer

1. Usar somente laboratório descartável com credenciais rotacionadas.
2. Guardar o arquivo fora do repositório e nunca publicá-lo.
3. Rodar `tools/e2e/e2e_f903.sh --check --sql /caminho/privado/bd-davidalmeida.sql`.
4. Se o hash não bater com o inventário F9-03, tratar como nova fonte: recalcular inventário sanitizado em outra tarefa.
5. Rodar o gate F9-04: `tools/e2e/e2e_f904.sh --check` na raiz aplicada.
6. Só depois desenhar transformação/dry-run específica, com backup e contagem antes/depois.
7. Nenhuma tabela marcada `excluded_prior_decision`, `archive_only` ou `out_of_scope` pode ser importada sem nova decisão explícita.

## 6. Limites desta entrega

- Não executa banco ou wizard.
- Não altera `schema.sql`, Models, Controllers, rotas, ACL, admin, catalog ou idiomas.
- Não inclui o dump real, nem credenciais, nem linhas de dados.
- Não reabre decisão sobre spam/contatos, blog/davidcreator.com ou plataforma de cursos.
- Não implementa a Fase 9 de campos personalizados runtime; isso deve voltar na próxima subtarefa.

## 7. Validação local

O pacote inclui `tools/e2e/e2e_f904.sh --check` + `e2e_f904.cjs`:

- confere que o dump público está ausente;
- valida que o mapa tem exatamente as 18 tabelas do inventário sanitizado F9-03;
- confere hash/bytes do último dump observado contra o contrato;
- bloqueia qualquer ação `import_now`/`direct_import`;
- bloqueia referência a banco comitado;
- opcionalmente aceita `--sql /caminho/privado/...` para conferir se o hash ainda é o registrado.

Execução feita pelo agente: **PASS** com o inventário F9-03 publicado e o diretório local sem dump.

## 8. Arquivos da entrega

- `docs/F9-04-Plano-DryRun-DavidAlmeida.md`
- `docs/F9-04-Mapa-Migracao-DavidAlmeida.json`
- `docs/README.md`
- `agents-ia/decisoes/0060-f9-04-plano-dryrun-davidalmeida.md`
- `tools/e2e/e2e_f904.sh`
- `tools/e2e/e2e_f904.cjs`
- `manifest.json` e `sha256sum.txt`
