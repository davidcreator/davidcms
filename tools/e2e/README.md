# Checker estático E2E — `core/audio`

Do diretório raiz do pacote:

```sh
node --check dev/admin/view/js/blog-block-editor.js
node --check dev/admin/view/js/blog-audio-media.js
node --check tools/e2e/e2e_bdre_audio_static.cjs
node tools/e2e/e2e_bdre_audio_static.cjs
python3 -m json.tool tests/fixtures/core_audio.json >/dev/null
```

O checker carrega o módulo do editor em uma VM Node sem DOM/browser para validar documentos sintéticos. Faz inspeção textual dos contratos no schema PHP, renderer, controllers, modelo, picker, modal, permissões e traduções. Não inicializa aplicação PHP, banco ou servidor; não testa reprodução física do áudio.
