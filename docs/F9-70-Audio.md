# F9-70 — Interações do bloco Audio com a biblioteca CMS Pages

## Implementação

- `core/audio` tem ação rotulada na barra contextual e no inspetor para abrir a biblioteca CMS Pages na aba Áudio, preservando o ID do bloco.
- É possível enviar um novo áudio e associá-lo imediatamente ao bloco, escolher outro da biblioteca para substituir o atual e excluir uploads da aba Áudio com confirmação. A exclusão avisa que os blocos que usam o arquivo deixarão de reproduzi-lo.
- O inspetor permite editar o caminho/selecionar ou trocar o áudio e editar o rótulo do link do player; prévia e renderer continuam usando o player nativo com controles e os caminhos CMS Pages autorizados.
- Exclusão protegida por ACL `modify` e CSRF; model limita tipo/extensão ao catálogo, rejeita traversal e symlinks. A biblioteca e armazenamento do Blog seguem independentes.
- Cache-bust: `threejs-17-audio-media-actions`.

## Verificações

- `node --check` nos três scripts JavaScript afetados passou; os smokes da biblioteca e do helper de URLs passaram (9 casos no helper).
- Bateria estática: **196 verificações aprovadas**. ZIP completo íntegro.
- PHP CLI, navegador real e staging indisponíveis; não alego lint PHP nem teste runtime.
