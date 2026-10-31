# F9-69 — Interações do bloco Download com a biblioteca CMS Pages

## Implementação

- O bloco `core/file` ganha ações rotuladas no inspetor e na barra contextual para abrir a aba **Downloads** da biblioteca independente CMS Pages. O alvo do bloco é preservado ao abrir o modal.
- No modal, pode-se enviar um arquivo permitido e aplicá-lo ao bloco imediatamente, ou selecionar outro arquivo já existente para substituir o atual. O rótulo do link continua editável no próprio bloco.
- A aba Downloads mostra ação **Excluir arquivo** para cada item. A exclusão pede confirmação e alerta que links/blocos que referenciam o arquivo deixarão de abri-lo. A operação usa ACL `modify`, CSRF, tipo `download`, validação de nome/extensão, diretório fixo sob `catalog/` e rejeição de symlinks.
- A exclusão CMS Pages agora contempla imagens, vídeos e downloads; não remove mídias do Blog nem altera seu fluxo independente.
- Cache-bust dos assets: `threejs-16-download-media-actions`.

## Verificações

- `node --check` nos arquivos JavaScript afetados passou.
- Smoke da biblioteca/URLs e smoke do helper passaram.
- Bateria estática: **192 verificações aprovadas**.
- O ZIP completo passou na checagem de integridade. PHP CLI, navegador real e staging não estão disponíveis; não alego lint PHP nem teste runtime.
