# F9-31 — Histórico persistente de versões BDRE

**Status:** especificação de implementação; ainda não integrado ao código.

## Objetivo

Adicionar histórico durável às versões salvas do BDRE, complementando o histórico temporário por aba da F9-30. O escopo abrange independentemente CMS Pages e Blog. Não substituir o botão Save por autosave.

## Comportamento

- Capturar uma revisão antes de cada salvamento explícito de um documento já existente.
- Isolar as revisões por tipo de entidade, ID do registro e `language_id`.
- Uma revisão contém os campos editoriais daquele idioma e a representação canônica BDRE correspondente. Em Pages, manter intacta a descrição serializada, inclusive dados de layout de página de sistema. Em Blog, incluir o sidecar BDRE quando aplicável.
- Evitar criar revisão quando o conteúdo persistido é idêntico ao que será salvo.
- Manter até 20 versões por entidade/idioma, eliminando as mais antigas.
- Mostrar data e um rótulo editorial sucinto no formulário. Restaurar uma revisão carrega seu conteúdo no formulário como alteração não salva; somente Save efetiva a restauração.
- Criar revisões em salvamento de edição; a criação inicial não gera uma revisão anterior inexistente.
- Excluir revisões quando o registro correspondente for excluído.

## Segurança e integridade

- Verificar a permissão de modificação do módulo e CSRF em todas as operações de listagem/restauração, respeitando o padrão de rotas do admin.
- Não aceitar `entity_type`, entidade ou idioma sem validar existência e autorização no servidor.
- Não executar HTML, Twig, `custom_js` ou conteúdo arbitrário durante visualização/restauração.
- Usar consultas parametrizadas pelo mecanismo padrão de escape/modelo e índice composto para consultas limitadas.
- Migration aditiva e rollback próprios, ambos entregues como arquivos; não executar migration automaticamente.
- Pages e Blog permanecem em fluxos e controladores independentes.

## Entrega e validação

Entregar arquivos completos alterados, migration, rollback, documentação e ZIP em `entregas`. Fazer verificações estáticas direcionadas após a integração, sem acesso a staging, banco, GitHub write ou deployment. Baterias abrangentes ficam para a fase final do produto.

## Próxima implementação

Implementar primeiro o armazenamento/snapshot transacional nos models e a leitura/listagem segura; depois integrar controles de histórico e restauração em cada formulário preservando o Save explícito. Este documento não representa uma implementação funcional nem autoriza execução de migration.
