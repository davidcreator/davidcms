/* CMS Pages-only image, audio, video, and download library. */
(function (global, document) {
  'use strict';
  function init() {
    var modal = document.getElementById('cmsPageMediaModal');
    if (!modal || !global.bootstrap || !global.bootstrap.Modal) return;
    var config; try { config = JSON.parse(modal.dataset.pageMediaConfig || '{}'); } catch (_) { return; }
    var strings = config.strings || {}, files = document.getElementById('cmsPageMediaFiles');
    var search = document.getElementById('cmsPageMediaSearch'), empty = document.getElementById('cmsPageMediaEmpty');
    var alertBox = document.getElementById('cmsPageMediaAlert'), pageLabel = document.getElementById('cmsPageMediaPage');
    var previous = document.getElementById('cmsPageMediaPrevious'), next = document.getElementById('cmsPageMediaNext');
    var uploadInput = document.getElementById('cmsPageMediaUploadFile'), uploadButton = document.getElementById('cmsPageMediaUploadButton');
    var hint = document.getElementById('cmsPageMediaUploadHint'), tabs = Array.prototype.slice.call(document.querySelectorAll('#cmsPageMediaTabs [data-media-kind]'));
    var accepts = { image: 'image/jpeg,image/png,image/webp,image/gif,.jpg,.jpeg,.png,.webp,.gif', audio: 'audio/mpeg,audio/ogg,audio/wav,.mp3,.ogg,.wav', video: 'video/mp4,video/webm,video/ogg,.mp4,.webm,.ogv', download: '.pdf,.docx,.xlsx,.pptx,.odt,.ods,.csv,.txt' };
    var roots = { image: 'view/image/cms/page/', audio: 'view/audio/cms/page/', video: 'view/video/cms/page/', download: 'view/download/cms/page/' };
    var currentEditor = null, currentNodeId = '', kind = 'image', lockedKind = 'image', currentPage = 1, totalPages = 1, debounce = 0;
    var instance = global.bootstrap.Modal.getOrCreateInstance(modal);
    function showAlert(text, danger) { alertBox.textContent = text || ''; alertBox.className = 'alert' + (text ? (danger ? ' alert-danger' : ' alert-info') : ' d-none'); }
    function safeUrl(value) { try { var u = new URL(value, global.location.href); return u.origin === global.location.origin ? u.href : ''; } catch (_) { return ''; } }
    function setKind(value) {
      if (!roots[value] || (lockedKind && value !== lockedKind)) return; kind = value;
      tabs.forEach(function (tab) { tab.classList.toggle('active', tab.dataset.mediaKind === kind); tab.disabled = !!lockedKind && tab.dataset.mediaKind !== lockedKind; tab.setAttribute('aria-selected', tab.dataset.mediaKind === kind ? 'true' : 'false'); });
      uploadInput.accept = accepts[kind]; hint.textContent = (strings.uploadHint || {})[kind] || '';
      currentPage = 1; load();
    }
    function apply(path) {
      if (!currentEditor || !roots[kind] || typeof path !== 'string' || path.indexOf('..') >= 0 || path.indexOf(roots[kind]) !== 0 || !/^view\/(?:image|audio|video|download)\/cms\/page\/[A-Za-z0-9][A-Za-z0-9._-]{0,179}$/.test(path)) return;
      if (kind === 'image') currentEditor.setSelectedAttribute('src', path);
      else {
        var eventKind = kind === 'download' ? 'file' : kind;
        document.dispatchEvent(new CustomEvent('blog-block-' + eventKind + '-selected', { detail: { editor: currentEditor, nodeId: currentNodeId || currentEditor.selectedId, path: path } }));
      }
      instance.hide(); currentEditor = null; currentNodeId = '';
    }
    function load() {
      var url; try { url = new URL(config.listUrl, global.location.href); } catch (_) { showAlert(strings.error, true); return; }
      if (url.origin !== global.location.origin) { showAlert(strings.error, true); return; }
      url.searchParams.set('kind', kind); url.searchParams.set('search', search.value || ''); url.searchParams.set('page', String(currentPage));
      files.replaceChildren(); empty.textContent = strings.loading || '';
      fetch(url.href, { credentials: 'same-origin', headers: { Accept: 'application/json' } }).then(function (r) { return r.json(); }).then(function (data) {
        if (!data || !data.success) throw new Error(data && data.error || strings.error);
        currentPage = data.page || 1; totalPages = data.pages || 1;
        pageLabel.textContent = (strings.page || 'Page %1 of %2').replace('%1', currentPage).replace('%2', totalPages);
        previous.disabled = currentPage <= 1; next.disabled = currentPage >= totalPages; files.replaceChildren();
        (data.files || []).forEach(function (item) {
          var button = document.createElement('button'); button.type = 'button'; button.className = 'list-group-item list-group-item-action d-flex align-items-center gap-3';
          if (kind === 'image') { var img = document.createElement('img'); img.src = safeUrl(item.url); img.alt = ''; img.loading = 'lazy'; img.style.cssText = 'width:64px;height:64px;object-fit:cover'; button.appendChild(img); }
          var name = document.createElement('span'); name.textContent = item.name || ''; var select = document.createElement('span'); select.className = 'ms-auto btn btn-sm btn-outline-primary'; select.textContent = strings.select || '';
          button.append(name, select); button.addEventListener('click', function () { apply(item.path); }); files.appendChild(button);
        });
        empty.textContent = files.children.length ? '' : ((strings.emptyKind || {})[kind] || strings.empty || '');
      }).catch(function (e) { files.replaceChildren(); empty.textContent = ''; showAlert(e.message || strings.error, true); });
    }
    document.addEventListener('cms-page-media-open', function (e) { var d = e.detail || {}; currentEditor = d.editor; currentNodeId = d.nodeId || (currentEditor && currentEditor.selectedId) || ''; if (!currentEditor) return; lockedKind = 'image'; showAlert('', false); setKind('image'); instance.show(); });
    ['audio','video','file'].forEach(function (eventKind) { document.addEventListener('blog-block-' + eventKind + '-open', function (e) { var d = e.detail || {}; currentEditor = d.editor; currentNodeId = d.nodeId || ''; if (!currentEditor) return; lockedKind = eventKind === 'file' ? 'download' : eventKind; showAlert('', false); setKind(lockedKind); instance.show(); }); });
    tabs.forEach(function (tab) { tab.addEventListener('click', function () { setKind(tab.dataset.mediaKind); }); });
    modal.addEventListener('shown.bs.modal', function () { search.focus(); });
    modal.addEventListener('hidden.bs.modal', function () { currentEditor = null; currentNodeId = ''; lockedKind = 'image'; tabs.forEach(function (tab) { tab.disabled = false; }); });
    search.addEventListener('input', function () { global.clearTimeout(debounce); debounce = global.setTimeout(function () { currentPage = 1; load(); }, 180); });
    previous.addEventListener('click', function () { if (currentPage > 1) { currentPage--; load(); } });
    next.addEventListener('click', function () { if (currentPage < totalPages) { currentPage++; load(); } });
    uploadButton.addEventListener('click', function () {
      var file = uploadInput.files && uploadInput.files[0]; if (!file) { showAlert(strings.error, true); return; }
      var form = new FormData(); form.append('csrf_token', config.csrfToken || ''); form.append('kind', kind); form.append('file', file, file.name);
      uploadButton.disabled = true; showAlert(strings.uploading || '', false); var url = safeUrl(config.uploadUrl);
      if (!url) { uploadButton.disabled = false; showAlert(strings.error, true); return; }
      fetch(url, { method: 'POST', credentials: 'same-origin', body: form, headers: { Accept: 'application/json' } }).then(function (r) { return r.json(); }).then(function (data) { if (!data || !data.success) throw new Error(data && data.error || strings.error); apply(data.path); }).catch(function (e) { showAlert(e.message || strings.error, true); }).finally(function () { uploadButton.disabled = false; uploadInput.value = ''; });
    });
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
})(window, document);
