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
 * KZ-03 (ADR 0085) — tokens ao vivo. A aba "Design" espelha os campos reais
 * do formulario (`tokens[...]`, `primary`, `surface`) e lista as variaveis que
 * o proprio tema declara em um <style> das suas fontes. Ao mexer em um
 * controle o valor e aplicado na hora como variavel CSS no quadro (so nomes
 * vindos do servidor e valores validados aqui) e o render do servidor confirma
 * em seguida. Esta e a UNICA escrita no documento do quadro.
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
  var designEl = root.querySelector('[data-kaz-canvas-design-body]');
  var config = { routes: [], devices: [], labels: {}, parts: {}, tokens: { fields: {}, groups: [], general: {}, pairs: {} } };
  try {
    var parsed = JSON.parse(root.getAttribute('data-kaz-canvas-config') || '{}');
    if (parsed && typeof parsed === 'object') {
      if (Array.isArray(parsed.routes)) config.routes = parsed.routes;
      if (Array.isArray(parsed.devices)) config.devices = parsed.devices;
      if (parsed.labels && typeof parsed.labels === 'object') config.labels = parsed.labels;
      if (parsed.parts && typeof parsed.parts === 'object') config.parts = parsed.parts;
      if (parsed.tokens && typeof parsed.tokens === 'object') {
        ['fields', 'general', 'pairs'].forEach(function (k) {
          if (parsed.tokens[k] && typeof parsed.tokens[k] === 'object') config.tokens[k] = parsed.tokens[k];
        });
        if (Array.isArray(parsed.tokens.groups)) config.tokens.groups = parsed.tokens.groups;
      }
    }
  } catch (e) { /* configuracao invalida: o canvas fica vazio, o editor segue */ }

  var MIN_SCALE = 0.05, MAX_SCALE = 4, GAP = 96, PAD = 48, LABEL_H = 28;
  var MAX_FRAME_H = 30000, DEBOUNCE_MS = 700, CLICK_SLOP = 4, HIT_GAP = 64;
  var STORE_KEY = 'reamur.kaz.canvas.v1';
  var MARK = /^kz:([be]):([a-z0-9_\/-]{1,64})$/;
  var HEX = /^#[0-9a-fA-F]{6}$/, CSS_VAR = /^--[a-z][a-z0-9-]{0,47}$/, PX = /^(\d{1,4}(?:\.\d{1,3})?)px$/;
  /* Propriedades comparadas para saber se um token muda a pagina. */
  var PROBE_PROPS = ['color', 'background-color', 'border-top-color', 'font-family', 'font-size', 'font-weight',
    'line-height', 'border-top-left-radius', 'border-bottom-right-radius', 'border-top-width', 'box-shadow',
    'padding-top', 'padding-left', 'margin-top', 'margin-bottom', 'gap', 'max-width', 'width'];
  var PROBE_SAMPLE = 3000;

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
  var scheme = 'light';     // esquema de cores pedido ao servidor
  var tab = 'piece';        // aba do painel direito: piece | design
  var styleVars = [];       // variaveis declaradas pelo proprio tema (do servidor)
  var varsKnown = false;    // o servidor ja respondeu ao menos uma vez
  var contrast = [];        // pares de contraste medidos pelo servidor
  var usage = {};           // id do controle -> o valor muda esta pagina?
  var usageKey = '';        // para que conteudo `usage` foi medido
  var probeRun = 0;         // cancela uma medicao em andamento
  var designSig = '';       // estrutura atual da aba Design
  var touched = 0;          // ultima vez que um controle da aba Design mudou

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
      window.localStorage.setItem(STORE_KEY, JSON.stringify({ route: routeSel ? routeSel.value : '', off: off, panels: panels, scheme: scheme, tab: tab }));
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
    scheme = prefs.scheme === 'dark' ? 'dark' : 'light';
    tab = prefs.tab === 'design' ? 'design' : 'piece';
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
    scheduleProbe();
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
    var tabs = root.querySelectorAll('[data-kaz-canvas-tab]');
    for (i = 0; i < tabs.length; i++) {
      var on = tabs[i].getAttribute('data-kaz-canvas-tab') === tab;
      tabs[i].setAttribute('aria-selected', on ? 'true' : 'false');
      tabs[i].tabIndex = on ? 0 : -1;
    }
    var tabPanels = root.querySelectorAll('[data-kaz-canvas-tabpanel]');
    for (i = 0; i < tabPanels.length; i++) tabPanels[i].hidden = tabPanels[i].getAttribute('data-kaz-canvas-tabpanel') !== tab;
    var schemes = root.querySelectorAll('[data-kaz-canvas-scheme]');
    for (i = 0; i < schemes.length; i++) {
      schemes[i].setAttribute('aria-pressed', schemes[i].getAttribute('data-kaz-canvas-scheme') === scheme ? 'true' : 'false');
    }
  }

  /* ---------------- KZ-03: aba Design ---------------- */
  function field(name) {
    var els = form.elements;
    for (var i = 0; i < els.length; i++) if (els[i].name === name) return els[i];
    return null;
  }
  /** Escreve no campo REAL do formulario; e ele que viaja no POST. */
  function writeField(name, value) {
    var el = field(name);
    if (!el) return false;
    el.value = value;
    el.dispatchEvent(new Event('input', { bubbles: true }));
    return true;
  }
  function designVisible() { return active && panels.inspector && tab === 'design'; }
  /** Valor CSS que um token produz, pela tabela derivada no servidor. */
  function tokenCss(spec, value) {
    if (!spec) return null;
    if (spec.kind === 'color') return HEX.test(value) ? value : null;
    if (spec.kind === 'map') {
      var v = spec.map ? spec.map[value] : null;
      return typeof v === 'string' ? v : null;
    }
    var n = Number(value);
    if (value === '' || !isFinite(n) || n < Number(spec.min) || n > Number(spec.max)) return null;
    return String(value) + String(spec.suffix || '');
  }
  /** Unica escrita no quadro: uma variavel CSS no <body>, com nome vindo do servidor. */
  function setBodyVar(name, css) {
    if (!CSS_VAR.test(String(name)) || typeof css !== 'string' || /[;{}<>]/.test(css)) return;
    visibleFrames().forEach(function (f) {
      var doc = null;
      try { doc = f.iframe.contentDocument; } catch (e) { doc = null; }
      if (doc && doc.body) doc.body.style.setProperty(name, css);
    });
  }
  /** Regras que declaram `name`, na ordem do documento (inclui @media). */
  function rulesDeclaring(doc, name) {
    var out = [];
    function walk(list) {
      for (var i = 0; list && i < list.length; i++) {
        var r = list[i];
        if (r.style && r.style.getPropertyValue(name) !== '') out.push(r);
        if (r.cssRules) walk(r.cssRules);
      }
    }
    for (var s = 0; s < doc.styleSheets.length; s++) {
      try { walk(doc.styleSheets[s].cssRules); } catch (e) { /* folha inacessivel */ }
    }
    return out;
  }
  function setRuleVar(name, index, css) {
    if (!CSS_VAR.test(String(name))) return;
    visibleFrames().forEach(function (f) {
      var doc = null;
      try { doc = f.iframe.contentDocument; } catch (e) { doc = null; }
      if (!doc) return;
      var rules = rulesDeclaring(doc, name);
      if (rules[index]) rules[index].style.setProperty(name, css);
    });
  }
  /**
   * Reescreve o valor da `index`-esima declaracao de `name` dentro dos <style>
   * de uma fonte. Mesma gramatica de ThemeLiveTokens::styleVars(). Devolve
   * null quando a declaracao nao esta mais la.
   */
  function rewriteVar(source, name, index, value) {
    if (!CSS_VAR.test(name)) return null;
    var blockRe = /<style\b[^>]*>([\s\S]*?)<\/style\s*>/gi, block, count = 0;
    while ((block = blockRe.exec(source))) {
      var start = block.index + block[0].indexOf('>') + 1;
      var css = block[1].replace(/\/\*[\s\S]*?\*\//g, function (c) { return c.replace(/[^\n]/g, ' '); });
      var declRe = new RegExp('(^|[^\\w-])(' + name + ')(\\s*:\\s*)([^;{}]*?)(?=\\s*[;}])', 'g'), d;
      while ((d = declRe.exec(css))) {
        if (count++ !== index) continue;
        var at = start + d.index + d[1].length + d[2].length + d[3].length;
        return source.slice(0, at) + value + source.slice(at + d[4].length);
      }
    }
    return null;
  }
  function varId(v) { return v.template + '|' + v.name + '|' + v.index; }
  function findVar(id) {
    for (var i = 0; i < styleVars.length; i++) if (varId(styleVars[i]) === id) return styleVars[i];
    return null;
  }
  function designSignature() {
    return scheme + '§' + (varsKnown ? '1' : '0') + '§' + styleVars.map(function (v) { return varId(v) + '|' + v.type; }).join('§');
  }
  function control(kind, id, type, value, spec) {
    var wrap = make('span', 'kaz-canvas__control');
    var attr = 'data-kaz-canvas-' + kind;
    if (type === 'color') {
      var c = make('input', 'kaz-canvas__swatch');
      c.type = 'color'; c.value = HEX.test(value) ? value : '#000000';
      c.setAttribute(attr, id);
      wrap.appendChild(c);
      var out = make('output', 'kaz-canvas__value', c.value);
      wrap.appendChild(out);
    } else if (type === 'map' && spec && spec.options) {
      var sel = make('select', 'kaz-canvas__select');
      spec.options.forEach(function (o) {
        var opt = make('option', '', o); opt.value = o; sel.appendChild(opt);
      });
      sel.value = value;
      sel.setAttribute(attr, id);
      wrap.appendChild(sel);
    } else {
      if (spec && spec.max !== undefined) {
        var range = make('input', 'kaz-canvas__range');
        range.type = 'range'; range.min = String(spec.min); range.max = String(spec.max); range.step = String(spec.step || '1');
        range.value = value;
        range.setAttribute(attr, id);
        range.setAttribute('data-kaz-canvas-twin', 'range');
        wrap.appendChild(range);
      }
      var num = make('input', 'kaz-canvas__number');
      num.type = 'number';
      num.min = spec && spec.min !== undefined ? String(spec.min) : '0';
      num.max = spec && spec.max !== undefined ? String(spec.max) : '4000';
      num.step = spec && spec.step ? String(spec.step) : '1';
      num.value = value;
      num.setAttribute(attr, id);
      num.setAttribute('data-kaz-canvas-twin', 'number');
      wrap.appendChild(num);
    }
    return wrap;
  }
  function row(labelText, mono, node, usageId, extra) {
    var r = make('div', 'kaz-canvas__field');
    if (usageId) r.setAttribute('data-kaz-canvas-usage', usageId);
    var name = make('label', 'kaz-canvas__fieldname' + (mono ? ' kaz-canvas__fieldname--mono' : ''), labelText);
    var input = node.querySelector('input,select');
    if (input) {
      input.id = 'kaz-canvas-ctl-' + (++row.n);
      name.htmlFor = input.id;
    }
    if (extra) name.appendChild(make('small', 'kaz-canvas__scope', extra));
    var badge = make('span', 'kaz-canvas__badge kaz-canvas__badge--off', label('design_unused', ''));
    badge.title = label('design_unused_help', '');
    badge.hidden = true;
    name.appendChild(badge);
    r.appendChild(name);
    r.appendChild(node);
    return r;
  }
  row.n = 0;
  function paintDesign() {
    if (!designEl) return;
    var sig = designSignature();
    if (sig === designSig && designEl.firstChild) { syncDesign(); return; }
    designSig = sig;
    clear(designEl);
    var T = config.tokens;

    /* 1. Variaveis do proprio tema: e o que os temas desenhados realmente leem. */
    if (varsKnown) designEl.appendChild(make('h5', 'kaz-canvas__sidesub', label('design_theme_vars', '')));
    if (!varsKnown) {
      /* Antes da primeira resposta nao se sabe o que o tema declara: nao afirma nada. */
    } else if (!styleVars.length) {
      designEl.appendChild(make('p', 'kaz-canvas__sidehelp', label('design_no_vars', '')));
    } else {
      designEl.appendChild(make('p', 'kaz-canvas__sidehelp', label('design_theme_vars_help', '')));
      [['color', 'design_colors'], ['length', 'design_sizes']].forEach(function (g) {
        var list = styleVars.filter(function (v) { return v.type === g[0]; });
        if (!list.length) return;
        designEl.appendChild(make('h6', 'kaz-canvas__sidesub2', label(g[1], '')));
        list.forEach(function (v) {
          var m = PX.exec(v.value);
          var node = control('var', varId(v), g[0] === 'color' ? 'color' : 'number', g[0] === 'color' ? v.value : (m ? m[1] : '0'),
            g[0] === 'color' ? null : { step: m && m[1].indexOf('.') !== -1 ? '0.1' : '1' });
          var scope = v.scope && v.scope !== ':root' ? v.scope : '';
          designEl.appendChild(row(v.name, true, node, '', scope));
        });
      });
    }

    /* 2. As duas cores do modulo Geral. */
    var general = Object.keys(T.general || {});
    if (general.length) {
      designEl.appendChild(make('h5', 'kaz-canvas__sidesub', label('design_general', '')));
      general.forEach(function (k) {
        var el = field(k);
        if (!el) return;
        designEl.appendChild(row(String(T.general[k].label || k), false, control('general', k, 'color', el.value, null), 'general:' + k));
      });
    }

    /* 3. Design tokens do sistema, por grupo. */
    (T.groups || []).forEach(function (g) {
      var keys = Object.keys(T.fields || {}).filter(function (k) { return T.fields[k].group === g.id && field('tokens[' + k + ']'); });
      if (!keys.length) return;
      designEl.appendChild(make('h5', 'kaz-canvas__sidesub', String(g.label || g.id)));
      if (g.id === 'dark' && scheme !== 'dark') designEl.appendChild(make('p', 'kaz-canvas__sidehelp', label('design_dark_only', '')));
      keys.forEach(function (k) {
        var spec = T.fields[k], el = field('tokens[' + k + ']');
        var type = spec.kind === 'color' ? 'color' : (spec.options ? 'map' : 'number');
        var text = String(spec.label || k) + (spec.unit ? ' (' + spec.unit + ')' : '');
        designEl.appendChild(row(text, false, control('token', k, type, el.value, spec), 'token:' + k));
      });
    });

    /* 4. Contraste medido pelo servidor com as cores do formulario. */
    if (varsKnown) {
      designEl.appendChild(make('h5', 'kaz-canvas__sidesub', label('design_contrast', '')));
      designEl.appendChild(make('ul', 'kaz-canvas__contrast'));
    }
    syncDesign();
  }
  /** Atualiza valores, avisos de uso e contraste sem reconstruir (o foco fica). */
  function syncDesign() {
    if (!designEl) return;
    var focus = document.activeElement;
    var ctls = designEl.querySelectorAll('[data-kaz-canvas-token],[data-kaz-canvas-general],[data-kaz-canvas-var]');
    for (var i = 0; i < ctls.length; i++) {
      var c = ctls[i], value = null, k;
      if (c === focus) continue;
      if ((k = c.getAttribute('data-kaz-canvas-token'))) { var el = field('tokens[' + k + ']'); value = el ? el.value : null; }
      else if ((k = c.getAttribute('data-kaz-canvas-general'))) { var ge = field(k); value = ge ? ge.value : null; }
      else if ((k = c.getAttribute('data-kaz-canvas-var'))) {
        var v = findVar(k);
        if (v) { var m = PX.exec(v.value); value = v.type === 'color' ? v.value : (m ? m[1] : null); }
      }
      if (value === null || c.value === value) continue;
      c.value = value;
      if (c.type === 'color' && c.nextSibling) c.nextSibling.textContent = c.value;
    }
    var rows = designEl.querySelectorAll('[data-kaz-canvas-usage]');
    for (i = 0; i < rows.length; i++) {
      var id = rows[i].getAttribute('data-kaz-canvas-usage'), badge = rows[i].querySelector('.kaz-canvas__badge--off');
      var off = usage[id] === false;
      if (badge) badge.hidden = !off;
      rows[i].classList.toggle('kaz-canvas__field--off', off);
    }
    var ul = designEl.querySelector('.kaz-canvas__contrast');
    if (ul) {
      clear(ul);
      var failed = false;
      contrast.forEach(function (p) {
        var li = make('li', p.ok ? '' : 'kaz-canvas__contrast--fail');
        li.appendChild(make('span', 'kaz-canvas__ratio', Number(p.ratio).toFixed(2) + ':1'));
        li.appendChild(make('span', '', String(config.tokens.pairs[p.code] || p.code)));
        ul.appendChild(li);
        if (!p.ok) failed = true;
      });
      if (failed) ul.appendChild(make('li', 'kaz-canvas__contrast--note', label('design_contrast_fail', '')));
    }
  }
  /** Um controle da aba Design mudou: campo real + previa imediata no quadro. */
  function onDesignInput(t) {
    var k, value = String(t.value);
    touched = Date.now();
    probeRun++;
    /* range e number do mesmo campo andam juntos. */
    var twin = t.getAttribute('data-kaz-canvas-twin');
    if (twin && t.parentNode) {
      var other = t.parentNode.querySelector('[data-kaz-canvas-twin="' + (twin === 'range' ? 'number' : 'range') + '"]');
      if (other) other.value = value;
    }
    if (t.type === 'color' && t.nextSibling) t.nextSibling.textContent = value;

    if ((k = t.getAttribute('data-kaz-canvas-token'))) {
      var spec = config.tokens.fields[k], css = tokenCss(spec, value);
      if (css === null) return true;
      writeField('tokens[' + k + ']', value);
      setBodyVar(spec['var'], css);
      return true;
    }
    if ((k = t.getAttribute('data-kaz-canvas-general'))) {
      var g = config.tokens.general[k];
      if (!g || !HEX.test(value)) return true;
      writeField(k, value);
      setBodyVar(g['var'], value);
      return true;
    }
    if ((k = t.getAttribute('data-kaz-canvas-var'))) {
      var v = findVar(k);
      if (!v) return true;
      var next = null;
      if (v.type === 'color') { if (HEX.test(value)) next = value.toLowerCase(); }
      else { var n = Number(value); if (value !== '' && isFinite(n) && n >= 0 && n <= 4000) next = String(Math.round(n * 1000) / 1000) + 'px'; }
      if (next === null) return true;
      var src = sourceField(v.template);
      var rewritten = src ? rewriteVar(src.value, v.name, v.index, next) : null;
      if (rewritten === null || rewritten.length > 131072) {
        notes([{ kind: 'warn', text: label('design_stale', '') }]);
        return true;
      }
      v.value = next;
      writeSource(v.template, rewritten);
      setRuleVar(v.name, v.index, next);
      /* O codigo aberto na aba Trecho mostra a mesma fonte. */
      if (keyOf(selected) === v.template) paintInspector();
      return true;
    }
    return false;
  }

  /* ---------------- KZ-03: este token muda a pagina? ---------------- */
  function probeKey() {
    var parts = [scheme, routeSel ? routeSel.value : '', (field('sections_order') || {}).value || ''], els = form.elements;
    for (var i = 0; i < els.length; i++) {
      var n = els[i].name || '';
      if (n.indexOf('templates[') === 0) parts.push(els[i].value.length);
      else if (n === 'sections[]' && els[i].checked) parts.push(els[i].value);
    }
    return parts.join('|');
  }
  function scheduleProbe() {
    if (!designVisible()) return;
    var key = probeKey();
    if (key === usageKey) { syncDesign(); return; }
    var run = ++probeRun;
    window.setTimeout(function () { if (run === probeRun) probe(run, key); }, 250);
  }
  /**
   * Mede no primeiro quadro: troca UMA variavel por um valor de sondagem, le o
   * estilo calculado de uma amostra de elementos e devolve a variavel ao valor
   * anterior. Se nada mudou, o token nao tem efeito nesta pagina.
   */
  function probe(run, key) {
    if (Date.now() - touched < 1500) { window.setTimeout(function () { if (run === probeRun) probe(run, key); }, 800); return; }
    var f = leadFrame(), doc = null, win = null;
    try { doc = f ? f.iframe.contentDocument : null; win = doc ? doc.defaultView : null; } catch (e) { doc = null; }
    if (!doc || !doc.body || !win || typeof win.getComputedStyle !== 'function') return;
    var all = doc.body.querySelectorAll('*'), els = [doc.body], step = Math.max(1, Math.ceil(all.length / PROBE_SAMPLE));
    for (var i = 0; i < all.length; i += step) els.push(all[i]);
    function print() {
      var out = [];
      for (var e = 0; e < els.length; e++) {
        var cs = win.getComputedStyle(els[e]);
        for (var p = 0; p < PROBE_PROPS.length; p++) out.push(cs.getPropertyValue(PROBE_PROPS[p]));
      }
      return out.join('|');
    }
    var jobs = [], T = config.tokens;
    Object.keys(T.general || {}).forEach(function (k) {
      var el = field(k);
      if (el) jobs.push({ id: 'general:' + k, name: T.general[k]['var'], css: el.value.toLowerCase() === '#ff00ff' ? '#00ff00' : '#ff00ff' });
    });
    Object.keys(T.fields || {}).forEach(function (k) {
      var spec = T.fields[k], el = field('tokens[' + k + ']');
      if (!el) return;
      /* O anel de foco so aparece com foco; cores do outro esquema nao estao em uso agora. */
      if (k === 'focus_ring' || (spec.group === 'dark' && scheme !== 'dark') || (spec.group === 'light' && scheme === 'dark' && k !== 'focus_ring')) return;
      var now = tokenCss(spec, el.value), css = null;
      if (spec.kind === 'color') css = String(now).toLowerCase() === '#ff00ff' ? '#00ff00' : '#ff00ff';
      else if (spec.kind === 'map') { Object.keys(spec.map || {}).forEach(function (o) { if (css === null && spec.map[o] !== now) css = spec.map[o]; }); }
      else css = tokenCss(spec, String(Number(el.value) === Number(spec.max) ? spec.min : spec.max));
      if (css !== null) jobs.push({ id: 'token:' + k, name: spec['var'], css: css });
    });
    var base = print(), result = {}, at = 0;
    (function next() {
      if (run !== probeRun || !doc.body) return;
      var until = Date.now() + 12;
      while (at < jobs.length && Date.now() < until) {
        var j = jobs[at++];
        if (!CSS_VAR.test(String(j.name))) continue;
        var before = doc.body.style.getPropertyValue(j.name);
        doc.body.style.setProperty(j.name, j.css);
        result[j.id] = print() !== base;
        if (before !== '') doc.body.style.setProperty(j.name, before); else doc.body.style.removeProperty(j.name);
      }
      if (at < jobs.length) { window.setTimeout(next, 0); return; }
      usage = result;
      usageKey = key;
      syncDesign();
    })();
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
    body.set('canvas_scheme', scheme);

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
      styleVars = [];
      if (Array.isArray(json.style_vars)) json.style_vars.forEach(function (v) {
        if (!v || !CSS_VAR.test(String(v.name)) || typeof v.template !== 'string' || typeof v.value !== 'string') return;
        if (v.type === 'color' ? !HEX.test(v.value) : (v.type !== 'length' || !PX.test(v.value))) return;
        styleVars.push({ template: v.template, name: String(v.name), index: Number(v.index) || 0, value: v.value, type: v.type, scope: String(v.scope || '') });
      });
      contrast = Array.isArray(json.contrast) ? json.contrast.filter(function (p) { return p && typeof p.code === 'string'; }) : [];
      varsKnown = true;
      probeRun++;
      setDocument(json.html);
      paintDesign();
      var lines = [];
      if (json.source === 'saved') {
        var why = typeof json.form_error === 'string' && json.form_error !== '' ? ' ' + label('form_invalid', '') + ' ' + json.form_error : '';
        lines.push({ kind: 'warn', text: label('saved_draft', '') + why });
      }
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
      '[data-kaz-canvas-tab],[data-kaz-canvas-scheme],' +
      '[data-kaz-canvas-section],[data-kaz-canvas-show-section],[data-kaz-canvas-source]') : null;
    if (!t || !root.contains(t)) return;
    var panel = t.getAttribute('data-kaz-canvas-panel');
    if (panel === 'structure' || panel === 'inspector') {
      panels[panel] = !panels[panel];
      paintPanels(); savePrefs();
      if (designVisible()) { paintDesign(); scheduleProbe(); }
      return;
    }
    var nextTab = t.getAttribute('data-kaz-canvas-tab');
    if (nextTab === 'piece' || nextTab === 'design') {
      tab = nextTab;
      paintPanels(); savePrefs();
      if (tab === 'design') { paintDesign(); scheduleProbe(); }
      return;
    }
    var nextScheme = t.getAttribute('data-kaz-canvas-scheme');
    if (nextScheme === 'light' || nextScheme === 'dark') {
      if (nextScheme === scheme) return;
      scheme = nextScheme;
      paintPanels(); savePrefs(); refresh();
      return;
    }
    var uid = t.getAttribute('data-kaz-canvas-uid');
    if (uid) {
      /* Escolher na Estrutura e pedir para ver aquele trecho. */
      if (tab !== 'piece') { tab = 'piece'; paintPanels(); savePrefs(); }
      select(uid, true);
      return;
    }
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
        var moduleBtn = shell.querySelector('button.kaz-module-link[data-kaz-module="templates"]');
        if (moduleBtn) moduleBtn.click();
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
    var t = ev.target;
    if (!t || !t.getAttribute) return;
    if (designEl && designEl.contains(t)) { onDesignInput(t); return; }
    var key = t.getAttribute('data-kaz-canvas-code');
    if (!key || t.readOnly) return;
    writeSource(key, t.value);
  });
  /* Ao sair de um controle, ele volta a mostrar o valor que o campo real aceitou. */
  root.addEventListener('focusout', function (ev) {
    if (designEl && ev.target && designEl.contains(ev.target)) window.setTimeout(syncDesign, 0);
  });
  /* Setas trocam de aba (padrao de tablist). */
  root.addEventListener('keydown', function (ev) {
    var t = ev.target;
    if (!t || !t.getAttribute || !t.getAttribute('data-kaz-canvas-tab')) return;
    if (ev.key !== 'ArrowLeft' && ev.key !== 'ArrowRight') return;
    var other = root.querySelector('[data-kaz-canvas-tab="' + (tab === 'piece' ? 'design' : 'piece') + '"]');
    if (other) { ev.preventDefault(); other.click(); other.focus(); }
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
    /* Os controles do Design nao dependem do servidor: aparecem ja na abertura. */
    if (tab === 'design') paintDesign();
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
