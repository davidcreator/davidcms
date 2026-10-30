# F9-46 — Expansão das guias da faixa BDREditor

## Entrega

A faixa agora distribui o conteúdo em seis guias: **Início**, **Inserir**, **Mídia**, **Layout**, **Revisar** e **Exibir**. Inserir, Mídia e Layout mostram ferramentas de bloco em suas próprias áreas, em vez de acumular todos os tipos na guia Inserir.

- **Inserir:** blocos de conteúdo e ferramentas para abrir o catálogo completo/importar.
- **Mídia:** imagem, vídeo incorporado/local, Three.js, galeria, arquivo e áudio.
- **Layout:** espaçador, separador, grupo, colunas, capa e tabela, além de quatro padrões de layout.
- **Revisar:** histórico de rascunhos e localizar/substituir, retirados da faixa Início. Atalho Ctrl/⌘+F abre esta guia e o painel de busca; ao fechar, mantém a guia Revisar ativa.
- **Início:** área de transferência/histórico de undo-redo, formatação inline e alinhamento.
- **Exibir:** painéis, viewport de prévia e tela cheia.

As ferramentas de blocos mantêm tooltips com nome/descrição e estado contextual habilitado/desabilitado. A navegação por setas entre guias continua cíclica e usa a ordem visual. As guias e tooltips possuem strings para PT-BR, EN-US e ES-EU; as traduções de rótulos da faixa para Blog foram conectadas nos controladores.

## Verificações e limites

- `node --check` em todos os JavaScripts do editor passou.
- Assertions estáticas verificaram seis guias, distribuição sem duplicação/omissão dos 21 tipos raiz, grupos e conexões das traduções nos fluxos Pages/Blog.
- CLI PHP indisponível; não foi possível lintar controladores.
- Sem staging, não houve QA visual/responsivo ou teste de navegador/E2E.
