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

  var config = { routes: [], devices: [], labels: {} };
  try {
    var parsed = JSON.parse(root.getAttribute('data-kaz-canvas-config') || '{}');
    if (parsed && typeof parsed === 'object') {
      if (Array.isArray(parsed.routes)) config.routes = parsed.routes;
      if (Array.isArray(parsed.devices)) config.devices = parsed.devices;
      if (parsed.labels && typeof parsed.labels === 'object') config.labels = parsed.labels;
    }
  } catch (e) { /* configuracao invalida: o canvas fica vazio, o editor segue */ }

  var MIN_SCALE = 0.05, MAX_SCALE = 4, GAP = 96, PAD = 48, LABEL_H = 28;
  var MAX_FRAME_H = 30000, DEBOUNCE_MS = 700;
  var STORE_KEY = 'reamur.kaz.canvas.v1';

  var view = { x: PAD, y: PAD + LABEL_H, k: 1 };
  var frames = {};          // id -> { el, iframe, label, device, on, height }
  var html = '';            // ultimo documento recebido
  var active = false;       // o modulo "pagina" esta visivel?
  var stale = true;         // o formulario mudou desde o ultimo render?
  var fitted = false;       // ja houve um enquadramento inicial?
  var seq = 0;              // descarta respostas antigas
  var timer = 0;
  var controller = null;

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
      window.localStorage.setItem(STORE_KEY, JSON.stringify({ route: routeSel ? routeSel.value : '', off: off }));
    } catch (e) { /* armazenamento indisponivel: preferencias nao persistem */ }
  }

  /* ---------------- quadros ---------------- */
  function buildFrames() {
    var prefs = loadPrefs();
    var off = Array.isArray(prefs.off) ? prefs.off : [];
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

      var shield = document.createElement('div');
      shield.className = 'kaz-canvas__shield';
      body.appendChild(shield);
      el.appendChild(body);
      world.appendChild(el);

      var f = { el: el, body: body, iframe: iframe, cap: cap, device: { id: d.id, label: String(d.label || d.id), w: w, h: h }, on: off.indexOf(d.id) === -1, height: h };
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
    if (!fitted && active) { fitted = true; fitInitial(); }
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
    if (list.length > 1 && r.width < 720) {
      var narrow = list[0];
      list.forEach(function (f) { if (f.device.w < narrow.device.w) narrow = f; });
      focusFrame(narrow);
      return;
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
    var t = ev.target && ev.target.closest ? ev.target.closest('[data-kaz-canvas-act],[data-kaz-canvas-device]') : null;
    if (!t || !root.contains(t)) return;
    var dev = t.getAttribute('data-kaz-canvas-device');
    if (dev) {
      var f = frames[dev];
      if (!f) return;
      f.on = !f.on;
      paintToggles(); layout(); fitAll(); savePrefs();
      return;
    }
    var act = t.getAttribute('data-kaz-canvas-act');
    if (act === 'refresh') refresh();
    else if (act === 'zoom-in') zoomCenter(1.25);
    else if (act === 'zoom-out') zoomCenter(0.8);
    else if (act === 'zoom-reset') { var r = viewport.getBoundingClientRect(); zoomAt(1, r.width / 2, r.height / 2); }
    else if (act === 'fit') fitAll();
  });
  if (routeSel) routeSel.addEventListener('change', function () { savePrefs(); refresh(); });
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

  var pointers = {}, pan = null, pinch = null;
  function pointerCount() { return Object.keys(pointers).length; }
  viewport.addEventListener('pointerdown', function (ev) {
    if (ev.pointerType === 'mouse' && ev.button !== 0 && ev.button !== 1) return;
    pointers[ev.pointerId] = { x: ev.clientX, y: ev.clientY };
    try { viewport.setPointerCapture(ev.pointerId); } catch (e) { /* sem captura: o arrasto ainda funciona dentro da area */ }
    if (pointerCount() === 1) {
      pan = { x: ev.clientX, y: ev.clientY, vx: view.x, vy: view.y };
      viewport.setAttribute('data-kaz-canvas-panning', '1');
      if (ev.button === 1) ev.preventDefault();
    } else if (pointerCount() === 2) {
      var ids = Object.keys(pointers), a = pointers[ids[0]], b = pointers[ids[1]];
      pinch = { d: Math.max(1, Math.hypot(a.x - b.x, a.y - b.y)), k: view.k };
      pan = null;
    }
  });
  viewport.addEventListener('pointermove', function (ev) {
    if (!pointers[ev.pointerId]) return;
    pointers[ev.pointerId] = { x: ev.clientX, y: ev.clientY };
    if (pinch && pointerCount() === 2) {
      var ids = Object.keys(pointers), a = pointers[ids[0]], b = pointers[ids[1]];
      var r = viewport.getBoundingClientRect();
      zoomAt(pinch.k * Math.hypot(a.x - b.x, a.y - b.y) / pinch.d, (a.x + b.x) / 2 - r.left, (a.y + b.y) / 2 - r.top);
      return;
    }
    if (pan) {
      view.x = pan.vx + (ev.clientX - pan.x);
      view.y = pan.vy + (ev.clientY - pan.y);
      apply();
    }
  });
  function release(ev) {
    delete pointers[ev.pointerId];
    if (pointerCount() < 2) pinch = null;
    if (pointerCount() === 0) { pan = null; viewport.removeAttribute('data-kaz-canvas-panning'); }
    else if (pointerCount() === 1) {
      var id = Object.keys(pointers)[0];
      pan = { x: pointers[id].x, y: pointers[id].y, vx: view.x, vy: view.y };
    }
  }
  viewport.addEventListener('pointerup', release);
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
  window.addEventListener('resize', function () { if (active && !fitted) fitInitial(); });

  buildFrames();
  apply();
  root.setAttribute('data-kaz-canvas-ready', '1');
  setActive(shell.getAttribute('data-kaz-active-module') === 'pagina');
})();
