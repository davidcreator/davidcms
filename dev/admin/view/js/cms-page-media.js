(function (global, document) {
  'use strict';

  function initialize() {
  const modal = document.getElementById('cmsPageMediaModal');
  if (!modal) return;
  let config = {};
  try { config = JSON.parse(modal.getAttribute('data-page-media-config') || '{}'); } catch (_) { return; }
  const strings = config.strings || {};
  const filesEl = document.getElementById('cmsPageMediaFiles');
  const emptyEl = document.getElementById('cmsPageMediaEmpty');
  const alertEl = document.getElementById('cmsPageMediaAlert');
  const searchEl = document.getElementById('cmsPageMediaSearch');
  const pageEl = document.getElementById('cmsPageMediaPage');
  const prevEl = document.getElementById('cmsPageMediaPrevious');
  const nextEl = document.getElementById('cmsPageMediaNext');
  const uploadInput = document.getElementById('cmsPageMediaUploadFile');
  const uploadButton = document.getElementById('cmsPageMediaUploadButton');
  const uploadHint = document.getElementById('cmsPageMediaUploadHint');
  if (!filesEl || !emptyEl || !searchEl || !pageEl || !prevEl || !nextEl || !uploadInput || !uploadButton) return;

  let activeEditor = null;
  let kind = 'image';
  let page = 1;
  let pages = 1;
  let searchTimer = null;
  let busy = false;
  let modalInstance = null;
  const accepted = {
    image: ['.jpg','.jpeg','.png','.gif','.webp'],
    audio: ['.mp3','.ogg','.wav'],
    video: ['.mp4','.webm','.ogv'],
    download: ['.pdf','.docx','.xlsx','.pptx','.odt','.ods','.csv','.txt']
  };

  function cleanUrl(value) {
    return String(value || '').replace(/&amp;/g, '&');
  }
  function endpoint(base, params) {
    const url = new URL(cleanUrl(base), global.location.href);
    Object.keys(params || {}).forEach((key) => url.searchParams.set(key, String(params[key])));
    return url.href;
  }
  function setAlert(message, isError) {
    alertEl.textContent = message || '';
    alertEl.classList.toggle('d-none', !message);
    alertEl.classList.toggle('alert-danger', !!isError);
    alertEl.classList.toggle('alert-info', !isError);
  }
  async function readJson(response) {
    let data;
    try { data = await response.json(); } catch (_) { throw new Error(strings.error || 'Resposta inválida do servidor.'); }
    if (!response.ok || !data || data.success !== true) throw new Error(data && data.error ? data.error : (strings.error || 'Não foi possível acessar a biblioteca.'));
    return data;
  }
  function updateKindControls() {
    modal.querySelectorAll('[data-media-kind]').forEach((button) => {
      const selected = button.dataset.mediaKind === kind;
      button.classList.toggle('active', selected);
      button.setAttribute('aria-selected', String(selected));
    });
    const suffixes = accepted[kind] || accepted.image;
    uploadInput.accept = suffixes.join(',');
    uploadInput.setAttribute('aria-label', (strings.upload || 'Enviar arquivo') + ' · ' + ((strings.tabs || {})[kind] || kind));
    uploadHint.textContent = (strings.uploadHint || {})[kind] || '';
  }
  function chooseMedia(path) {
    if (!activeEditor || typeof path !== 'string') return;
    if (typeof activeEditor.setSelectedAttribute !== 'function' || !activeEditor.setSelectedAttribute('src', path)) {
      setAlert(strings.error || 'Não foi possível aplicar o arquivo ao bloco selecionado.', true);
      return;
    }
    setAlert('', false);
    if (activeEditor.selectedId && typeof activeEditor.focusNode === 'function') activeEditor.focusNode(activeEditor.selectedId);
    if (modalInstance) modalInstance.hide();
  }
  function renderFiles(files) {
    filesEl.replaceChildren();
    (Array.isArray(files) ? files : []).forEach((file) => {
      if (!file || typeof file.path !== 'string' || typeof file.name !== 'string') return;
      const button = document.createElement('button');
      button.type = 'button';
      button.className = 'list-group-item list-group-item-action d-flex align-items-center gap-3';
      button.dataset.mediaPath = file.path;
      if (kind === 'image' && typeof file.url === 'string') {
        const image = document.createElement('img');
        image.src = file.url;
        image.alt = '';
        image.loading = 'lazy';
        image.width = 64;
        image.height = 48;
        image.style.objectFit = 'cover';
        image.style.flex = '0 0 64px';
        button.append(image);
      }
      const details = document.createElement('span');
      details.className = 'text-break';
      details.textContent = file.name + (Number.isFinite(Number(file.size)) ? ' · ' + Math.ceil(Number(file.size) / 1024) + ' KB' : '');
      button.append(details);
      filesEl.append(button);
    });
    emptyEl.textContent = filesEl.children.length ? '' : ((strings.emptyKind || {})[kind] || strings.empty || 'Nenhum arquivo encontrado.');
  }
  async function loadFiles() {
    if (busy) return;
    busy = true;
    filesEl.replaceChildren();
    emptyEl.textContent = strings.loading || 'Carregando…';
    setAlert('', false);
    try {
      const response = await fetch(endpoint(config.listUrl, {kind, search: searchEl.value.trim(), page}), {
        method: 'GET', credentials: 'same-origin', cache: 'no-store', headers: {'X-Requested-With':'XMLHttpRequest','Accept':'application/json'}
      });
      const data = await readJson(response);
      page = Math.max(1, Number(data.page) || 1);
      pages = Math.max(1, Number(data.pages) || 1);
      pageEl.textContent = (strings.page || 'Página %1$d de %2$d').replace('%1$d', String(page)).replace('%2$d', String(pages));
      prevEl.disabled = page <= 1;
      nextEl.disabled = page >= pages;
      renderFiles(data.files);
    } catch (error) {
      filesEl.replaceChildren();
      emptyEl.textContent = '';
      setAlert(error && error.message ? error.message : (strings.error || 'Erro ao carregar a biblioteca.'), true);
    } finally { busy = false; }
  }
  function openLibrary(editor) {
    activeEditor = editor || null;
    kind = 'image';
    page = 1;
    searchEl.value = '';
    updateKindControls();
    if (document.body) document.body.classList.add('rb-page-media-open');
    try {
      if (global.bootstrap && global.bootstrap.Modal) {
        modalInstance = global.bootstrap.Modal.getOrCreateInstance(modal);
        modalInstance.show();
      } else {
        modal.classList.add('show');
        modal.style.display = 'block';
        modal.removeAttribute('aria-hidden');
      }
    } catch (error) {
      modalInstance = null;
      modal.classList.add('show');
      modal.style.display = 'block';
      modal.removeAttribute('aria-hidden');
      setAlert(strings.error || 'A biblioteca abriu sem o componente visual do modal.', true);
    }
    loadFiles();
  }

  document.addEventListener('cms-page-media-open', (event) => {
    if (!event.detail || !event.detail.editor) return;
    event.preventDefault();
    try { openLibrary(event.detail.editor); }
    catch (error) { setAlert(strings.error || 'Não foi possível abrir a biblioteca de mídia.', true); }
  });
  modal.addEventListener('click', (event) => {
    const tab = event.target.closest('[data-media-kind]');
    if (tab && modal.contains(tab)) {
      kind = ['image','audio','video','download'].includes(tab.dataset.mediaKind) ? tab.dataset.mediaKind : 'image';
      page = 1;
      updateKindControls();
      loadFiles();
      return;
    }
    const file = event.target.closest('[data-media-path]');
    if (file && modal.contains(file)) chooseMedia(file.dataset.mediaPath);
  });
  searchEl.addEventListener('input', () => {
    global.clearTimeout(searchTimer);
    searchTimer = global.setTimeout(() => { page = 1; loadFiles(); }, 250);
  });
  prevEl.addEventListener('click', () => { if (page > 1) { page -= 1; loadFiles(); } });
  nextEl.addEventListener('click', () => { if (page < pages) { page += 1; loadFiles(); } });
  uploadButton.addEventListener('click', async () => {
    const file = uploadInput.files && uploadInput.files[0];
    if (!file || busy) { if (!file) setAlert(strings.error || 'Selecione um arquivo para enviar.', true); return; }
    const body = new FormData();
    body.append('kind', kind);
    body.append('file', file);
    body.append('csrf_token', String(config.csrfToken || ''));
    busy = true;
    uploadButton.disabled = true;
    uploadInput.disabled = true;
    uploadButton.textContent = strings.uploading || 'Enviando…';
    setAlert(strings.uploading || 'Enviando…', false);
    try {
      const response = await fetch(endpoint(config.uploadUrl, {}), {
        method: 'POST', credentials: 'same-origin', cache: 'no-store', body,
        headers: {'X-Requested-With':'XMLHttpRequest','Accept':'application/json'}
      });
      const data = await readJson(response);
      if (typeof data.path !== 'string') throw new Error(strings.error || 'O servidor não retornou o caminho do arquivo.');
      chooseMedia(data.path);
    } catch (error) {
      setAlert(error && error.message ? error.message : (strings.error || 'Falha ao enviar o arquivo.'), true);
    } finally {
      busy = false;
      uploadButton.disabled = false;
      uploadInput.disabled = false;
      uploadButton.textContent = strings.upload || 'Enviar arquivo';
      uploadInput.value = '';
    }
  });
  modal.addEventListener('hidden.bs.modal', () => { activeEditor = null; if (document.body) document.body.classList.remove('rb-page-media-open'); });
  updateKindControls();
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initialize, {once:true});
  else initialize();
})(window, document);
