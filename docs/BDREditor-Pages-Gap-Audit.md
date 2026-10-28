# Auditoria funcional — editor de CMS > Páginas

**Data:** 2026-10-28  
**HEAD remoto consultado:** `6fa3b7431277ec83b613221994e89f8c6f7b7725` — `feat: add native validation recovery for fullscreen editors`  
**Escopo:** CMS > Páginas e sua integração com BDREditor. Blog continua fora deste escopo; não se propõe duplicar suas rotas, bibliotecas de mídia ou persistência.

## Resumo executivo

A edição de páginas já dispõe de uma base funcional ampla: blocos estruturados, edição inline de texto, layouts aninhados, outline, operações de bloco com undo/redo, preview local responsivo, contexto de página e salvamento pelo POST existente. O principal trabalho restante não é adicionar outra camada genérica ao BDREditor: são **duas decisões específicas de Páginas** — como disponibilizar mídia com permissões próprias e se/como fornecer uma prévia fiel ao catálogo sem salvar um rascunho ou expor conteúdo privado.

Esta auditoria é estática. Nenhum browser, runtime PHP, SQL, staging ou dado de usuário foi acessado; nenhum arquivo do produto foi alterado.

## Matriz recurso × estado observado × próximo critério

| Área | Estado confirmado no HEAD | Lacuna / critério para uma próxima tarefa |
| --- | --- | --- |
| Conteúdo por idioma | A tela CMS mantém abas de idioma e monta uma instância de BDREditor para cada descrição em modo `blocks`. `en-us` tem título obrigatório. | Manter a distinção por idioma e usar a validação existente de todas as instâncias antes do POST. Não juntar textos de idiomas nem criar armazenamento paralelo. |
| Texto e blocos | A fonte de verdade é documento JSON v2, prefixado em `page_description.description`. O editor oferece blocos de conteúdo, mídia, layout, rich text inline, agrupamento/colunas e inserção por palette/comando. | Não falta um editor genérico novo para começar a editar conteúdo público. Novos tipos só após prioridade e contrato aprovados, com validador/renderer sincronizados. |
| Organização | Outline hierárquica, seleção/foco, reordenação, indent/outdent, duplicação, transformação, undo/redo e clipboard local já estão no editor. | Evitar duplicar controles. Validar interativamente em browser quando autorizado, mas não bloquear entregas de produto por uma bateria de checkers. |
| Workspace imersivo | Tela cheia tem Estrutura/Elementos à esquerda, canvas ao centro, inspetor à direita, contexto de página/idioma e botão Salvar condicionado à permissão `modify`. O modo mantém o formulário e o estado do documento atuais. | Não introduzir autosave. O envio deve continuar sendo o POST de CMS Page com ACL, CSRF, serialização e validação existentes. |
| Validação e foco | Se a validação HTML nativa bloquear o envio, o tratamento de CMS Page fecha a tela imersiva, ativa a aba do primeiro campo inválido e devolve o foco. Erros de documento continuam tratados pelo fluxo do editor/formulário. | Confirmar em uso real o ciclo de erro/correção/envio quando houver ambiente autorizado; não alterar regras de validação para contornar campos obrigatórios. |
| Preview local | Desktop/tablet/mobile e preview client-side existem; na integração de Páginas, a prévia mostra o título ativo seguido dos blocos. | Não equivale à página pública completa: não executa o renderer PHP nem inclui cabeçalho, rodapé, CSS/tema e dados públicos adicionais. Manter claro que é uma aproximação local. |
| Preview público/draft | Não há preview de rascunho em contexto de catálogo na integração examinada. O catálogo renderiza conteúdo marcado com `BlockRenderer` após validação server-side. | Se priorizado, primeiro aprovar um desenho de segurança: same-origin, ACL, proteção CSRF apropriada, conteúdo não persistido, sem bearer token e sem tornar draft público. É tarefa própria; não reutilizar URL pública como atalho. |
| Imagens e arquivos | A integração desabilita os seletores genéricos de mídia/imagem/arquivo usados pelo Blog. Paths ainda obedecem às allowlists do documento/renderer. Áudio/vídeo dependem da flag de acesso já existente. | Decidir se Pages precisa de uma biblioteca própria ou de um contrato explícito de mídia compartilhada. Não copiar endpoints/dialogs do Blog nem ampliar ACL por conveniência. |
| SEO e estado da página | Título, keyword, meta title/description, publicação/status e ordem continuam campos do formulário CMS; título/idioma aparecem como contexto, sem cópias editáveis no inspetor. | Preservar essa fonte única. Só levar metadados ao workspace se houver um objetivo claro e sem duplicar campos ou causar divergência. |
| Campos personalizados | Permanecem no fluxo de campos da própria página; o catálogo só publica os grupos explicitamente públicos. | Não transformar campos dinâmicos de outros módulos em blocos nem duplicar dados no documento BDREditor. |
| Legado | Conteúdo sem marcador permanece no fluxo legado; conversão para blocos ocorre apenas quando segura. Conteúdo que não pode ser convertido continua preservado/editável no textarea apropriado. | Manter a preservação sem perda silenciosa. Nunca forçar conversão ou regravar conteúdo legado ao apenas abrir a tela. |
| Segurança de blocos | O servidor valida o documento e o renderer gera HTML a partir de tipos/atributos allowlisted; documento inválido falha fechado no caminho de blocos. | O servidor segue como autoridade. Não permitir HTML/CSS arbitrário ou JavaScript Three.js livre; qualquer evolução de cenas precisa permanecer em componentes configuráveis aprovados. |
| Padrões | Há padrões locais incorporados para inserção; o backlog exclui padrões/templates persistentes sem aprovação de permissões e armazenamento. | Priorizar inserção e reutilização local antes de considerar biblioteca persistente; qualquer persistência compartilhada exige decisão de ACL/schema separada. |

## Decisões que a auditoria deixa abertas

1. **Mídia para Pages:** quais tipos de arquivo e raízes são necessários, quem pode selecionar/uploadar e qual módulo é dono do fluxo. A auditoria não presume que a biblioteca do Blog deva ser reutilizada.
2. **Prévia de catálogo:** se a aproximação local é suficiente ou se é necessário visualizar a página com renderer e tema públicos. Uma prévia de draft exige revisão de segurança/ACL antes de qualquer endpoint.
3. **Prioridade de novos blocos:** selecionar necessidades editoriais concretas antes de alterar schema/renderer. O inventário atual já cobre texto, mídia e layout básicos.
4. **Three.js:** manter o guardrail de cenas e componentes aprovados/configuráveis; esta auditoria não valida o runtime da propriedade `custom_js` nem recomenda ampliá-la.

## Sequência recomendada

1. O responsável escolhe entre **fluxo próprio de mídia para Pages** e **desenho de prévia segura de catálogo**; não implementar ambos por suposição.
2. Para a opção escolhida, preparar escopo e critério de aceite específicos, mapear ACL e contratos existentes e confirmar antes de tocar no núcleo compartilhado ou criar endpoint/schema.
3. Manter o fluxo atual de edição/salvamento como base; não executar migration, SQL, staging, deploy ou GitHub write sem autorização separada.

## Fontes verificadas

- `dev/admin/view/template/cms/page_form.twig`
- `dev/admin/controller/cms/page.php`
- `dev/admin/view/js/blog-block-editor.js`
- `dev/admin/view/js/blog-block-editor-init.js`
- `dev/system/library/blog/block_document.php`
- `dev/system/library/blog/block_renderer.php`
- `dev/catalog/controller/cms/page.php`
- `dev/catalog/view/template/cms/page_info.twig`
- `docs/BDREditor-CMS-Page-Integration.md`
- `Editor-Content-Gutenberg-Backlog.md` — próximo passo documental: gap audit funcional; este documento cobre CMS > Páginas sem renumerar tarefas históricas.
