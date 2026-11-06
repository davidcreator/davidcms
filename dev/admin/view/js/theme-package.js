/* Frente BF — Gerenciador de temas: import por arquivo + inspecao do pacote.
   CORRECAO: o botao "Importar tema" agora ABRE o painel (antes era apenas uma
   ancora para um <details> fechado, sem evento visivel).
   SOMENTE LEITURA no cliente: FileReader -> textarea. Sem upload em disco,
   fetch, eval, Function, innerHTML, document.write, storage ou rede.
   O POST continua `theme_package` (string) para design/theme.import; TODA a
   validacao de seguranca (checksum hash_equals, allowlists, ACL, CSRF)
   permanece em ThemePackage::decode(), no servidor. */
(function (document, window) {
  'use strict';
  var MAX = 524288, FMT = 'reamurcms-theme', VER = 1;
  var area    = document.getElementById('theme-package-json');
  var panel   = document.getElementById('theme-import');
  var trigger = document.querySelector('[data-theme-import-open]');

  /* 1. Evento real do botao — registrado ANTES do early-return do FileReader. */
  if (trigger && panel) {
    trigger.addEventListener('click', function (e) {
      e.preventDefault();
      if (typeof panel.open === 'boolean') { panel.open = true; }
      if (panel.scrollIntoView) { panel.scrollIntoView({ block: 'start' }); }
      if (area && area.focus) { area.focus(); }
    });
  }

  var input  = document.getElementById('theme-package-file');
  var drop   = document.querySelector('[data-theme-package-drop]');
  var prev   = document.querySelector('[data-theme-package-preview]');
  var status = document.querySelector('[data-theme-package-status]');
  if (!input || !area || !window.FileReader) { return; }

  var ALLOW_TPL = (drop && drop.getAttribute('data-template-keys') || '').split(',').filter(Boolean);
  var ALLOW_SCENE = (drop && drop.getAttribute('data-scenes') || '').split(',').filter(Boolean);
  function msg(key, fallback) {
    return (drop && drop.getAttribute('data-msg-' + key)) || fallback;
  }
  function put(name, value) {
    var el = prev && prev.querySelector('[data-p-' + name + ']');
    if (el) { el.textContent = String(value == null ? '' : value); }
  }
  function putList(sel, values, limit) {
    var ul = prev && prev.querySelector(sel);
    if (!ul) { return; }
    while (ul.firstChild) { ul.removeChild(ul.firstChild); }
    values.slice(0, limit).forEach(function (v) {
      var li = document.createElement('li');
      li.textContent = String(v);
      ul.appendChild(li);
    });
    if (values.length > limit) {
      var more = document.createElement('li');
      more.textContent = '+ ' + (values.length - limit) + ' ...';
      ul.appendChild(more);
    }
  }
  function clearPreview() {
    if (prev) { prev.hidden = true; }
    ['name','slug','version','parent','file','size','twig-count','twig-status',
     'three-scene','three-slot','three-status','scene-layers','scene-kinds','blocks'].forEach(function (k) { put(k, ''); });
    putList('[data-p-twig-list]', [], 0);
  }
  function setStatus(text, kind) {
    if (!status) { return; }
    status.textContent = text || '';
    status.setAttribute('data-state', kind || '');
    status.className = 'small mt-2 ' + (kind === 'error' ? 'text-danger' : 'text-body-secondary');
  }
  function keys(obj) {
    return (obj && typeof obj === 'object') ? Object.keys(obj) : [];
  }
  function inspect(pkg, file) {
    var cfg = (pkg.config && typeof pkg.config === 'object') ? pkg.config : {};
    var tpl = keys(cfg.templates);
    var three = (cfg.three && typeof cfg.three === 'object') ? cfg.three : {};
    var scene = String(three.three_scene || three.scene || cfg.scene || '');
    var slot  = String(three.three_slot || three.slot || '');
    var ks = (cfg.kaz_scene && typeof cfg.kaz_scene === 'object') ? cfg.kaz_scene : {};
    var layers = Array.isArray(ks.layers) ? ks.layers : [];
    var kinds = [];
    layers.forEach(function (l) {
      var k = l && l.kind ? String(l.kind) : '';
      if (k && kinds.indexOf(k) === -1) { kinds.push(k); }
    });
    var blocks = [];
    ['tokens','conditions','sections','components','schedule','media','sites'].forEach(function (k) {
      var v = cfg[k];
      if (!v) { return; }
      if ((Array.isArray(v) && v.length) || (!Array.isArray(v) && keys(v).length)) { blocks.push(k); }
    });

    put('name', pkg.name); put('slug', pkg.slug);
    put('version', pkg.format + ' v' + pkg.version);
    put('parent', pkg.parent || msg('none', '(tema base)'));
    put('file', file.name || ''); put('size', file.size + ' bytes');

    /* Conteudo Twig */
    put('twig-count', tpl.length + ' ' + msg('fonts', 'fonte(s)'));
    var badTpl = tpl.filter(function (k) { return ALLOW_TPL.length && ALLOW_TPL.indexOf(k) === -1; });
    put('twig-status', tpl.length === 0 ? msg('absent', 'Ausente neste pacote')
      : (badTpl.length ? msg('twig-bad', 'Chave fora da allowlist') : msg('twig-ok', 'Todas allowlistadas')));
    putList('[data-p-twig-list]', tpl, 25);

    /* Conteudo ThreeJS */
    put('three-scene', scene || msg('absent', 'Ausente neste pacote'));
    put('three-slot', slot || '-');
    put('three-status', !scene ? msg('absent', 'Ausente neste pacote')
      : (ALLOW_SCENE.length && ALLOW_SCENE.indexOf(scene) === -1
        ? msg('three-bad', 'Cena fora da allowlist') : msg('three-ok', 'Cena allowlistada')));
    put('scene-layers', layers.length + ' (' + (ks.version || 1) + ')');
    put('scene-kinds', kinds.length ? kinds.join(', ') : '-');

    put('blocks', blocks.length ? blocks.join(', ') : msg('blocks-none', 'Nenhum bloco adicional'));
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
      area.value = raw;
      inspect(pkg, file);
      setStatus(msg('ready', 'Pacote pronto para importar como rascunho.'), 'ok');
    };
    reader.readAsText(file);
  }
  input.addEventListener('change', function () { handle(input.files && input.files[0]); });
  if (drop) {
    ['dragenter', 'dragover'].forEach(function (ev) {
      drop.addEventListener(ev, function (e) { e.preventDefault(); drop.setAttribute('data-drag', '1'); });
    });
    ['dragleave', 'drop'].forEach(function (ev) {
      drop.addEventListener(ev, function (e) { e.preventDefault(); drop.removeAttribute('data-drag'); });
    });
    drop.addEventListener('drop', function (e) {
      var dt = e.dataTransfer;
      if (!dt || !dt.files || !dt.files.length) { return; }
      handle(dt.files[0]);
    });
  }
  area.addEventListener('input', function () { clearPreview(); setStatus('', ''); });
})(document, window);
