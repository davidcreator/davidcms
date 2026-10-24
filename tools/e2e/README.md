# F9-16 — checklist E2E (não executado)

> Procedimento futuro apenas. Não execute nesta entrega: o usuário restringiu testes PHP/SQL/browser/app e migrations. A existência do arquivo não implica que F9-12 esteja aplicada.

## Pré-condições para execução autorizada

- Ambiente de teste isolado e descartável, nunca produção.
- Commit/arquivos F9-16 integrados em checkout completo e revistos.
- Migration F9-12 aplicada explicitamente pelo responsável nesse ambiente e schema conferido por procedimento autorizado.
- Fixtures sintéticas, sem dados reais nem dump privado.

## Casos manuais

1. Sem sidecar F9-12: formulário de post usa o editor legado; nenhuma consulta de conteúdo sidecar deve converter falha de banco em tabela ausente.
2. Com sidecar: formulário autorizado carrega editor de blocos e três idiomas; usuário sem permissão não obtém o fluxo de edição/salvamento de blocos.
3. Criar post com blocos: inserir e reorganizar blocos, selecionar imagem da Biblioteca `view/image/blog/...`, enviar o formulário e confirmar documento e snapshot renderizado no idioma correto.
4. Editar post em blocos: mudança só persiste no POST normal; confirmar que sidecar e descrições são gravados sob a transação prevista.
5. Conteúdo legado conversível: revisar conversão antes de salvar e confirmar preservação do snapshot/hash do HTML legado original.
6. Conteúdo legado não conversível: permanece no modo legado; nenhuma conversão silenciosa ou perda de conteúdo.
7. Sidecar malformado/versão incompatível: editor bloqueado, sem fallback para conteúdo vazio e sem sobrescrita.
8. POST adulterado: documento inválido, idioma desconhecido, modo inconsistente ou editor não inicializado deve ser rejeitado no servidor.
9. Simular falha em cada gravação numa base descartável: rollback integral de post, descrições, SEO e sidecar.
10. Excluir post: sidecar é removido sem tocar em imagens ou outros arquivos.

Este checklist é planejamento; nenhuma ação acima foi executada.
