/* Blog media catalog for BDREditor: allowlisted audio/video uploads, selection and drag/drop. */
(function () {
  'use strict';
  var definitions = {
    audio: { prefix: 'blogAudio', listKey: 'audioListUrl', uploadKey: 'audioUploadUrl',
      openEvent: 'blog-block-audio-open', selectedEvent: 'blog-block-audio-selected',
      mime: 'application/x-reamurcms-audio', errorKey: 'audioUploadError' },
    video: { prefix: 'blogVideo', listKey: 'videoListUrl', uploadKey: 'videoUploadUrl',
      openEvent: 'blog-block-video-open', selectedEvent: 'blog-block-video-selected',
      mime: 'application/x-reamurcms-video', errorKey: 'videoUploadError' }
  };
  function safePath(kind, path) {
    var folder = kind === 'video' ? 'video' : 'audio';
    var extensions = kind === 'video' ? ['mp4', 'webm', 'ogv'] : ['mp3', 'ogg', 'wav'];
    return typeof path === 'string' && new RegExp('^view/' + folder + '/blog/[A-Za-z0-9][A-Za-z0-9._-]{0,179}$').test(path) &&
      path.indexOf('..') === -1 && extensions.indexOf(path.split('.').pop().toLowerCase()) !== -1;
  }
  function initKind(kind) {
    var def = definitions[kind], prefix = def.prefix, modal = document.getElementById(prefix + 'Modal');
    if (!modal) return;
    var config;
    try { config = JSON.parse(modal.getAttribute('data-media-config') || '{}'); } catch (_) { return; }
    var strings = config.strings || {};
    var list = document.getElementById(prefix + 'Files'), empty = document.getElementById(prefix + 'Empty');
    var alert = document.getElementById(prefix + 'Alert'), search = document.getElementById(prefix + 'Search');
    var pageLabel = document.getElementById(prefix + 'Page'), previous = document.getElementById(prefix + 'Previous');
    var next = document.getElementById(prefix + 'Next'), uploadFile = document.getElementById(prefix + 'UploadFile');
    var uploadButton = document.getElementById(prefix + 'UploadButton');
    if (![list, empty, alert, search, pageLabel, previous, next, uploadFile, uploadButton].every(Boolean)) return;
    var state = { target: null, query: '', page: 1, pages: 1, request: 0, timer: null };
    function modalInstance() { return window.bootstrap && window.bootstrap.Modal ? window.bootstrap.Modal.getOrCreateInstance(modal) : null; }
    function showError(text) { alert.textContent = text || strings[def.errorKey] || strings.audioError || ''; alert.className = 'alert alert-danger'; alert.hidden = false; }
    function clearError() { alert.textContent = ''; alert.className = 'alert d-none'; alert.hidden = true; }
    function requestJson(url, options) { return fetch(url, Object.assign({ credentials: 'same-origin', headers: { Accept: 'application/json' } }, options || {})).then(function (response) { return response.json(); }); }
    function choose(path) {
      if (!state.target || !safePath(kind, path)) return;
      document.dispatchEvent(new CustomEvent(def.selectedEvent, { detail: { editor: state.target.editor, nodeId: state.target.nodeId, path: path } }));
      var instance = modalInstance(); if (instance) instance.hide();
    }
    function load() {
      var requestId = ++state.request; clearError(); empty.textContent = strings.loading || '';
      var endpoint = config[def.listKey];
      if (!endpoint) { showError(strings[def.errorKey]); return; }
      var url = new URL(endpoint, window.location.href); url.searchParams.set('search', state.query); url.searchParams.set('page', String(state.page));
      requestJson(url.toString()).then(function (result) {
        if (requestId !== state.request) return;
        if (!result.success) { list.replaceChildren(); showError(result.error || strings[kind === 'video' ? 'videoError' : 'audioError']); return; }
        state.page = Number(result.page) || 1; state.pages = Number(result.pages) || 1; list.replaceChildren();
        (result.files || []).forEach(function (file) {
          if (!safePath(kind, file.path)) return;
          var row = document.createElement('div'); row.className = 'list-group-item d-flex justify-content-between align-items-center gap-3';
          row.draggable = true; row.setAttribute('aria-label', (strings[kind === 'video' ? 'videoDragHint' : 'audioDragHint'] || 'Drag into the editor') + ': ' + file.name);
          row.addEventListener('dragstart', function (event) { event.dataTransfer.setData(def.mime, file.path); event.dataTransfer.effectAllowed = 'copy'; });
          var label = document.createElement('span'); label.className = 'text-break'; label.textContent = file.name + ' · ' + Math.ceil((Number(file.size) || 0) / 1024) + ' KB';
          var button = document.createElement('button'); button.type = 'button'; button.className = 'btn btn-sm btn-outline-primary flex-shrink-0';
          button.textContent = strings[kind === 'video' ? 'selectVideo' : 'selectAudio'] || (kind === 'video' ? 'Use video' : 'Use audio');
          button.setAttribute('aria-label', button.textContent + ': ' + file.name); button.addEventListener('click', function () { choose(file.path); });
          row.append(label, button); list.appendChild(row);
        });
        empty.textContent = list.children.length ? '' : (strings[kind === 'video' ? 'videoEmpty' : 'audioEmpty'] || '');
        pageLabel.textContent = state.page + ' / ' + state.pages; previous.disabled = state.page <= 1; next.disabled = state.page >= state.pages;
      }).catch(function () { if (requestId === state.request) showError(strings[kind === 'video' ? 'videoError' : 'audioError']); });
    }
    function upload() {
      clearError();
      var file = uploadFile.files && uploadFile.files[0];
      if (!file) { showError(strings.chooseFile || strings[def.errorKey] || strings.audioError); return; }
      var endpoint = config[def.uploadKey];
      if (!endpoint || !config.csrfToken) { showError(strings[def.errorKey] || strings.audioError); return; }
      var form = new FormData(); form.append('file', file); form.append('csrf_token', config.csrfToken);
      uploadButton.disabled = true;
      requestJson(endpoint, { method: 'POST', body: form }).then(function (result) {
        if (!result.success) { showError(result.error || strings[def.errorKey]); return; }
        uploadFile.value = ''; choose(result.path);
      }).catch(function () { showError(strings[def.errorKey] || strings.audioError); }).finally(function () { uploadButton.disabled = false; });
    }
    document.addEventListener(def.openEvent, function (event) {
      var detail = event.detail || {}; if (!detail.editor || !detail.nodeId) return;
      var instance = modalInstance(); if (!instance) { showError(strings[def.errorKey] || strings.audioError); return; }
      state.target = { editor: detail.editor, nodeId: detail.nodeId }; state.page = 1; state.query = ''; search.value = ''; uploadFile.value = ''; instance.show();
    });
    modal.addEventListener('shown.bs.modal', load);
    modal.addEventListener('hidden.bs.modal', function () { state.target = null; });
    search.addEventListener('input', function () { window.clearTimeout(state.timer); state.timer = window.setTimeout(function () { state.query = search.value.trim(); state.page = 1; load(); }, 250); });
    previous.addEventListener('click', function () { if (state.page > 1) { state.page -= 1; load(); } });
    next.addEventListener('click', function () { if (state.page < state.pages) { state.page += 1; load(); } });
    uploadButton.addEventListener('click', upload);
  }
  function init() { initKind('audio'); initKind('video'); }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init, { once: true }); else init();
})();
