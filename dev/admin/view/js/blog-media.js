/* F8-05 — seletor de mídia do Blog; biblioteca em filesystem restrita a view/image/blog/. */
(function initBlogMedia() {
  'use strict';

  // O shell carrega este arquivo no head; inicialize somente com o DOM pronto.
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initBlogMedia, { once: true });
    return;
  }

  var modal = document.getElementById('blogMediaModal');
  var imageInput = document.getElementById('input-image');
  if (!modal || !imageInput) { return; }

  var config;
  try {
    config = JSON.parse(modal.getAttribute('data-media-config') || '{}');
  } catch (error) {
    return;
  }

  var strings = config.strings || {};
  var state = { folder: '', search: '', page: 1, pages: 1, requestId: 0, timer: null };
  var alertBox = document.getElementById('blogMediaAlert');
  var folderList = document.getElementById('blogMediaFolders');
  var fileList = document.getElementById('blogMediaFiles');
  var emptyBox = document.getElementById('blogMediaEmpty');
  var breadcrumb = document.getElementById('blogMediaBreadcrumb');
  var pageLabel = document.getElementById('blogMediaPageLabel');
  var searchInput = document.getElementById('blogMediaSearch');
  var previousButton = document.getElementById('blogMediaPrevious');
  var nextButton = document.getElementById('blogMediaNext');
  var uploadButton = document.getElementById('blogMediaUploadButton');
  var createFolderButton = document.getElementById('blogMediaCreateFolder');
  var useUrlButton = document.getElementById('blogMediaUseUrl');

  function showAlert(message, type) {
    alertBox.textContent = message || strings.genericError || '';
    alertBox.className = 'alert alert-' + (type || 'danger');
    alertBox.hidden = false;
  }

  function clearAlert() {
    alertBox.textContent = '';
    alertBox.className = 'alert d-none';
    alertBox.hidden = true;
  }

  function hideModal() {
    if (window.bootstrap && window.bootstrap.Modal) {
      var instance = window.bootstrap.Modal.getInstance(modal);
      if (instance) { instance.hide(); }
    }
  }

  function chooseImage(value) {
    imageInput.value = value;
    imageInput.dispatchEvent(new Event('input', { bubbles: true }));
    imageInput.dispatchEvent(new Event('change', { bubbles: true }));
    hideModal();
  }

  function requestJson(url, options) {
    return fetch(url, options).then(function (response) {
      return response.json();
    });
  }

  function appendIcon(parent, className) {
    var icon = document.createElement('i');
    icon.className = className;
    icon.setAttribute('aria-hidden', 'true');
    parent.appendChild(icon);
    return icon;
  }

  function makeButton(label, className, onClick, title) {
    var button = document.createElement('button');
    button.type = 'button';
    button.className = className;
    button.textContent = label;
    if (title) { button.title = title; }
    button.addEventListener('click', onClick);
    return button;
  }

  function renderBreadcrumb() {
    breadcrumb.replaceChildren();

    var rootButton = makeButton(
      strings.root || '',
      'btn btn-link btn-sm p-0 text-decoration-none',
      function () { openFolder(''); }
    );
    breadcrumb.appendChild(rootButton);

    if (!state.folder) { return; }

    var segments = state.folder.split('/');
    var current = '';

    segments.forEach(function (segment, index) {
      var separator = document.createElement('span');
      separator.className = 'mx-2 text-body-secondary';
      separator.textContent = '/';
      breadcrumb.appendChild(separator);
      current = current ? current + '/' + segment : segment;

      if (index === segments.length - 1) {
        var active = document.createElement('span');
        active.className = 'fw-semibold';
        active.textContent = segment;
        breadcrumb.appendChild(active);
      } else {
        (function (path, label) {
          breadcrumb.appendChild(makeButton(label, 'btn btn-link btn-sm p-0 text-decoration-none', function () {
            openFolder(path);
          }));
        })(current, segment);
      }
    });
  }

  function renderFolders(folders) {
    folderList.replaceChildren();

    folders.forEach(function (folder) {
      var column = document.createElement('div');
      column.className = 'col-12 col-sm-6 col-lg-4';

      var card = document.createElement('div');
      card.className = 'card h-100';
      var body = document.createElement('div');
      body.className = 'card-body d-flex align-items-center justify-content-between gap-2 py-2';

      var open = document.createElement('button');
      open.type = 'button';
      open.className = 'btn btn-link p-0 text-decoration-none text-start text-truncate';
      open.setAttribute('aria-label', folder.name);
      appendIcon(open, 'bi bi-folder-fill me-2 text-warning');
      var label = document.createElement('span');
      label.textContent = folder.name;
      open.appendChild(label);
      open.addEventListener('click', function () { openFolder(folder.folder); });
      body.appendChild(open);

      if (folder.empty) {
        var remove = makeButton('', 'btn btn-sm btn-outline-danger', function () {
          if (!window.confirm(strings.confirmFolderDelete || '')) { return; }
          deleteMedia('folder', 'view/image/blog/' + folder.folder);
        }, strings.delete || '');
        appendIcon(remove, 'bi bi-trash');
        remove.setAttribute('aria-label', strings.delete || '');
        body.appendChild(remove);
      }

      card.appendChild(body);
      column.appendChild(card);
      folderList.appendChild(column);
    });
  }

  function renderFiles(files) {
    fileList.replaceChildren();

    files.forEach(function (file) {
      var column = document.createElement('div');
      column.className = 'col-6 col-sm-4 col-md-3 col-lg-2';

      var card = document.createElement('div');
      card.className = 'card h-100';

      var choose = document.createElement('button');
      choose.type = 'button';
      choose.className = 'btn btn-link text-decoration-none text-body p-2 w-100';
      choose.setAttribute('aria-label', (strings.select || '') + ': ' + file.name);
      choose.addEventListener('click', function () { chooseImage(file.path); });

      var preview = document.createElement('span');
      preview.className = 'd-flex align-items-center justify-content-center bg-body-tertiary rounded mb-2';
      preview.style.aspectRatio = '1 / 1';
      var image = document.createElement('img');
      image.src = file.url;
      image.alt = '';
      image.loading = 'lazy';
      image.className = 'img-fluid rounded';
      image.style.maxHeight = '100%';
      image.style.objectFit = 'contain';
      preview.appendChild(image);
      choose.appendChild(preview);

      var name = document.createElement('span');
      name.className = 'd-block small text-truncate';
      name.textContent = file.name;
      name.title = file.name;
      choose.appendChild(name);
      card.appendChild(choose);

      var footer = document.createElement('div');
      footer.className = 'card-footer bg-transparent border-0 pt-0';
      var remove = makeButton(strings.delete || '', 'btn btn-sm btn-outline-danger w-100', function () {
        if (file.referenced) { return; }
        if (!window.confirm(strings.confirmFileDelete || '')) { return; }
        deleteMedia('file', file.path);
      });
      remove.disabled = !!file.referenced;
      if (file.referenced) { remove.title = strings.inUse || ''; }
      footer.appendChild(remove);
      card.appendChild(footer);
      column.appendChild(card);
      fileList.appendChild(column);
    });
  }

  function formatPageLabel(page, pages) {
    var values = [page, pages];
    return String(strings.page || '').replace(/%d/g, function () {
      return values.length ? values.shift() : '';
    });
  }

  function loadMedia() {
    var requestId = ++state.requestId;
    clearAlert();
    emptyBox.textContent = strings.loading || '';
    emptyBox.className = 'text-center text-body-secondary py-4';

    var url = new URL(config.listUrl, window.location.href);
    url.searchParams.set('folder', state.folder);
    url.searchParams.set('search', state.search);
    url.searchParams.set('page', String(state.page));

    requestJson(url.toString(), { credentials: 'same-origin', headers: { Accept: 'application/json' } })
      .then(function (result) {
        if (requestId !== state.requestId) { return; }
        if (!result.success) {
          showAlert(result.error || strings.genericError);
          folderList.replaceChildren();
          fileList.replaceChildren();
          emptyBox.className = 'text-center text-body-secondary py-4 d-none';
          return;
        }

        state.folder = result.folder || '';
        state.page = Number(result.page) || 1;
        state.pages = Number(result.pages) || 1;
        renderBreadcrumb();
        renderFolders(result.folders || []);
        renderFiles(result.files || []);
        pageLabel.textContent = formatPageLabel(state.page, state.pages);
        previousButton.disabled = state.page <= 1;
        nextButton.disabled = state.page >= state.pages;

        var noFolders = !result.folders || result.folders.length === 0;
        var noFiles = !result.files || result.files.length === 0;
        if (noFolders && noFiles) {
          emptyBox.textContent = state.search ? (strings.noMatches || '') : (strings.empty || '');
          emptyBox.className = 'text-center text-body-secondary py-4';
        } else {
          emptyBox.textContent = '';
          emptyBox.className = 'text-center text-body-secondary py-4 d-none';
        }
      })
      .catch(function () {
        if (requestId === state.requestId) {
          emptyBox.textContent = '';
          emptyBox.className = 'text-center text-body-secondary py-4 d-none';
          showAlert(strings.genericError);
        }
      });
  }

  function openFolder(folder) {
    state.folder = folder || '';
    state.page = 1;
    loadMedia();
  }

  function deleteMedia(type, path) {
    var body = new FormData();
    body.append('csrf_token', config.csrfToken || '');
    body.append('type', type);
    body.append('path', path);

    requestJson(config.deleteUrl, { method: 'POST', body: body, credentials: 'same-origin' })
      .then(function (result) {
        if (!result.success) { showAlert(result.error || strings.genericError); return; }
        loadMedia();
        showAlert(result.message || '', 'success');
      })
      .catch(function () { showAlert(strings.genericError); });
  }

  modal.addEventListener('shown.bs.modal', function () {
    state.folder = '';
    state.search = '';
    state.page = 1;
    searchInput.value = '';
    loadMedia();
  });

  searchInput.addEventListener('input', function () {
    window.clearTimeout(state.timer);
    state.timer = window.setTimeout(function () {
      state.search = searchInput.value.trim();
      state.page = 1;
      loadMedia();
    }, 300);
  });

  previousButton.addEventListener('click', function () {
    if (state.page > 1) { state.page -= 1; loadMedia(); }
  });

  nextButton.addEventListener('click', function () {
    if (state.page < state.pages) { state.page += 1; loadMedia(); }
  });

  uploadButton.addEventListener('click', function () {
    var fileInput = document.getElementById('blogMediaFile');
    if (!fileInput.files || !fileInput.files[0]) {
      showAlert(strings.chooseFile || strings.genericError);
      return;
    }

    var body = new FormData();
    body.append('csrf_token', config.csrfToken || '');
    body.append('folder', state.folder);
    body.append('file', fileInput.files[0]);
    uploadButton.disabled = true;
    clearAlert();

    requestJson(config.uploadUrl, { method: 'POST', body: body, credentials: 'same-origin' })
      .then(function (result) {
        if (!result.success) { showAlert(result.error || strings.genericError); return; }
        fileInput.value = '';
        chooseImage(result.path);
      })
      .catch(function () { showAlert(strings.genericError); })
      .finally(function () { uploadButton.disabled = false; });
  });

  createFolderButton.addEventListener('click', function () {
    var nameInput = document.getElementById('blogMediaFolderName');
    var body = new FormData();
    body.append('csrf_token', config.csrfToken || '');
    body.append('folder', state.folder);
    body.append('name', nameInput.value.trim());
    createFolderButton.disabled = true;
    clearAlert();

    requestJson(config.folderUrl, { method: 'POST', body: body, credentials: 'same-origin' })
      .then(function (result) {
        if (!result.success) { showAlert(result.error || strings.genericError); return; }
        nameInput.value = '';
        loadMedia();
        showAlert(result.message || '', 'success');
      })
      .catch(function () { showAlert(strings.genericError); })
      .finally(function () { createFolderButton.disabled = false; });
  });

  useUrlButton.addEventListener('click', function () {
    var urlInput = document.getElementById('blogMediaExternalUrl');
    var value = urlInput.value.trim();

    try {
      var parsed = new URL(value);
      if (parsed.protocol !== 'http:' && parsed.protocol !== 'https:') { throw new Error('scheme'); }
    } catch (error) {
      showAlert(strings.invalidUrl || strings.genericError);
      return;
    }

    chooseImage(value);
  });
})();
