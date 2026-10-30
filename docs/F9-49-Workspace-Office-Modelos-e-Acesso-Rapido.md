# F9-49 — Workspace Office: acesso rápido e galeria de padrões

**Escopo desta etapa:** aproximar a interface real do BDREditor do protótipo sem simular recursos de servidor ainda inexistentes.

## Implementado no editor

- A faixa Office, as guias e a barra vertical existente permanecem como navegação principal.
- A ação **Padrões** da barra agora abre uma galeria dentro do editor. Ela apresenta os quatro padrões locais que já existem e os padrões salvos que o usuário pode ler; inserir, renomear, excluir e salvar continuam usando as operações existentes, as verificações de compatibilidade, IDs novos e as permissões já configuradas.
- A galeria informa que esses itens são padrões de blocos, não modelos completos de documentos. Padrões incompatíveis com o ponto selecionado ficam desabilitados.
- O cabeçalho ganha acesso rápido a Localizar/Substituir, Desfazer, Refazer e Exportar BDRE (JSON), usando as ações existentes. Os controles de histórico continuam sincronizados com o estado do editor.
- A galeria e o acesso rápido mantêm comportamento responsivo e foco/nomes acessíveis.

A mesma interface compartilhada continua embutida nos fluxos independentes de **Pages** e **Blog**; não há mudança de rotas, controllers, formulários de salvamento, modelos de dados ou banco.

## Não incluído nesta etapa

- **IA real:** nenhuma chamada a serviço/API é feita; falta definir provedor e integração autorizada.
- **PDF/DOCX/PPTX:** o acesso rápido oferece apenas a exportação JSON que já existia. Formatos adicionais exigem uma implementação de exportação real.
- **Compartilhamento, arquivos de projeto e paginação visual:** não foram simulados como capacidades reais; dependem de serviços/modelos específicos. O BDREditor continua tratando uma página CMS ou um post como documento de blocos, sem inventar páginas internas do Word.

## Arquivos completos no ZIP

- `dev/admin/view/js/blog-block-editor.js`
- `dev/admin/view/css/blog-block-editor.css`
- `docs/F9-49-Workspace-Office-Modelos-e-Acesso-Rapido.md`

O pacote **não contém controllers, idiomas, migrações ou patches**. Essa restrição é intencional após a regressão de rota do F9-48. O hotfix de fallbacks dos controllers continua separado e não é alterado por esta entrega.

## Validação

- `node --check` no JavaScript.
- Assertivas estáticas da galeria, permissões/compatibilidade já conectadas, comandos de acesso rápido e CSS responsivo.
- Passaram as verificações estáticas de teclado, navegação, estrutura de tópicos, qualidade F9-26 (427 chaves alinhadas nas três localidades) e `check_single_page_ribbon.cjs` (12/12).
- Nenhum runtime PHP, navegador real, staging, banco, migração, deploy ou escrita ao GitHub foi executado. O ambiente de trabalho disponível é uma árvore parcial; testes que exigem arquivos ausentes do catálogo/tema não foram usados como prova desta etapa.
