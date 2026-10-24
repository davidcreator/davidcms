/* F9-16 safe form binding: serialize only to the same POST form; no autosave/network. */
(function (global, document) {
  'use strict';
  var pendingMediaEditor = null;
  document.addEventListener('blog-block-media-open', function (event) {
    pendingMediaEditor = event.detail && event.detail.editor || null;
    var trigger = document.querySelector('[data-bs-target="#blogMediaModal"]');
    if (trigger) trigger.click();
  });
  document.addEventListener('blog-block-media-selected', function (event) {
    if (pendingMediaEditor && event.detail && typeof event.detail.path === 'string') pendingMediaEditor.setSelectedAttribute('src', event.detail.path);
    pendingMediaEditor = null;
  });
  document.addEventListener('hidden.bs.modal', function (event) { if (event.target && event.target.id === 'blogMediaModal') pendingMediaEditor = null; });
  function init() {
    if (!global.ReamurBlogBlockEditor) return;
    document.querySelectorAll('[data-blog-block-editor]').forEach(function (root) {
      var hidden = document.getElementById(root.dataset.hiddenInput || '');
      var ready = document.getElementById(root.dataset.readyInput || '');
      var form = root.closest('form');
      try {
        var initial = JSON.parse(root.dataset.document || '');
        root.setAttribute('aria-label', 'Editor visual de blocos do Blog');
        var editor = global.ReamurBlogBlockEditor.mount(root, {
          document: initial,
          connectedSave: true,
          onChange: function (doc, validation) {
            if (hidden) hidden.value = validation.valid && doc ? JSON.stringify(doc) : '';
            root.dataset.valid = validation.valid ? 'true' : 'false';
          }
        });
        if (hidden && editor.getDocument()) {
          hidden.value = editor.serialize();
          if (ready) ready.value = '1';
          root.dataset.valid = 'true';
        } else {
          if (hidden) hidden.value = '';
          if (ready) ready.value = '';
          root.dataset.valid = 'false';
        }
        if (form) form.addEventListener('submit', function (event) {
          if (root.dataset.valid === 'false' || !hidden || !hidden.value) {
            event.preventDefault();
            root.focus();
            root.scrollIntoView({ behavior: 'smooth', block: 'center' });
          }
        });
      } catch (error) {
        root.replaceChildren();
        root.setAttribute('role', 'alert');
        root.textContent = 'Documento indisponível para edição. Nenhum conteúdo foi alterado; revise o conteúdo legado.';
        if (hidden) hidden.value = '';
        if (ready) ready.value = '';
      }
    });
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init, { once: true });
  else init();
})(window, document);
