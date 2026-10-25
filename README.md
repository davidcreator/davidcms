# BDREditor — atalhos e foco de edição

Pacote cumulativo do editor de conteúdo exclusivo ReamurCMS. Acrescenta uma melhoria de edição por teclado sobre `BDREditor-Audio`, mantendo os incrementos anteriores de `core/file` e `core/audio`. Gutenberg segue apenas como referência conceitual; sem dependência, código ou assets Gutenberg/WordPress.

## Este incremento

- Centraliza a classificação dos atalhos existentes: `Ctrl/⌘+B/I/U`, `Ctrl/⌘+K`, `Ctrl/⌘+Z`, `Ctrl/⌘+Shift+Z` ou `Ctrl+Y`, `Alt+↑/↓` e `/` no começo de um trecho.
- Escopa undo/redo, marcas e movimento ao contexto adequado. `Ctrl/⌘+Z` em input/textarea/select volta a ser o undo nativo do campo; marcas não interceptam atalhos fora do texto; movimento não captura setas de campos.
- Adiciona uma ajuda de atalhos recolhível com `<details>/<summary>`, localizada em PT-BR, EN-US e ES-EU, com foco visível e sem HTML fornecido pelo usuário.
- Não usa `execCommand`, não muda o JSON, não altera o fluxo de salvar, não adiciona endpoints, schema, migration, autosave ou persistência.
- Mantém os verificadores/fixtures cumulativos dos blocos de áudio e arquivo/download.

## Matriz breve de gap e aceite

| Área | Estado na base conferida | Este incremento |
|---|---|---|
| Atalhos de marks/link | Já existentes, vinculados a conteúdo editável | Centraliza e restringe por contexto |
| Undo/redo | Já existentes no editor | Preserva undo nativo de campos de formulário |
| Mover bloco por teclado | `Alt+↑/↓` já anunciado | Não captura teclas de campos/selects |
| Inserter `/` | Já existente no início/depois de espaço | Preservado, sem mudar inserção |
| Descoberta dos atalhos | Faltava uma ajuda visível/localizada | Disclosure acessível na barra superior |

## Verificações executadas

```sh
node --check dev/admin/view/js/blog-block-editor.js
node --check dev/admin/view/js/blog-file-media.js
node --check dev/admin/view/js/blog-audio-media.js
node --check tools/e2e/e2e_bdre_keyboard_static.cjs
node --check tools/e2e/e2e_bdre_audio_static.cjs
node --check tools/e2e/e2e_bdre_file_static.cjs
node tools/e2e/e2e_bdre_keyboard_static.cjs
node tools/e2e/e2e_bdre_audio_static.cjs
node tools/e2e/e2e_bdre_file_static.cjs
python3 -m json.tool tests/fixtures/keyboard_shortcuts.json >/dev/null
python3 -m json.tool tests/fixtures/core_audio.json >/dev/null
python3 -m json.tool tests/fixtures/bdre_file.json >/dev/null
```

Resultado observado: **25 PASS** no checker de atalhos, **49 PASS** no checker de áudio e **48 PASS** no checker de arquivo/download. As fixtures são sintéticas. O checker executa a classificação JS em VM sem DOM/browser e inspeciona contratos/strings; não comprova integração real, acessibilidade com leitor de tela ou comportamento em todos os navegadores.

## Base e limites operacionais

A cópia-base foi comparada por arquivos pontuais com `main` no HEAD `8216b0c2f7ed56edd73e4c7d2ffa14bbee0f77d7`. O pacote não contém o projeto inteiro e não publicou nem alterou o GitHub. Nenhuma numeração oficial foi atribuída.

Não foram executados PHP/runtime, navegador, aplicação, banco, staging, deploy ou migrations. Staging permanece pausado; sem autorização de produção ou escrita GitHub.

## Arquivos de apoio

- Escopo/decisões: `docs/BDREditor-Keyboard-Editing-Scope.md`
- Matriz/contrato: `docs/BDREditor-Keyboard-Editing.md`
- Fixture: `tests/fixtures/keyboard_shortcuts.json`
- Checker novo: `tools/e2e/e2e_bdre_keyboard_static.cjs`
- Checkers cumulativos: `tools/e2e/e2e_bdre_audio_static.cjs` e `tools/e2e/e2e_bdre_file_static.cjs`
- Manifesto SHA-256: `SHA256SUMS`
