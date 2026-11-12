/**
 * @package   ReamurCMS
 * @license   https://opensource.org/licenses/GPL-3.0
 *
 * KZ-01 (ADR 0083) — canvas do KAZEditor-Theme com a PAGINA REAL do rascunho.
 *
 * O que faz: pede ao servidor (POST + CSRF em design/theme.canvas) a pagina
 * inteira do rascunho que esta no formulario e a mostra em quadros lado a lado
 * (desktop, tablet, celular) sobre um canvas com zoom e deslocamento livres.
 *
 * O que NAO faz: nao grava nada, nao altera o formulario, nao executa o HTML
 * do tema. Cada quadro e um <iframe sandbox="allow-same-origin"> SEM
 * allow-scripts; `allow-same-origin` existe apenas para este script medir a
 * altura do documento. Uma camada transparente cobre cada quadro: a pagina e
 * uma imagem viva, sem clique, link ou envio de formulario.
 *
 * KZ-02 (ADR 0084) — apontar e editar. O servidor cerca a saida de cada fonte
 * allowlistada com comentarios `<!--kz:b:CHAVE-->`…`<!--kz:e:CHAVE-->`. Este
 * script le esses comentarios no documento do quadro (somente leitura), desenha
 * o contorno do trecho sob o ponteiro e, ao clicar, mostra de qual fonte ele
 * vem. O codigo editado no painel e escrito no <textarea name="templates[...]">
 * do modulo Templates; ordem e visibilidade de secoes usam os controles do
 * modulo Secoes. Nenhum campo novo entra no POST do tema.
 *
 * Independente de kaz-theme-editor.js: os dois conversam somente pelo evento
 * `kaz:module` e pelo atributo `data-kaz-active-module` do shell.
 */
(function () {
  'use strict';

  var shell = document.getElementById('kaz-theme-editor');
  var root = shell ? shell.querySelector('[data-kaz-canvas-root]') : null;
  if (!shell || !root) return;

  var viewport = root.querySelector('[data-kaz-canvas-viewport]');
  var world = root.querySelector('[data-kaz-canvas-world]');
  var statusEl = root.querySelector('[data-kaz-canvas-status]');
  var notesEl = root.querySelector('[data-kaz-canvas-notes]');
  var zoomEl = root.querySelector('[data-kaz-canvas-zoom]');
  var routeSel = root.querySelector('[data-kaz-canvas-route]');
  var langSel = root.querySelector('[data-kaz-canvas-language]');
  var form = shell.querySelector('#kaz-theme-form') || shell.closest('form');
  var url = root.getAttribute('data-kaz-canvas-url') || '';
  if (!viewport || !world || !form || !url || typeof window.fetch !== 'function') return;

  var treeEl = root.querySelector('[data-kaz-canvas-tree]');
  var hiddenEl = root.querySelector('[data-kaz-canvas-hidden]');
  var inspectorEl = root.querySelector('[data-kaz-canvas-inspector-body]');
  var config = { routes: [], devices: [], labels: {}, parts: {} };
  try {
    var parsed = JSON.parse(root.getAttribute('data-kaz-canvas-config') || '{}');
    if (parsed && typeof parsed === 'object') {
      if (Array.isArray(parsed.routes)) config.routes = parsed.routes;
      if (Array.isArray(parsed.devices)) config.devices = parsed.devices;
      if (parsed.labels && typeof parsed.labels === 'object') config.labels = parsed.labels;
      if (parsed.parts && typeof parsed.parts === 'object') config.parts = parsed.parts;
    }
  } catch (e) { /* configuracao invalida: o canvas fica vazio, o editor segue */ }

  var MIN_SCALE = 0.05, MAX_SCALE = 4, GAP = 96, PAD = 48, LABEL_H = 28;
  var MAX_FRAME_H = 30000, DEBOUNCE_MS = 700, CLICK_SLOP = 4, HIT_GAP = 64;
  var STORE_KEY = 'reamur.kaz.canvas.v1';
  var MARK = /^kz:([be]):([a-z0-9_\/-]{1,64})$/;

  var view = { x: PAD, y: PAD + LABEL_H, k: 1 };
  var frames = {};          // id -> { el, iframe, label, device, on, height }
  var html = '';            // ultimo documento recebido
  var active = false;       // o modulo "pagina" esta visivel?
  var stale = true;         // o formulario mudou desde o ultimo render?
  var fitted = false;       // ja houve um enquadramento inicial?
  var seq = 0;              // descarta respostas antigas
  var timer = 0;
  var controller = null;
  var themed = {};          // chave -> true quando a fonte vem do tema
  var defaults = {};        // chave -> fonte padrao (so das partes desta pagina)
  var selected = '';        // uid do trecho selecionado ("chave#n")
  var hovered = '';
  var reverted = {};        // chave -> fonte guardada por "voltar ao padrao"
  var panels = { structure: true, inspector: true };

  function label(key, fallback) {
    var v = config.labels[key];
    return typeof v === 'string' && v !== '' ? v : fallback;
  }
  function clamp(v, lo, hi) { return Math.min(hi, Math.max(lo, v)); }

  /* ---------------- preferencias (somente UI; nunca dados do tema) ---------------- */
  function loadPrefs() {
    try {
      var raw = window.localStorage.getItem(STORE_KEY);
      var p = raw ? JSON.parse(raw) : null;
      return p && typeof p === 'object' ? p : {};
    } catch (e) { return {}; }
  }
  function savePrefs() {
    var off = [];
    Object.keys(frames).forEach(function (id) { if (!frames[id].on) off.push(id); });
    try {
      window.localStorage.setItem(STORE_KEY, JSON.stringify({ route: routeSel ? routeSel.value : '', off: off, panels: panels }));
    } catch (e) { /* armazenamento indisponivel: preferencias nao persistem */ }
  }

  /* ---------------- quadros ---------------- */
  function buildFrames() {
    var prefs = loadPrefs();
    var off = Array.isArray(prefs.off) ? prefs.off : [];
    if (prefs.panels && typeof prefs.panels === 'object') {
      panels.structure = prefs.panels.structure !== false;
      panels.inspector = prefs.panels.inspector !== false;
    }
    config.devices.forEach(function (d) {
      if (!d || typeof d.id !== 'string') return;
      var w = clamp(Number(d.w) || 0, 240, 2560), h = clamp(Number(d.h) || 0, 240, 2560);
      var el = document.createElement('div');
      el.className = 'kaz-canvas__frame';
      el.setAttribute('data-kaz-canvas-frame', d.id);
      el.style.width = w + 'px';

      var cap = document.createElement('div');
      cap.className = 'kaz-canvas__framelabel';
      cap.title = label('frame_hint', '');
      el.appendChild(cap);

      var body = document.createElement('div');
      body.className = 'kaz-canvas__framebody';
      body.style.height = h + 'px';

      var iframe = document.createElement('iframe');
      /* SEM allow-scripts, allow-forms, allow-popups ou allow-top-navigation. */
      iframe.setAttribute('sandbox', 'allow-same-origin');
      iframe.setAttribute('referrerpolicy', 'no-referrer');
      iframe.setAttribute('tabindex', '-1');
      iframe.setAttribute('aria-hidden', 'true');
      iframe.setAttribute('scrolling', 'no');
      iframe.title = String(d.label || d.id);
      iframe.style.width = w + 'px';
      iframe.style.height = h + 'px';
      body.appendChild(iframe);

      var hoverBox = document.createElement('div');
      hoverBox.className = 'kaz-canvas__mark kaz-canvas__mark--hover';
      hoverBox.hidden = true;
      body.appendChild(hoverBox);
      var selectBox = document.createElement('div');
      selectBox.className = 'kaz-canvas__mark kaz-canvas__mark--selected';
      selectBox.hidden = true;
      var selectTag = document.createElement('span');
      selectTag.className = 'kaz-canvas__marktag';
      selectBox.appendChild(selectTag);
      body.appendChild(selectBox);

      var shield = document.createElement('div');
      shield.className = 'kaz-canvas__shield';
      body.appendChild(shield);
      el.appendChild(body);
      world.appendChild(el);

      var f = {
        el: el, body: body, iframe: iframe, cap: cap, hoverBox: hoverBox, selectBox: selectBox, selectTag: selectTag,
        device: { id: d.id, label: String(d.label || d.id), w: w, h: h }, on: off.indexOf(d.id) === -1, height: h, regions: []
      };
      frames[d.id] = f;
      iframe.addEventListener('load', function () { measure(f); });
      paintCaption(f);
    });
    if (routeSel && typeof prefs.route === 'string') {
      for (var i = 0; i < routeSel.options.length; i++) {
        if (routeSel.options[i].value === prefs.route) { routeSel.value = prefs.route; break; }
      }
    }
    paintToggles();
    paintPanels();
    layout();
  }

  function paintCaption(f) {
    f.cap.textContent = f.device.label + ' · ' + f.device.w + ' × ' + Math.round(f.height);
  }

  function visibleFrames() {
    var out = [];
    config.devices.forEach(function (d) { var f = d && frames[d.id]; if (f && f.on) out.push(f); });
    return out;
  }

  function layout() {
    var x = 0;
    Object.keys(frames).forEach(function (id) { frames[id].el.hidden = !frames[id].on; });
    visibleFrames().forEach(function (f) {
      f.el.style.left = x + 'px';
      f.left = x;
      x += f.device.w + GAP;
    });
    world.setAttribute('data-kaz-canvas-empty', visibleFrames().length ? '0' : '1');
    world.setAttribute('data-kaz-canvas-empty-label', label('empty', ''));
  }

  function measure(f) {
    var doc = null;
    try { doc = f.iframe.contentDocument; } catch (e) { doc = null; }
    if (!doc || !doc.documentElement) return;
    /* Mede com a altura do aparelho (unidades vh corretas) e so depois estica. */
    f.iframe.style.height = f.device.h + 'px';
    var h = Math.max(doc.documentElement.scrollHeight, doc.body ? doc.body.scrollHeight : 0, f.device.h);
    f.height = clamp(h, f.device.h, MAX_FRAME_H);
    f.iframe.style.height = f.height + 'px';
    f.body.style.height = f.height + 'px';
    paintCaption(f);
    readRegions(f, doc);
    paintMarks();
    paintTree();
    paintInspector();
    if (!fitted && active) { fitted = true; fitInitial(); }
  }

  /* ---------------- KZ-02: trechos da pagina ---------------- */
  function partLabel(key) {
    var v = config.parts[key];
    return typeof v === 'string' && v !== '' ? v : key;
  }
  /** Le os marcadores do documento do quadro. Somente leitura: nada e escrito no quadro. */
  function readRegions(f, doc) {
    var out = [], stack = [], counts = {}, node, m, r, range, box;
    f.regions = out;
    if (!doc || typeof doc.createTreeWalker !== 'function') return;
    var walker = doc.createTreeWalker(doc.documentElement, 128 /* NodeFilter.SHOW_COMMENT */);
    while ((node = walker.nextNode())) {
      m = MARK.exec(String(node.nodeValue || ''));
      if (!m) continue;
      if (m[1] === 'b') {
        counts[m[2]] = (counts[m[2]] || 0) + 1;
        r = { key: m[2], uid: m[2] + '#' + counts[m[2]], depth: stack.length, start: node, x: 0, y: 0, w: 0, h: 0, shown: false };
        stack.push(r); out.push(r);
        continue;
      }
      while (stack.length) {
        r = stack.pop();
        if (r.key !== m[2]) continue;
        try {
          range = doc.createRange();
          range.setStartAfter(r.start);
          range.setEndBefore(node);
          box = range.getBoundingClientRect();
          r.x = Math.max(0, box.left); r.y = Math.max(0, box.top);
          r.w = Math.min(f.device.w, box.right) - r.x; r.h = box.bottom - r.y;
          r.shown = r.w >= 1 && r.h >= 1;
        } catch (e) { r.shown = false; }
        break;
      }
    }
    out.forEach(function (x) { x.start = null; });
  }
  function regionOf(f, uid) {
    for (var i = 0; i < f.regions.length; i++) if (f.regions[i].uid === uid) return f.regions[i];
    return null;
  }
  function keyOf(uid) { return uid ? uid.slice(0, uid.lastIndexOf('#')) : ''; }
  /** Quadro de referencia para a estrutura: o primeiro visivel com trechos lidos. */
  function leadFrame() {
    var list = visibleFrames();
    for (var i = 0; i < list.length; i++) if (list[i].regions.length) return list[i];
    return list[0] || null;
  }
  /** Trecho mais interno (e, no empate, o menor) sob um ponto do mundo. */
  function hitTest(wx, wy) {
    var best = null, near = null, nearGap = 0;
    visibleFrames().forEach(function (f) {
      if (wx < f.left || wx > f.left + f.device.w || wy < 0 || wy > f.height) return;
      var lx = wx - f.left;
      f.regions.forEach(function (r) {
        if (!r.shown) return;
        if (lx >= r.x && lx <= r.x + r.w && wy >= r.y && wy <= r.y + r.h) {
          if (!best || r.depth > best.depth || (r.depth === best.depth && r.w * r.h < best.w * best.h)) best = r;
          return;
        }
        /* Margem entre dois trechos: vale o mais proximo na vertical. */
        var gap = wy < r.y ? r.y - wy : wy - (r.y + r.h);
        if (gap > 0 && gap <= HIT_GAP && (!near || gap < nearGap)) { near = r; nearGap = gap; }
      });
    });
    return best ? best.uid : (near ? near.uid : '');
  }
  function placeMark(el, r) {
    if (!r || !r.shown) { el.hidden = true; return; }
    el.hidden = false;
    el.style.left = r.x + 'px'; el.style.top = r.y + 'px';
    el.style.width = r.w + 'px'; el.style.height = r.h + 'px';
  }
  function paintMarks() {
    Object.keys(frames).forEach(function (id) {
      var f = frames[id];
      placeMark(f.hoverBox, hovered && hovered !== selected ? regionOf(f, hovered) : null);
      placeMark(f.selectBox, selected ? regionOf(f, selected) : null);
      f.selectTag.textContent = selected ? partLabel(keyOf(selected)) : '';
    });
    if (treeEl) {
      var items = treeEl.querySelectorAll('[data-kaz-canvas-uid]');
      for (var i = 0; i < items.length; i++) {
        var uid = items[i].getAttribute('data-kaz-canvas-uid');
        items[i].setAttribute('aria-current', uid === selected ? 'true' : 'false');
        items[i].classList.toggle('kaz-canvas__treeitem--hover', uid === hovered && uid !== selected);
      }
    }
  }
  function setHover(uid) {
    if (uid === hovered) return;
    hovered = uid;
    paintMarks();
  }
  function select(uid, reveal) {
    selected = uid || '';
    root.setAttribute('data-kaz-canvas-selection', selected ? '1' : '0');
    paintMarks();
    paintInspector();
    if (reveal && selected) revealRegion(selected);
  }
  /** Leva o trecho para o topo da area visivel, sem mudar o zoom. */
  function revealRegion(uid) {
    var f = leadFrame(), r = f ? regionOf(f, uid) : null;
    if (!f || !r || !r.shown) return;
    var vr = viewport.getBoundingClientRect();
    var top = view.y + r.y * view.k, bottom = top + r.h * view.k;
    if (top >= 40 && bottom <= vr.height - 20) return;
    view.y = Math.round(72 - r.y * view.k);
    apply();
  }

  /* ---------------- KZ-02: estrutura ---------------- */
  function make(tag, cls, text) {
    var el = document.createElement(tag);
    if (cls) el.className = cls;
    if (text !== undefined && text !== null) el.textContent = String(text);
    return el;
  }
  function clear(el) { while (el && el.firstChild) el.removeChild(el.firstChild); }
  function paintTree() {
    if (!treeEl) return;
    clear(treeEl);
    var f = leadFrame();
    if (f) f.regions.forEach(function (r) {
      var li = make('li', 'kaz-canvas__treerow');
      var b = make('button', 'kaz-canvas__treeitem');
      b.type = 'button';
      b.setAttribute('data-kaz-canvas-uid', r.uid);
      b.style.paddingLeft = (8 + Math.min(r.depth, 4) * 14) + 'px';
      b.appendChild(make('span', 'kaz-canvas__treename', partLabel(r.key)));
      b.appendChild(make('span', 'kaz-canvas__badge' + (themed[r.key] ? ' kaz-canvas__badge--theme' : ''), label(themed[r.key] ? 'from_theme' : 'from_default', '')));
      if (!r.shown) b.classList.add('kaz-canvas__treeitem--off');
      li.appendChild(b);
      treeEl.appendChild(li);
    });
    paintHiddenSections();
    paintMarks();
  }

  /* ---------------- KZ-02: secoes da pagina inicial (modulo Secoes) ---------------- */
  function sectionId(key) {
    if (key.indexOf('partial/') !== 0) return '';
    return key.slice(8).replace(/-3d$/, '');
  }
  function sectionRow(id) {
    if (!id || !/^[a-z0-9_-]+$/.test(id)) return null;
    var list = document.getElementById('theme-section-list');
    return list ? list.querySelector('li[data-section="' + id + '"]') : null;
  }
  function sectionBox(row) { return row ? row.querySelector('input[type="checkbox"]') : null; }
  function isHome() { return !routeSel || routeSel.value === 'common/home'; }
  /** Move usando os botoes do proprio modulo Secoes, ate passar por uma secao ATIVA. */
  function moveSection(id, dir) {
    var row = sectionRow(id), guard = 0;
    if (!row) return;
    while (guard++ < 24) {
      var other = dir === 'up' ? row.previousElementSibling : row.nextElementSibling;
      var btn = row.querySelector('[data-section-move="' + dir + '"]');
      if (!other || !btn || btn.disabled) break;
      var box = sectionBox(other), wasActive = !!(box && box.checked);
      btn.click();
      if (wasActive) break;
    }
    schedule();
  }
  function toggleSection(id, on) {
    var box = sectionBox(sectionRow(id));
    if (!box || box.checked === on) return;
    box.click();
    schedule();
  }
  function paintHiddenSections() {
    if (!hiddenEl) return;
    clear(hiddenEl);
    var list = document.getElementById('theme-section-list');
    var rows = list && isHome() ? list.querySelectorAll('li[data-section]') : [];
    var count = 0;
    for (var i = 0; i < rows.length; i++) {
      var box = sectionBox(rows[i]);
      if (!box || box.checked) continue;
      if (!count++) hiddenEl.appendChild(make('h5', 'kaz-canvas__sidesub', label('hidden_sections', '')));
      var line = make('div', 'kaz-canvas__hiddenrow');
      var name = rows[i].querySelector('strong');
      line.appendChild(make('span', '', name ? name.textContent : rows[i].getAttribute('data-section')));
      var b = make('button', 'kaz-btn kaz-btn--ghost', label('section_show', ''));
      b.type = 'button';
      b.setAttribute('data-kaz-canvas-show-section', rows[i].getAttribute('data-section') || '');
      line.appendChild(b);
      hiddenEl.appendChild(line);
    }
    hiddenEl.hidden = count === 0;
  }

  /* ---------------- KZ-02: trecho selecionado ---------------- */
  function sourceField(key) {
    var name = 'templates[' + key + ']', els = form.elements;
    for (var i = 0; i < els.length; i++) if (els[i].name === name && els[i].tagName === 'TEXTAREA') return els[i];
    return null;
  }
  function writeSource(key, value) {
    var field = sourceField(key);
    if (!field) return false;
    field.value = value;
    /* O evento e do campo REAL: marca o formulario como alterado e agenda o render. */
    field.dispatchEvent(new Event('input', { bubbles: true }));
    return true;
  }
  function paintInspector() {
    if (!inspectorEl) return;
    var keep = inspectorEl.querySelector('[data-kaz-canvas-code]');
    var key = keyOf(selected);
    /* Digitando no codigo do mesmo trecho: nao reconstruir (o foco e o cursor ficam). */
    if (keep && document.activeElement === keep && keep.getAttribute('data-kaz-canvas-code') === key) {
      paintSizes();
      return;
    }
    clear(inspectorEl);
    if (!key) { inspectorEl.appendChild(make('p', 'kaz-canvas__sidehelp', label('select_hint', ''))); return; }

    var field = sourceField(key);
    var own = !!(field && field.value !== '');
    var head = make('div', 'kaz-canvas__piece');
    head.appendChild(make('strong', 'kaz-canvas__piecename', partLabel(key)));
    head.appendChild(make('code', 'kaz-canvas__piecekey', key + '.twig'));
    head.appendChild(make('span', 'kaz-canvas__badge' + (own ? ' kaz-canvas__badge--theme' : ''), label(own ? 'from_theme' : 'from_default', '')));
    inspectorEl.appendChild(head);
    if (!own) inspectorEl.appendChild(make('p', 'kaz-canvas__sidehelp', label('from_default_help', '')));

    var sid = sectionId(key), row = isHome() ? sectionRow(sid) : null;
    if (row) {
      inspectorEl.appendChild(make('h5', 'kaz-canvas__sidesub', label('section', '')));
      var acts = make('div', 'kaz-canvas__actions');
      [['up', 'section_up'], ['down', 'section_down'], ['hide', 'section_hide']].forEach(function (a) {
        var b = make('button', 'kaz-btn kaz-btn--ghost', label(a[1], a[0]));
        b.type = 'button';
        b.setAttribute('data-kaz-canvas-section', a[0]);
        b.setAttribute('data-kaz-canvas-section-id', sid);
        acts.appendChild(b);
      });
      inspectorEl.appendChild(acts);
    }

    inspectorEl.appendChild(make('h5', 'kaz-canvas__sidesub', label('size', '')));
    inspectorEl.appendChild(make('ul', 'kaz-canvas__sizes'));
    paintSizes();

    if (field) {
      inspectorEl.appendChild(make('h5', 'kaz-canvas__sidesub', label('code', '')));
      var code = make('textarea', 'kaz-canvas__code');
      code.setAttribute('data-kaz-canvas-code', key);
      code.setAttribute('spellcheck', 'false');
      code.setAttribute('autocapitalize', 'off');
      code.setAttribute('autocomplete', 'off');
      code.setAttribute('wrap', 'off');
      code.setAttribute('aria-label', label('code', '') + ' — ' + key + '.twig');
      code.maxLength = 131072;
      code.value = own ? field.value : (typeof defaults[key] === 'string' ? defaults[key] : '');
      code.readOnly = !own;
      inspectorEl.appendChild(code);
      inspectorEl.appendChild(make('p', 'kaz-canvas__sidehelp', label('code_help', '')));
      var bar = make('div', 'kaz-canvas__actions');
      var main = make('button', 'kaz-btn ' + (own ? 'kaz-btn--ghost' : 'kaz-btn--primary'), label(own ? 'revert' : 'customize', ''));
      main.type = 'button';
      main.setAttribute('data-kaz-canvas-source', own ? 'revert' : 'customize');
      if (!own && typeof defaults[key] !== 'string') main.disabled = true;
      bar.appendChild(main);
      if (!own && typeof reverted[key] === 'string') {
        var undo = make('button', 'kaz-btn kaz-btn--ghost', label('revert_undo', ''));
        undo.type = 'button';
        undo.setAttribute('data-kaz-canvas-source', 'undo');
        bar.appendChild(undo);
      }
      var open = make('button', 'kaz-btn kaz-btn--ghost', label('open_templates', ''));
      open.type = 'button';
      open.setAttribute('data-kaz-canvas-source', 'open');
      bar.appendChild(open);
      inspectorEl.appendChild(bar);
    }
  }
  function paintSizes() {
    var ul = inspectorEl ? inspectorEl.querySelector('.kaz-canvas__sizes') : null;
    if (!ul) return;
    clear(ul);
    visibleFrames().forEach(function (f) {
      var r = regionOf(f, selected);
      var li = make('li', '');
      li.appendChild(make('span', '', f.device.label + ' ' + f.device.w));
      li.appendChild(make('span', '', r && r.shown ? Math.round(r.w) + ' × ' + Math.round(r.h) : label('not_visible', '—')));
      ul.appendChild(li);
    });
  }
  function paintPanels() {
    root.setAttribute('data-kaz-canvas-show-structure', panels.structure ? '1' : '0');
    root.setAttribute('data-kaz-canvas-show-inspector', panels.inspector ? '1' : '0');
    var btns = root.querySelectorAll('[data-kaz-canvas-panel]');
    for (var i = 0; i < btns.length; i++) {
      btns[i].setAttribute('aria-pressed', panels[btns[i].getAttribute('data-kaz-canvas-panel')] ? 'true' : 'false');
    }
  }

  function setDocument(doc) {
    html = doc;
    Object.keys(frames).forEach(function (id) { frames[id].iframe.srcdoc = doc; });
  }

  /* ---------------- camera ---------------- */
  function apply() {
    world.style.transform = 'translate(' + view.x + 'px,' + view.y + 'px) scale(' + view.k + ')';
    if (zoomEl) zoomEl.textContent = Math.round(view.k * 100) + '%';
    viewport.style.setProperty('--kaz-canvas-k', String(view.k));
  }
  function zoomAt(k, cx, cy) {
    k = clamp(k, MIN_SCALE, MAX_SCALE);
    var wx = (cx - view.x) / view.k, wy = (cy - view.y) / view.k;
    view.k = k;
    view.x = cx - wx * k;
    view.y = cy - wy * k;
    apply();
  }
  function zoomCenter(factor) {
    var r = viewport.getBoundingClientRect();
    zoomAt(view.k * factor, r.width / 2, r.height / 2);
  }
  /** Enquadra a LARGURA dos quadros visiveis; o topo das paginas fica no topo. */
  function fitAll() {
    var list = visibleFrames();
    var r = viewport.getBoundingClientRect();
    if (!list.length || r.width < 50) return;
    var last = list[list.length - 1];
    var total = last.left + last.device.w;
    view.k = clamp((r.width - PAD * 2) / total, MIN_SCALE, 1);
    view.x = Math.round((r.width - total * view.k) / 2);
    view.y = PAD + LABEL_H;
    apply();
  }
  /** Enquadramento de abertura: em tela estreita, tres quadros lado a lado
      ficam ilegiveis; abre no quadro mais estreito (os outros seguem ao lado). */
  function fitInitial() {
    var list = visibleFrames();
    var r = viewport.getBoundingClientRect();
    if (list.length > 1) {
      var last = list[list.length - 1];
      var all = (r.width - PAD * 2) / (last.left + last.device.w);
      if (all < 0.28) {
        /* Nao cabe com leitura: abre no primeiro quadro; em tela de celular, no mais estreito. */
        var pick = list[0];
        if (r.width < 520) list.forEach(function (f) { if (f.device.w < pick.device.w) pick = f; });
        focusFrame(pick);
        return;
      }
    }
    fitAll();
  }
  function focusFrame(f) {
    var r = viewport.getBoundingClientRect();
    if (!f.on || r.width < 50) return;
    view.k = clamp((r.width - PAD * 2) / f.device.w, MIN_SCALE, 1);
    view.x = Math.round((r.width - f.device.w * view.k) / 2 - f.left * view.k);
    view.y = PAD + LABEL_H;
    apply();
  }

  /* ---------------- estado e avisos ---------------- */
  function say(state, text) {
    root.setAttribute('data-kaz-canvas-state', state);
    if (statusEl) statusEl.textContent = text;
  }
  function notes(lines) {
    if (!notesEl) return;
    while (notesEl.firstChild) notesEl.removeChild(notesEl.firstChild);
    lines.forEach(function (line) {
      var p = document.createElement('p');
      p.className = 'kaz-canvas__note kaz-canvas__note--' + line.kind;
      if (line.strong) { var b = document.createElement('strong'); b.textContent = line.strong + ' '; p.appendChild(b); }
      p.appendChild(document.createTextNode(line.text));
      notesEl.appendChild(p);
    });
    notesEl.hidden = lines.length === 0;
  }

  /* ---------------- render ---------------- */
  function refresh() {
    window.clearTimeout(timer);
    var body;
    try { body = new FormData(form); } catch (e) { say('error', label('error', 'Error')); return; }
    /* Arquivos nunca viajam para a previa. */
    var drop = [];
    body.forEach(function (value, key) { if (typeof value !== 'string') drop.push(key); });
    drop.forEach(function (key) { body.delete(key); });
    body.set('canvas_route', routeSel ? routeSel.value : 'common/home');
    body.set('canvas_language', langSel ? langSel.value : 'en-us');

    if (controller) { try { controller.abort(); } catch (e) { /* ja terminou */ } }
    controller = typeof window.AbortController === 'function' ? new window.AbortController() : null;
    var mine = ++seq;
    stale = false;
    say('loading', label('loading', '…'));

    window.fetch(url, {
      method: 'POST', body: body, credentials: 'same-origin', cache: 'no-store',
      headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      signal: controller ? controller.signal : undefined
    }).then(function (res) {
      return res.json().catch(function () { return { success: false, error: 'HTTP ' + res.status }; });
    }).then(function (json) {
      if (mine !== seq) return;
      if (!json || json.success !== true || typeof json.html !== 'string') {
        say('error', label('error', 'Error'));
        notes([{ kind: 'error', text: json && typeof json.error === 'string' ? json.error : label('error', 'Error') }]);
        return;
      }
      themed = {};
      if (Array.isArray(json.themed)) json.themed.forEach(function (k) { themed[String(k)] = true; });
      defaults = json.defaults && typeof json.defaults === 'object' ? json.defaults : {};
      setDocument(json.html);
      var lines = [];
      if (json.source === 'saved') lines.push({ kind: 'warn', text: label('saved_draft', '') });
      if (Array.isArray(json.errors) && json.errors.length) {
        json.errors.forEach(function (e) {
          lines.push({
            kind: 'error', strong: label('template_errors', 'Template') + ' · ' + String(e.template || '') + '.twig:',
            text: String(e.message || '') + (e.fallback ? ' ' + label('default_source', '') : '')
          });
        });
      }
      notes(lines);
      var stamp = new Date();
      var hh = ('0' + stamp.getHours()).slice(-2) + ':' + ('0' + stamp.getMinutes()).slice(-2) + ':' + ('0' + stamp.getSeconds()).slice(-2);
      say(lines.length ? 'warn' : 'ready', label('ready', 'OK') + ' · ' + hh);
    }).catch(function (err) {
      if (mine !== seq || (err && err.name === 'AbortError')) return;
      say('error', label('error', 'Error'));
      notes([{ kind: 'error', text: label('error', 'Error') }]);
    });
  }
  function schedule() {
    stale = true;
    if (!active) return;
    window.clearTimeout(timer);
    timer = window.setTimeout(refresh, DEBOUNCE_MS);
  }

  /* ---------------- barra ---------------- */
  function paintToggles() {
    var btns = root.querySelectorAll('[data-kaz-canvas-device]');
    for (var i = 0; i < btns.length; i++) {
      var f = frames[btns[i].getAttribute('data-kaz-canvas-device')];
      btns[i].setAttribute('aria-pressed', f && f.on ? 'true' : 'false');
    }
  }
  root.addEventListener('click', function (ev) {
    var t = ev.target && ev.target.closest ? ev.target.closest(
      '[data-kaz-canvas-act],[data-kaz-canvas-device],[data-kaz-canvas-panel],[data-kaz-canvas-uid],' +
      '[data-kaz-canvas-section],[data-kaz-canvas-show-section],[data-kaz-canvas-source]') : null;
    if (!t || !root.contains(t)) return;
    var panel = t.getAttribute('data-kaz-canvas-panel');
    if (panel === 'structure' || panel === 'inspector') {
      panels[panel] = !panels[panel];
      paintPanels(); savePrefs();
      return;
    }
    var uid = t.getAttribute('data-kaz-canvas-uid');
    if (uid) { select(uid, true); return; }
    var show = t.getAttribute('data-kaz-canvas-show-section');
    if (show) { toggleSection(show, true); paintHiddenSections(); return; }
    var sec = t.getAttribute('data-kaz-canvas-section');
    if (sec) {
      var sid = t.getAttribute('data-kaz-canvas-section-id') || '';
      if (sec === 'hide') { toggleSection(sid, false); select('', false); paintHiddenSections(); }
      else moveSection(sid, sec);
      return;
    }
    var src = t.getAttribute('data-kaz-canvas-source');
    if (src) {
      var key = keyOf(selected), field = sourceField(key);
      if (!key || !field) return;
      if (src === 'customize' && typeof defaults[key] === 'string') { delete reverted[key]; writeSource(key, defaults[key]); }
      else if (src === 'revert') { reverted[key] = field.value; writeSource(key, ''); }
      else if (src === 'undo' && typeof reverted[key] === 'string') { writeSource(key, reverted[key]); delete reverted[key]; }
      else if (src === 'open') {
        var tab = shell.querySelector('button.kaz-module-link[data-kaz-module="templates"]');
        if (tab) tab.click();
        var det = field.closest ? field.closest('details') : null;
        if (det) det.open = true;
        if (typeof field.scrollIntoView === 'function') field.scrollIntoView({ block: 'center' });
        field.focus();
        return;
      }
      paintInspector();
      return;
    }
    var dev = t.getAttribute('data-kaz-canvas-device');
    if (dev) {
      var f = frames[dev];
      if (!f) return;
      f.on = !f.on;
      paintToggles(); layout(); fitAll(); savePrefs();
      paintTree(); paintInspector();
      return;
    }
    var act = t.getAttribute('data-kaz-canvas-act');
    if (act === 'refresh') refresh();
    else if (act === 'zoom-in') zoomCenter(1.25);
    else if (act === 'zoom-out') zoomCenter(0.8);
    else if (act === 'zoom-reset') { var r = viewport.getBoundingClientRect(); zoomAt(1, r.width / 2, r.height / 2); }
    else if (act === 'fit') fitAll();
  });
  root.addEventListener('input', function (ev) {
    var t = ev.target, key = t && t.getAttribute ? t.getAttribute('data-kaz-canvas-code') : null;
    if (!key || t.readOnly) return;
    writeSource(key, t.value);
  });
  if (treeEl) {
    treeEl.addEventListener('mouseover', function (ev) {
      var t = ev.target && ev.target.closest ? ev.target.closest('[data-kaz-canvas-uid]') : null;
      setHover(t ? t.getAttribute('data-kaz-canvas-uid') : '');
    });
    treeEl.addEventListener('mouseleave', function () { setHover(''); });
    treeEl.addEventListener('focusin', function (ev) {
      var t = ev.target && ev.target.closest ? ev.target.closest('[data-kaz-canvas-uid]') : null;
      if (t) setHover(t.getAttribute('data-kaz-canvas-uid'));
    });
  }
  if (routeSel) routeSel.addEventListener('change', function () { savePrefs(); select('', false); refresh(); });
  if (langSel) langSel.addEventListener('change', function () { refresh(); });

  /* ---------------- gestos: roda, arrasto, pinca, teclado ---------------- */
  viewport.addEventListener('wheel', function (ev) {
    ev.preventDefault();
    var r = viewport.getBoundingClientRect();
    var unit = ev.deltaMode === 1 ? 32 : (ev.deltaMode === 2 ? r.height : 1);
    if (ev.ctrlKey || ev.metaKey) {
      zoomAt(view.k * Math.exp(-ev.deltaY * unit * 0.0022), ev.clientX - r.left, ev.clientY - r.top);
      return;
    }
    var dx = ev.deltaX * unit, dy = ev.deltaY * unit;
    if (ev.shiftKey && dx === 0) { dx = dy; dy = 0; }
    view.x -= dx; view.y -= dy;
    apply();
  }, { passive: false });

  var pointers = {}, pan = null, pinch = null, press = null;
  function worldPoint(ev) {
    var r = viewport.getBoundingClientRect();
    return { x: (ev.clientX - r.left - view.x) / view.k, y: (ev.clientY - r.top - view.y) / view.k };
  }
  function pointerCount() { return Object.keys(pointers).length; }
  viewport.addEventListener('pointerdown', function (ev) {
    if (ev.pointerType === 'mouse' && ev.button !== 0 && ev.button !== 1) return;
    pointers[ev.pointerId] = { x: ev.clientX, y: ev.clientY };
    try { viewport.setPointerCapture(ev.pointerId); } catch (e) { /* sem captura: o arrasto ainda funciona dentro da area */ }
    if (pointerCount() === 1) {
      pan = { x: ev.clientX, y: ev.clientY, vx: view.x, vy: view.y };
      press = ev.button === 0 ? { id: ev.pointerId, x: ev.clientX, y: ev.clientY } : null;
      if (!press) viewport.setAttribute('data-kaz-canvas-panning', '1');
      if (ev.button === 1) ev.preventDefault();
    } else if (pointerCount() === 2) {
      press = null;
      var ids = Object.keys(pointers), a = pointers[ids[0]], b = pointers[ids[1]];
      pinch = { d: Math.max(1, Math.hypot(a.x - b.x, a.y - b.y)), k: view.k };
      pan = null;
    }
  });
  viewport.addEventListener('pointermove', function (ev) {
    if (!pointers[ev.pointerId]) {
      /* Sem botao apertado: so aponta o trecho sob o ponteiro. */
      if (ev.pointerType !== 'touch') { var w = worldPoint(ev); setHover(hitTest(w.x, w.y)); }
      return;
    }
    if (press && press.id === ev.pointerId && Math.hypot(ev.clientX - press.x, ev.clientY - press.y) > CLICK_SLOP) {
      press = null;
      setHover('');
      viewport.setAttribute('data-kaz-canvas-panning', '1');
    }
    pointers[ev.pointerId] = { x: ev.clientX, y: ev.clientY };
    if (pinch && pointerCount() === 2) {
      var ids = Object.keys(pointers), a = pointers[ids[0]], b = pointers[ids[1]];
      var r = viewport.getBoundingClientRect();
      zoomAt(pinch.k * Math.hypot(a.x - b.x, a.y - b.y) / pinch.d, (a.x + b.x) / 2 - r.left, (a.y + b.y) / 2 - r.top);
      return;
    }
    if (pan && !press) {
      view.x = pan.vx + (ev.clientX - pan.x);
      view.y = pan.vy + (ev.clientY - pan.y);
      apply();
    }
  });
  function release(ev) {
    /* Soltou sem arrastar: e um clique. Seleciona o trecho (ou limpa, no vazio). */
    if (press && press.id === ev.pointerId && ev.type === 'pointerup') {
      var w = worldPoint(ev);
      select(hitTest(w.x, w.y), false);
    }
    if (press && press.id === ev.pointerId) press = null;
    delete pointers[ev.pointerId];
    if (pointerCount() < 2) pinch = null;
    if (pointerCount() === 0) { pan = null; viewport.removeAttribute('data-kaz-canvas-panning'); }
    else if (pointerCount() === 1) {
      var id = Object.keys(pointers)[0];
      pan = { x: pointers[id].x, y: pointers[id].y, vx: view.x, vy: view.y };
    }
  }
  viewport.addEventListener('pointerup', release);
  viewport.addEventListener('pointerleave', function () { if (!pointerCount()) setHover(''); });
  viewport.addEventListener('pointercancel', release);

  /* A captura de ponteiro entrega o duplo clique ao viewport: o quadro e
     achado pela posicao no mundo, nao pelo alvo do evento. */
  viewport.addEventListener('dblclick', function (ev) {
    var r = viewport.getBoundingClientRect();
    var wx = (ev.clientX - r.left - view.x) / view.k, wy = (ev.clientY - r.top - view.y) / view.k;
    var hit = null;
    visibleFrames().forEach(function (f) {
      if (wx >= f.left && wx <= f.left + f.device.w && wy >= -LABEL_H / view.k && wy <= f.height) hit = f;
    });
    if (hit) focusFrame(hit); else fitAll();
  });

  viewport.addEventListener('keydown', function (ev) {
    if (ev.altKey) return;
    var ctrl = ev.ctrlKey || ev.metaKey, step = ev.shiftKey ? 240 : 80, used = true;
    if (ev.key === '+' || ev.key === '=') zoomCenter(1.25);
    else if (ev.key === '-' || ev.key === '_') zoomCenter(0.8);
    else if (ev.key === '0') { var r = viewport.getBoundingClientRect(); zoomAt(1, r.width / 2, r.height / 2); }
    else if (ev.key === '1' && !ctrl) fitAll();
    else if (ev.key === 'ArrowLeft') { view.x += step; apply(); }
    else if (ev.key === 'ArrowRight') { view.x -= step; apply(); }
    else if (ev.key === 'ArrowUp') { view.y += step; apply(); }
    else if (ev.key === 'ArrowDown') { view.y -= step; apply(); }
    else if (ev.key === 'PageDown' || (ev.key === ' ' && !ev.shiftKey)) { view.y -= viewport.clientHeight * 0.8; apply(); }
    else if (ev.key === 'PageUp' || (ev.key === ' ' && ev.shiftKey)) { view.y += viewport.clientHeight * 0.8; apply(); }
    else if (ev.key === 'Home') { view.y = PAD + LABEL_H; apply(); }
    else if (ev.key.toLowerCase() === 'r' && !ctrl) refresh();
    else if (ev.key === 'Escape' && selected) select('', false);
    else used = false;
    if (used) { ev.preventDefault(); ev.stopPropagation(); }
  });

  /* ---------------- integracao com o editor ---------------- */
  function setActive(on) {
    if (on === active) return;
    active = on;
    if (!active) { window.clearTimeout(timer); return; }
    if (stale || html === '') refresh();
    /* O painel acabou de ganhar tamanho: enquadra na proxima pintura. */
    window.requestAnimationFrame(function () {
      if (!fitted) fitInitial();
    });
  }
  shell.addEventListener('kaz:module', function (ev) {
    setActive(!!(ev.detail && ev.detail.module === 'pagina'));
  });
  form.addEventListener('input', function (ev) { if (ev.target && ev.target.name) schedule(); });
  form.addEventListener('change', function (ev) { if (ev.target && ev.target.name) schedule(); });
  /* Os botoes ↑/↓ do modulo Secoes mudam a ordem sem disparar input/change. */
  form.addEventListener('click', function (ev) {
    if (ev.target && ev.target.closest && ev.target.closest('[data-section-move]')) schedule();
  });
  window.addEventListener('resize', function () { if (active && !fitted) fitInitial(); });

  buildFrames();
  apply();
  root.setAttribute('data-kaz-canvas-ready', '1');
  setActive(shell.getAttribute('data-kaz-active-module') === 'pagina');
})();
