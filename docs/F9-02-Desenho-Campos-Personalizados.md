# Fase 9 · Tarefa 02 — Desenho físico de campos personalizados

**Data:** 2026-10-23 · **Base pública confirmada:** `110eb513a90f6be8565a0ac92de0bf40535f2924`
**Estado:** desenho/migração propostos, sem execução.

## Escopo autorizado

Na Tarefa01 o usuário aprovou o MVP proposto: páginas CMS primeiro; seis tipos; namespace separado; privado por padrão; regras de localize/csrf/visibilidade; e aprovação para preparar scripts de migração/rollback com Tool E2E em ZIP, sem executar no banco. Fase8 fechada por aceite explícito do operador conforme Tarefa01/12.

## Proposta física aprovada para revisão

### Tabelas novas propostas
- `rms_custom_field_group`: grupos por entidade, label e estado/ordem.
- `rms_custom_field_group_description`: labels/descrição por idioma, sem traduzir campos.
- `rms_custom_field`: campos por entidade; grupo, tipo, localized/required/visibility/options/sort_order.
- `rms_custom_field_description`: label/ajuda por idioma.
- `rms_custom_field_value`: valores para entidade/campo/idioma lógico; language_id 0 = compartilhado; number DECIMAL(38,6) distinto do TEXT; índices de utilização e juntas.

Regras conceituais preservadas:
- `language_id > 0` = idioma de conteúdo; id0 é compartilhado, não um idioma real.
- A base CMS/page é o primeiro alvo; nenhum registro seed/teste é criado.
- Sem FK constraint, conforme convenção das outras tabelas; integridade no Model.
- Prefixo `rms_` é placeholder; wizard substitui por `DB_PREFIX`.
- Não editar migração existente; correções futuras devem ser novas migrações.

### Não implementado neste lote
- Nenhum schema novo no instalador/schema.sql; a migração executável está em `upgrade_f9_02_custom_fields_schema.sql` para revisão.
- Nenhum model/controller/idioma/cms alterado.
- Nenhum endpoint API/CRUD/admin/visual/renderização.
- Dicionário de dados não ganha essas tabelas até a migração ser aplicada/aprovada.

## Validação local incluída

- `tools/e2e/e2e_f902.sh --check` executa análises estáticas: sintaxe/ordem dos arquivos, lista exata de tabelas, ausência de DDL destrutivo no upgrade, rollback inverso,
- - controles de payload/visibilidade IDs e limite de38/6 para NUMBER no armazenamento; IDs/índices esperados; não se instala nada.

Testes reais executados pelo agente:
- **verificação de SQL estático 5 grupos/0 erros**.
- Guardas do Tool executados: pass 10/fail0 em E2E 902 --check.

Nenhum banco/PHP/HTTP/servidor/navegador/production executado. Nenhum bootstrap/instalação SQL foi executado ou feito aqui.

### Diretrizes de execução segura posterior

Para testar em laboratório (não obrigatório nesta fase):
1. Salvar backup e licenciar aplicar no DB teste.
2. Escolher prefixo e revisar migração.utf8mb4/InnoDB.
3. Rodar só depois de revisar o SQL e usar plano de backup.
4. O rollback incluído deve ser chamado apenas se você precisar descartar o que não teve dados.

**Aviso de rollback:** `DROP TABLE IF EXISTS` destrói dados; usar somente em laboratório descartável ou com backup validado. Não há restauração automática dos valores customizados.

## Validação guia estática E2E

Teste local `e2e_f902.sh` (nativo): **10 testes aprovados / 0 falhas**. Framework: shell estático, sem PHP/Node/browser/banco nem drivers reais. Nenhuma tentativa feita em fake ou em produção; foi usado apenas inspeção estática do SQL. Suíte passou no agente; manual também compatível com macOS/Linux/Windows Git Bash.

## Arquivos da entrega

- `dev/install/sql/upgrade_f9_02_custom_fields_schema.sql`
- `dev/install/sql/rollback_f9_02_custom_fields_schema.sql`
- `docs/F9-02-Desenho-Campos-Personalizados.md`
- `docs/README.md`
- `agents-ia/decisoes/0058-f9-02-desenho-campos-personalizados.md`
- `tools/e2e/e2e_f902.sh`

Nenhum código runtime alterado; zero `dev/admin` ou `dev/catalog` criado além dos arquivos SQL.
