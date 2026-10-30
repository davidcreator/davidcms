# F9-53 — Bloco Parágrafo: conteúdo, personalização e ações claras

> **Complementado pela F9-54:** o mesmo padrão de inspector e ações foi estendido ao bloco Heading; veja [F9-54](F9-54-Bloco-Heading-Inspetor-e-Acoes.md).

## Alterações

- A aba **Conteúdo** do inspetor agora inclui uma área de texto rich-text ligada ao mesmo conteúdo do canvas; as marcas inline existentes são mantidas ao editar pelos dois pontos.
- O alinhamento do parágrafo fica disponível em Conteúdo e permanece sincronizado com os botões de alinhamento da faixa. A alteração limpa uma personalização antiga de alinhamento quando necessário, evitando que o estilo anterior esconda o novo valor.
- A aba **Personalizar** identifica o grupo de estilo do parágrafo, oferece os controles visuais seguros já suportados e não repete o alinhamento estrutural do bloco.
- Removi o rótulo duplicado do tipo de bloco no inspetor. As ações de mover, duplicar, copiar, personalizar, transformar, remover e inserir agora exibem rótulos no toolbar contextual do parágrafo; Conteúdo também nomeia as ações de organização.
- Atalhos, aria-labels e tooltips existentes foram preservados; os rótulos explicativos são localizados em pt, en e es.

## Arquivos incluídos

- `dev/admin/view/js/blog-block-editor.js`
- `dev/admin/view/css/blog-block-editor.css`
- `docs/F9-53-Bloco-Paragrafo-Inspector-e-Acoes.md`
- `docs/F9-52-BDREditor-Workspace-Fullscreen.md` (atualizado com referência à melhoria de parágrafo)

## Validação e limites

- `node --check` do JavaScript e revisão estática de atualização sincronizada do texto, controles de alinhamento, rótulos e estados de ações.
- Balanceamento de chaves CSS e teste CRC do pacote.
- Não foi possível validar com navegador real, PHP/Twig ou runtime. Não foram alterados controllers, banco, migrações, rotas, GitHub ou deploy.
