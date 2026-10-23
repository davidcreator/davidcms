/* Fase 8 / Tarefa 07 — validação nativa por aba e upload MediaUpload com CSRF. */
(function initCategoryForm() {
  'use strict';
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initCategoryForm, { once: true });
    return;
  }
  var form = document.querySelector('[data-category-form]');
  if (!form || form.dataset.categoryReady === '1') { return; }
  form.dataset.categoryReady = '1';
  form.addEventListener('invalid', function (event) {
    var firstInvalid = form.querySelector('input:invalid, select:invalid, textarea:invalid');
    if (firstInvalid && firstInvalid !== event.target) { return; }
    var pane = event.target.closest('.tab-pane');
    if (!pane || pane.classList.contains('active')) { return; }
    var tab = form.querySelector('[data-bs-target="#' + pane.id + '"]');
    if (tab && window.bootstrap && window.bootstrap.Tab) { tab.click(); }
  }, true);

  var button = form.querySelector('#btn-upload-cover');
  if (!button || button.disabled) { return; }
  var config;
  try { config = JSON.parse(form.dataset.categoryConfig); } catch (error) { return; }
  var input = form.querySelector('#input-cover-file');
  var hidden = form.querySelector('#input-image');
  var preview = form.querySelector('#cover-preview');
  var errorBox = form.querySelector('#category-upload-error');
  var busy = false;
  function showError(message) {
    errorBox.textContent = typeof message === 'string' ? message : config.error;
    errorBox.classList.remove('d-none');
  }
  form.addEventListener('submit', function (event) {
    if (busy) { event.preventDefault(); showError(config.wait); }
  });
  button.addEventListener('click', async function () {
    if (busy || !input.files || !input.files[0]) { return; }
    busy = true;
    button.disabled = true;
    input.disabled = true;
    errorBox.textContent = '';
    errorBox.classList.add('d-none');
    try {
      var upload = new URL(config.upload, window.location.href);
      var catalog = new URL(config.catalog, window.location.href);
      if (upload.origin !== window.location.origin || catalog.origin !== window.location.origin) {
        throw new Error('origin');
      }
      var token = form.querySelector('[name="csrf_token"]');
      if (!token || !token.value) { throw new Error('csrf'); }
      var data = new FormData();
      data.append('csrf_token', token.value);
      data.append('file', input.files[0]);
      var response = await fetch(upload.href, {
        method: 'POST', body: data, credentials: 'same-origin', redirect: 'error',
        signal: AbortSignal.timeout(30000)
      });
      if (!response.ok) { throw new Error('http'); }
      var result = await response.json();
      if (result.success !== true) { showError(result.error || config.error); return; }
      if (typeof result.path !== 'string' || result.path.indexOf('..') >= 0 ||
          !/^view\/image\/category\/[A-Za-z0-9][A-Za-z0-9._-]*\.(jpe?g|png|webp|gif)$/i.test(result.path)) {
        throw new Error('path');
      }
      var imageUrl = new URL(result.path, catalog.href);
      if (imageUrl.origin !== catalog.origin || imageUrl.pathname.indexOf(catalog.pathname + 'view/image/category/') !== 0) {
        throw new Error('path');
      }
      var image = document.createElement('img');
      image.src = imageUrl.href;
      image.alt = '';
      image.width = 96;
      image.height = 96;
      image.className = 'rounded object-fit-cover';
      errorBox.textContent = '';
      errorBox.classList.add('d-none');
      hidden.value = result.path;
      hidden.dispatchEvent(new Event('input', { bubbles: true }));
      hidden.dispatchEvent(new Event('change', { bubbles: true }));
      preview.textContent = '';
      preview.appendChild(image);
    } catch (error) {
      showError(config.error);
    } finally {
      busy = false;
      button.disabled = false;
      input.disabled = false;
    }
  });
})();
