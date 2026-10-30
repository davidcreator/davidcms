# F9-45 — Faixa Office para blocos e dicas das ferramentas

## Alterações

- A guia **Inserir** agora distribui os 21 tipos de bloco de primeiro nível em três grupos: **Conteúdo**, **Mídia** e **Layout**, seguindo o padrão de faixa de aplicativos Office.
- Cada bloco aparece como uma ferramenta de ícone com nome acessível e dica de ferramenta ao passar o mouse/focar. A dica apresenta nome e uma descrição curta; PT, EN e ES têm descrições próprias.
- A descrição também é associada por `aria-describedby` e o nome permanece disponível por `aria-label`, sem depender do ícone para leitores de tela.
- As ferramentas de inserção rápida são desativadas quando o bloco selecionado não aceita aquele tipo (por exemplo, dentro de listas, capa, galeria ou colunas), usando as mesmas regras de compatibilidade do inserter. A ação continua passando pela validação e pelo histórico normais.
- Ferramentas compactas com ícones — incluindo as ações longas da faixa e da barra do bloco — recebem nome e descrição em tooltip; os nomes visuais longos ficam ocultos, preservando rótulo acessível.
- A guia Inserir mantém o catálogo completo, importação e padrões; guias Início e Exibir mantêm suas funções existentes.

## Verificações e limites

- `node --check` em todos os JavaScripts do editor passou.
- Assertion estática confirmou os 21 tipos em categorias, ícones e descrições PT/EN/ES; confirmou também a associação acessível de tooltip e a habilitação contextual.
- PHP CLI não está instalado; não foi possível executar lint PHP.
- Sem staging pronto, não houve inspeção visual de hover/touch, navegador ou E2E. As dicas são fornecidas pelo atributo `title` nativo do navegador, além das associações acessíveis.
