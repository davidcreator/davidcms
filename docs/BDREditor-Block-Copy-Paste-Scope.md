# Escopo — copiar/colar blocos locais

## Objetivo

Dar o próximo passo do backlog de edição com uma operação simples de cópia de bloco, baseada em clone validado e IDs novos, sem abrir transferência de conteúdo entre documentos.

## Dentro

- Um buffer local por instância BDREditor.
- Copiar um bloco por botão da toolbar.
- Colar depois do bloco selecionado (ou ao fim se não houver alvo) por botão da toolbar.
- Regenerar todos os IDs recursivos.
- Pré-validar o documento completo e o limite de bytes; registrar uma única alteração atômica de histórico.
- Mensagens acessíveis e traduções PT-BR, EN-US, ES-EU.
- Fixture sintética, checker estático, docs e pacote cumulativo.

## Fora

- Clipboard do sistema/navegador, Ctrl+C/Ctrl+V para blocos, HTML no clipboard.
- Colar entre posts, documentos, abas, usuários ou sessões.
- Seleção múltipla, operações em lote, transferência de padrões ou sincronização.
- Novos tipos, mudanças de schema, endpoints, banco, migrations, autosave, preview draft, staging ou deploy.
- Alteração das regras de conteúdo preexistentes, mutações de imagem/áudio/download ou exclusões.

## Guardrails

- O schema do editor continua autoridade para hierarquia e limites.
- Os dados copiados são derivados da árvore validada atual, não de HTML arbitrário.
- Falha de pré-validação não grava histórico nem substitui conteúdo.
- Não atribuir número oficial de tarefa; essa entrega é um incremento local de revisão.
