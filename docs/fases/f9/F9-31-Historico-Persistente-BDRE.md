# F9-31 — Histórico persistente de versões BDRE

**Status:** integração implementada em Pages e Blog (captura, listagem protegida e restauração em memória); depende da migration F9-31 aplicada no ambiente para persistir histórico.

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

## Comportamento integrado

- Os models Page e Blog capturam snapshots do estado persistido antes de cada edição explícita, por idioma; a criação inicial não gera uma revisão anterior.
- Captura ignora hash igual ao snapshot mais recente e mantém no máximo 20 snapshots por entidade/idioma.
- Blog inclui o sidecar BDRE quando disponível e tenta converter legado com segurança; Pages preserva a descrição e decodifica o envelope de layout permitido para restaurar documento e ordem/seleção de seções.
- Endpoints de listagem/detalhe são POST-only, verificam permissão `modify`, CSRF e existência do registro. A listagem devolve metadados; detalhes retornam somente a revisão pertencente ao registro e idioma solicitados.
- Os formulários oferecem carregamento do histórico por idioma. Restaurar aplica campos editoriais e documento ao editor em memória; não executa Save. A confirmação e o estado não salvo deixam claro que é preciso clicar em Salvar para persistir.
- A captura é fail-soft enquanto a tabela de revisão não existir; exclusão de página/post remove suas revisões.
- Revisões não incluem custom fields de Pages, nem têm comparador visual; conteúdo legado que não converte com segurança pode ser restaurado nos campos legados quando editáveis.

## Validação e implantação

Migration e rollback são aditivos e permanecem não executados. Não foram feitos testes mutativos, acesso a banco/staging ou push. Executada apenas validação de sintaxe JavaScript; PHP não está instalado neste ambiente, portanto o lint PHP fica pendente. Aplicar a migration pelo fluxo autorizado antes de esperar histórico persistente. Esta documentação não autoriza execução da migration.
