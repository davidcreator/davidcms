# ADR 0062 — F9-06: UI administrativa para definições de campos personalizados

**Data:** 2026-10-24 · **Agente:** admin / orquestrador.  
**Status:** implementação local para revisão; sem runtime público ou banco.  
**Base verificada:** `davidcreator/davidcms` @ `5fbf66dc64e8001ae7bea9b95aa3455221372cbe`.

## Contexto

A F9-05 entregou Model de definições e grant dormente `custom_field/definition`, mas ainda não existia interface/controller. A F9-01 autorizou o MVP para CMS/page, tipos básicos e namespace separado; a sequência incremental separou backend e UI.

## Decisão

1. Criar controller administrativo `custom_field/definition` para listar e manter grupos e definições.
2. Implementar formulários/listagem server-rendered com strings em `en-us`; não introduzir JS ou dependência nova.
3. Toda mutação é POST e exige `modify` + token CSRF; GET nunca exclui.
4. Validar chaves e unicidade, exigir label en-US, restringir entidade/tipos/visibilidade e validar que o grupo pertence a `cms/page`.
5. Manter `key`, `type` e `localized` imutáveis após criação. A F9-05 Model mantém as guardas de deleção para dados dependentes.
6. A UI administra apenas metadados de definição. Nenhum endpoint lê, cria, atualiza ou apaga valores; a renderização e integração de página ficam para tarefa futura.
7. Não alterar navegação global neste pacote; rota direta permanece sujeita à ACL. O menu será incluído após revisão separada, evitando mudança transversal sem levantamento completo da navegação.

## Alternativas consideradas

- **Integrar imediatamente os valores ao formulário CMS/page:** rejeitado; mistura administração de schema com valores/conteúdo e exige lifecycle, validação e renderização pública.
- **Expor `public` agora:** rejeitado; o campo pode registrar metadado, mas a página pública ainda não consome essa flag.
- **Excluir valores em cascata:** rejeitado; Model bloqueia exclusão se houver valor.
- **Adicionar novos idiomas de UI:** adiado para tarefa de localização; strings de origem ficam em `en-us`, respeitando o padrão do repositório.

## Consequências

- Administradores autorizados podem preparar as definições antes do trabalho de integração de conteúdo.
- A rota só funciona depois de F9-02 (schema) e F9-05 (grant) aplicados manualmente em laboratório.
- A ausência de menu é conhecida; nenhuma navegação pública ou menu administrativo existente foi alterado.
- É necessária validação real em laboratório com PHP/UI antes de uso operacional; Tool atual é estático.

## Validação

`tools/e2e/e2e_f906.sh --check` valida estaticamente o contrato. Nenhum PHP, SQL, browser ou banco foi executado pelo agente.
