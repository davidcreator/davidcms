# Fase 9 · Tarefa 03 — Auditoria segura do banco `davidalmeida.xyz`

**Data:** 2026-10-23 · **Base verificada:** `davidcreator/davidcms` no `main`
**HEAD:** `9b570ef4828ce128674a501cc523800b624d609e`
**Estado:** auditoria/leitura estática concluída; **nenhuma importação, execução SQL ou acesso a produção realizada**.

## 1. Escopo de dados aprovado

- Prioridade: banco atual de **davidalmeida.xyz**.
- Arquivo-fonte reconhecido no repositório: `dev/install/sql/bd-davidalmeida.sql`.
- **davidcreator.com está fora desta etapa**: não criar mapa/importação do blog nem do site antigo agora.
- O usuário autorizou a leitura das credenciais/informações do dump porque serão alteradas futuramente. Mesmo assim, esta entrega **não copia valores nem credenciais**; registra apenas hash, estrutura, contagens e classes de dados sensíveis.

## 2. Integridade e estado técnico do dump

| Item | Resultado |
| --- | ---: |
| Tamanho | 201.775 bytes |
| SHA256 | `805d178e7b9122c332f3253042bef5b5fa9849936c76df39a23658585407e6a5` |
| Gerador | phpMyAdmin SQL Dump 5.2.2 |
| Servidor declarado | MariaDB 11.8.9 |
| PHP no host gerador | 7.2.34 |
| Charset | `utf8mb4` em todas as 18 tabelas |
| Engine | `InnoDB` em todas as 18 tabelas |
| `DROP TABLE` | 0 |
| `TRUNCATE` | 0 |
| Tabelas com prefixo `rms_` | 0 |
| Tabelas `custom_field*` | 0 |
| Referências textuais a `davidcreator.com` | 4 — não mapear nesta etapa |

O dump já está versionado no repositório em `dev/install/sql/bd-davidalmeida.sql`. A forma `install/sql/` mencionada pelo usuário corresponde à pasta real dentro de `dev/` na estrutura atual.

## 3. Inventário agregado

| Grupo | Tabelas | Linhas estimadas | Observações |
| --- | --- | ---: | --- |
| Auditoria/admin | `admin_audit_logs` | 1 | Preservar como histórico; não importar para rotas públicas. |
| Contato | `contact_messages`, `contact_message_events`, `contact_reply_templates` | 13 | Contém pessoas, mensagens, IPs e notas internas; exige minimização. |
| Cursos/classes | `course_tracks`, `course_modules`, `course_contents`, `course_classes`, `class_attendance`, `class_students` | 166 | Sem destino atual no ReamurCMS; decidir importação futura separada. |
| Ferramentas externas | `external_resources`, `external_resource_clicks` | 694 | Recursos mapeáveis para ferramentas; cliques são telemetria e ficam fora do catálogo. |
| Portfólio legado | `portfolio_items` | 0 | Sem dados no dump; F3 já tem seed curado. |
| Configurações | `site_settings` | 1 | Só whitelist; `setting_value` foi tratada como sensível. |
| Área de estudantes | `student_accounts`, `student_content_progress`, `student_materials`, `student_track_enrollments` | 34 | Contas/hash/URLs/materiais; não importar sem decisão de escopo. |

Totais: **18 tabelas**, **14 com INSERT**, **16 instruções INSERT**, **909 linhas estimadas**. O inventário completo e sanitizado está em `docs/F9-03-Inventario-Banco-DavidAlmeida.json`, com nomes/tipos de colunas e contagens, sem valores.

## 4. Classes de dados sensíveis

O dump contém campos que podem expor pessoas ou segredos, incluindo exemplos de categoria como e-mail, telefone, nomes de alunos, hash de senha, IPs, user-agent, notas internas, payloads de auditoria e `setting_value`. Esta entrega **não reproduz nenhum valor**. Qualquer importação posterior precisa:

1. usar clone descartável com credenciais rotacionadas;
2. rodar primeiro em `dry-run` com contagem antes/depois;
3. não logar valores de linha;
4. gravar somente contagens, tabela, motivo de rejeição e IDs estáveis;
5. permitir revisão de consentimento/retenção antes de ir ao destino final;
6. deixar dumps antigos fora de diretório público HTTP.

## 5. Mapeamento preliminar, sem importação

| Origem | Destino candidato no ReamurCMS | Decisão desta tarefa |
| --- | --- | --- |
| `external_resources` (4 linhas) | `rms_tool` + `rms_tool_description` + futura keyword em `rms_seo_url` | candidata; requer mapping e idioma. |
| `contact_messages` (9) | extensão futura de `rms_contact_message` ou staging próprio | reter para decisão; estrutura atual do CMS não cobre status/prioridade/eventos. |
| `contact_message_events` / `contact_reply_templates` | nenhum destino direto atual | não importar ainda. |
| `site_settings` (1) | `rms_setting` somente por whitelist | não copiar valor até revisar segredo/configuração. |
| course/student/class tables | possível camada de staging ou módulo futuro de cursos | preservar; não aceitar perda. |
| `portfolio_items` (0) | `rms_project*` | nada a importar do dump. |
| `admin_audit_logs` / `external_resource_clicks` | arquivo/staging analítico | não importar ao catálogo. |
| davidcreator.com/blog | fora | nenhuma importação nesta etapa. |

## 6. Que decisão ainda **não** foi tomada

Esta tarefa não altera schema, não cria rotinas PHP, não roda importador, não liga staging ao painel e não decide ainda quais domínios do dump entrarão no banco final. A próxima tarefa recomendada é **Fase 9 · Tarefa 04 — plano de migração/staging do davidalmeida com dry-run**, limitada a transformações revisáveis, ainda sem executar banco real.

## 7. Validação local

O pacote inclui `tools/e2e/e2e_f903.sh --check` e `tools/e2e/e2e_f903.cjs`:

- autocheck com fixture SQL sem dados sensíveis;
- modo `--sql dev/install/sql/bd-davidalmeida.sql` confere hash, estrutura, tabelas, engine/charset, DDL destrutivo e contagens contra o inventário JSON;
- nenhum SQL é executado; o arquivo é apenas lido e parseado.

Execução feita pelo agente: **PASS** no autocheck e **PASS** no modo real contra o arquivo publicado no GitHub, sem reproduzir valores.

## 8. Arquivos da entrega

- `docs/F9-03-Auditoria-Banco-DavidAlmeida.md`
- `docs/F9-03-Inventario-Banco-DavidAlmeida.json`
- `docs/README.md`
- `agents-ia/decisoes/0059-f9-03-auditoria-banco-davidalmeida.md`
- `tools/e2e/e2e_f903.sh`
- `tools/e2e/e2e_f903.cjs`
- `tools/e2e/fixtures/f903_minimal.sql`
- `manifest.json` e `sha256sum.txt`
