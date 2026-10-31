# F9-73 — Interações do bloco Group

## Implementação

- O bloco `core/group` agora tem ação rotulada **Adicionar bloco ao grupo** na barra contextual e no inspetor.
- O inspetor mostra a quantidade de blocos filhos e abre o inserter com o grupo como destino, adicionando o conteúdo dentro do grupo em vez de criar um bloco irmão.
- O rótulo opcional para o navegador da estrutura permanece editável; os blocos internos seguem editáveis, reordenáveis e removíveis. A aba Personalizar continua oferecendo a aparência compartilhada.
- A inserção preserva validação de tipos, limites e profundidade já presentes no documento. Sem alteração de banco ou fluxo de mídia do Blog.
- Cache-bust: `threejs-20-group-inserter`.

## Verificações

- `node --check` nos scripts JavaScript e smokes de URL passaram.
- Bateria estática: **206 verificações aprovadas**.
- ZIP íntegro. PHP CLI, navegador real e staging indisponíveis; não alego teste runtime.
