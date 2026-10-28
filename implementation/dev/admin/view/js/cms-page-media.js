/* CMS Pages-only image library. No Blog routes, folders, deletion, or third-party calls. */
(function (global, document) {
  'use strict';
  function init() {
    var modal = document.getElementById('cmsPageImageModal');
    if (!modal || !global.bootstrap || !global.bootstrap.Modal) return;
    var config; try { config = JSON.parse(modal.dataset.pageMediaConfig || '{}'); } catch (_) { return; }
    var strings = config.strings || {}, files = document.getElementById('cmsPageImageFiles');
    var search = document.getElementById('cmsPageImageSearch'), empty = document.getElementById('cmsPageImageEmpty');
    var alertBox = document.getElementById('cmsPageImageAlert'), pageLabel = document.getElementById('cmsPageImagePage');
    var previous = document.getElementById('cmsPageImagePrevious'), next = document.getElementById('cmsPageImageNext');
    var uploadInput = document.getElementById('cmsPageImageUploadFile'), uploadButton = document.getElementById('cmsPageImageUploadButton');
    var currentEditor = null, currentPage = 1, totalPages = 1, debounce = 0;
    var instance = global.bootstrap.Modal.getOrCreateInstance(modal);
    function showAlert(text, danger) { alertBox.textContent = text || ''; alertBox.className = 'alert' + (text ? (danger ? ' alert-danger' : ' alert-info') : ' d-none'); }
    function safeUrl(value) {
      try { var url = new URL(value, global.location.href); return url.origin === global.location.origin ? url.href : ''; } catch (_) { return ''; }
    }
    function apply(path) {
      if (!currentEditor || !/^view\/image\/cms\/page\/[A-Za-z0-9][A-Za-z0-9._-]{0,179}$/.test(path || '') || path.indexOf('..') >= 0) return;
      currentEditor.setSelectedAttribute('src', path);
      instance.hide(); currentEditor = null;
    }
    function load() {
      var url; try { url = new URL(config.listUrl, global.location.href); } catch (_) { showAlert(strings.error, true); return; }
      if (url.origin !== global.location.origin) { showAlert(strings.error, true); return; }
      url.searchParams.set('search', search.value || ''); url.searchParams.set('page', String(currentPage));
      files.replaceChildren(); empty.textContent = strings.loading || '';
      fetch(url.href, { credentials: 'same-origin', headers: { 'Accept': 'application/json' } }).then(function (response) { return response.json(); }).then(function (data) {
        if (!data || !data.success) throw new Error(data && data.error || strings.error);
        currentPage = data.page || 1; totalPages = data.pages || 1; pageLabel.textContent = (strings.page || 'Page %1 of %2').replace('%1', currentPage).replace('%2', totalPages);
        previous.disabled = currentPage <= 1; next.disabled = currentPage >= totalPages;
        files.replaceChildren();
        (data.files || []).forEach(function (item) {
          var button = document.createElement('button'); button.type = 'button'; button.className = 'list-group-item list-group-item-action d-flex align-items-center gap-3';
          var img = document.createElement('img'); img.src = safeUrl(item.url); img.alt = ''; img.loading = 'lazy'; img.style.cssText = 'width:64px;height:64px;object-fit:cover';
          var name = document.createElement('span'); name.textContent = item.name || '';
          var select = document.createElement('span'); select.className = 'ms-auto btn btn-sm btn-outline-primary'; select.textContent = strings.select || '';
          button.append(img, name, select); button.addEventListener('click', function () { apply(item.path); }); files.appendChild(button);
        });
        empty.textContent = files.children.length ? '' : (strings.empty || '');
      }).catch(function (error) { files.replaceChildren(); empty.textContent = ''; showAlert(error.message || strings.error, true); });
    }
    document.addEventListener('cms-page-media-open', function (event) { currentEditor = event.detail && event.detail.editor; if (!currentEditor) return; currentPage = 1; showAlert('', false); instance.show(); load(); });
    modal.addEventListener('shown.bs.modal', function () { search.focus(); });
    modal.addEventListener('hidden.bs.modal', function () { currentEditor = null; });
    search.addEventListener('input', function () { global.clearTimeout(debounce); debounce = global.setTimeout(function () { currentPage = 1; load(); }, 180); });
    previous.addEventListener('click', function () { if (currentPage > 1) { currentPage--; load(); } });
    next.addEventListener('click', function () { if (currentPage < totalPages) { currentPage++; load(); } });
    uploadButton.addEventListener('click', function () {
      var file = uploadInput.files && uploadInput.files[0];
      if (!file) { showAlert(strings.error, true); return; }
      var form = new FormData(); form.append('csrf_token', config.csrfToken || ''); form.append('file', file, file.name);
      uploadButton.disabled = true; showAlert(strings.uploading || '', false);
      var url = safeUrl(config.uploadUrl);
      if (!url) { uploadButton.disabled = false; showAlert(strings.error, true); return; }
      fetch(url, { method: 'POST', credentials: 'same-origin', body: form, headers: { 'Accept': 'application/json' } }).then(function (response) { return response.json(); }).then(function (data) {
        if (!data || !data.success) throw new Error(data && data.error || strings.error);
        apply(data.path);
      }).catch(function (error) { showAlert(error.message || strings.error, true); }).finally(function () { uploadButton.disabled = false; uploadInput.value = ''; });
    });
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
})(window, document);
