# F9-50 — Reconstrução do workspace Office do BDREditor

> **Complementado pelas F9-51 e F9-52:** a navegação/workspace permanece válida; a área do formulário foi reorganizada em [F9-51](F9-51-BDREditor-Area-de-Edicao-em-Largura-Total.md) e o modo fullscreen automático foi incluído em [F9-52](F9-52-BDREditor-Workspace-Fullscreen.md).

## Objetivo

Substituir a entrega F9-49, que acrescentava sobretudo a galeria de padrões e ações rápidas, por uma interface de workspace mais próxima do protótipo aprovado. A navegação é apresentada em uma faixa superior de menus Office e em uma barra vertical responsiva; cada área ocupa a superfície principal do editor, em vez de ser apenas um controle pontual.

## Áreas e integração

- **Início:** retorna ao editor de blocos já existente. Os comandos continuam atuando sobre o documento BDRE/JSON em memória.
- **Modelos:** abre a galeria de padrões locais e padrões salvos. São padrões de blocos, não modelos completos de documentos; busca e filtros respeitam o catálogo e as permissões existentes. Inserção, salvamento, renomeação e exclusão continuam usando as operações já disponíveis.
- **Biblioteca:** exibe ações contextuais para imagem, arquivo, áudio e vídeo. Os seletores só ficam habilitados quando há um bloco compatível selecionado e a tela oferece o picker; os botões encaminham para os eventos existentes de CMS Pages ou Blog. Pages e Blog continuam independentes.
- **Assistente IA:** a área deixa explícito que não há provedor configurado. Ações e campo de solicitação permanecem desabilitados; nenhum conteúdo é enviado ou gerado.
- **Exportar:** permite a cópia estrutural BDRE/JSON e a importação pelo importador existente. PDF, DOCX, PPTX, imagem e HTML aparecem identificados como indisponíveis, sem exportação fictícia.
- **Compartilhar:** informa que links e permissões não estão configurados; campos e ação ficam desabilitados, sem criação de links.
- **Ajuda:** apresenta os atalhos de teclado já implementados.

A faixa Office também dá acesso às guias de edição existentes. As telas de workspace não substituem os formulários ou mecanismos de salvamento originais. O conteúdo do site continua sendo salvo pelo formulário correspondente.

## Escopo preservado

- Não foram alterados controllers, rotas, banco, migrações, repositório GitHub ou deploy.
- Os fluxos de **CMS Pages** e **Blog** continuam independentes; a interface compartilhada reutiliza os eventos e seletores já existentes.
- Não se executa `custom_js`, não se introduz HTML arbitrário e não se simulam IA, compartilhamento ou formatos indisponíveis.
- Os fallbacks do hotfix F9-48 nos controllers permanecem fora deste pacote e não foram reaplicados.

## Arquivos incluídos

- `dev/admin/view/js/blog-block-editor.js`
- `dev/admin/view/css/blog-block-editor.css`
- `docs/F9-50-Workspace-Office-Interface-Completa.md`
- `docs/F9-49-Workspace-Office-Modelos-e-Acesso-Rapido.md` (aviso explícito de que o registro anterior foi supersedido)

O ZIP contém somente esses arquivos completos; não é um patch e não inclui controllers, idiomas, banco ou migrações.

## Validação realizada

- `node --check dev/admin/view/js/blog-block-editor.js` — passou.
- Revisão estática das rotas de navegação, filtros dos padrões, chamadas a seletores existentes, importação/exportação suportada e estados desabilitados para integrações indisponíveis.
- Não foi possível executar a aplicação em PHP, navegador real ou staging. Também não foram executados testes runtime, banco, migrações, deploy ou escrita no GitHub. A árvore disponível é parcial; a checagem Git não se aplica porque ela não contém metadados de repositório.
