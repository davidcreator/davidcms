# Checkers estáticos cumulativos — BDREditor

A partir da raiz do pacote:

```sh
node --check dev/admin/view/js/blog-block-editor.js
node --check tools/e2e/e2e_bdre_block_copy_static.cjs
node tools/e2e/e2e_bdre_block_copy_static.cjs
node tools/e2e/e2e_bdre_keyboard_static.cjs
node tools/e2e/e2e_bdre_audio_static.cjs
node tools/e2e/e2e_bdre_file_static.cjs
python3 -m json.tool tests/fixtures/block_copy.json >/dev/null
python3 -m json.tool tests/fixtures/keyboard_shortcuts.json >/dev/null
python3 -m json.tool tests/fixtures/core_audio.json >/dev/null
python3 -m json.tool tests/fixtures/bdre_file.json >/dev/null
```

O checker novo executa somente o helper de clonagem JS em VM Node sem DOM/browser e inspeciona estaticamente o fluxo do editor. As suites anteriores continuam cobrindo atalhos, áudio e arquivo/download. Nenhum checker inicia runtime PHP, aplicação, banco ou servidor.
