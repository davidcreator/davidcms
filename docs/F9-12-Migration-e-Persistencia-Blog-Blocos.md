# Fase 9 · Tarefa 12 — Persistência versionada para blocos do Blog

**Data:** 2026-10-24 · **Repositório:** `davidcreator/davidcms`, branch `main`  
**HEAD base:** `d0f8706402a722d24ab76077974d765542999673`  
**Estado:** migration upgrade + downgrade conservador preparados; **não aplicados**.

## Contrato de storage

Adiciona-se uma tabela sidecar por idioma, `rms_blog_post_content`, com chave composta `(post_id, language_id)` e:

- `format_version` para evolução futura do modelo de blocos;
- `content_json MEDIUMTEXT` para árvore validada pela aplicação;
- `source_hash CHAR(64)` para identificar o HTML no instante da primeira conversão;
- `legacy_html_backup MEDIUMTEXT` para preservar a fonte anterior;
- datas de inclusão/modificação.

A tabela é criada por `CREATE TABLE IF NOT EXISTS`, InnoDB/utf8mb4, sem FK. A integridade com `blog_post` e `language` fica no Model, seguindo o padrão existente de tabelas sem FK.

## Compatibilidade com o Blog atual

`rms_blog_post_description.description` continua intacta e mantém o contrato atual de HTML usado pelo admin/catálogo e pelo resumo da listagem. Não adicionamos colunas a essa tabela nem convertemos posts nesta tarefa.

Quando o parser/renderer for desenvolvido em tarefas posteriores, cada salvamento de bloco deverá atualizar também `description` com o snapshot HTML compatível. Isso permite reverter o código para o reader legado sem perder a apresentação mais recente. A cópia `legacy_html_backup` guarda a fonte HTML anterior à primeira conversão e não deve ser sobrescrita nas edições subsequentes.

## Upgrade e rollback

- Upgrade: `dev/install/sql/upgrade_f9_12_blog_block_content.sql`.
- Rollback: `dev/install/sql/rollback_f9_12_blog_block_content.sql`.
- Upgrade cria somente uma tabela vazia; sem grants, migração de linhas, alterações nas tabelas antigas ou execução.
- Rollback seguro é **reversão de código** para HTML reader e retenção do sidecar. Script fornece apenas aviso; não apaga conteúdo.
- `DROP TABLE` aparece comentado como procedimento futuro, sujeito a export/backup independente e autorização explícita. Esta escolha evita perda de documentos de blocos.

## Operação segura

Antes de eventual aplicação futura: confirmar prefixo real, versão compatível MariaDB/MySQL, backup, staging, tabelas F7-01 presentes, e executar manualmente fora desta entrega. Não há alteração do schema base nem do banco no workspace.

## Não coberto

- Parser/conversor de HTML;
- código Model/Controller/frontend para ler/gravar blocos;
- UX do editor;
- transformação real de conteúdo;
- ativação de renderer público ou alteração de `|raw` existente.

Essas entregas ficam em tarefas posteriores. O pacote deve ser revisado antes de qualquer aplicação manual.

## Validação

`tools/e2e/e2e_f912.sh --check` verifica a forma das duas migrations, os tipos/índices requeridos, o contrato JSON, a preservação do campo legado e o caráter não destrutivo do rollback. O Tool não se conecta ao banco nem executa SQL.
