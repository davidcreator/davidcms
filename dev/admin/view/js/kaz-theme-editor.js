/* KAZTheme-Editor — frente U (fundação visual).
   Editor de temas estruturado: canvas SVG + árvore de camadas + inspector.
   Apenas DOM puro: sem rede, codigo executavel do usuario, WebGL, Three.js,
   upload ou escrita remota. O estado da cena e
   browser-local (kaztheme-scene/v1), como os style presets do BDREditor.
   A conexão com o tema usa SOMENTE campos allowlistados do formulário. */
(function (document, window) {
  'use strict';
  var NS = 'http://www.w3.org/2000/svg';
  var VERSION = 1;
  var KINDS = ['rect', 'text', 'button', 'card', 'section'];
  var ROLES = ['none', 'primary', 'surface', 'section', 'component'];
  var GRID = 8;
  var W = 960, H = 540;
  var MAX_LAYERS = 48;

  var root = document.getElementById('kaz-theme-editor');
  if (!root) return;
  var canvas = root.querySelector('[data-kaz-canvas]');
  var layerList = root.querySelector('[data-kaz-layer-list]');
  var jsonBox = root.querySelector('[data-kaz-json]');
  var status = root.querySelector('[data-kaz-status]');
  var form = root.closest('form');
  if (!canvas || !layerList || !jsonBox) return;

  function say(t) { if (status) status.textContent = String(t); }
  function el(tag, cls, text) {
    var n = document.createElement(tag);
    if (cls) n.setAttribute('class', cls);
    if (text !== undefined && text !== null) n.textContent = String(text);
    return n;
  }
  function svg(tag, attrs) {
    var n = document.createElementNS(NS, tag);
    for (var k in attrs) if (Object.prototype.hasOwnProperty.call(attrs, k)) n.setAttribute(k, String(attrs[k]));
    return n;
  }
  function clear(n) { while (n.firstChild) n.removeChild(n.firstChild); }
  function num(v, lo, hi) { return typeof v === 'number' && isFinite(v) && v >= lo && v <= hi; }
  function hex(v) { return typeof v === 'string' && /^#[0-9a-fA-F]{6}$/.test(v); }
  function idOk(v) { return typeof v === 'string' && /^[a-z0-9_-]{1,48}$/.test(v); }
  function bindOk(v) { return v === '' || v === undefined || idOk(v); }
  function snap(v) { return Math.round(v / GRID) * GRID; }
  function clamp(v, lo, hi) { return Math.max(lo, Math.min(hi, v)); }
  function copy(o) { return JSON.parse(JSON.stringify(o)); }

  function validLayer(l) {
    return !!l && typeof l === 'object' && !Array.isArray(l)
      && idOk(l.id) && KINDS.indexOf(l.kind) >= 0 && ROLES.indexOf(l.role) >= 0
      && num(l.x, -2000, 2000) && num(l.y, -2000, 2000)
      && num(l.w, 8, 960) && num(l.h, 8, 540)
      && hex(l.fill) && num(l.radius, 0, 64) && num(l.fontSize, 8, 72)
      && typeof l.text === 'string' && l.text.length <= 80
      && typeof l.visible === 'boolean' && typeof l.locked === 'boolean'
      && bindOk(l.bindId);
  }
  function validScene(s) {
    if (!s || typeof s !== 'object' || s.version !== VERSION) return false;
    if (!s.canvas || !num(s.canvas.w, 320, 3840) || !num(s.canvas.h, 240, 2160)) return false;
    if (!Array.isArray(s.layers) || s.layers.length > MAX_LAYERS) return false;
    var seen = {};
    for (var i = 0; i < s.layers.length; i++) {
      var l = s.layers[i];
      if (!validLayer(l) || seen[l.id]) return false;
      seen[l.id] = true;
    }
    return true;
  }

  function fallback(cfg) {
    return {
      version: VERSION,
      canvas: { w: W, h: H, grid: GRID },
      layers: [
        { id: 'kaz-primary', kind: 'rect', role: 'primary', x: 40, y: 40, w: 200, h: 120,
          fill: cfg.primary || '#2563eb', radius: 12, text: 'Primary', fontSize: 16,
          visible: true, locked: false, bindId: '' },
        { id: 'kaz-surface', kind: 'card', role: 'surface', x: 40, y: 200, w: 320, h: 180,
          fill: cfg.surface || '#ffffff', radius: 12, text: 'Surface', fontSize: 16,
          visible: true, locked: false, bindId: '' },
        { id: 'kaz-title', kind: 'text', role: 'none', x: 400, y: 56, w: 480, h: 80,
          fill: '#0f172a', radius: 0, text: 'KAZTheme-Editor', fontSize: 32,
          visible: true, locked: false, bindId: '' },
        { id: 'kaz-section', kind: 'section', role: 'section', x: 400, y: 176, w: 220, h: 120,
          fill: '#e2e8f0', radius: 8, text: 'Section', fontSize: 16,
          visible: true, locked: false, bindId: cfg.sectionId || '' },
        { id: 'kaz-component', kind: 'button', role: 'component', x: 650, y: 176, w: 180, h: 64,
          fill: cfg.primary || '#2563eb', radius: 12, text: 'Button', fontSize: 16,
          visible: true, locked: false, bindId: cfg.componentId || '' }
      ]
    };
  }
  function initial() {
    var cfg = {
      primary: root.getAttribute('data-kaz-primary') || '#2563eb',
      surface: root.getAttribute('data-kaz-surface') || '#ffffff'
    };
    var raw = root.getAttribute('data-kaz-scene') || '';
    try {
      var s = JSON.parse(raw);
      if (validScene(s)) return s;
    } catch (e) { /* fail-closed: usa a cena derivada do config */ }
    return fallback(cfg);
  }

  var scene = initial();
  var selected = scene.layers.length ? scene.layers[0].id : '';
  var history = [copy(scene)];
  var cursor = 0;
  var drag = null;

  function commit() {
    history = history.slice(0, cursor + 1);
    history.push(copy(scene));
    if (history.length > 60) history.shift();
    cursor = history.length - 1;
  }
  function restore(i) {
    if (i < 0 || i >= history.length) return false;
    cursor = i; scene = copy(history[i]);
    if (!scene.layers.some(function (l) { return l.id === selected; }))
      selected = scene.layers.length ? scene.layers[0].id : '';
    render(); return true;
  }
  function layer(id) {
    for (var i = 0; i < scene.layers.length; i++) if (scene.layers[i].id === id) return scene.layers[i];
    return null;
  }

  /* ---- SVG: apenas createElementNS / setAttribute / textContent ---------- */
  function drawCanvas() {
    clear(canvas);
    canvas.setAttribute('viewBox', '0 0 ' + scene.canvas.w + ' ' + scene.canvas.h);
    canvas.setAttribute('role', 'img');
    canvas.setAttribute('aria-label', 'KAZTheme-Editor canvas');
    var defs = svg('defs', {});
    var pat = svg('pattern', { id: 'kaz-grid', width: GRID, height: GRID, patternUnits: 'userSpaceOnUse' });
    pat.appendChild(svg('path', { d: 'M ' + GRID + ' 0 L 0 0 0 ' + GRID, fill: 'none', stroke: '#e2e8f0', 'stroke-width': '1' }));
    defs.appendChild(pat); canvas.appendChild(defs);
    canvas.appendChild(svg('rect', { x: 0, y: 0, width: scene.canvas.w, height: scene.canvas.h, fill: 'url(#kaz-grid)' }));
    scene.layers.forEach(function (l) {
      if (!l.visible) return;
      var g = svg('g', { 'data-kaz-layer': l.id, 'class': 'kaz-g' + (l.id === selected ? ' kaz-g--selected' : '') });
      var shapeFill = l.kind === 'text' ? 'none' : l.fill;
      g.appendChild(svg('rect', {
        x: l.x, y: l.y, width: l.w, height: l.h, rx: l.radius,
        fill: shapeFill, stroke: l.id === selected ? '#2563eb' : '#94a3b8',
        'stroke-width': l.id === selected ? 2 : 1,
        'stroke-dasharray': l.kind === 'section' ? '6 4' : ''
      }));
      if (l.kind !== 'rect') {
        var t = svg('text', {
          x: l.x + 12, y: l.y + Math.min(l.h - 8, l.fontSize + 12),
          'font-size': l.fontSize, 'font-family': 'system-ui, sans-serif',
          fill: l.kind === 'text' ? l.fill : (l.role === 'primary' || l.kind === 'button' ? '#ffffff' : '#0f172a')
        });
        t.textContent = l.text;
        g.appendChild(t);
      }
      if (l.id === selected && !l.locked) {
        g.appendChild(svg('rect', {
          'data-kaz-handle': l.id, x: l.x + l.w - 10, y: l.y + l.h - 10,
          width: 12, height: 12, rx: 2, fill: '#2563eb', stroke: '#ffffff', 'stroke-width': 1
        }));
      }
      canvas.appendChild(g);
    });
  }

  function drawLayers() {
    clear(layerList);
    scene.layers.forEach(function (l, i) {
      var li = el('li', 'kaz-layer' + (l.id === selected ? ' kaz-layer--selected' : ''));
      li.setAttribute('data-kaz-layer-row', l.id);
      var b = el('button', 'kaz-layer__name', (i + 1) + '. ' + l.kind + ' · ' + l.id);
      b.setAttribute('type', 'button');
      b.setAttribute('data-kaz-select', l.id);
      li.appendChild(b);
      var flags = el('span', 'kaz-layer__flags', (l.visible ? '' : 'oculto ') + (l.locked ? 'bloqueado' : ''));
      li.appendChild(flags);
      layerList.appendChild(li);
    });
  }

  function drawInspector() {
    var l = layer(selected);
    root.querySelectorAll('[data-kaz-field]').forEach(function (f) {
      var key = f.getAttribute('data-kaz-field');
      if (!l) { f.disabled = true; return; }
      f.disabled = false;
      if (f.tagName === 'SELECT') f.value = String(l[key]);
      else if (f.type === 'checkbox') f.checked = !!l[key];
      else f.value = String(l[key]);
    });
    var hint = root.querySelector('[data-kaz-hint]');
    if (hint) hint.textContent = l ? l.id : 'nenhuma camada';
  }

  function render() { drawCanvas(); drawLayers(); drawInspector(); }

  /* ---- Conexão com campos allowlistados do tema -------------------------- */
  /* Aplicacao aditiva e nao destrutiva: marca camadas allowlistadas sem
     desmarcar o que ja estava salvo no formulario. Nunca roda no boot — so
     em acao explicita (edicao de cor/papel, Aplicar ao tema ou import).
     Abrir o editor nao altera o tema antes da interacao do usuario. */
  function setInput(name, value) {
    if (!form) return false;
    var n = form.querySelector('[name="' + name + '"]');
    if (!n) return false;
    n.value = value;
    return true;
  }
  function applyBindings() {
    var sections = [], components = [];
    scene.layers.forEach(function (l) {
      if (!l.visible) return;
      if (l.role === 'primary' && hex(l.fill)) setInput('primary', l.fill);
      if (l.role === 'surface' && hex(l.fill)) setInput('surface', l.fill);
      if (l.role === 'section' && l.bindId && sections.indexOf(l.bindId) < 0) sections.push(l.bindId);
      if (l.role === 'component' && l.bindId && components.indexOf(l.bindId) < 0) components.push(l.bindId);
    });
    if (form) {
      var order = form.querySelector('[name="sections_order"]');
      if (order) {
        var merged = sections.slice();
        String(order.value || '').split(',').forEach(function (piece) {
          var id = piece.trim();
          if (id && merged.indexOf(id) < 0) merged.push(id);
        });
        order.value = merged.join(',');
      }
      form.querySelectorAll('[name="sections[]"]').forEach(function (c) {
        if (sections.indexOf(String(c.value)) >= 0) c.checked = true;
      });
      form.querySelectorAll('[name^="components["][name$="][enabled]"]').forEach(function (c) {
        var m = /^components\[([^\]]+)\]\[enabled\]$/.exec(c.getAttribute('name') || '');
        if (m && components.indexOf(m[1]) >= 0) c.checked = true;
      });
    }
    return { sections: sections, components: components };
  }

  /* ---- Ações ------------------------------------------------------------- */
  function newId() {
    var i = scene.layers.length + 1, id;
    do { id = 'kaz-layer-' + i; i++; } while (layer(id));
    return id;
  }
  function add(kind) {
    if (KINDS.indexOf(kind) < 0 || scene.layers.length >= MAX_LAYERS) return '';
    var l = {
      id: newId(), kind: kind, role: 'none', x: 80, y: 80, w: 160, h: 96,
      fill: '#2563eb', radius: 8, text: kind, fontSize: 16,
      visible: true, locked: false, bindId: ''
    };
    scene.layers.push(l); selected = l.id; commit(); render();
    say('camada adicionada: ' + l.id);
    return l.id;
  }
  function remove(id) {
    var i = scene.layers.findIndex(function (l) { return l.id === id; });
    if (i < 0 || scene.layers[i].locked) return false;
    scene.layers.splice(i, 1);
    selected = scene.layers.length ? scene.layers[Math.min(i, scene.layers.length - 1)].id : '';
    commit(); render(); return true;
  }
  function duplicate(id) {
    var l = layer(id);
    if (!l || scene.layers.length >= MAX_LAYERS) return '';
    var c = copy(l); c.id = newId(); c.x = clamp(c.x + GRID * 2, 0, scene.canvas.w - c.w);
    c.y = clamp(c.y + GRID * 2, 0, scene.canvas.h - c.h);
    scene.layers.push(c); selected = c.id; commit(); render(); return c.id;
  }
  function set(id, field, value) {
    var l = layer(id); if (!l || (l.locked && field !== 'locked')) return false;
    if (['x', 'y', 'w', 'h', 'radius', 'fontSize'].indexOf(field) >= 0) {
      value = Number(value);
      if (!isFinite(value)) return false;
      var lim = { x: [-2000, 2000], y: [-2000, 2000], w: [8, 960], h: [8, 540], radius: [0, 64], fontSize: [8, 72] }[field];
      value = snap(field === 'x' || field === 'y' || field === 'w' || field === 'h' ? value : value);
      value = clamp(value, lim[0], lim[1]);
    } else if (field === 'fill') {
      if (!hex(value)) return false;
    } else if (field === 'text') {
      value = String(value).slice(0, 80);
    } else if (field === 'kind' || field === 'role') {
      if ((field === 'kind' ? KINDS : ROLES).indexOf(value) < 0) return false;
    } else if (field === 'bindId') {
      if (!bindOk(value)) return false;
    } else if (field === 'visible' || field === 'locked') {
      value = !!value;
    } else { return false; }
    l[field] = value; commit(); render(); applyBindings(); return true;
  }
  function move(id, dx, dy) {
    var l = layer(id); if (!l || l.locked) return false;
    l.x = clamp(snap(l.x + dx), -2000, 2000); l.y = clamp(snap(l.y + dy), -2000, 2000);
    commit(); render(); return true;
  }
  function resize(id, dw, dh) {
    var l = layer(id); if (!l || l.locked) return false;
    l.w = clamp(snap(l.w + dw), 8, 960); l.h = clamp(snap(l.h + dh), 8, 540);
    commit(); render(); return true;
  }
  function reorder(id, delta) {
    var i = scene.layers.findIndex(function (l) { return l.id === id; });
    var j = i + delta;
    if (i < 0 || j < 0 || j >= scene.layers.length) return false;
    var t = scene.layers[i]; scene.layers[i] = scene.layers[j]; scene.layers[j] = t;
    commit(); render(); return true;
  }
  function exportScene() {
    var s = JSON.stringify(scene, null, 2);
    jsonBox.value = s; say('cena exportada (' + scene.layers.length + ' camadas)');
    return s;
  }
  function importScene(raw) {
    var s;
    try { s = JSON.parse(String(raw)); } catch (e) { say('import inválido: JSON'); return false; }
    if (!validScene(s)) { say('import inválido: contrato kaztheme-scene/v1'); return false; }
    scene = s; selected = scene.layers.length ? scene.layers[0].id : '';
    history = [copy(scene)]; cursor = 0;
    render(); applyBindings(); say('cena importada'); return true;
  }

  /* ---- Eventos reais de mouse (drag / resize) --------------------------- */
  function scale(ev) {
    var r = canvas.getBoundingClientRect();
    if (!r || !r.width) return { x: 1, y: 1, left: 0, top: 0 };
    return { x: scene.canvas.w / r.width, y: scene.canvas.h / r.height, left: r.left, top: r.top };
  }
  canvas.addEventListener('mousedown', function (ev) {
    var target = ev.target;
    var handle = target && target.getAttribute ? target.getAttribute('data-kaz-handle') : null;
    var g = target;
    while (g && g !== canvas && !g.getAttribute('data-kaz-layer')) g = g.parentNode;
    var id = handle || (g && g !== canvas ? g.getAttribute('data-kaz-layer') : null);
    if (!id) return;
    var l = layer(id); if (!l || l.locked) { selected = id; render(); return; }
    selected = id;
    var s = scale(ev);
    drag = { id: id, mode: handle ? 'resize' : 'move', sx: ev.clientX, sy: ev.clientY,
             ox: l.x, oy: l.y, ow: l.w, oh: l.h, scale: s };
    render();
  });
  document.addEventListener('mousemove', function (ev) {
    if (!drag) return;
    var l = layer(drag.id); if (!l) return;
    var dx = (ev.clientX - drag.sx) * drag.scale.x;
    var dy = (ev.clientY - drag.sy) * drag.scale.y;
    if (drag.mode === 'move') {
      l.x = clamp(snap(drag.ox + dx), -2000, 2000);
      l.y = clamp(snap(drag.oy + dy), -2000, 2000);
    } else {
      l.w = clamp(snap(drag.ow + dx), 8, 960);
      l.h = clamp(snap(drag.oh + dy), 8, 540);
    }
    drawCanvas();
  });
  document.addEventListener('mouseup', function () {
    if (!drag) return;
    drag = null; commit(); render();
  });

  /* ---- Toolbar / inspector / IO ----------------------------------------- */
  root.addEventListener('click', function (ev) {
    var b = ev.target;
    while (b && b !== root && !b.getAttribute('data-kaz-action') && !b.getAttribute('data-kaz-select') && !b.getAttribute('data-kaz-kind')) b = b.parentNode;
    if (!b || b === root) return;
    var sel = b.getAttribute('data-kaz-select');
    if (sel) { selected = sel; render(); return; }
    var kind = b.getAttribute('data-kaz-kind');
    if (kind) { add(kind); return; }
    var act = b.getAttribute('data-kaz-action');
    if (!act) return;
    if (act === 'duplicate') duplicate(selected);
    else if (act === 'delete') remove(selected);
    else if (act === 'undo') restore(cursor - 1);
    else if (act === 'redo') restore(cursor + 1);
    else if (act === 'up') reorder(selected, -1);
    else if (act === 'down') reorder(selected, 1);
    else if (act === 'export') exportScene();
    else if (act === 'import') importScene(jsonBox.value);
    else if (act === 'bind') { applyBindings(); say('camadas aplicadas ao tema'); }
    else if (act === 'visible') set(selected, 'visible', !(layer(selected) || {}).visible);
    else if (act === 'lock') set(selected, 'locked', !(layer(selected) || {}).locked);
  });
  root.addEventListener('change', function (ev) {
    var f = ev.target;
    var key = f && f.getAttribute ? f.getAttribute('data-kaz-field') : null;
    if (!key || !selected) return;
    var v = f.type === 'checkbox' ? f.checked : f.value;
    set(selected, key, v);
  });
  document.addEventListener('keydown', function (ev) {
    var t = ev.target;
    if (t && (t.tagName === 'INPUT' || t.tagName === 'TEXTAREA' || t.tagName === 'SELECT')) return;
    var ctrl = ev.ctrlKey || ev.metaKey;
    if (ctrl && ev.key.toLowerCase() === 'z') { ev.preventDefault(); restore(ev.shiftKey ? cursor + 1 : cursor - 1); return; }
    if (ctrl && ev.key.toLowerCase() === 'd') { ev.preventDefault(); duplicate(selected); return; }
    if (ev.key === 'Delete' || ev.key === 'Backspace') { ev.preventDefault(); remove(selected); return; }
    var step = ev.shiftKey ? GRID * 4 : GRID;
    if (ev.key === 'ArrowLeft') { ev.preventDefault(); move(selected, -step, 0); }
    else if (ev.key === 'ArrowRight') { ev.preventDefault(); move(selected, step, 0); }
    else if (ev.key === 'ArrowUp') { ev.preventDefault(); move(selected, 0, -step); }
    else if (ev.key === 'ArrowDown') { ev.preventDefault(); move(selected, 0, step); }
  });

  render();
  /* Exposição mínima para testes automatizados (sem alterar comportamento). */
  root.__kaz = {
    state: function () { return copy(scene); },
    selected: function () { return selected; },
    select: function (id) { if (layer(id)) { selected = id; render(); return true; } return false; },
    add: add, remove: remove, duplicate: duplicate, set: set, move: move, resize: resize,
    reorder: reorder, undo: function () { return restore(cursor - 1); },
    redo: function () { return restore(cursor + 1); },
    history: function () { return { cursor: cursor, length: history.length }; },
    export: exportScene, import: importScene, bind: applyBindings,
    render: render, validScene: validScene, fallback: fallback
  };
  say('KAZTheme-Editor pronto · ' + scene.layers.length + ' camadas');
})(document, window);
