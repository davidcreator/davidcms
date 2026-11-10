# F9-75 — Sistema visual do BDREditor

## Escopo e salvaguardas

Esta etapa aplica a linguagem visual da prévia interpretativa ao editor existente. Não reescreve o editor, schema, conteúdo, blocos, insertion logic, validação, mídia, storage, import/export, atalhos, inspeção, histórico, padrões ou salvamento. Todas as mudanças funcionais anteriores permanecem no mesmo JavaScript; a implementação principal desta frente é uma camada CSS final e escopada ao `.rb-editor`, com novos testes estáticos e cache-bust.

- Unifica superfície, barra de ferramentas Office, navegação, painéis, canvas e botões em uma paleta clara nos modos incorporado e tela cheia.
- Normaliza dimensões/estados dos controles. Ações icon-only passam a mostrar visualmente seu rótulo abaixo do ícone, preservando título, descrição acessível, labels de leitor de tela e handlers existentes.
- Mantém toda ação e todos os painéis. As barras continuam roláveis em telas estreitas, em vez de remover/comprimir recursos.
- Trata media queries para o formulário embutido e o modo fullscreen; mantém independentemente os contextos de CMS Pages e Blog.
- Nenhuma alteração em dados de documento, PHP de validação/renderização ou servidor. Não há novos recursos externos.
- Cache-bust Pages/Blog: `threejs-22-bdre-ui-system`.

## Verificações

- `node --check` para os scripts JS existentes.
- Smoke tests de URL e bateria estática do editor passaram (**214 verificações**).
- Conferido que o JavaScript e os blocos existentes foram preservados no pacote; ZIP íntegro.
- Navegador real/PHP CLI não disponíveis neste ambiente; não alego verificação visual runtime. O protótipo HTML é uma referência visual, não uma aplicação de edição funcional.
