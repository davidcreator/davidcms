# BDREditor — copiar e colar blocos no documento

Incremento cumulativo do BDREditor, editor de conteúdo exclusivo do ReamurCMS. Acrescenta copiar/colar **dentro do mesmo documento e da mesma instância do editor**, mantendo os blocos `core/file` e `core/audio` e os incrementos de edição por teclado. Gutenberg segue apenas referência conceitual; sem dependência, código ou assets Gutenberg/WordPress.

## O que entra

- Botão **Copiar bloco** em cada toolbar de bloco e **Colar bloco** na toolbar superior.
- Buffer privado em memória da instância; não é gravado no documento, no servidor ou no clipboard do sistema.
- Colar cria IDs novos recursivamente para o bloco e seus descendentes, preservando atributos, texto/runs e referências já presentes.
- O destino é depois do bloco selecionado; sem seleção válida, acrescenta ao fim do documento. A inserção dentro de estruturas deve satisfazer o schema completo.
- Pré-validação do documento v2, unicidade de IDs e limite de 4 MiB antes da alteração. Uma única operação de commit/histórico por colagem; undo desfaz a cópia inteira.
- Mensagens de cópia/colagem via região `aria-live`, botões rotulados e strings PT-BR, EN-US e ES-EU.
- Colagem existente do clipboard do usuário continua limitada a `text/plain`; o editor não passa a interceptar Ctrl+C nem usa `navigator.clipboard` ou `execCommand`.

## Limites escolhidos

- Um bloco por vez; não há seleção múltipla nem operações em lote.
- Apenas dentro da instância atual. Não exporta/importa blocos entre posts/documentos, abas ou sessões.
- Sem persistência, endpoint, schema, migration, autosave ou dado adicional no JSON.
- Blocos estruturais que não cabem no contexto (por exemplo, coluna extra em layout fixo) falham sem alterar o documento e anunciam a razão geral de indisponibilidade.

## Base e verificações

Fontes pontuais comparados com `main` em `a9333fb367e3d82e2626e3e2d5a4db56fbb07929`, commit que inclui o incremento Keyboard-Editing. Sem clone integral, sem GitHub write.

Resultado executado: **29 PASS** no checker copy/paste, **25 PASS** no checker de teclado, **49 PASS** no checker de áudio e **48 PASS** no checker de arquivo/download. `node --check` nos scripts/JS e JSON parse das quatro fixtures também passaram. As fixtures são sintéticas; os checkers executam helpers JS em VM sem DOM/browser e fazem inspeção de fonte. Isso não equivale a runtime real.

Não executar nesta fase PHP/runtime, navegador real, aplicação, banco, staging, deploy, migrations ou testes destrutivos. Staging permanece pausado; nenhuma numeração oficial de tarefa foi atribuída.

## Arquivos de apoio

- Contrato: `docs/BDREditor-Block-Copy-Paste.md`
- Escopo: `docs/BDREditor-Block-Copy-Paste-Scope.md`
- Fixture: `tests/fixtures/block_copy.json`
- Checker novo: `tools/e2e/e2e_bdre_block_copy_static.cjs`
- Checkers cumulativos: `tools/e2e/e2e_bdre_keyboard_static.cjs`, `tools/e2e/e2e_bdre_audio_static.cjs`, `tools/e2e/e2e_bdre_file_static.cjs`
- Manifesto SHA-256: `SHA256SUMS`
