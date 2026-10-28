/* F9-16 form binding with localized unsaved-state feedback; same POST only, no autosave/network. */
(function (global, document) {
  'use strict';
  var pendingMediaEditor = null;

  function unsavedState(form, strings) {
    if (!form.__blogBlockUnsavedState) {
      var status = document.createElement('p');
      status.className = 'rb-save-status';
      status.setAttribute('role', 'status');
      status.setAttribute('aria-live', 'polite');
      status.setAttribute('aria-atomic', 'true');
      status.dataset.saveStatus = '1';
      form.insertBefore(status, form.firstChild);
      var state = { dirty: !!form.querySelector('.alert-danger'), bypass: false, status: status, strings: strings || {}, invalidRecoveryScheduled: false };
      state.render = function (mode) {
        var key = mode === 'saving' ? 'saveStateSaving' : (state.dirty ? 'saveStateUnsaved' : 'saveStateSaved');
        status.textContent = state.strings[key] || (mode === 'saving' ? 'Saving…' : (state.dirty ? 'Changes not saved' : 'All changes saved'));
        status.dataset.state = mode || (state.dirty ? 'unsaved' : 'saved');
        Array.prototype.forEach.call(form.querySelectorAll('[data-page-context-save-state]'), function (mirror) {
          mirror.textContent = status.textContent;
          mirror.dataset.state = status.dataset.state;
        });
      };
      state.markDirty = function () { state.dirty = true; state.render(); };
      state.render();
      form.__blogBlockUnsavedState = state;
      form.addEventListener('input', function (event) {
        if (event.target && !event.target.matches('[name^="blog_blocks["],[name^="blog_editor_ready["],[name="csrf_token"]')) state.markDirty();
      });
      form.addEventListener('change', function (event) {
        if (event.target && !event.target.matches('[name^="blog_blocks["],[name^="blog_editor_ready["],[name="csrf_token"]')) state.markDirty();
      });
      if (form.matches('[data-cms-page-form]')) form.addEventListener('invalid', function (event) {
        var field = event.target;
        if (!field || state.invalidRecoveryScheduled) return;
        var fullscreenRoot = form.querySelector('.rb-editor.is-fullscreen');
        if (!fullscreenRoot) return;
        state.invalidRecoveryScheduled = true;
        var instance = fullscreenRoot.__blogBlockEditorInstance;
        if (instance && typeof instance.setFullscreen === 'function') instance.setFullscreen(false);
        var pane = field.closest && field.closest('.tab-pane');
        var tab = pane && pane.getAttribute('aria-labelledby') ? document.getElementById(pane.getAttribute('aria-labelledby')) : null;
        if (tab && !pane.classList.contains('active')) tab.click();
        global.setTimeout(function () {
          state.invalidRecoveryScheduled = false;
          if (!field.isConnected) return;
          field.focus();
          if (field.scrollIntoView) field.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }, 0);
      }, true);
      global.addEventListener('beforeunload', function (event) {
        if (!state.dirty || state.bypass) return;
        event.preventDefault();
        event.returnValue = '';
      });
      global.addEventListener('pageshow', function () {
        state.bypass = false;
        state.render();
      });
    } else if (strings) {
      form.__blogBlockUnsavedState.strings = strings;
      form.__blogBlockUnsavedState.render();
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
      var editorStrings = {};
      try { editorStrings = JSON.parse(root.dataset.editorStrings || '{}'); } catch (_) {}
      var pageContext = null;
      try {
        var contextLabels = JSON.parse(root.dataset.pageContext || '');
        pageContext = Object.assign({}, contextLabels, {
          pageTitle: root.dataset.pageTitle || '',
          pageTitleInput: document.getElementById(root.dataset.pageTitleInput || ''),
          languageName: root.dataset.pageLanguage || '',
          languageCode: root.dataset.pageLanguageCode || ''
        });
      } catch (_) {}
      var changeState = form ? unsavedState(form, editorStrings) : null;
      if (pageContext) pageContext.saveStatus = changeState ? changeState.status : null;
      try {
        var initial = JSON.parse(root.dataset.document || '');
        root.setAttribute('aria-label', editorStrings.editorLabel || 'Blog block editor');
        var editor = global.ReamurBlogBlockEditor.mount(root, {
          document: initial,
          strings: editorStrings,
          workspaceContext: pageContext,
          connectedSave: true,
          mediaPickers: root.dataset.mediaPickers !== 'false',
          pageImagePicker: !!(pageContext && pageContext.canSave),
          filePicker: root.dataset.filePicker === 'false' ? false : (root.dataset.mediaPickers !== 'false'),
          audioPicker: root.dataset.audioPicker === 'true' || root.dataset.mediaPickers !== 'false',
          videoPicker: root.dataset.videoPicker === 'true' || root.dataset.mediaPickers !== 'false',
          onChange: function (doc, validation) {
            if (hidden && (!validation.valid || !doc)) hidden.value = '';
            root.dataset.valid = validation.valid ? 'true' : 'false';
            root.setAttribute('aria-invalid', validation.valid ? 'false' : 'true');
            root.dataset.dirty = 'true';
            if (changeState) changeState.markDirty();
          }
        });
        root.__blogBlockEditorInstance = editor;
        if (hidden && editor.getDocument()) {
          hidden.value = (root.dataset.storagePrefix || '') + editor.serialize();
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
                target.value = (host.dataset.storagePrefix || '') + instance.serialize();
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
          if (!allEditorsValid || !hidden || !hidden.value || hosts.some(function (host) { return host.dataset.valid === 'false'; })) {
            event.preventDefault();
            if (changeState) { changeState.bypass = false; changeState.markDirty(); }
            root.focus();
            root.scrollIntoView({ behavior: 'smooth', block: 'center' });
            return;
          }
          if (changeState) {
            changeState.bypass = true;
            changeState.status.textContent = changeState.strings.saveStateSaving || 'Saving…';
            changeState.status.dataset.state = 'saving';
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
