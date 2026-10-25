# Edição por teclado — matriz, contrato e limites

## Matriz de gap funcional

| Recurso | Antes deste incremento | Critério entregue |
|---|---|---|
| Negrito/itálico/sublinhado | Atalhos Ctrl/Meta já chamavam a toolbar de marks | Mantidos somente quando o foco está em conteúdo editável |
| Link | Ctrl/Meta+K já abria o campo de link | Mantido somente em conteúdo editável; não intercepta campo já focado |
| Undo/redo do documento | Ctrl/Meta+Z, Ctrl/Meta+Shift+Z e Ctrl+Y | Classificador comum; não intercepta undo/redo de input, textarea ou select |
| Mover bloco | Alt+↑/↓ anunciado na UI | Mantido dentro de bloco, sem sequestrar setas em controles de formulário |
| Inserter por `/` | Já ativava no início/limite de palavra | Preservado sem alterar seleção ou regras de inserção |
| Ajuda visível | Atalhos eram pouco descobríveis | Painel nativo `<details>/<summary>`, strings localizadas e foco visível |

## Contrato de interação

`classifyKeyboardShortcut(event, context)` retorna somente um comando interno allowlisted (`bold`, `italic`, `underline`, `link`, `undo`, `redo`, `move-up`, `move-down`) ou `null`. Contexto vem do DOM do editor: conteúdo editável, campo de formulário e bloco selecionado. A UI executa comandos existentes; nenhum comando textual arbitrário é avaliado.

Atalhos mantidos:

- Ctrl/⌘+B: negrito;
- Ctrl/⌘+I: itálico;
- Ctrl/⌘+U: sublinhado;
- Ctrl/⌘+K: abrir campo de link quando o foco está no texto;
- Ctrl/⌘+Z: desfazer; Ctrl/⌘+Shift+Z ou Ctrl+Y: refazer;
- Alt+↑/↓: mover o bloco focado;
- `/`: abrir inserter no começo de texto ou após whitespace.

Inputs, textareas, selects e busca do inserter não recebem comandos de formatação, undo/redo ou movimento. Ctrl+Alt+Z fica fora do classificador para não tomar atalhos do navegador. Teclas nativas de edição são preservadas em campos de formulário.

## UI e localização

A ajuda é uma disclosure HTML nativa criada com elementos DOM e strings textuais, não com HTML interpretado. A descrição e o rótulo existem nas três línguas suportadas. CSS novo limita-se ao layout do disclosure e indicador de foco.

## Compatibilidade e escopo

- Não muda o contrato JSON v2 nem os blocos.
- Não executa `execCommand`; mantém runs v2 e renderização existentes.
- Não altera autosave, formulário, ACL, banco, schema, endpoints, migrations ou snapshots.
- Sem colar HTML; o tratamento existente de colagem como texto puro permanece.
- Não adiciona seleção múltipla, operações em lote, atalhos de duplicar/excluir nem confirmação nova; isso requer incremento próprio e critérios específicos.

## Verificação

A fixture cobre comandos válidos, Ctrl/Meta, inputs, selects, contexto ausente e modificadores combinados. O checker usa VM Node sem DOM/browser e verifica a presença das strings/controlos; não substitui teste com navegador real, leitor de tela ou runtime PHP. Staging continua pausado.
