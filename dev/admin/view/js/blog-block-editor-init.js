/* F9-16 safe form binding: serialize only to the same POST form; no autosave/network. */
(function (global, document) {
  'use strict';
  var pendingMediaEditor = null;

  function unsavedState(form) {
    if (!form.__blogBlockUnsavedState) {
      var state = { dirty: !!form.querySelector('.alert-danger'), bypass: false };
      form.__blogBlockUnsavedState = state;
      form.addEventListener('input', function (event) {
        if (event.target && !event.target.matches('[name^="blog_blocks["],[name^="blog_editor_ready["],[name="csrf_token"]')) state.dirty = true;
      });
      form.addEventListener('change', function (event) {
        if (event.target && !event.target.matches('[name^="blog_blocks["],[name^="blog_editor_ready["],[name="csrf_token"]')) state.dirty = true;
      });
      global.addEventListener('beforeunload', function (event) {
        if (!state.dirty || state.bypass) return;
        event.preventDefault();
        event.returnValue = '';
      });
    }
    return form.__blogBlockUnsavedState;
  }

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
      var changeState = form ? unsavedState(form) : null;
      try {
        var initial = JSON.parse(root.dataset.document || '');
        var editorStrings = JSON.parse(root.dataset.editorStrings || '{}');
        root.setAttribute('aria-label', editorStrings.editorLabel || 'Blog block editor');
        var editor = global.ReamurBlogBlockEditor.mount(root, {
          document: initial,
          strings: editorStrings,
          connectedSave: true,
          onChange: function (doc, validation) {
            if (hidden && (!validation.valid || !doc)) hidden.value = '';
            root.dataset.valid = validation.valid ? 'true' : 'false';
            root.setAttribute('aria-invalid', validation.valid ? 'false' : 'true');
            root.dataset.dirty = 'true';
            if (changeState) changeState.dirty = true;
          }
        });
        root.__blogBlockEditorInstance = editor;
        if (hidden && editor.getDocument()) {
          hidden.value = editor.serialize();
          if (ready) ready.value = '1';
          root.dataset.valid = 'true';
          root.setAttribute('aria-invalid', 'false');
          root.dataset.dirty = 'false';
        } else {
          if (hidden) hidden.value = '';
          if (ready) ready.value = '';
          root.dataset.valid = 'false';
        }
        if (form) form.addEventListener('submit', function (event) {
          var hosts = Array.prototype.slice.call(form.querySelectorAll('[data-blog-block-editor]'));
          var allEditorsValid = hosts.every(function (host) { return host.dataset.valid === 'true'; });
          if (allEditorsValid && !event.__blogBlockSerialized) {
            hosts.forEach(function (host) {
              var instance = host.__blogBlockEditorInstance;
              var target = document.getElementById(host.dataset.hiddenInput || '');
              try {
                if (!instance || !target) throw new Error('editor_submission_unavailable');
                target.value = instance.serialize();
                host.dataset.valid = 'true';
                host.setAttribute('aria-invalid', 'false');
              } catch (error) {
                host.dataset.valid = 'false';
                host.setAttribute('aria-invalid', 'true');
                var message = 'O documento excede o limite ou contém dados inválidos.';
                try {
                  var strings = JSON.parse(host.dataset.editorStrings || '{}');
                  message = error && error.message === 'document_too_large' ? (strings.documentTooLarge || message) : (strings.initialInvalid || message);
                } catch (_) {}
                var live = host.querySelector('[data-editor-status]');
                if (live) live.textContent = message;
              }
            });
            allEditorsValid = hosts.every(function (host) { return host.dataset.valid === 'true'; });
            event.__blogBlockSerialized = true;
          }
          if (root.dataset.valid === 'false' || !hidden || !hidden.value || !allEditorsValid) {
            event.preventDefault();
            if (changeState) changeState.bypass = false;
            root.focus();
            root.scrollIntoView({ behavior: 'smooth', block: 'center' });
            return;
          }
          if (changeState) {
            changeState.bypass = true;
            changeState.dirty = false;
          }
        });
      } catch (error) {
        root.replaceChildren();
        root.setAttribute('role', 'alert');
        var invalidMessage = 'Documento indisponível para edição; a fonte original foi preservada.';
        try { var failureStrings = JSON.parse(root.dataset.editorStrings || '{}'); if (failureStrings.initialInvalid) invalidMessage = failureStrings.initialInvalid; } catch (_) {}
        root.textContent = invalidMessage;
        if (hidden) hidden.value = '';
        if (ready) ready.value = '';
      }
    });
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init, { once: true });
  else init();
})(window, document);
