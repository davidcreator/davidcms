# Checkers estáticos cumulativos — BDREditor

A partir da raiz do pacote:

```sh
node --check dev/admin/view/js/blog-block-editor.js
node --check tools/e2e/e2e_bdre_navigation_static.cjs
node tools/e2e/e2e_bdre_navigation_static.cjs
node tools/e2e/e2e_bdre_block_copy_static.cjs
node tools/e2e/e2e_bdre_keyboard_static.cjs
node tools/e2e/e2e_bdre_audio_static.cjs
node tools/e2e/e2e_bdre_file_static.cjs
python3 -m json.tool tests/fixtures/navigation_tree.json >/dev/null
```

O checker novo roda o helper JS de caminho em VM Node sem DOM/browser e inspeciona UI/foco/strings. Os demais checkers são cumulativos. Nenhum inicia PHP, aplicação, banco ou servidor.
