/* F13-E — apenas comodidade: le um .json local para a textarea.
   Sem upload, fetch, eval, innerHTML ou document.write. Sem JS, basta colar. */
(function (document, window) {
  'use strict';
  var input = document.getElementById('theme-package-file');
  var area = document.getElementById('theme-package-json');
  if (!input || !area || !window.FileReader) return;
  input.addEventListener('change', function () {
    var file = input.files && input.files[0];
    if (!file || file.size > 524288) { area.value = ''; return; }
    var reader = new window.FileReader();
    reader.onload = function () { area.value = String(reader.result || ''); };
    reader.readAsText(file);
  });
})(document, window);
