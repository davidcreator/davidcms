/* CMS Pages-only POST preview launcher. */
(function (document) {
  'use strict';
  function hidden(form, name, value) {
    var input = document.createElement('input'); input.type = 'hidden'; input.name = name; input.value = value; form.appendChild(input);
  }
  document.addEventListener('click', function (event) {
    var button = event.target && event.target.closest ? event.target.closest('[data-cms-page-preview]') : null;
    if (!button) return;
    event.preventDefault();
    var status = button.parentElement && button.parentElement.querySelector('[data-page-preview-status]');
    function fail() { if (status) status.textContent = button.dataset.previewError || ''; }
    var editorRoot = document.getElementById(button.dataset.editorId || '');
    var titleInput = document.getElementById(button.dataset.titleId || '');
    var csrfInput = document.querySelector('form[data-cms-page-form] input[name="csrf_token"]');
    var editor = editorRoot && editorRoot.__blogBlockEditorInstance;
    if (!editor || !editorRoot || editorRoot.dataset.valid !== 'true' || !csrfInput || !button.dataset.previewUrl) { fail(); return; }
    var json;
    try { json = editor.serialize(); } catch (_) { fail(); return; }
    if (typeof json !== 'string' || json.length < 2) { fail(); return; }
    if (status) status.textContent = '';
    var form = document.createElement('form');
    form.method = 'post'; form.action = button.dataset.previewUrl || ''; form.target = '_blank'; form.hidden = true;
    hidden(form, 'csrf_token', csrfInput.value);
    hidden(form, 'preview_document', json);
    hidden(form, 'preview_title', titleInput ? titleInput.value : '');
    hidden(form, 'preview_language', button.dataset.language || 'en-us');
    document.body.appendChild(form);
    form.submit();
    form.remove();
  });
})(document);
