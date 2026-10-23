# ADR 0061 — F9-05: Model de definições e ACL dormente de campos personalizados

**Data:** 2026-10-23 · **Agente:** admin / install / orquestrador.
**Status:** aceita como backend parcial; controller/UI/valores ficaram fora.
**Base verificada:** `davidcreator/davidcms` @ `2be7cc06c982e24faca1195b6cdd126f34960322`.

## Contexto

A F9-02 publicou o desenho físico de campos personalizados. A F9-03/F9-04 fecharam a auditoria do banco legado e impediram que o dump sensível continuasse público. Com o ciclo de migração legada bloqueado com segurança, a Fase 9 retoma a trilha principal.

A próxima dependência aprovada era preparar rota/ACL e Model das definições antes de qualquer UI ou gravação em `cms/page`.

## Decisão

1. **Model backend primeiro:** criar `dev/admin/model/custom_field/definition.php`, classe `Reamur\Admin\Model\Custom_field\Definition`, carregável futuramente como `custom_field/definition` → `model_custom_field_definition`.
2. **Apenas definições:** o Model conhece grupos, campos e descrições; **nunca** escreve em `custom_field_value`. A tabela de valores pode ser contada apenas para proteger exclusão.
3. **Entidade única:** o whitelist é `cms/page`, conforme MVP aprovado da F9-01; Blog/projetos seguem candidatos, não habilitados.
4. **Tipos e visibilidades fixos:** `text`, `textarea`, `number`, `boolean`, `url`, `select`; `admin_only`/`public` apenas como metadado — exposição pública continua desligada nesta etapa.
5. **Lifecycle conservador:** `entity_type`, `key`, `type` e `localized` são imutáveis; grupo só sai vazio; campo só sai sem valores. Desativação/preservação virá com UI/integração.
6. **ACL dormente:** a rota futura `custom_field/definition` entra nos grants de Administrator por migração e no seed do wizard, mas controller/templates ainda não foram entregues. Isso diminui o diff da próxima tarefa e preserva compatibilidade.
7. **Rollback da ACL:** SQL separado remove só a rota do JSON de permissões, sem tocar nas tabelas F9-02.

## Alternativas consideradas

- **Entregar controller/UI junto:** rejeitado para manter a regra de subtarefas pequenas e testáveis; o Model precisava ser estabilizado antes da interface.
- **Permitir editar type/key:** rejeitado; contradiz a decisão da F9-01 e exigiria migração explícita com risco de perda.
- **Cascatear exclusão para valores:** rejeitado; dados são preservados e exclusão bloqueia enquanto houver valor.
- **Hard-delete de grupo com campos:** rejeitado; grupo deve ser esvaziado/desativado antes.

## Consequências

- O CRUD de definições poderá ser implementado na F9-06 sobre um Model já versionado.
- Novas instalações nascem com permissão dormente para a rota futura; instalações existentes ganham migração idempotente.
- Nenhum valor de página é criado; nenhuma tela pública altera sua saída.
- Se a migração F9-02 ainda não foi aplicada, o Model existe, mas não deve ser usado até o schema estar presente no laboratório.

## Validação

`tools/e2e/e2e_f905.sh --check` valida o contrato, o Model, a imutabilidade esperada, a tabela de valores somente-leitura e a ACL SQL. Nada foi executado em PHP/banco/produção.
