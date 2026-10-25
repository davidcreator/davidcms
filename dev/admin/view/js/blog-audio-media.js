/* BDREditor core/audio — picker only for pre-provisioned local audio, no upload/delete. */
(function () {
  'use strict';
  function init() {
    var modal = document.getElementById('blogAudioModal');
    if (!modal) return;
    var config;
    try { config = JSON.parse(modal.getAttribute('data-audio-config') || '{}'); } catch (_) { return; }
    var strings = config.strings || {};
    var list = document.getElementById('blogAudioFiles');
    var empty = document.getElementById('blogAudioEmpty');
    var alert = document.getElementById('blogAudioAlert');
    var search = document.getElementById('blogAudioSearch');
    var pageLabel = document.getElementById('blogAudioPage');
    var previous = document.getElementById('blogAudioPrevious');
    var next = document.getElementById('blogAudioNext');
    var state = { target: null, query: '', page: 1, pages: 1, request: 0, timer: null };
    function getModalInstance() { return window.bootstrap && window.bootstrap.Modal ? window.bootstrap.Modal.getOrCreateInstance(modal) : null; }
    function showError(text) { alert.textContent = text || strings.audioError || ''; alert.className = 'alert alert-danger'; alert.hidden = false; }
    function clearError() { alert.textContent = ''; alert.className = 'alert d-none'; alert.hidden = true; }
    function requestJson(url) { return fetch(url, { credentials: 'same-origin', headers: { Accept: 'application/json' } }).then(function (response) { return response.json(); }); }
    function choose(path) {
      if (!state.target || typeof path !== 'string' || !/^view\/audio\/blog\/[A-Za-z0-9][A-Za-z0-9._-]{0,179}$/.test(path) || path.indexOf('..') !== -1) return;
      document.dispatchEvent(new CustomEvent('blog-block-audio-selected', { detail: { editor: state.target.editor, nodeId: state.target.nodeId, path: path } }));
      var instance = getModalInstance(); if (instance) instance.hide();
    }
    function load() {
      var requestId = ++state.request; clearError(); empty.textContent = strings.loading || '';
      var url = new URL(config.audioListUrl, window.location.href);
      url.searchParams.set('search', state.query); url.searchParams.set('page', String(state.page));
      requestJson(url.toString()).then(function (result) {
        if (requestId !== state.request) return;
        if (!result.success) { list.replaceChildren(); showError(result.error || strings.audioError); return; }
        state.page = Number(result.page) || 1; state.pages = Number(result.pages) || 1; list.replaceChildren();
        (result.files || []).forEach(function (file) {
          var row = document.createElement('div'); row.className = 'list-group-item d-flex justify-content-between align-items-center gap-3';
          if (typeof file.path === 'string' && /^view\/audio\/blog\/[A-Za-z0-9][A-Za-z0-9._-]{0,179}$/.test(file.path) && file.path.indexOf('..') === -1) {
            row.draggable = true; row.setAttribute('aria-label', strings.audioDragHint || 'Drag audio into the editor');
            row.addEventListener('dragstart', function (event) { event.dataTransfer.setData('application/x-reamurcms-audio', file.path); event.dataTransfer.effectAllowed = 'copy'; });
          }
          if (typeof file.path === 'string' && /^view\/audio\/blog\/[A-Za-z0-9][A-Za-z0-9._-]{0,179}$/.test(file.path) && file.path.indexOf('..') === -1) {
            row.draggable = true; row.setAttribute('aria-label', strings.audioDragHint || 'Drag audio into the editor');
            row.addEventListener('dragstart', function (event) { event.dataTransfer.setData('application/x-reamurcms-audio', file.path); event.dataTransfer.effectAllowed = 'copy'; });
          }
          var label = document.createElement('span'); label.className = 'text-break'; label.textContent = file.name + ' · ' + Math.ceil((Number(file.size) || 0) / 1024) + ' KB';
          var button = document.createElement('button'); button.type = 'button'; button.className = 'btn btn-sm btn-outline-primary flex-shrink-0'; button.textContent = strings.selectAudio || 'Use audio';
          button.setAttribute('aria-label', (strings.selectAudio || 'Use audio') + ': ' + file.name); button.addEventListener('click', function () { choose(file.path); });
          row.append(label, button); list.appendChild(row);
        });
        empty.textContent = (result.files || []).length ? '' : (strings.audioEmpty || '');
        pageLabel.textContent = state.page + ' / ' + state.pages; previous.disabled = state.page <= 1; next.disabled = state.page >= state.pages;
      }).catch(function () { if (requestId === state.request) showError(strings.audioError); });
    }
    document.addEventListener('blog-block-audio-open', function (event) {
      var detail = event.detail || {}; if (!detail.editor || !detail.nodeId) return;
      var instance = getModalInstance(); if (!instance) { showError(strings.audioError); return; }
      state.target = { editor: detail.editor, nodeId: detail.nodeId }; state.page = 1; state.query = ''; search.value = ''; instance.show();
    });
    modal.addEventListener('shown.bs.modal', load);
    modal.addEventListener('hidden.bs.modal', function () { state.target = null; });
    search.addEventListener('input', function () { window.clearTimeout(state.timer); state.timer = window.setTimeout(function () { state.query = search.value.trim(); state.page = 1; load(); }, 250); });
    previous.addEventListener('click', function () { if (state.page > 1) { state.page -= 1; load(); } });
    next.addEventListener('click', function () { if (state.page < state.pages) { state.page += 1; load(); } });
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init, { once: true }); else init();
})();
