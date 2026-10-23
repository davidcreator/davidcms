# ADR 0058 — F9-02: Desenho físico dos campos personalizados

**Data:** 2026-10-23 · **Agente:** install / orquestrador.
**Status:** proposta aprovada pelo usuário; scripts de migração preparados, nenhum banco executado.
**Base:** `110eb513a90f6be8565a0ac92de0bf40535f2924` (repositório correto `davidcreator/davidcms`).

## Contexto

O F9-01 mapeou cms/page como primeiro alvo e regras de namespace/localização/visibilidade/CSRF. O usuário escolheu avançar para o desenho físico com migração/rollback em laboratório, sem executar no banco. O repositório correto foi conferido novamente antes da implementação.

## Decisão

1. **Criação física proposta somente em SQL:** duas novas migrações separadas:
   - `upgrade_f9_02_custom_fields_schema.sql` — cinco tabelas novas;
   - `rollback_f9_02_custom_fields_schema.sql` — remoção controlada em teste, sem dados.
2. **Não há FKs:** mantém-se a convenção do projeto — integridade na camada Model (cascata manual).
3. **Prefixo usando o mesmo padrão do projeto:** `rms_` é placeholder; o fluxo real de deploy tem que usar o `DB_PREFIX` efetivo do ambiente, não hard-coded.
4. **Só migração de schema; sem seeds/grants/registros de teste:** não criar conteúdo de idioma/padrões/overrides/permissões/autorização nova.
5. **Modelagem:**
   - `rms_custom_field_group` por entidade (`entity_type`), chave estável, nome técnico, estado/ordem;
   - `rms_custom_field_group_description` / `rms_custom_field_description` só labels/ajuda por idioma (sem alterar language/registry de fato);
   - `rms_custom_field` define tipo, grupo, flags localized/required/visibility, options (JSON quando select) e sort_order;
   - `rms_custom_field_value` guarda TEXT/DECIMAL por entidade(field_id, id int) e idioma lógico; `language_id=0` marca compartilhado sem confundir com ID real.
6. **Identificadores:** `field_key` imutável por entidade; group_key único por entidade; entidade/campo/idioma único para valores (-- que será verificável pelo Model posterior).
7. **Tipo DECIMAL(38,6):** abrange o contrato de 18 dígitos totais/precision/scale; separar do TEXT para não truncar valores e permitir cálculo/ordenação numérica futuros, sem misturar com strings de idioma.

## Alternativas consideradas

- Labels normalizadas com outra tabela em vez de colunas TEXT/JSON — rejeitada neste lote para manter o padrão do projeto; revisão futura pode migrar, mas não aplicamos agora às tabelas base.
- Tabela genérica `custom_field_*` sem `entity_type` — rejeitada porque precisa suportar cms/page primeiro e impedir colisões de chave entre projetos/blog/páginas automaticamente.
- Usar JSON/TEXT para tudo — rejeitada para `value_number` com dtype exato; o language slot lógico (id0 compartilhado) é validado pela regra de valores, não pelo idioma físico.

## Verificação local

- SQL estático: 5/5 grupos esperados, `CREATE TABLE IF NOT EXISTS`, sem DROP/DELETE no upgrade interno.
- Rollback separado e desenhado com DROP em ordem inversa.
- Tool `tools/e2e/e2e_f902.sh` passa com **PASS estático sem DB executado**.
- Nenhum controller/model/idioma/schema-base/dependência/site/editor/defaults alterado aqui.

## Consequências

- Este pacote entende enquanto contrato do futuro Model. Implementação/CRUD admin/catalog não entra neste lote.
- Migração precisa de backup e aplicação em laboratório antes de ampla. Aplicar migração sozinha não liga nada ao painel.
- Permissions de gestão/visão ainda pendentes da Tarefa03; nenhum endpoint novo foi registrado.
- Dicionário/Guia/estrutura devem gant citar as tabelas após a migração ser aplicada e aprovada; não alteramos esses docs aqui por enquanto.

Guia e Tool: [F9-02 — Desenho físico](../../docs/F9-02-Desenho-Campos-Personalizados.md).
