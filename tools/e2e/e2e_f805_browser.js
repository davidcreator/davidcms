/* Fase 8 / Tarefa 05 — Tool E2E assistido no navegador nativo.
 * Não é asset da aplicação. O CLI publica uma cópia temporária, com permissão
 * curta e nonce, APENAS no laboratório local descartável. Sem CDN/dependências.
 * Importável pelo Node somente para testar os validadores; não simula um passe
 * de navegador. Nenhum fluxo/gravação começa apenas por carregar este arquivo.
 */
(function (root, factory) {
  'use strict';
  var api = factory(root);
  if (typeof module === 'object' && module.exports) { module.exports = api; }
  else { root.ReamurF805 = api; }
})(typeof window === 'object' ? window : globalThis, function (root) {
  'use strict';
  var KIND = 'reamur-f805-disposable';
  var CONFIRM = 'YES_WRITE_REAMUR_TEST';
  var BASE_COMMIT = 'f4270d0cba86a2b9b79cbbbb9f7f37655b3d646a';
  var TTL = 15 * 60 * 1000;
  var lastReport = null;
  var active = false;
  var PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAIAAAACCAYAAABytg0kAAAAEUlEQVR4nGOQqgj4D8IMMAYAPqgHhR+OGvoAAAAASUVORK5CYII=';
  var CODES = ['en-us', 'pt-br', 'es-eu'];
  var PHRASES = { 'en-us': 'English body & links.', 'pt-br': 'Ação, edição e português: ç, ã, €.', 'es-eu': 'Español: edición, ñ y énfasis.' };

  function ensure(condition, message) { if (!condition) { throw new Error(message); } }
  function normalizeBase(value) {
    ensure(typeof value === 'string' && value.length > 0, 'Defina B, a URL raiz do laboratório.');
    var url = new URL(value);
    ensure(['http:', 'https:'].indexOf(url.protocol) >= 0, 'B precisa usar HTTP(S).');
    ensure(['localhost', '127.0.0.1', '[::1]'].indexOf(url.hostname) >= 0, 'B precisa ser loopback; servidor remoto/produção não é permitido.');
    ensure(!url.username && !url.password && !url.search && !url.hash, 'B não pode ter credenciais, query ou fragmento.');
    ensure(!/%|\\/.test(url.pathname) && /^\/[A-Za-z0-9_./-]*$/.test(url.pathname), 'Caminho de B não canônico.');
    ensure(!/\/(admin|catalog|install)\/?$/i.test(url.pathname), 'B deve apontar à raiz dev, não à aplicação admin/catalog/install.');
    return url.origin + url.pathname.replace(/\/+$/, '') + '/';
  }
  function nonceValid(nonce) { return typeof nonce === 'string' && /^[a-f0-9]{32}$/.test(nonce); }
  function runIdFor(nonce) { ensure(nonceValid(nonce), 'Nonce inválido.'); return 'f805-' + nonce.slice(0, 16); }
  function scopedUrl(candidate, base) {
    var normalized = normalizeBase(base);
    var url = new URL(candidate, normalized);
    var baseUrl = new URL(normalized);
    ensure(url.origin === baseUrl.origin && url.pathname.indexOf(baseUrl.pathname) === 0, 'URL fora do laboratório autorizado.');
    ensure(!url.username && !url.password && !/%(?:2e|2f|5c)|\\/i.test(url.pathname), 'URL não canônica.');
    return url;
  }
  function routeOf(candidate, base) {
    var url = scopedUrl(candidate, base);
    var prefix = new URL(normalizeBase(base)).pathname + 'admin/';
    ensure(url.pathname.indexOf(prefix) === 0, 'Rota fora do admin.');
    var relative = url.pathname.slice(prefix.length);
    if (relative === 'index.php' || relative === '') { return url.searchParams.get('route') || ''; }
    return decodeURIComponent(relative).replace(/\/$/, '');
  }
  function postIdOf(candidate, base, verb) {
    ensure(verb === 'edit' || verb === 'delete', 'Operação de post inválida.');
    var match = routeOf(candidate, base).match(new RegExp('^blog/blog\\.' + verb + '\\|([1-9][0-9]*)$'));
    ensure(match && Number.isSafeInteger(Number(match[1])), 'ID/rota de post inválidos; nunca usar ID presumido.');
    return Number(match[1]);
  }
  function ownedImage(candidate, runId) {
    ensure(/^f805-[a-f0-9]{16}$/.test(runId), 'Identificador do ensaio inválido.');
    var prefix = 'view/image/blog/' + runId + '/';
    ensure(typeof candidate === 'string' && candidate.indexOf(prefix) === 0, 'Imagem fora da pasta deste ensaio.');
    var filename = candidate.slice(prefix.length);
    ensure(/^[A-Za-z0-9][A-Za-z0-9._-]*\.png$/.test(filename) && filename.indexOf('..') < 0, 'Nome de imagem inesperado.');
    return filename;
  }
  function validatePermit(permit, base, nonce, now) {
    ensure(nonceValid(nonce), 'Nonce inválido.');
    ensure(permit && permit.kind === KIND && permit.nonce === nonce, 'Permissão ausente ou de outro ensaio.');
    ensure(permit.base === normalizeBase(base) && permit.database === 'reamur_test', 'Permissão não corresponde ao laboratório descartável.');
    ensure(permit.runId === runIdFor(nonce), 'Identificador da permissão inválido.');
    ensure(Number.isSafeInteger(permit.createdAt) && permit.expiresAt - permit.createdAt === TTL, 'Prazo da permissão inválido.');
    ensure(permit.createdAt <= now + 60000 && now < permit.expiresAt, 'Permissão vencida/futura. Prepare novamente.');
    var names = ['admin/view/js/admin.js', 'admin/view/js/blog-media.js', 'admin/view/js/ckeditor/ckeditor.js'];
    ensure(permit.hashes && Object.keys(permit.hashes).length === names.length, 'Manifesto de assets inválido.');
    names.forEach(function (name) { ensure(/^[a-f0-9]{64}$/.test(permit.hashes[name] || ''), 'Hash de asset inválido.'); });
    return permit;
  }
  function redact(message) {
    return String(message).replace(/((?:csrf_?token|user_?token|password|senha|nonce)["']?\s*[=:]\s*["']?)[^\s&"'<>]+/gi, '$1[REDACTED]');
  }
  function routeUrl(base, route, params) {
    var url = new URL('admin/index.php', normalizeBase(base));
    url.searchParams.set('route', route);
    Object.keys(params || {}).forEach(function (key) { url.searchParams.set(key, params[key]); });
    return url.href;
  }
  function expected(runId, code, version) {
    var marker = runId + ' ' + code + ' v' + version;
    return {
      title: 'F8-05 ' + marker,
      keyword: runId + '-' + code + '-v' + version,
      metaTitle: 'Meta ' + marker,
      metaDescription: 'Descrição ' + marker,
      html: '<h2>' + marker + '</h2><p>' + PHRASES[code] + ' <strong>negrito-' + version + '</strong> <a href="#f805">link</a></p><ul><li>item-' + version + '</li></ul>',
      marker: marker
    };
  }
  function assertPayload(payload, rows, runId, version, image) {
    ensure(payload && payload.image === image && payload.published === false, 'Submit não preservou a capa/rascunho.');
    ensure(payload.csrfPresent, 'Submit sem CSRF.');
    rows.forEach(function (row) {
      var wanted = expected(runId, row.code, version);
      ensure(payload.titles[row.id] === wanted.title && payload.keywords[row.id] === wanted.keyword, 'Submit alterou título/keyword multilíngue.');
      ensure(typeof payload.bodies[row.id] === 'string' && payload.bodies[row.id].indexOf(wanted.marker) >= 0, 'autoUpdateElement não enviou o conteúdo atual do editor.');
    });
  }

  async function run(options) {
    options = options || {};
    ensure(options.confirm === CONFIRM, 'Gravação bloqueada: confirmação explícita ausente.');
    ensure(!active, 'Já existe um ensaio em andamento nesta janela.');
    var base = normalizeBase(options.base);
    ensure(nonceValid(options.nonce), 'Nonce inválido.');
    ensure(root.document && typeof root.open === 'function', 'Execute no navegador do laboratório, após fazer login.');
    ensure(scopedUrl(root.location.href, base).pathname.indexOf(new URL(base).pathname + 'admin/') === 0, 'Abra o admin do mesmo laboratório antes de executar.');
    ensure(root.crypto && root.crypto.subtle && root.TextEncoder && root.DataTransfer && root.AbortSignal && typeof root.AbortSignal.timeout === 'function', 'Requer navegador moderno em contexto seguro/localhost com WebCrypto, DataTransfer e AbortSignal.timeout.');
    var nonce = options.nonce;
    var runId = runIdFor(nonce);
    active = true;
    var child = null;
    var permit = null;
    var rows = [];
    var config = null;
    var imagePath = null;
    var postId = null;
    var postVerified = false;
    var writesStarted = false;
    var report = {
      task: 'Fase 8 / Tarefa 05', kind: 'native-browser-workflow', toolBaseCommit: BASE_COMMIT,
      runId: runId, startedAt: new Date().toISOString(), status: 'running', steps: [],
      artifacts: { folder: 'view/image/blog/' + runId, image: null, postId: null },
      cleanup: 'not-needed',
      notCovered: ['ACL negativa com outra conta', 'tamanho/MIME/traversal/paginação exaustivos', 'auditoria de segurança/licença do CKEditor', 'teste visual/acessibilidade completo']
    };
    async function phase(label, fn) {
      try { await fn(); report.steps.push({ label: label, status: 'pass' }); }
      catch (error) { report.steps.push({ label: label, status: 'fail', error: redact(error.message || error) }); throw error; }
    }
    function freshPermit() { ensure(permit && Date.now() < permit.expiresAt, 'Permissão venceu; nenhuma nova gravação foi autorizada.'); }
    function page() {
      ensure(child && !child.closed, 'A janela do ensaio foi fechada.');
      if (child.location.href !== 'about:blank') { scopedUrl(child.location.href, base); }
      return child.document;
    }
    async function waitFor(check, label, timeout) {
      var until = Date.now() + (timeout || 30000);
      while (Date.now() < until) {
        if (check()) { return; }
        await new Promise(function (resolve) { root.setTimeout(resolve, 100); });
      }
      throw new Error('Tempo excedido: ' + label + '. Veja a janela do ensaio; não tente reenviar automaticamente.');
    }
    async function navigate(url) {
      scopedUrl(url, base);
      var previous = child.document;
      child.location.href = url;
      await waitFor(function () { return page() !== previous && page().readyState === 'complete'; }, 'navegação');
    }
    function find(selector) { var el = page().querySelector(selector); ensure(el, 'Controle obrigatório ausente: ' + selector); return el; }
    function fire(el, type) { el.dispatchEvent(new child.Event(type, { bubbles: true })); }
    function fill(el, value) { el.value = value; fire(el, 'input'); fire(el, 'change'); }
    async function showModal(selector, trigger) {
      var modal = find(selector);
      ensure(!modal.classList.contains('show'), 'Modal já aberto; não interagir durante o ensaio.');
      var shown = false;
      var onShown = function () { shown = true; };
      // A classe .show aparece ANTES de shown.bs.modal. Aguardar o evento é
      // essencial: o handler real da biblioteca só então reseta folder/search.
      modal.addEventListener('shown.bs.modal', onShown, { once: true });
      try {
        trigger.click();
        await waitFor(function () { return shown && modal.classList.contains('show'); }, 'modal totalmente aberto');
      } finally { modal.removeEventListener('shown.bs.modal', onShown); }
    }
    async function jsonRequest(url, body) {
      var target = scopedUrl(url, base);
      if (body) { freshPermit(); }
      var response = await root.fetch(target.href, {
        method: body ? 'POST' : 'GET', credentials: 'same-origin', redirect: 'error', cache: 'no-store', signal: root.AbortSignal.timeout(30000),
        headers: body ? { 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8' } : { Accept: 'application/json' },
        body: body ? new URLSearchParams(body).toString() : undefined
      });
      ensure(response.ok && /application\/json/i.test(response.headers.get('Content-Type') || ''), 'Endpoint não devolveu JSON válido; verifique sessão/permissão.');
      return response.json();
    }
    function configFromPage() {
      var result = JSON.parse(find('#blogMediaModal').getAttribute('data-media-config'));
      var mapping = { listUrl: 'mediaList', uploadUrl: 'mediaUpload', folderUrl: 'mediaFolder', deleteUrl: 'mediaDelete' };
      Object.keys(mapping).forEach(function (key) { ensure(routeOf(result[key], base) === 'blog/blog.' + mapping[key], 'Contrato de endpoint inesperado.'); });
      ensure(typeof result.csrfToken === 'string' && result.csrfToken.length > 0, 'CSRF da biblioteca ausente.');
      ensure(result.csrfToken === find('form.form-layout input[name="csrf_token"]').value, 'CSRF do formulário/biblioteca divergentes.');
      ensure(scopedUrl(result.catalogBase, base).pathname === new URL(base).pathname + 'catalog/', 'Base da capa fora do catálogo local.');
      return result;
    }
    async function readyEditors() {
      await waitFor(function () {
        var tas = Array.from(page().querySelectorAll('textarea[data-rte]'));
        return tas.length === 3 && child.CKEDITOR && tas.every(function (ta) { return child.CKEDITOR.instances[ta.id] && child.CKEDITOR.instances[ta.id].status === 'ready'; });
      }, 'três editores reais instanceReady');
      rows = CODES.map(function (code) {
        var tab = find('#blogLangTabs [data-lang-tab="' + code + '"]');
        var pane = find(tab.getAttribute('data-bs-target'));
        var ta = pane.querySelector('textarea[data-rte]');
        ensure(ta && /^input-description-[1-9][0-9]*$/.test(ta.id), 'Textarea por idioma incompatível.');
        var id = ta.id.slice('input-description-'.length);
        ensure(ta.name === 'blog_description[' + id + '][description]', 'Name do corpo divergente do contrato PHP.');
        var editor = child.CKEDITOR.instances[ta.id];
        var locale = (page().documentElement.lang || 'en-us').toLowerCase().replace(/_/g, '-');
        var lang = locale === 'pt-br' ? 'pt-br' : locale === 'es-eu' ? 'es' : 'en';
        ensure(editor.config.customConfig === '' && editor.langCode === lang, 'Configuração/idioma real do CKEditor incompatível.');
        ensure(editor.config.autoUpdateElement === true, 'autoUpdateElement não está habilitado.');
        ensure(editor.container && editor.container.$.querySelector('.cke_button__source') && editor.container.$.querySelector('.cke_button__bold'), 'Toolbar real não contém Source/Bold.');
        return { code: code, id: id, tab: tab, pane: pane, ta: ta, editor: editor };
      });
      var legacy = child.performance.getEntriesByType('resource').some(function (entry) {
        return /\/ckeditor\/(?:config\.js(?:[?#]|$)|plugins\/(?:reamur|codemirror)\/)/i.test(entry.name);
      });
      ensure(!legacy, 'O navegador carregou configuração/plugin legado indevido.');
      ensure(child.bootstrap && child.bootstrap.Modal && child.bootstrap.Tab, 'Bootstrap local não inicializado.');
      config = configFromPage();
      report.editorVersion = child.CKEDITOR.version;
      report.editorLanguage = rows[0].editor.langCode;
    }
    async function fillPost(version) {
      for (var row of rows) {
        row.tab.click();
        await waitFor(function () { return row.pane.classList.contains('active'); }, 'tab de conteúdo');
        var wanted = expected(runId, row.code, version);
        fill(find('#input-title-' + row.id), wanted.title);
        fill(find('#input-keyword-' + row.id), wanted.keyword);
        fill(find('#input-meta-title-' + row.id), wanted.metaTitle);
        fill(find('#input-meta-description-' + row.id), wanted.metaDescription);
        await new Promise(function (resolve, reject) {
          var timer = root.setTimeout(function () { reject(new Error('setData não concluiu no editor real.')); }, 30000);
          row.editor.setData(wanted.html, { callback: function () { root.clearTimeout(timer); resolve(); } });
        });
      }
      var en = rows[0];
      en.tab.click();
      await waitFor(function () { return en.pane.classList.contains('active'); }, 'tab en-us');
      var sourceButton = en.editor.container.$.querySelector('.cke_button__source');
      sourceButton.click();
      await waitFor(function () { return en.editor.mode === 'source'; }, 'modo Source');
      var sourceArea = en.editor.container.$.querySelector('textarea.cke_source');
      ensure(sourceArea && sourceArea.value.indexOf(expected(runId, en.code, version).marker) >= 0, 'Source não contém o HTML corrente.');
      fill(sourceArea, sourceArea.value + '\n<p>source-v' + version + '</p>');
      sourceButton.click();
      await waitFor(function () { return en.editor.mode === 'wysiwyg'; }, 'retorno do Source');
      var status = find('#input-status'); status.checked = false; fire(status, 'change');
    }
    async function savePost(version) {
      freshPermit();
      var form = find('form.form-layout');
      var action = routeOf(form.action, base);
      ensure(action === (postId ? 'blog/blog.edit|' + postId : 'blog/blog.add'), 'Action inesperada; submit bloqueado.');
      ensure(form.checkValidity(), 'Formulário nativo inválido; nenhuma tentativa de reenviar.');
      var captured = null;
      form.addEventListener('formdata', function (event) {
        captured = { image: event.formData.get('image'), published: event.formData.has('status'), csrfPresent: Boolean(event.formData.get('csrf_token')), titles: {}, keywords: {}, bodies: {} };
        rows.forEach(function (row) {
          captured.titles[row.id] = event.formData.get('blog_description[' + row.id + '][title]');
          captured.keywords[row.id] = event.formData.get('blog_description[' + row.id + '][keyword]');
          captured.bodies[row.id] = event.formData.get('blog_description[' + row.id + '][description]');
        });
      }, { once: true });
      var previous = page();
      // Não chamar updateElement(): o submit NATIVO deve acionar o próprio CKEditor.
      form.querySelector('button[type="submit"]').click();
      await waitFor(function () { return page() !== previous && page().readyState === 'complete'; }, 'salvar/redirect');
      ensure(routeOf(child.location.href, base) === 'blog/blog', 'Salvar não redirecionou à lista.');
      assertPayload(captured, rows, runId, version, imagePath);
    }
    async function ownRow() {
      await navigate(routeUrl(base, 'blog/blog', { filter_title: runId, filter_status: 'all' }));
      var found = Array.from(page().querySelectorAll('table tbody tr')).filter(function (tr) {
        var link = tr.querySelector('a.link-plain'); return link && link.textContent.indexOf(runId) >= 0;
      });
      ensure(found.length === 1, 'Não foi encontrado exatamente um post com o identificador deste ensaio; não presumir ID.');
      var row = found[0];
      ensure(row.querySelector('.badge-status--draft'), 'O post de teste não permaneceu em rascunho.');
      return row;
    }
    async function reopen(version) {
      var row = await ownRow();
      var href = row.querySelector('a.link-plain').href;
      var foundId = postIdOf(href, base, 'edit');
      if (postId) { ensure(foundId === postId, 'Edição criou/encontrou outro post.'); }
      postId = foundId; report.artifacts.postId = postId;
      await navigate(href);
      // Segunda carga GET: não aceitar apenas valores ecoados após um POST.
      await navigate(child.location.href);
      await readyEditors();
      rows.forEach(function (language) {
        var wanted = expected(runId, language.code, version);
        ensure(find('#input-title-' + language.id).value === wanted.title, 'Título não persistido.');
        ensure(find('#input-keyword-' + language.id).value === wanted.keyword, 'Keyword não persistida.');
        ensure(find('#input-meta-title-' + language.id).value === wanted.metaTitle && find('#input-meta-description-' + language.id).value === wanted.metaDescription, 'Metadados não persistidos.');
        var fragment = root.document.createElement('template'); fragment.innerHTML = language.editor.getData();
        var content = fragment.content;
        ensure(content.querySelector('h2') && content.querySelector('h2').textContent === wanted.marker, 'Corpo/idioma/versão não persistidos.');
        ensure(content.querySelector('p').textContent.indexOf(PHRASES[language.code]) >= 0, 'Acentos/entidades não preservados.');
        ensure(content.querySelector('strong').textContent === 'negrito-' + version && content.querySelector('li').textContent === 'item-' + version && content.querySelector('a').getAttribute('href') === '#f805', 'Formatação HTML não preservada.');
        if (language.code === 'en-us') { ensure(content.textContent.indexOf('source-v' + version) >= 0, 'Edição pelo Source não persistiu.'); }
      });
      ensure(find('#input-image').value === imagePath && !find('#input-status').checked, 'Capa/rascunho não persistidos.');
      await previewReady();
      postVerified = true;
    }
    async function previewReady() {
      await waitFor(function () { var img = page().querySelector('#coverPreview img'); return img && img.complete && img.naturalWidth > 0 && !img.hidden; }, 'preview real da capa');
      var image = find('#coverPreview img');
      ensure(scopedUrl(image.src, base).href === new URL(imagePath, config.catalogBase).href, 'URL real do preview divergente.');
    }
    async function openLibrary() {
      await showModal('#blogMediaModal', find('[data-bs-target="#blogMediaModal"]'));
      await waitFor(function () { return Array.from(find('#blogMediaFolders').querySelectorAll('button[aria-label]')).some(function (button) { return button.getAttribute('aria-label') === runId; }); }, 'pasta própria na listagem');
      var folder = Array.from(find('#blogMediaFolders').querySelectorAll('button[aria-label]')).find(function (button) { return button.getAttribute('aria-label') === runId; });
      folder.click();
      await waitFor(function () { var last = find('#blogMediaBreadcrumb').lastElementChild; return last && last.textContent === runId; }, 'navegação da pasta');
    }
    function mediaList(folder) {
      var url = new URL(config.listUrl, base); url.searchParams.set('folder', folder); url.searchParams.set('page', '1'); url.searchParams.set('search', '');
      return jsonRequest(url.href);
    }

    try {
      // Abrir sincronamente antes do primeiro await. Popups devem estar liberados.
      child = root.open('about:blank', '_blank');
      ensure(child, 'Libere popup somente para o laboratório e execute novamente.');
      await phase('Permissão curta, origem e hashes dos três assets servidos', async function () {
        var permitUrl = new URL('admin/view/js/f805-' + nonce + '.json', base);
        var response = await root.fetch(permitUrl.href, { credentials: 'same-origin', cache: 'no-store', redirect: 'error', signal: root.AbortSignal.timeout(30000) });
        ensure(response.ok, 'Permissão temporária não encontrada no servidor local.');
        permit = validatePermit(await response.json(), base, nonce, Date.now());
        for (var name of Object.keys(permit.hashes)) {
          var asset = await root.fetch(new URL(name, base).href, { credentials: 'same-origin', cache: 'no-store', redirect: 'error', signal: root.AbortSignal.timeout(30000) });
          ensure(asset.ok, 'Asset local indisponível.');
          var digest = await root.crypto.subtle.digest('SHA-256', await asset.arrayBuffer());
          var hex = Array.from(new Uint8Array(digest)).map(function (b) { return b.toString(16).padStart(2, '0'); }).join('');
          ensure(hex === permit.hashes[name], 'Asset servido não corresponde ao arquivo autorizado; confira deploy/cache.');
        }
        report.assetHashes = permit.hashes;
      });
      await phase('Sessão existente, três CKEditors reais, idioma e toolbar local', async function () {
        await navigate(routeUrl(base, 'blog/blog.add'));
        ensure(page().querySelector('form.form-layout'), 'Faça login com modify em blog/blog; o Tool não pede nem armazena credenciais.');
        await readyEditors();
      });
      await phase('Conteúdo en/pt-BR/es, metadados e edição real pelo Source', async function () { await fillPost(1); });
      await phase('Criar pasta própria pelo modal da biblioteca', async function () {
        var listing = await mediaList('');
        ensure(listing.success && !(listing.folders || []).some(function (f) { return f.folder === runId; }), 'Pasta do ensaio já existe; prepare outro nonce.');
        freshPermit(); writesStarted = true; report.cleanup = 'pending';
        await showModal('#blogMediaModal', find('[data-bs-target="#blogMediaModal"]'));
        find('#blogMediaFolderTab').click(); fill(find('#blogMediaFolderName'), runId); find('#blogMediaCreateFolder').click();
        await waitFor(function () { return Array.from(find('#blogMediaFolders').querySelectorAll('button[aria-label]')).some(function (b) { return b.getAttribute('aria-label') === runId; }); }, 'criação de pasta');
        find('#blogMediaBrowseTab').click();
        var folder = Array.from(find('#blogMediaFolders').querySelectorAll('button[aria-label]')).find(function (b) { return b.getAttribute('aria-label') === runId; });
        folder.click();
        await waitFor(function () { var last = find('#blogMediaBreadcrumb').lastElementChild; return last && last.textContent === runId; }, 'pasta criada');
      });
      await phase('CSRF: criação sem token recusada e ausência conferida', async function () {
        var denied = await jsonRequest(config.folderUrl, { folder: runId, name: 'csrf-probe' });
        ensure(denied.success === false, 'CSRF negativo falhou; inspecione a subpasta csrf-probe deste ensaio.');
        var listing = await mediaList(runId);
        ensure(listing.success && !(listing.folders || []).some(function (f) { return f.name === 'csrf-probe'; }), 'Uma pasta foi criada sem CSRF.');
      });
      await phase('Upload PNG pelo formulário nativo, eventos e preview real', async function () {
        freshPermit();
        var input = find('#input-image'); var events = { input: 0, change: 0 };
        input.addEventListener('input', function () { events.input++; }); input.addEventListener('change', function () { events.change++; });
        find('#blogMediaUploadTab').click();
        var bytes = Uint8Array.from(root.atob(PNG), function (c) { return c.charCodeAt(0); });
        var transfer = new child.DataTransfer(); transfer.items.add(new child.File([bytes], runId + '.png', { type: 'image/png' }));
        find('#blogMediaFile').files = transfer.files; fire(find('#blogMediaFile'), 'change'); find('#blogMediaUploadButton').click();
        await waitFor(function () { return input.value.indexOf('view/image/blog/' + runId + '/') === 0; }, 'upload');
        imagePath = input.value; ownedImage(imagePath, runId); report.artifacts.image = imagePath;
        ensure(events.input > 0 && events.change > 0, 'Upload não emitiu input/change.');
        await waitFor(function () { var modal = find('#blogMediaModal'); return !modal.classList.contains('show') && modal.getAttribute('aria-hidden') === 'true' && modal.style.display === 'none'; }, 'fechar modal após upload');
        await previewReady();
      });
      await phase('Selecionar o arquivo já enviado pela listagem do modal', async function () {
        fill(find('#input-image'), '');
        await openLibrary();
        var filename = ownedImage(imagePath, runId);
        await waitFor(function () { return Array.from(find('#blogMediaFiles').querySelectorAll('button')).some(function (b) { var label = b.querySelector('span[title]'); return label && label.title === filename; }); }, 'arquivo da biblioteca');
        var choose = Array.from(find('#blogMediaFiles').querySelectorAll('button')).find(function (b) { var label = b.querySelector('span[title]'); return label && label.title === filename; });
        choose.click();
        await waitFor(function () { var modal = find('#blogMediaModal'); return find('#input-image').value === imagePath && !modal.classList.contains('show') && modal.getAttribute('aria-hidden') === 'true' && modal.style.display === 'none'; }, 'seleção/fechamento');
        await previewReady();
      });
      await phase('Salvar novo rascunho pelo botão e conferir FormData nativo', async function () { await savePost(1); });
      await phase('Reabrir por ID descoberto e recarregar: dados/capa persistidos', async function () { await reopen(1); });
      await phase('Editar o mesmo post e salvar conteúdo v2 sem updateElement manual', async function () { await fillPost(2); await savePost(2); });
      await phase('Reabrir/recarregar: três idiomas v2, HTML, capa e rascunho', async function () { await reopen(2); });
      await phase('Arquivo referenciado: leitura marca uso e servidor bloqueia exclusão', async function () {
        var listing = await mediaList(runId);
        var own = (listing.files || []).find(function (file) { return file.path === imagePath; });
        ensure(listing.success && own && own.referenced === true, 'Referência da capa não foi detectada.');
        var result = await jsonRequest(config.deleteUrl, { csrf_token: config.csrfToken, type: 'file', path: imagePath });
        ensure(result.success === false, 'Servidor permitiu excluir capa em uso.');
        var after = await mediaList(runId);
        ensure(after.success && (after.files || []).some(function (file) { return file.path === imagePath; }), 'Capa referenciada desapareceu.');
      });
      await phase('Excluir SOMENTE o post próprio pelo modal de delete e conferir ausência', async function () {
        freshPermit(); ensure(postVerified && postId, 'Propriedade do post não comprovada; exclusão bloqueada.');
        var row = await ownRow();
        ensure(postIdOf(row.querySelector('a.link-plain').href, base, 'edit') === postId, 'ID do ensaio mudou.');
        var form = row.querySelector('form[data-rms-confirm-delete]');
        ensure(form && postIdOf(form.action, base, 'delete') === postId && form.querySelector('input[name="csrf_token"]').value, 'Delete não pertence ao post próprio/sem CSRF.');
        ensure(page().querySelector('#rmsDeleteModal [data-rms-delete-confirm]'), 'Modal de delete não encontrado.');
        await showModal('#rmsDeleteModal', form.querySelector('button[type="submit"]'));
        var previous = page(); find('#rmsDeleteModal [data-rms-delete-confirm]').click();
        await waitFor(function () { return page() !== previous && page().readyState === 'complete'; }, 'exclusão do post próprio');
        await navigate(routeUrl(base, 'blog/blog', { filter_title: runId, filter_status: 'all' }));
        ensure(!Array.from(page().querySelectorAll('a.link-plain')).some(function (link) { return link.textContent.indexOf(runId) >= 0; }), 'Post próprio ainda aparece na lista.');
      });
      await phase('Excluir apenas PNG/pasta próprios após liberar a referência', async function () {
        ownedImage(imagePath, runId);
        var deleted = await jsonRequest(config.deleteUrl, { csrf_token: config.csrfToken, type: 'file', path: imagePath });
        ensure(deleted.success === true, 'Arquivo próprio não pôde ser removido.');
        var listing = await mediaList(runId);
        ensure(listing.success && !(listing.files || []).some(function (file) { return file.path === imagePath; }), 'Arquivo próprio ainda existe.');
        var folder = await jsonRequest(config.deleteUrl, { csrf_token: config.csrfToken, type: 'folder', path: 'view/image/blog/' + runId });
        ensure(folder.success === true, 'Pasta própria não pôde ser removida; inspecione-a, sem limpeza recursiva.');
        var after = await mediaList('');
        ensure(after.success && !(after.folders || []).some(function (f) { return f.folder === runId; }), 'Pasta própria ainda aparece.');
        report.cleanup = 'complete';
      });
      report.status = 'passed';
    } catch (error) {
      report.status = writesStarted ? 'failed' : 'blocked';
      report.error = redact(error.message || error);
      if (!writesStarted && child && !child.closed) { child.close(); }
      // Falha após gravação: preservar para inspeção. Nunca adivinhar ID, varrer
      // arquivos, executar SQL, apagar diretórios nem repetir POST automaticamente.
    } finally {
      report.finishedAt = new Date().toISOString();
      report.summary = { passed: report.steps.filter(function (s) { return s.status === 'pass'; }).length, failed: report.steps.filter(function (s) { return s.status === 'fail'; }).length };
      lastReport = report; active = false;
      root.console.info('Fase 8 / Tarefa 05 — resultado (sem cookies, senhas ou tokens)', report);
      if (report.cleanup === 'pending') { root.console.warn('Há artefatos próprios para inspeção: use o identificador e os caminhos do relatório. Remova post → arquivo → pasta pela UI. Não use limpeza ampla.'); }
    }
    return report;
  }
  function download() {
    ensure(lastReport && root.document && root.Blob, 'Nenhum relatório de navegador disponível.');
    var blob = new root.Blob([JSON.stringify(lastReport, null, 2) + '\n'], { type: 'application/json' });
    var url = root.URL.createObjectURL(blob); var link = root.document.createElement('a');
    link.href = url; link.download = 'F8-05-resultado-' + lastReport.runId + '.json'; link.click();
    root.setTimeout(function () { root.URL.revokeObjectURL(url); }, 1000);
  }
  return {
    version: '1.0', run: run, download: download,
    get lastReport() { return lastReport; },
    helpers: Object.freeze({ normalizeBase: normalizeBase, nonceValid: nonceValid, runIdFor: runIdFor, scopedUrl: scopedUrl, routeOf: routeOf, postIdOf: postIdOf, ownedImage: ownedImage, validatePermit: validatePermit, redact: redact, expected: expected, assertPayload: assertPayload, KIND: KIND, CONFIRM: CONFIRM, TTL: TTL })
  };
});
