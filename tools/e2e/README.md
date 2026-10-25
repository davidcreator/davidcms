# Checkers estáticos cumulativos — BDREditor

A partir da raiz do pacote:

```sh
node --check dev/admin/view/js/blog-block-editor.js
node --check tools/e2e/e2e_bdre_keyboard_static.cjs
node tools/e2e/e2e_bdre_keyboard_static.cjs
node tools/e2e/e2e_bdre_audio_static.cjs
node tools/e2e/e2e_bdre_file_static.cjs
python3 -m json.tool tests/fixtures/keyboard_shortcuts.json >/dev/null
```

O checker de atalhos carrega o classificador JS em VM Node sem DOM/browser e valida a fixture sintética, além de verificar UI/strings/escopo por inspeção textual. Os checkers de áudio e arquivo/download permanecem no pacote para regressão estática cumulativa. Nenhum deles inicializa PHP, aplicação, banco ou servidor.
