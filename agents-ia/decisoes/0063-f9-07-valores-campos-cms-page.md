# ADR 0063 — F9-07: Persistência administrativa de valores de campos em CMS/page

**Data:** 2026-10-24 · **Agente:** admin / orquestrador.  
**Status:** implementação local para revisão; nenhum SQL foi executado.  
**Base verificada:** `davidcreator/davidcms` @ `9151ba7c8d9d00772c788388d58104ce430460f5`.

## Contexto

F9-05 introduziu o Model de definições; F9-06 acrescentou a UI para grupos/campos. A aprovação F9-01 elegeu CMS/page como primeiro alvo, mas nenhum formulário de entidade ainda persistia valores. O schema F9-02 reserva `custom_field_value` com `language_id=0` para compartilhados e IDs reais de idioma para localizados.

## Decisão

1. Criar `custom_field/value` separado do Model de definições. O adapter é restrito a `entity_type='cms/page'` e a grupos ativos.
2. Integrar os valores à tela existente de criar/editar páginas, sem criar endpoint novo. A ACL existente `cms/page` protege leitura/mutação e o fluxo existente mantém CSRF.
3. Respeitar `localized`: compartilhado grava uma única linha em idioma zero; localizado produz um controle/valor por idioma ativo.
4. Validar required/tipo no servidor, incluindo JSON options de `select`, URL HTTP(S), boolean `0/1` e precisão compatível com `DECIMAL(38,6)`.
5. Fazer upsert idempotente por chave única da F9-02, em transação do lote de custom values; rollback do lote em falha detectável.
6. Ao excluir uma página, remover as linhas vinculadas por `entity_type` e `entity_id`, pois o schema F9-02 não cria FK.
7. **Não habilitar exposição pública:** o catálogo/site não muda; a flag `visibility` é ignorada para render público nesta etapa.

## Alternativas consideradas

- **Armazenar campos no texto/JSON da descrição:** rejeitado; contraria a tabela de valores normalizada F9-02 e complica consulta/locale.
- **Salvar valores sem validar no Model:** rejeitado; controller não é fronteira suficiente contra POST artesanal.
- **Renderizar `visibility=public` imediatamente:** rejeitado; ainda faltam escaping/contrato de consumo, cache e testes de frontend.
- **Cascata de FK:** não disponível no schema publicado, então a exclusão é explícita e escopada à página.

## Consequências e riscos aceitos

- Páginas administrativas podem armazenar valores sem alterar HTML público.
- F9-02 precisa estar aplicado; o formulário e a exclusão dependem da existência das tabelas custom-field.
- A transação cobre somente o lote de valores. A gravação da página base ocorre antes no Model legado; se a escrita custom falhar, página base pode permanecer salva e o usuário recebe erro explícito. Atomicidade total fica para tarefa posterior.
- Uma falha de banco pode lançar exceção pelo adapter DB existente; o framework trata erro como falha de request. Nenhuma estratégia de retry automático é adicionada.

## Validação

`tools/e2e/e2e_f907.sh --check` confere estruturalmente escopo/validação/upsert/delete e controles. Não é PHP lint nem E2E de browser/banco. Não houve execução destrutiva, importação ou aplicação de migração.
