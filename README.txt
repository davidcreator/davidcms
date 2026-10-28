ReamurCMS BDREditor — refinamento de workspace

Alvo esperado: main HEAD 36fb7cb1de469ac1f6b10922f88a9e29f4da6797
Conteúdo: layout em tela cheia com Estrutura/Elementos à esquerda, canvas ao centro e inspetor à direita; painel compacto preservado; documentação do fluxo.

Aplicação local opcional (dentro do repositório alvo):
  bash apply-patch.sh Pages-Editor-UX.patch 36fb7cb1de469ac1f6b10922f88a9e29f4da6797

O script verifica o HEAD e executa git apply --check antes de aplicar. Não faz commit, push, deploy, SQL ou migrations. Aplicar somente após confirmar que o checkout está no HEAD indicado.
