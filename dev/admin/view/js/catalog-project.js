/* Fase 8 / Tarefa 08 — tabs/validação nativa e capa/galeria MediaUpload com CSRF. */
(function initProjectForm() {
  'use strict';
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initProjectForm, { once: true });
    return;
  }
  var form = document.querySelector('[data-project-form]');
  if (!form || form.dataset.projectReady === '1') { return; }
  form.dataset.projectReady = '1';
  form.addEventListener('invalid', function (event) {
    var first = form.querySelector('input:invalid, select:invalid, textarea:invalid');
    if (first && first !== event.target) { return; }
    var pane = event.target.closest('.tab-pane');
    if (!pane || pane.classList.contains('active')) { return; }
    var tab = form.querySelector('[data-bs-target="#' + pane.id + '"]');
    if (tab && window.bootstrap && window.bootstrap.Tab) { tab.click(); }
  }, true);
  var coverButton = form.querySelector('[data-upload]');
  if (!coverButton || coverButton.disabled) { return; }
  var config;
  try { config = JSON.parse(form.dataset.projectConfig); } catch (error) { return; }
  var coverInput = form.querySelector('#input-cover-file');
  var galleryButton = form.querySelector('#btn-add-image');
  var galleryInput = form.querySelector('#input-gallery-file');
  var hiddenCover = form.querySelector('#input-image');
  var coverPreview = form.querySelector('#cover-preview');
  var gallery = form.querySelector('#gallery');
  var errorBox = form.querySelector('#project-upload-error');
  var controls = [coverButton, galleryButton, coverInput, galleryInput];
  var busy = false;
  var nextRow = 0;
  gallery.querySelectorAll('input[data-img]').forEach(function (input) {
    var match = input.name.match(/^project_image\[([0-9]+)\]\[image\]$/);
    if (match) { nextRow = Math.max(nextRow, Number(match[1]) + 1); }
  });
  function showError(message) {
    errorBox.textContent = typeof message === 'string' ? message : config.error;
    errorBox.classList.remove('d-none');
  }
  function clearError() { errorBox.textContent = ''; errorBox.classList.add('d-none'); }
  function changed(input) {
    input.dispatchEvent(new Event('input', { bubbles: true }));
    input.dispatchEvent(new Event('change', { bubbles: true }));
  }
  function element(tag, className, text) {
    var node = document.createElement(tag);
    if (className) { node.className = className; }
    if (text !== undefined) { node.textContent = text; }
    return node;
  }
  function imageUrl(relative) {
    if (typeof relative !== 'string' || relative.indexOf('..') >= 0 ||
        !/^view\/image\/project\/[A-Za-z0-9][A-Za-z0-9._-]*\.(jpe?g|png|webp|gif)$/i.test(relative)) { throw new Error('path'); }
    var catalog = new URL(config.catalog, window.location.href);
    var image = new URL(relative, catalog.href);
    if (catalog.origin !== window.location.origin || image.origin !== catalog.origin ||
        image.pathname.indexOf(catalog.pathname + 'view/image/project/') !== 0) { throw new Error('origin'); }
    return image.href;
  }
  function thumbnail(relative, size) {
    var image = element('img', 'rounded object-fit-cover');
    image.src = imageUrl(relative); image.alt = ''; image.width = size; image.height = size;
    return image;
  }
  function addGallery(relative) {
    var index = nextRow;
    var row = element('div', 'row g-2 align-items-end border rounded p-2 mb-2 gallery-row');
    row.setAttribute('data-row', '');
    var hidden = element('input'); hidden.type = 'hidden'; hidden.name = 'project_image[' + index + '][image]'; hidden.value = relative; hidden.setAttribute('data-img', ''); row.appendChild(hidden);
    var thumbColumn = element('div', 'col-sm-2');
    var image = thumbnail(relative, 64); image.setAttribute('data-thumb', ''); thumbColumn.appendChild(image); row.appendChild(thumbColumn);
    var altColumn = element('div', 'col-sm-6 field');
    var altLabel = element('label', 'form-label', config.labels.alt); altLabel.htmlFor = 'input-gallery-alt-' + index;
    var alt = element('input', 'form-control'); alt.type = 'text'; alt.name = 'project_image[' + index + '][alt]'; alt.id = altLabel.htmlFor; alt.value = ''; alt.maxLength = 255;
    altColumn.appendChild(altLabel); altColumn.appendChild(alt); row.appendChild(altColumn);
    var sortColumn = element('div', 'col-sm-2 field');
    var sortLabel = element('label', 'form-label', config.labels.sort); sortLabel.htmlFor = 'input-gallery-sort-' + index;
    var sort = element('input', 'form-control'); sort.type = 'number'; sort.name = 'project_image[' + index + '][sort_order]'; sort.id = sortLabel.htmlFor; sort.value = '0'; sort.min = '0'; sort.step = '1';
    sortColumn.appendChild(sortLabel); sortColumn.appendChild(sort); row.appendChild(sortColumn);
    var removeColumn = element('div', 'col-sm-2');
    var remove = element('button', 'btn btn-outline-danger btn-sm', config.labels.remove); remove.type = 'button'; remove.setAttribute('data-remove', ''); removeColumn.appendChild(remove); row.appendChild(removeColumn);
    gallery.appendChild(row); nextRow++; changed(hidden);
  }
  form.addEventListener('submit', function (event) { if (busy) { event.preventDefault(); showError(config.wait); } });
  gallery.addEventListener('click', function (event) {
    var button = event.target && event.target.closest ? event.target.closest('[data-remove]') : null;
    if (!button || !gallery.contains(button)) { return; }
    if (busy) { showError(config.wait); return; }
    var row = button.closest('[data-row]');
    if (row && gallery.contains(row)) { row.remove(); }
  });
  async function upload(fileInput, isCover) {
    if (busy || !fileInput.files || !fileInput.files[0]) { return; }
    busy = true; controls.forEach(function (control) { control.disabled = true; }); clearError();
    try {
      var url = new URL(config.upload, window.location.href);
      var catalog = new URL(config.catalog, window.location.href);
      if (url.origin !== window.location.origin || catalog.origin !== window.location.origin) { throw new Error('origin'); }
      var token = form.querySelector('[name="csrf_token"]');
      if (!token || !token.value) { throw new Error('csrf'); }
      var data = new FormData(); data.append('csrf_token', token.value); data.append('file', fileInput.files[0]);
      var response = await fetch(url.href, { method: 'POST', body: data, credentials: 'same-origin', redirect: 'error', signal: AbortSignal.timeout(30000) });
      if (!response.ok) { throw new Error('http'); }
      var result = await response.json();
      if (result.success !== true) { showError(result.error || config.error); return; }
      imageUrl(result.path);
      if (isCover) {
        var image = thumbnail(result.path, 96);
        hiddenCover.value = result.path; changed(hiddenCover); coverPreview.textContent = ''; coverPreview.appendChild(image);
      } else { addGallery(result.path); }
      clearError();
    } catch (error) { showError(config.error); }
    finally { busy = false; controls.forEach(function (control) { control.disabled = false; }); }
  }
  coverButton.addEventListener('click', function () { return upload(coverInput, true); });
  galleryButton.addEventListener('click', function () { return upload(galleryInput, false); });
})();
