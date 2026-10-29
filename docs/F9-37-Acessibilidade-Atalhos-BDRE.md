# F9-37 — Acessibilidade e atalhos do BDREditor

## Auditoria focalizada

A leitura estática do editor encontrou que o reconhecimento de atalhos, navegação por teclado das abas e da estrutura, regiões de status e estilos `:focus-visible` já existiam. Porém, a lista de atalhos já traduzida e enviada pelos controladores não era apresentada na interface. Também não havia atalho Ctrl/⌘+F para abrir a busca criada no F9-36. As traduções de ajuda de teclado estavam presentes no fluxo Blog, mas ausentes nos arquivos de idioma Page.

## Entregue

- Adicionado `<details>/<summary>` nativo, navegável por teclado, para revelar a ajuda de atalhos na interface; mantém a ajuda recolhida até solicitação do usuário.
- Ctrl+F/Cmd+F abre e foca Localizar quando o foco está no conteúdo/bloco do editor, sem interceptar o atalho enquanto o foco está em input, textarea ou select.
- `aria-keyshortcuts` nos controles correspondentes para localizar, desfazer/refazer, negrito/itálico/sublinhado e mover bloco para cima/baixo. A execução continua pelo tratamento de teclado existente.
- Ajuda de atalhos atualizada para mencionar Localizar, com traduções PT-BR, EN-US e ES-EU nos fluxos Pages e Blog.
- O salvamento explícito e a separação entre Page e Blog permanecem inalterados.

## Verificações e limites

`node --check` e teste unitário isolado da função pública `classifyKeyboardShortcut` executados; verificações estáticas confirmam traduções Page/Blog, `aria-keyshortcuts`, a divulgação nativa e estilos de foco. Não houve teste com leitor de tela ou navegador real, PHP CLI não está disponível e não foram executados runtime PHP, banco, staging ou E2E. A verificação automatizada não substitui validação manual com tecnologias assistivas.
