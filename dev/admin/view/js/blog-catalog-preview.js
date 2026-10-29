/* Private, non-persistent Catalog preview for the Blog block editor. */
(function (document) {
  'use strict';

  function addHidden(form, name, value) {
    var input = document.createElement('input');
    input.type = 'hidden';
    input.name = name;
    input.value = value;
    form.appendChild(input);
  }

  document.addEventListener('click', function (event) {
    var button = event.target && event.target.closest ? event.target.closest('[data-blog-catalog-preview]') : null;
    if (!button) return;
    event.preventDefault();

    var status = button.parentElement && button.parentElement.querySelector('[data-blog-preview-status]');
    function fail() { if (status) status.textContent = button.dataset.previewError || ''; }

    var root = document.getElementById(button.dataset.editorId || '');
    var editor = root && root.__blogBlockEditorInstance;
    var ready = document.getElementById(button.dataset.readyId || '');
    var parentForm = document.querySelector('form[data-blog-post-form]');
    var csrf = parentForm && parentForm.querySelector('input[name="csrf_token"]');
    var title = document.getElementById(button.dataset.titleId || '');
    var metaTitle = document.getElementById(button.dataset.metaTitleId || '');
    var metaDescription = document.getElementById(button.dataset.metaDescriptionId || '');
    var image = document.getElementById('input-image');
    var published = document.getElementById('input-date-published');

    if (!editor || !root || root.dataset.valid !== 'true' || !ready || ready.value !== '1' || !csrf || !button.dataset.previewUrl) {
      fail(); return;
    }

    var json;
    try { json = editor.serialize(); } catch (_) { fail(); return; }
    if (typeof json !== 'string' || json.length < 2 || new Blob([json]).size > 4194304) { fail(); return; }
    if (status) status.textContent = '';

    var form = document.createElement('form');
    form.method = 'post';
    form.action = button.dataset.previewUrl;
    form.target = '_blank';
    form.hidden = true;
    addHidden(form, 'csrf_token', csrf.value);
    addHidden(form, 'preview_document', json);
    addHidden(form, 'preview_editor_ready', ready.value);
    addHidden(form, 'preview_post_id', button.dataset.postId || '0');
    addHidden(form, 'preview_language_id', button.dataset.languageId || '0');
    addHidden(form, 'preview_language', button.dataset.language || 'en-us');
    addHidden(form, 'preview_title', title ? title.value : '');
    addHidden(form, 'preview_meta_title', metaTitle ? metaTitle.value : '');
    addHidden(form, 'preview_meta_description', metaDescription ? metaDescription.value : '');
    addHidden(form, 'preview_image', image ? image.value : '');
    addHidden(form, 'preview_date_published', published ? published.value : '');
    document.body.appendChild(form);
    form.submit();
    form.remove();
  });
})(document);
