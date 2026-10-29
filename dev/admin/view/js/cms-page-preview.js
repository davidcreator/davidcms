/* CMS Pages-only POST preview launcher; no autosave, network persistence, or public draft URL. */
(function (document) {
  'use strict';
  function hidden(form, name, value) {
    var input = document.createElement('input'); input.type = 'hidden'; input.name = name; input.value = value; form.appendChild(input);
  }
  function publicCustomFields(form) {
    var out = {};
    if (!form) return out;
    Array.prototype.forEach.call(form.querySelectorAll('[name^="custom_field["]'), function (input) {
      if (input.disabled || !input.closest('[data-custom-field-public="1"]')) return;
      var match = /^custom_field\[(\d+)\]\[(\d+)\]$/.exec(input.name || '');
      if (!match) return;
      var fieldId = match[1], languageId = match[2];
      if (!out[fieldId]) out[fieldId] = {};
      out[fieldId][languageId] = String(input.value || '');
    });
    return out;
  }
  document.addEventListener('click', function (event) {
    var button = event.target && event.target.closest ? event.target.closest('[data-cms-page-preview]') : null;
    if (!button) return;
    event.preventDefault();
    var status = button.parentElement && button.parentElement.querySelector('[data-page-preview-status]');
    function fail() { if (status) status.textContent = button.dataset.previewError || ''; }
    var editorRoot = document.getElementById(button.dataset.editorId || '');
    var titleInput = document.getElementById(button.dataset.titleId || '');
    var metaTitle = document.getElementById(button.dataset.metaTitleId || '');
    var metaDescription = document.getElementById(button.dataset.metaDescriptionId || '');
    var csrfInput = document.querySelector('form[data-cms-page-form] input[name="csrf_token"]');
    var formElement = document.querySelector('form[data-cms-page-form]');
    var editor = editorRoot && editorRoot.__blogBlockEditorInstance;
    if (!editor || !editorRoot || editorRoot.dataset.valid !== 'true' || !csrfInput || !button.dataset.previewUrl) { fail(); return; }
    var json, fieldsJson, fieldsBytes;
    try {
      json = editor.serialize();
      fieldsJson = JSON.stringify(publicCustomFields(formElement));
      fieldsBytes = new Blob([fieldsJson], { type: 'application/json' }).size;
    } catch (_) { fail(); return; }
    if (typeof json !== 'string' || json.length < 2 || fieldsBytes > 262144) { fail(); return; }
    if (status) status.textContent = '';
    var form = document.createElement('form');
    form.method = 'post'; form.action = button.dataset.previewUrl || ''; form.target = '_blank'; form.hidden = true;
    hidden(form, 'csrf_token', csrfInput.value);
    hidden(form, 'preview_document', json);
    hidden(form, 'preview_title', titleInput ? titleInput.value : '');
    hidden(form, 'preview_meta_title', metaTitle ? metaTitle.value : '');
    hidden(form, 'preview_meta_description', metaDescription ? metaDescription.value : '');
    hidden(form, 'preview_language', button.dataset.language || 'en-us');
    hidden(form, 'preview_language_id', button.dataset.languageId || '1');
    hidden(form, 'preview_page_id', button.dataset.pageId || '0');
    var languageId = String(button.dataset.languageId || '1');
    var layoutList = formElement && formElement.querySelector('[data-page-layout-list][data-page-layout-language="' + languageId + '"]');
    var sections = [];
    if (layoutList) Array.prototype.forEach.call(layoutList.querySelectorAll('input[type=checkbox][name^="page_layout_sections["]:checked'), function (input) { sections.push(input.value); });
    hidden(form, 'preview_layout_sections', JSON.stringify(sections));
    hidden(form, 'preview_custom_fields', fieldsJson);
    document.body.appendChild(form);
    form.submit();
    form.remove();
  });
})(document);
