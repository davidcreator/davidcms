/* BDREditor core/file — seletor/upload de downloads Blog em raiz independente. */
(function () {
  'use strict';
  function init() {
    var modal = document.getElementById('blogDownloadModal');
    if (!modal) return;
    var config;
    try { config = JSON.parse(modal.getAttribute('data-download-config') || '{}'); } catch (_) { return; }
    var strings = config.strings || {};
    var list = document.getElementById('blogDownloadFiles');
    var empty = document.getElementById('blogDownloadEmpty');
    var alert = document.getElementById('blogDownloadAlert');
    var search = document.getElementById('blogDownloadSearch');
    var page = document.getElementById('blogDownloadPage');
    var previous = document.getElementById('blogDownloadPrevious');
    var next = document.getElementById('blogDownloadNext');
    var upload = document.getElementById('blogDownloadUploadButton');
    var input = document.getElementById('blogDownloadFile');
    var state = { target: null, query: '', page: 1, pages: 1, request: 0, timer: null };
    function getModalInstance() {
      return window.bootstrap && window.bootstrap.Modal ? window.bootstrap.Modal.getOrCreateInstance(modal) : null;
    }

    function message(text, kind) {
      alert.textContent = text || strings.downloadUploadError || '';
      alert.className = 'alert alert-' + (kind || 'danger');
      alert.hidden = false;
    }
    function clearMessage() { alert.textContent = ''; alert.className = 'alert d-none'; alert.hidden = true; }
    function json(url, options) {
      return fetch(url, options).then(function (response) { return response.json(); });
    }
    function choose(path) {
      if (!state.target || typeof path !== 'string' || !/^view\/download\/blog\/[A-Za-z0-9][A-Za-z0-9._-]{0,179}$/.test(path) || path.indexOf('..') !== -1) return;
      document.dispatchEvent(new CustomEvent('blog-block-file-selected', { detail: { editor: state.target.editor, nodeId: state.target.nodeId, path: path } }));
      var instance = getModalInstance(); if (instance) instance.hide();
    }
    function load() {
      var id = ++state.request; clearMessage(); empty.textContent = strings.loading || '';
      var url = new URL(config.downloadListUrl, window.location.href);
      url.searchParams.set('search', state.query); url.searchParams.set('page', String(state.page));
      json(url.toString(), { credentials: 'same-origin', headers: { Accept: 'application/json' } }).then(function (result) {
        if (id !== state.request) return;
        if (!result.success) { list.replaceChildren(); message(result.error || strings.downloadUploadError); return; }
        state.page = Number(result.page) || 1; state.pages = Number(result.pages) || 1; list.replaceChildren();
        (result.files || []).forEach(function (file) {
          var row = document.createElement('div'); row.className = 'list-group-item d-flex justify-content-between align-items-center gap-3';
          var label = document.createElement('span'); label.className = 'text-break'; label.textContent = file.name + ' · ' + Math.ceil((Number(file.size) || 0) / 1024) + ' KB';
          var button = document.createElement('button'); button.type = 'button'; button.className = 'btn btn-sm btn-outline-primary flex-shrink-0'; button.textContent = strings.selectDownload || 'Select'; button.setAttribute('aria-label', (strings.selectDownload || 'Select') + ': ' + file.name);
          button.addEventListener('click', function () { choose(file.path); }); row.append(label, button); list.appendChild(row);
        });
        empty.textContent = (result.files || []).length ? '' : (strings.downloadEmpty || '');
        page.textContent = state.page + ' / ' + state.pages; previous.disabled = state.page <= 1; next.disabled = state.page >= state.pages;
      }).catch(function () { if (id === state.request) message(strings.downloadUploadError); });
    }
    document.addEventListener('blog-block-file-open', function (event) {
      var detail = event.detail || {};
      if (!detail.editor || !detail.nodeId) return;
      var instance = getModalInstance();
      if (!instance) { message(strings.downloadUploadError); return; }
      state.target = { editor: detail.editor, nodeId: detail.nodeId };
      state.page = 1; state.query = ''; search.value = ''; input.value = ''; instance.show();
    });
    modal.addEventListener('shown.bs.modal', load);
    modal.addEventListener('hidden.bs.modal', function () { state.target = null; });
    search.addEventListener('input', function () {
      window.clearTimeout(state.timer); state.timer = window.setTimeout(function () { state.query = search.value.trim(); state.page = 1; load(); }, 250);
    });
    previous.addEventListener('click', function () { if (state.page > 1) { state.page -= 1; load(); } });
    next.addEventListener('click', function () { if (state.page < state.pages) { state.page += 1; load(); } });
    upload.addEventListener('click', function () {
      if (!input.files || !input.files[0]) { message(strings.downloadChooseFile || strings.downloadUploadError); return; }
      var body = new FormData(); body.append('csrf_token', config.csrfToken || ''); body.append('file', input.files[0]);
      upload.disabled = true; clearMessage();
      json(config.downloadUploadUrl, { method: 'POST', body: body, credentials: 'same-origin' }).then(function (result) {
        if (!result.success) { message(result.error || strings.downloadUploadError); return; }
        input.value = ''; choose(result.path);
      }).catch(function () { message(strings.downloadUploadError); }).finally(function () { upload.disabled = false; });
    });
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init, { once: true }); else init();
})();
