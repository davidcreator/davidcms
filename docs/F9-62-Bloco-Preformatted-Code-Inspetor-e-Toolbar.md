# F9-62 — Bloco Preformatted Code: inspetor e toolbar

## Alterações

- O inspetor de `core/code` agora apresenta uma área de texto literal para editar o conteúdo pré-formatado. O editor do inspetor e o textarea do canvas compartilham `updateCodeNode()` e permanecem sincronizados sem recriar os campos nem mover o cursor.
- Mantidos o limite existente de conteúdo, a validação de tamanho e o tratamento inerte do bloco: o código continua sendo texto e **não é executado**.
- A aba **Personalizar** identifica os controles de estilo de código; toolbar contextual e ações do inspetor recebem rótulos visíveis, usando a regra F9-57 de texto abaixo do ícone. Ícones reconhecíveis mantêm tooltip.
- Conteúdo, estilo e ações do inspetor têm rótulos localizados em português, inglês e espanhol.

## Validação

- `node --check` passou.
- Bateria estática completa: **145 verificações aprovadas**, cobrindo os 21 tipos principais e 4 tipos aninhados, além de verificações específicas para Preformatted Code.
- Teste de URL do hotfix F9-61: 9 casos aprovados; análise profunda do CSS sem erros; ZIP validado.
- Não foi possível fazer teste de interação em navegador/PHP neste ambiente. Nenhum controller, banco, migração, rota, GitHub ou deploy foi alterado.

## Arquivos completos no pacote

- `dev/admin/view/js/blog-block-editor.js`
- `dev/admin/view/css/blog-block-editor.css`
- `tools/f9-57-static-battery.py`
- `tools/f9-61-preview-url-smoke.js`
- Esta documentação e referências F9-52 a F9-61.
