# BDREditor — escopo de edição por teclado

## Objetivo

Prosseguir após o bloco `core/audio` com o passo de edição por teclado do backlog: consolidar/documentar atalhos já existentes e corrigir sua fronteira de foco para não sobrepor a edição nativa de campos.

## Dentro do escopo

- Classificação pura dos atalhos de marca, link, undo/redo e movimento.
- Limitar comandos de marca/link ao conteúdo editável.
- Ignorar inputs, textareas, selects e busca do inserter para preservar edição nativa.
- Ajuda recolhível acessível e traduções PT-BR, EN-US e ES-EU.
- Fixture sintética, checker estático e documentação da matriz funcional.

## Fora do escopo

- Atalhos novos de duplicar/excluir, seleção múltipla, copiar/colar blocos, ações em lote ou novas regras de undo atômico.
- Mudanças em persistência, servidor, schema, migrations, formulários de salvamento ou conteúdo JSON.
- Site Editor, tema, header/footer, colaboração, Gutenberg ou extensões externas.
- Execução em navegador real, PHP/runtime, banco, staging, deploy ou produção nesta etapa.

## Decisões

- Nenhuma numeração oficial de tarefa foi inventada.
- Nenhum GitHub write foi realizado; HEAD consultado somente leitura: `8216b0c2f7ed56edd73e4c7d2ffa14bbee0f77d7`.
- A entrega é cumulativa para facilitar revisão dos incrementos `core/file` e `core/audio`.
