/* Frente BC — import de pacote por ARQUIVO (recorte A).
   SOMENTE LEITURA no cliente: FileReader -> textarea. Nao ha upload em disco,
   fetch, eval, Function, innerHTML, document.write, storage ou rede.
   O POST continua enviando `theme_package` (string) para design/theme.import;
   TODA validacao de seguranca (checksum hash_equals, allowlists, ACL, CSRF)
   permanece no servidor, em ThemePackage::decode().
   Sem JS, colar o JSON na textarea continua funcionando. */
(function (document, window) {
  'use strict';
  var MAX = 524288;                 // ThemePackage::MAX_BYTES (512 KiB)
  var FMT = 'reamurcms-theme';      // ThemePackage::FORMAT
  var VER = 1;                      // ThemePackage::VERSION
  var input  = document.getElementById('theme-package-file');
  var area   = document.getElementById('theme-package-json');
  var drop   = document.querySelector('[data-theme-package-drop]');
  var prev   = document.querySelector('[data-theme-package-preview]');
  var status = document.querySelector('[data-theme-package-status]');
  if (!input || !area || !window.FileReader) { return; }

  function msg(key, fallback) {
    return (drop && drop.getAttribute('data-msg-' + key)) || fallback;
  }
  function put(selector, value) {
    var el = prev && prev.querySelector('[data-p-' + selector + ']');
    if (el) { el.textContent = String(value == null ? '' : value); }
  }
  function clearPreview() {
    if (prev) { prev.hidden = true; }
    ['name','slug','version','parent','file','size'].forEach(function (k) { put(k, ''); });
  }
  function setStatus(text, kind) {
    if (!status) { return; }
    status.textContent = text || '';
    status.setAttribute('data-state', kind || '');
    status.className = 'small mt-2 ' + (kind === 'error' ? 'text-danger' : 'text-body-secondary');
  }
  function showPreview(pkg, file) {
    if (!prev) { return; }
    put('name', pkg.name);
    put('slug', pkg.slug);
    put('version', pkg.format + ' v' + pkg.version);
    put('parent', pkg.parent || msg('none', '(tema base)'));
    put('file', file.name || '');
    put('size', file.size + ' bytes');
    prev.hidden = false;
  }
  function handle(file) {
    area.value = '';
    clearPreview();
    if (!file) { setStatus('', ''); return; }
    if (file.size > MAX) { setStatus(msg('size', 'Arquivo maior que 512 KiB.'), 'error'); return; }
    var name = String(file.name || '');
    if (!/\.json$/i.test(name) && file.type !== 'application/json') {
      setStatus(msg('type', 'Use um arquivo .json.'), 'error'); return;
    }
    var reader = new window.FileReader();
    reader.onerror = function () { setStatus(msg('json', 'JSON invalido.'), 'error'); };
    reader.onload = function () {
      var raw = String(reader.result || '');
      var pkg;
      try { pkg = JSON.parse(raw); } catch (e) {
        setStatus(msg('json', 'JSON invalido.'), 'error'); return;
      }
      if (!pkg || typeof pkg !== 'object' || pkg.format !== FMT ||
          Number(pkg.version) !== VER || typeof pkg.name !== 'string' || pkg.name === '' ||
          typeof pkg.slug !== 'string' || !/^[a-z0-9_-]{1,48}$/.test(pkg.slug) ||
          !pkg.config || typeof pkg.config !== 'object' ||
          typeof pkg.checksum !== 'string' || !/^[0-9a-f]{64}$/.test(pkg.checksum)) {
        setStatus(msg('format', 'Pacote incompativel (formato/versao/checksum).'), 'error'); return;
      }
      area.value = raw;                 // mesmo contrato do POST, sem alteracao de backend
      showPreview(pkg, file);
      setStatus(msg('ready', 'Pacote pronto para importar como rascunho.'), 'ok');
    };
    reader.readAsText(file);
  }

  input.addEventListener('change', function () {
    handle(input.files && input.files[0]);
  });
  if (drop) {
    ['dragenter', 'dragover'].forEach(function (ev) {
      drop.addEventListener(ev, function (e) {
        e.preventDefault();
        drop.setAttribute('data-drag', '1');
      });
    });
    ['dragleave', 'drop'].forEach(function (ev) {
      drop.addEventListener(ev, function (e) {
        e.preventDefault();
        drop.removeAttribute('data-drag');
      });
    });
    drop.addEventListener('drop', function (e) {
      var dt = e.dataTransfer;
      if (!dt || !dt.files || !dt.files.length) { return; }
      handle(dt.files[0]);
    });
  }
  area.addEventListener('input', function () {
    clearPreview();
    setStatus('', '');
  });
})(document, window);
