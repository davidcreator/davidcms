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
 * KZ-05 (ADR 0098) — atributos do elemento. O bloco "Elemento" ganha o
 * destino do link (`href`), "abrir em nova aba", o texto alternativo de imagem
 * (`alt`) e o rotulo para leitor de tela (`aria-label`). As regras de escrita
 * ficam em kaz-element-attrs.js (window.KazElementAttrs); aqui fica so o
 * painel. Esses campos nao escrevem no documento do quadro.
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
  /* KZ-04 (ADR 0086): elemento dentro de um trecho. */
  var EL_ATTR = 'data-kz-el', EL_REF = /^([a-z0-9_\/-]{1,64}):(\d{1,5})$/;
  var TEXT_MAX = 2000, SOURCE_MAX = 131072, TEXT_LIST_MAX = 80, WS = ' \t\r\n\f';
  /* KZ-05 (ADR 0098): regras de atributo. Sem o arquivo, os campos nao aparecem. */
  var ATTRS = window.KazElementAttrs && typeof window.KazElementAttrs.setValue === 'function' ? window.KazElementAttrs : null;
  /* KZ-06: o teto do servidor e em bytes; a conta fica no arquivo de regras. */
  function overLimit(s) { return ATTRS && typeof ATTRS.tooLong === 'function' ? ATTRS.tooLong(s) : s.length > SOURCE_MAX; }
  var ATTR_FIELDS = ['href', 'alt', 'aria-label'];
  /* KZ-06 (ADR 0099): cor do texto, cor de fundo e tamanho do texto do elemento. */
  var STYLE_FIELDS = ['color', 'background-color', 'font-size'];
  var WARN_NAMES = ATTR_FIELDS.concat(['newtab'], STYLE_FIELDS);
  var STYLE_LIST_MAX = 60, VAR_SCAN_MAX = 600;

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
  var elements = {};        // chave -> { sig, items } (mapa de elementos, do servidor)
  var picked = null;        // elemento selecionado: { key, n, inst, tag, sig }
  var hoverEl = null;       // elemento sob o ponteiro: { key, n, inst }
  var liveText = null;      // texto digitado que o servidor ainda nao devolveu: { key, n, text }

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

      var elHoverBox = document.createElement('div');
      elHoverBox.className = 'kaz-canvas__mark kaz-canvas__mark--elhover';
      elHoverBox.hidden = true;
      body.appendChild(elHoverBox);
      var elBox = document.createElement('div');
      elBox.className = 'kaz-canvas__mark kaz-canvas__mark--el';
      elBox.hidden = true;
      var elTag = document.createElement('span');
      elTag.className = 'kaz-canvas__marktag kaz-canvas__marktag--el';
      elBox.appendChild(elTag);
      body.appendChild(elBox);

      var shield = document.createElement('div');
      shield.className = 'kaz-canvas__shield';
      body.appendChild(shield);
      el.appendChild(body);
      world.appendChild(el);

      var f = {
        el: el, body: body, iframe: iframe, cap: cap, hoverBox: hoverBox, selectBox: selectBox, selectTag: selectTag,
        elHoverBox: elHoverBox, elBox: elBox, elTag: elTag,
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
    if (liveText) setElementText(doc, liveText.key, liveText.n, liveText.text);
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
      placeMark(f.elHoverBox, hoverEl && !sameElement(hoverEl, picked) ? elementRect(f, hoverEl) : null);
      placeMark(f.elBox, picked ? elementRect(f, picked) : null);
      f.elTag.textContent = picked ? '<' + picked.tag + '>' : '';
    });
    root.setAttribute('data-kaz-canvas-element', picked ? '1' : '0');
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
    if (picked && picked.key !== keyOf(selected)) picked = null;
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

  /* ---------------- KZ-04: elemento dentro do trecho ---------------- */
  var crcTable = null;
  /** CRC-32 dos bytes UTF-8 + tamanho em unidades UTF-16: igual a ThemeSourceMap::signature(). */
  function sigOf(text) {
    var i, j, c, bytes;
    if (!crcTable) {
      crcTable = [];
      for (i = 0; i < 256; i++) { c = i; for (j = 0; j < 8; j++) c = (c & 1) ? (0xEDB88320 ^ (c >>> 1)) : (c >>> 1); crcTable[i] = c >>> 0; }
    }
    text = String(text);
    try { bytes = new window.TextEncoder().encode(text); } catch (e) { return ''; }
    c = 0xFFFFFFFF;
    for (i = 0; i < bytes.length; i++) c = crcTable[(c ^ bytes[i]) & 0xFF] ^ (c >>> 8);
    return ('0000000' + ((c ^ 0xFFFFFFFF) >>> 0).toString(16)).slice(-8) + ':' + text.length;
  }
  function frameDoc(f) {
    var doc = null;
    try { doc = f.iframe.contentDocument; } catch (e) { doc = null; }
    return doc && doc.documentElement ? doc : null;
  }
  function elementItem(key, n) {
    var m = elements[key];
    return m && m.items[n] ? m.items[n] : null;
  }
  /** Todas as ocorrencias de um elemento do codigo na pagina (um laco gera varias). */
  function elementNodes(doc, key, n) {
    if (!doc || !EL_REF.test(key + ':' + n)) return [];
    return doc.querySelectorAll('[' + EL_ATTR + '="' + key + ':' + n + '"]');
  }
  /** Elemento mapeado mais proximo, subindo a partir de um no. Confere chave, indice e tag. */
  function elementRef(node) {
    while (node && node.nodeType === 1) {
      var m = EL_REF.exec(node.getAttribute(EL_ATTR) || '');
      if (m) {
        var it = elementItem(m[1], Number(m[2]));
        if (it && it[0] === String(node.localName || '').toLowerCase()) return { key: m[1], n: Number(m[2]), node: node };
      }
      node = node.parentElement;
    }
    return null;
  }
  function instanceOf(doc, ref) {
    return Math.max(0, Array.prototype.indexOf.call(elementNodes(doc, ref.key, ref.n), ref.node));
  }
  /** Elemento sob um ponto do mundo. Somente leitura do documento do quadro. */
  function elementAt(wx, wy) {
    var out = null;
    visibleFrames().forEach(function (f) {
      if (out || wx < f.left || wx > f.left + f.device.w || wy < 0 || wy > f.height) return;
      var doc = frameDoc(f), node = null;
      if (!doc || typeof doc.elementFromPoint !== 'function') return;
      try { node = doc.elementFromPoint(wx - f.left, wy); } catch (e) { node = null; }
      var ref = elementRef(node);
      if (ref) out = { key: ref.key, n: ref.n, inst: instanceOf(doc, ref), tag: elementItem(ref.key, ref.n)[0] };
    });
    return out;
  }
  function sameElement(a, b) { return !!a && !!b && a.key === b.key && a.n === b.n && a.inst === b.inst; }
  function elementNode(f, sel) {
    var list = elementNodes(frameDoc(f), sel.key, sel.n);
    return list[sel.inst] || list[0] || null;
  }
  function elementRect(f, sel) {
    var node = elementNode(f, sel);
    if (!node) return null;
    var b = node.getBoundingClientRect(), x = Math.max(0, b.left), y = Math.max(0, b.top);
    var r = { x: x, y: y, w: Math.min(f.device.w, b.right) - x, h: b.bottom - y, shown: false };
    r.shown = r.w >= 1 && r.h >= 1;
    return r;
  }
  function setHoverElement(sel) {
    if (sameElement(sel, hoverEl) || (!sel && !hoverEl)) return;
    hoverEl = sel;
    paintMarks();
  }
  /** Trecho (uid) da chave do elemento que contem o ponto; senao o primeiro daquela chave. */
  function regionFor(key, wx, wy) {
    var inside = '', first = '';
    visibleFrames().forEach(function (f) {
      var lx = wx - f.left;
      f.regions.forEach(function (r) {
        if (r.key !== key) return;
        if (!first) first = r.uid;
        if (!inside && r.shown && lx >= r.x && lx <= r.x + r.w && wy >= r.y && wy <= r.y + r.h) inside = r.uid;
      });
    });
    return inside || first;
  }
  /** Escolhe um elemento (ou nenhum) e o trecho que o contem. */
  function pick(sel, uid, reveal) {
    picked = sel && elementItem(sel.key, sel.n) ? { key: sel.key, n: sel.n, inst: sel.inst || 0, tag: elementItem(sel.key, sel.n)[0], sig: elements[sel.key].sig } : null;
    if (picked && keyOf(uid) !== picked.key) uid = regionFor(picked.key, -1, -1) || uid;
    if (picked && keyOf(uid) !== picked.key) picked = null;
    select(uid, false);
    /* Nova escolha: o painel volta ao topo, onde fica o bloco do elemento. */
    var side = inspectorEl && typeof inspectorEl.closest === 'function' ? inspectorEl.closest('.kaz-canvas__side') : null;
    if (side) side.scrollTop = 0;
    if (reveal && picked) revealElement();
  }
  function revealElement() {
    var f = leadFrame(), r = f && picked ? elementRect(f, picked) : null;
    if (!f || !r || !r.shown) return;
    var vr = viewport.getBoundingClientRect();
    var top = view.y + r.y * view.k, bottom = top + r.h * view.k;
    if (top >= 40 && bottom <= vr.height - 20) return;
    view.y = Math.round(Math.max(72, vr.height / 3) - r.y * view.k);
    apply();
  }
  /** Indices dos elementos da MESMA fonte que contem o escolhido, de fora para dentro. */
  function elementChain(sel) {
    var f = leadFrame(), node = f ? elementNode(f, sel) : null, out = [sel.n];
    node = node ? node.parentElement : null;
    while (node && out.length < 32) {
      var ref = elementRef(node);
      if (!ref) break;
      if (ref.key === sel.key && out[0] !== ref.n) out.unshift(ref.n);
      node = ref.node.parentElement;
    }
    return out;
  }
  /** Fonte em que as posicoes do mapa valem: o campo do tema ou a fonte padrao. */
  function elementSource(key) {
    var field = sourceField(key);
    if (field && field.value !== '') return field.value;
    return typeof defaults[key] === 'string' ? defaults[key] : '';
  }
  function mapFresh(key) {
    var m = elements[key];
    return !!m && m.sig !== '' && m.sig === sigOf(elementSource(key));
  }
  function trimWs(text) {
    var a = 0, b = text.length;
    while (a < b && WS.indexOf(text.charAt(a)) !== -1) a++;
    while (b > a && WS.indexOf(text.charAt(b - 1)) !== -1) b--;
    return { lead: text.slice(0, a), body: text.slice(a, b), trail: text.slice(b) };
  }
  /** Texto do codigo -> texto como se le na pagina (entidades resolvidas, espacos juntos). */
  function decodeText(raw) {
    var box = document.createElement('textarea');
    box.innerHTML = String(raw);
    return trimWs(box.value.replace(/[ \t\r\n\f]+/g, ' ')).body;
  }
  /**
   * Texto digitado -> texto seguro para o codigo. Nunca vira tag nem Twig, e
   * nao esbarra nos padroes que a validacao do tema recusa.
   */
  function encodeText(value) {
    var v = String(value).replace(/[\u0000-\u0008\u000B\u000C\u000E-\u001F\u007F]/g, '').slice(0, TEXT_MAX);
    v = v.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/\{/g, '&#123;');
    if (/\b(on[a-z]+|srcdoc|formaction)\s*=/i.test(v)) v = v.replace(/=/g, '&#61;');
    if (/\|\s*raw\b/i.test(v)) v = v.replace(/\|/g, '&#124;');
    return v.replace(/(javascript\s*):/gi, '$1&#58;');
  }
  /** Previa imediata: troca so o texto das ocorrencias do elemento em um documento. */
  function setElementText(doc, key, n, text) {
    var list = elementNodes(doc, key, n);
    for (var i = 0; i < list.length; i++) {
      /* So folhas: um elemento com filhos nunca e sobrescrito. */
      if (list[i].firstElementChild) continue;
      list[i].textContent = text;
    }
  }
  /** O campo "Texto" mudou: reescreve so aquele texto na fonte do tema. */
  function onTextInput(t) {
    if (!picked) return;
    var key = picked.key, n = picked.n, map = elements[key], it = elementItem(key, n), field = sourceField(key);
    var warn = inspectorEl ? inspectorEl.querySelector('[data-kaz-canvas-el-warn]') : null;
    if (!map || !it || !field || field.value === '' || (it[1] !== 'fixed' && it[1] !== 'empty') || it[4] < 0) return;
    var src = field.value;
    if (map.sig !== sigOf(src)) { if (warn) { warn.hidden = false; warn.textContent = label('el_stale', ''); } return; }
    if (warn) warn.hidden = true;
    /* As pontas digitadas nao entram: o recuo original do codigo e mantido
       e um espaco no fim nao se acumula a cada tecla. */
    var parts = trimWs(src.slice(it[4], it[5])), enc = trimWs(encodeText(t.value)).body;
    var next = src.slice(0, it[4]) + parts.lead + enc + parts.trail + src.slice(it[5]);
    /* KZ-05: fonte cheia avisa em vez de ignorar a tecla em silencio. */
    if (overLimit(next)) { if (warn) { warn.hidden = false; warn.textContent = label('el_full', ''); } return; }
    var delta = next.length - src.length;
    /* O mapa local acompanha a escrita: a proxima tecla nao espera o servidor. */
    for (var j = n + 1; j < map.items.length; j++) {
      for (var k = 2; k <= 5; k++) if (map.items[j][k] >= 0) map.items[j][k] += delta;
      /* KZ-05: os atributos dos elementos seguintes ficam depois deste texto. */
      var later = map.items[j][7];
      if (Array.isArray(later)) for (var a = 0; a < later.length; a++) for (k = 2; k <= 5; k++) later[a][k] += delta;
    }
    it[5] += delta;
    it[1] = trimWs(enc).body === '' ? 'empty' : 'fixed';
    liveText = { key: key, n: n, text: String(t.value).slice(0, TEXT_MAX) };
    visibleFrames().forEach(function (f) { setElementText(frameDoc(f), key, n, liveText.text); });
    commitSource(key, map, next);
  }
  /** A fonte de um trecho foi reescrita pelo painel: mapa, codigo a vista e campo real acompanham. */
  function commitSource(key, map, next) {
    map.sig = sigOf(next);
    if (picked && picked.key === key) picked.sig = map.sig;
    var code = inspectorEl ? inspectorEl.querySelector('[data-kaz-canvas-code]') : null;
    if (code && code.getAttribute('data-kaz-canvas-code') === key) code.value = next;
    writeSource(key, next);
    paintMarks();
  }

  /* ---------------- KZ-05: atributos do elemento ---------------- */
  /** Valor de atributo do codigo -> valor como o navegador o le (entidades resolvidas). */
  function decodeAttr(raw) {
    raw = String(raw);
    var q = raw.indexOf('"') === -1 ? '"' : "'";
    if (raw.indexOf(q) !== -1 || typeof window.DOMParser !== 'function') return raw;
    try {
      /* Documento inerte: nada dele e executado, carregado ou mostrado. */
      var doc = new window.DOMParser().parseFromString('<!doctype html><body><i title=' + q + raw + q + '></i>', 'text/html');
      var el = doc.body ? doc.body.firstElementChild : null;
      return el && el.localName === 'i' && el.getAttribute('title') !== null ? String(el.getAttribute('title')) : raw;
    } catch (e) { return raw; }
  }
  function attrWarn(name, labelKey) {
    var warn = inspectorEl && WARN_NAMES.indexOf(name) !== -1 ? inspectorEl.querySelector('[data-kaz-canvas-attr-warn="' + name + '"]') : null;
    if (!warn) return;
    warn.hidden = !labelKey;
    warn.textContent = labelKey ? label(labelKey, '') : '';
  }
  /** O que a fonte do tema tem agora, e o mapa dela, se os dois ainda combinam. */
  function attrTarget() {
    if (!picked || !ATTRS) return null;
    var map = elements[picked.key], field = sourceField(picked.key);
    if (!map || !field || field.value === '') return null;
    return { key: picked.key, n: picked.n, map: map, src: field.value, fresh: map.sig === sigOf(field.value) };
  }
  /** Um campo de atributo mudou: reescreve so aquele valor na fonte do tema. */
  function onAttrInput(t) {
    var name = t.getAttribute('data-kaz-canvas-attr'), at = attrTarget();
    if (!at || ATTR_FIELDS.indexOf(name) === -1) return;
    if (!at.fresh) { attrWarn(name, 'el_stale'); return; }
    var res = ATTRS.setValue(at.src, at.map, at.n, name, t.value);
    if (res.error === 'link') { attrWarn(name, 'el_link_bad'); return; }
    if (res.error === 'long') { attrWarn(name, 'el_full'); return; }
    if (res.error || typeof res.src !== 'string') { attrWarn(name, 'el_stale'); return; }
    attrWarn(name, '');
    commitSource(at.key, at.map, res.src);
  }
  /** A caixa "abrir em nova aba" mudou: escreve ou retira `target` e `rel`. */
  function onNewTab(t) {
    var at = attrTarget(), res = at && at.fresh ? ATTRS.setNewTab(at.src, at.map, at.n, !!t.checked) : null;
    if (!res || res.error || typeof res.src !== 'string') {
      /* Nada foi escrito: a caixa volta a mostrar o que o codigo tem. */
      t.checked = at ? ATTRS.newTab(at.src, at.map, at.n).on : false;
      attrWarn('newtab', res && res.error === 'long' ? 'el_full' : 'el_stale');
      return;
    }
    attrWarn('newtab', '');
    commitSource(at.key, at.map, res.src);
  }
  function attrHelp(host, labelKey, expr) {
    var p = make('p', 'kaz-canvas__sidehelp', label(labelKey, '') + (expr ? ' ' : ''));
    if (expr) p.appendChild(make('code', 'kaz-canvas__expr', expr));
    host.appendChild(p);
    return p;
  }
  /** Linha de aviso de um campo do elemento: vazia e escondida enquanto o mapa esta em dia. */
  function warnSlot(host, name, fresh) {
    var warn = make('p', 'kaz-canvas__sidehelp kaz-canvas__sidehelp--warn', '');
    warn.setAttribute('data-kaz-canvas-attr-warn', name);
    warn.setAttribute('role', 'status');
    warn.hidden = fresh;
    if (!fresh) warn.textContent = label('el_stale', '');
    host.appendChild(warn);
  }
  /** Campos de atributo do elemento escolhido. Devolve true quando mostrou algum campo. */
  function paintAttrs(key, host, it, own, fresh) {
    if (!ATTRS) return false;
    var tag = it[0], list = it[7], src = elementSource(key), shown = false;
    if (!Array.isArray(list)) {
      /* Tag que o servidor nao soube ler com seguranca: so avisa onde faria diferenca. */
      if (tag === 'a' || tag === 'img') attrHelp(host, 'el_attr_locked', '');
      return false;
    }
    function warnLine(name) { warnSlot(host, name, fresh); }
    /* Um campo de texto para o valor de um atributo, ou a frase que explica por que nao ha campo. */
    function valueField(name, title, help, fromData) {
      var a = ATTRS.find(it, name);
      if (!a) return false;
      if (a[1] === 'variable') {
        attrHelp(host, fromData, a[6] !== '' ? '{{ ' + a[6] + ' }}' : (fresh ? src.slice(a[2], a[3]).slice(0, 120) : ''))
          .setAttribute('data-kaz-canvas-attr-from', name);
        attrHelp(host, 'el_attr_variable_help', '');
        return true;
      }
      if (a[1] !== 'fixed') { attrHelp(host, 'el_attr_locked', ''); return true; }
      var id = 'kaz-canvas-el-attr-' + name;
      var lab = make('label', 'kaz-canvas__fieldname', label(title, ''));
      lab.setAttribute('for', id);
      host.appendChild(lab);
      var box = make('input', 'kaz-canvas__input');
      box.type = 'text';
      box.id = id;
      box.maxLength = name === 'href' ? ATTRS.LINK_MAX : ATTRS.LABEL_MAX;
      box.setAttribute('data-kaz-canvas-attr', name);
      box.setAttribute('autocomplete', 'off');
      if (name === 'href') {
        box.setAttribute('inputmode', 'url');
        box.setAttribute('spellcheck', 'false');
        box.setAttribute('autocapitalize', 'off');
      }
      box.value = fresh ? decodeAttr(src.slice(a[2], a[3])) : '';
      box.readOnly = !own || !fresh;
      host.appendChild(box);
      warnLine(name);
      attrHelp(host, help, '');
      shown = true;
      return true;
    }

    if (valueField('href', 'el_link', 'el_link_help', 'el_link_variable') && tag === 'a') {
      /* "Abrir em nova aba" fica logo abaixo do destino do link. */
      var state = fresh ? ATTRS.newTab(src, { items: [it] }, 0) : { can: false, on: false };
      var row = make('label', 'kaz-canvas__check');
      var tick = make('input', '');
      tick.type = 'checkbox';
      tick.setAttribute('data-kaz-canvas-newtab', '');
      tick.checked = state.on;
      tick.disabled = !own || !fresh || !state.can;
      row.appendChild(tick);
      row.appendChild(make('span', '', label('el_newtab', '')));
      host.appendChild(row);
      warnLine('newtab');
      attrHelp(host, fresh && !state.can ? 'el_newtab_locked' : 'el_newtab_help', '');
      shown = true;
    }
    if (!valueField('alt', 'el_alt', 'el_alt_help', 'el_alt_variable') && tag === 'img') attrHelp(host, 'el_alt_missing', '');
    valueField('aria-label', 'el_label', 'el_label_help', 'el_label_variable');
    return shown;
  }

  /* ---------------- KZ-06: cor e tamanho do texto ---------------- */
  var varCache = { doc: null, names: [] };
  /**
   * Nomes das variaveis CSS que as folhas de estilo da pagina declaram.
   * Somente leitura do quadro. A ultima folha vem primeiro: e a do proprio
   * tema, escrita depois das folhas de base do sistema.
   */
  function pageVarNames(doc) {
    if (!doc) return [];
    if (varCache.doc === doc) return varCache.names;
    var names = [], seen = {}, count = 0;
    function walk(list, into) {
      for (var i = 0; list && i < list.length && count < VAR_SCAN_MAX; i++) {
        var st = list[i].style;
        for (var j = 0; st && j < st.length && count < VAR_SCAN_MAX; j++) {
          var name = String(st[j]);
          if (!CSS_VAR.test(name) || seen[name]) continue;
          seen[name] = true;
          into.push(name);
          count++;
        }
        if (list[i].cssRules) walk(list[i].cssRules, into);
      }
    }
    for (var s = doc.styleSheets.length - 1; s >= 0; s--) {
      var sheet = [];
      try { walk(doc.styleSheets[s].cssRules, sheet); } catch (e) { /* folha inacessivel */ }
      names = names.concat(sheet);
    }
    varCache = { doc: doc, names: names };
    return names;
  }
  /** Estilo calculado de um no do quadro, ou null. Somente leitura. */
  function computedOf(node) {
    try {
      var view = node && node.ownerDocument ? node.ownerDocument.defaultView : null;
      return view ? view.getComputedStyle(node) : null;
    } catch (e) { return null; }
  }
  /**
   * Cores do tema que valem para este elemento: variaveis que a pagina declara
   * (mais as do painel Design) e cujo valor, neste elemento, e uma cor.
   */
  function themeColors(node) {
    var cs = computedOf(node), out = [], seen = {};
    if (!cs) return out;
    var view = node.ownerDocument.defaultView, names = pageVarNames(node.ownerDocument).slice(), T = config.tokens;
    Object.keys(T.general || {}).forEach(function (k) { names.push(String(T.general[k]['var'])); });
    Object.keys(T.fields || {}).forEach(function (k) { if (T.fields[k].kind === 'color') names.push(String(T.fields[k]['var'])); });
    for (var i = 0; i < names.length && out.length < STYLE_LIST_MAX; i++) {
      var name = names[i];
      if (seen[name] || !CSS_VAR.test(name)) continue;
      seen[name] = true;
      var value = String(cs.getPropertyValue(name)).replace(/^\s+|\s+$/g, ''), ok = HEX.test(value);
      if (!ok && value !== '' && value.length <= 64) {
        try { ok = !!(view.CSS && view.CSS.supports('color', value)); } catch (e) { ok = false; }
      }
      if (ok) out.push({ name: name, value: value });
    }
    return out;
  }
  /** `#rgb` ou `#rrggbb` -> `#rrggbb` em minusculas; outra coisa -> ''. */
  function hex6(value) {
    var v = String(value).toLowerCase();
    if (/^#[0-9a-f]{3}$/.test(v)) return '#' + v.charAt(1) + v.charAt(1) + v.charAt(2) + v.charAt(2) + v.charAt(3) + v.charAt(3);
    return HEX.test(v) ? v : '';
  }
  /** `rgb(r, g, b)` do estilo calculado -> `#rrggbb`; transparente ou outra coisa -> ''. */
  function rgbToHex(text) {
    var m = /^rgba?\(\s*(\d{1,3})[,\s]+(\d{1,3})[,\s]+(\d{1,3})(?:[,\s\/]+([\d.]+))?/.exec(String(text)), out = '#';
    if (!m || (m[4] !== undefined && Number(m[4]) === 0)) return '';
    for (var k = 1; k <= 3; k++) {
      var h = Math.min(255, Number(m[k])).toString(16);
      out += h.length < 2 ? '0' + h : h;
    }
    return out;
  }
  /** Cor que a pagina mostra hoje para `prop` neste no. Fundo transparente: o fundo do que esta atras dele. */
  function shownColor(node, prop) {
    for (var hops = 0; node && node.nodeType === 1 && hops < 40; hops++) {
      var cs = computedOf(node), hex = cs ? rgbToHex(cs.getPropertyValue(prop)) : '';
      if (hex !== '' || prop !== 'background-color') return hex;
      node = node.parentElement;
    }
    return '';
  }
  /** Previa imediata: a mesma declaracao, nas ocorrencias do elemento em cada quadro. */
  function previewStyle(key, n, prop, value) {
    if (STYLE_FIELDS.indexOf(prop) === -1) return;
    visibleFrames().forEach(function (f) {
      var list = elementNodes(frameDoc(f), key, n);
      for (var i = 0; i < list.length; i++) {
        if (!list[i].style) continue;
        if (value === '') list[i].style.removeProperty(prop);
        else list[i].style.setProperty(prop, value);
      }
    });
  }
  /** Um campo de cor ou de tamanho mudou: reescreve so aquela declaracao no `style` da tag. */
  function onStyleInput(t) {
    var prop = t.getAttribute('data-kaz-canvas-style'), at = attrTarget();
    if (!at || STYLE_FIELDS.indexOf(prop) === -1) return;
    if (!at.fresh) { attrWarn(prop, 'el_stale'); return; }
    var value = String(t.value), row = t.parentNode, isList = String(t.tagName).toLowerCase() === 'select';
    if (prop === 'font-size') {
      /* Numero pela metade ("1e") chega vazio: nao e um pedido para retirar o tamanho. */
      if (t.validity && t.validity.badInput) { attrWarn(prop, 'el_size_bad'); return; }
      if (value !== '') value += 'px';
    } else if (isList) {
      if (value === '=') return;
      if (value === '#') {
        var own = row ? row.querySelector('input[type="color"]') : null;
        if (!own) return;
        value = own.value;
      }
    }
    var res = ATTRS.setStyle(at.src, at.map, at.n, prop, value);
    if (res.error === 'value') { attrWarn(prop, prop === 'font-size' ? 'el_size_bad' : 'el_color_bad'); return; }
    if (res.error === 'long') { attrWarn(prop, 'el_full'); return; }
    if (res.error || typeof res.src !== 'string') { attrWarn(prop, 'el_stale'); return; }
    attrWarn(prop, '');
    /* O aviso "a pagina mostra outro valor" era do valor antigo: sai, e volta no proximo redesenho se ainda valer. */
    var forced = inspectorEl ? inspectorEl.querySelector('[data-kaz-canvas-style-forced="' + prop + '"]') : null;
    if (forced) forced.parentNode.removeChild(forced);
    /* O que ficou escrito no codigo, lido de volta: e isso que a previa mostra. */
    var now = ATTRS.styleOf(res.src, at.map, at.n).values[prop] || '';
    if (prop !== 'font-size' && row) {
      var list = row.querySelector('select'), written = list ? list.querySelector('option[value="="]') : null;
      if (written) written.parentNode.removeChild(written);
      if (list && !isList) list.value = '#';
    }
    if (prop === 'font-size' && inspectorEl) {
      var note = inspectorEl.querySelector('[data-kaz-canvas-style-written]');
      if (note) note.hidden = true;
    }
    previewStyle(at.key, at.n, prop, now);
    commitSource(at.key, at.map, res.src);
  }
  /** No da pagina (primeiro quadro com trechos lidos) que corresponde ao elemento escolhido. */
  function pickedNode() {
    var f = picked ? leadFrame() : null, nodes = f ? elementNodes(frameDoc(f), picked.key, picked.n) : [];
    return nodes.length ? nodes[Math.min(picked.inst || 0, nodes.length - 1)] : null;
  }
  /**
   * Propriedades em que a pagina mostra outro valor que o escrito na tag do
   * elemento escolhido: uma regra do tema (com !important, por exemplo) esta
   * vencendo. So compara o que da para comparar com certeza: cor em hex (ou
   * variavel que vale um hex) e tamanho em px.
   */
  function styleForced(key, it, node) {
    var out = [], cs = computedOf(node);
    if (!cs || !ATTRS || !mapFresh(key)) return out;
    var st = ATTRS.styleOf(elementSource(key), { items: [it] }, 0);
    if (st.state !== 'fixed') return out;
    ['color', 'background-color'].forEach(function (prop) {
      var cur = st.values[prop] || '', want = hex6(cur), got = rgbToHex(cs.getPropertyValue(prop));
      if (want === '' && cur.slice(0, 6) === 'var(--') want = hex6(String(cs.getPropertyValue(cur.slice(4, -1))).replace(/^\s+|\s+$/g, ''));
      if (want !== '' && got !== '' && got !== want) out.push(prop);
    });
    var px = /^(\d{1,3}(?:\.\d{1,2})?)px$/i.exec(st.values['font-size'] || ''), live = parseFloat(cs.getPropertyValue('font-size'));
    if (px && isFinite(live) && Math.abs(live - Number(px[1])) > 0.05) out.push('font-size');
    return out;
  }
  /** Poe ou tira, sob cada campo, o aviso "a pagina mostra outro valor". Nao reconstroi o painel. */
  function syncForced() {
    var box = inspectorEl && picked ? inspectorEl.querySelector('[data-kaz-canvas-elbox]') : null, it = picked ? elementItem(picked.key, picked.n) : null;
    if (!box || !it) return;
    var list = styleForced(picked.key, it, pickedNode());
    STYLE_FIELDS.forEach(function (prop) {
      var slot = box.querySelector('[data-kaz-canvas-attr-warn="' + prop + '"]'), note = box.querySelector('[data-kaz-canvas-style-forced="' + prop + '"]');
      var need = list.indexOf(prop) !== -1;
      if (note && !need) note.parentNode.removeChild(note);
      if (note || !need || !slot) return;
      note = make('p', 'kaz-canvas__sidehelp kaz-canvas__sidehelp--warn', label('el_style_forced', ''));
      note.setAttribute('data-kaz-canvas-style-forced', prop);
      slot.parentNode.insertBefore(note, slot.nextSibling);
    });
  }
  /** Campos de cor e tamanho do elemento escolhido. Devolve true quando mostrou os campos. */
  function paintStyle(key, host, it, own, fresh) {
    if (!ATTRS || typeof ATTRS.styleOf !== 'function') return false;
    /* A mesma pergunta com uma tag vazia: so diz se estes campos se aplicam a este tipo de tag. */
    if (ATTRS.styleOf('', { items: [[it[0], 'group', 0, 0, -1, -1, '', []]] }, 0).state === 'skip') return false;
    if (!Array.isArray(it[7])) {
      if (it[0] !== 'a') attrHelp(host, 'el_attr_locked', '');
      return false;
    }
    var src = elementSource(key), st = fresh ? ATTRS.styleOf(src, { items: [it] }, 0) : { state: 'none', values: {} };
    host.appendChild(make('h6', 'kaz-canvas__sidesub2', label('el_style_title', '')));
    if (st.state === 'variable') {
      var a = ATTRS.find(it, 'style');
      attrHelp(host, 'el_style_variable', a[6] !== '' ? '{{ ' + a[6] + ' }}' : src.slice(a[2], a[3]).slice(0, 120)).setAttribute('data-kaz-canvas-attr-from', 'style');
      attrHelp(host, 'el_attr_variable_help', '');
      return false;
    }
    if (st.state !== 'none' && st.state !== 'fixed') {
      attrHelp(host, 'el_style_locked', '').setAttribute('data-kaz-canvas-style-locked', '');
      return false;
    }
    var node = pickedNode(), cs = computedOf(node), colors = themeColors(node), off = !own || !fresh;

    [['color', 'el_color'], ['background-color', 'el_bg']].forEach(function (p) {
      var prop = p[0], cur = st.values[prop] || '', id = 'kaz-canvas-el-style-' + prop;
      var lab = make('label', 'kaz-canvas__fieldname', label(p[1], ''));
      lab.setAttribute('for', id);
      host.appendChild(lab);
      var row = make('span', 'kaz-canvas__control kaz-canvas__stylerow');
      var list = make('select', 'kaz-canvas__select');
      list.id = id;
      list.setAttribute('data-kaz-canvas-style', prop);
      function option(parent, value, text) {
        var o = make('option', '', text);
        o.value = value;
        parent.appendChild(o);
      }
      option(list, '', label('el_style_auto', ''));
      var known = cur === '' || hex6(cur) !== '', group = null;
      colors.forEach(function (c) {
        if (!group) {
          group = document.createElement('optgroup');
          group.label = label('el_style_theme', '');
          list.appendChild(group);
        }
        option(group, 'var(' + c.name + ')', c.name + ' · ' + c.value);
        if (cur === 'var(' + c.name + ')') known = true;
      });
      option(list, '#', label('el_style_custom', ''));
      /* Valor que o editor nao escreveria (rgba, nome de cor...): aparece como esta, e so sai se outro for escolhido. */
      if (!known) option(list, '=', label('el_style_written', '') + ' ' + cur.slice(0, 60));
      list.value = cur === '' ? '' : (!known ? '=' : (hex6(cur) !== '' ? '#' : cur));
      list.disabled = off;
      row.appendChild(list);
      var own2 = make('input', 'kaz-canvas__swatch');
      own2.type = 'color';
      own2.value = hex6(cur) || shownColor(node, prop) || (prop === 'color' ? '#000000' : '#ffffff');
      own2.setAttribute('data-kaz-canvas-style', prop);
      own2.setAttribute('aria-label', label(p[1], '') + ': ' + label('el_style_custom', ''));
      own2.disabled = off;
      row.appendChild(own2);
      host.appendChild(row);
      warnSlot(host, prop, fresh);
    });

    var size = st.values['font-size'] || '', px = /^(\d{1,3}(?:\.\d{1,2})?)px$/i.exec(size);
    var sizeLab = make('label', 'kaz-canvas__fieldname', label('el_size', ''));
    sizeLab.setAttribute('for', 'kaz-canvas-el-style-font-size');
    host.appendChild(sizeLab);
    var num = make('input', 'kaz-canvas__input kaz-canvas__input--short');
    num.type = 'number';
    num.id = 'kaz-canvas-el-style-font-size';
    num.min = String(ATTRS.SIZE_MIN);
    num.max = String(ATTRS.SIZE_MAX);
    num.step = 'any';
    num.setAttribute('inputmode', 'decimal');
    num.setAttribute('data-kaz-canvas-style', 'font-size');
    num.value = px ? String(Number(px[1])) : '';
    /* O tamanho que a pagina usa hoje, como referencia. */
    var live = cs ? parseFloat(cs.getPropertyValue('font-size')) : NaN;
    if (isFinite(live) && live > 0) num.placeholder = String(Math.round(live * 100) / 100);
    num.readOnly = off;
    host.appendChild(num);
    if (size !== '' && !px) {
      var note = attrHelp(host, 'el_style_written', size.slice(0, 60));
      note.setAttribute('data-kaz-canvas-style-written', 'font-size');
    }
    warnSlot(host, 'font-size', fresh);
    attrHelp(host, 'el_style_help', '');
    syncForced();
    return true;
  }
  /** Lista "Links deste trecho": caminho por teclado ate cada link de destino fixo. */
  function paintLinkList(key, host) {
    var map = elements[key];
    if (!ATTRS || !map || !mapFresh(key)) return;
    var src = elementSource(key), rows = [], total = 0;
    map.items.forEach(function (it, n) {
      var a = it[0] === 'a' ? ATTRS.find(it, 'href') : null;
      if (!a || a[1] !== 'fixed') return;
      total++;
      if (rows.length < TEXT_LIST_MAX) rows.push([n, decodeAttr(src.slice(a[2], a[3]))]);
    });
    if (!rows.length) return;
    host.appendChild(make('h5', 'kaz-canvas__sidesub', label('el_links', '')));
    host.appendChild(make('p', 'kaz-canvas__sidehelp', label('el_links_help', '')));
    var ul = make('ul', 'kaz-canvas__textlist');
    rows.forEach(function (r) {
      var li = make('li', ''), b = make('button', 'kaz-canvas__textrow', '');
      b.type = 'button';
      b.setAttribute('data-kaz-canvas-el', String(r[0]));
      b.setAttribute('data-kaz-canvas-focus', 'href');
      b.appendChild(make('code', 'kaz-canvas__texttag', 'a'));
      b.appendChild(make('span', 'kaz-canvas__textsnip', r[1].length > 90 ? r[1].slice(0, 90) + '…' : r[1]));
      li.appendChild(b);
      ul.appendChild(li);
    });
    host.appendChild(ul);
    if (total > rows.length) host.appendChild(make('p', 'kaz-canvas__sidehelp', label('el_links_more', '').replace('%s', String(total - rows.length))));
  }
  /** Mostra, no codigo do trecho, a tag de abertura do elemento escolhido. */
  function showInCode() {
    var code = inspectorEl ? inspectorEl.querySelector('[data-kaz-canvas-code]') : null;
    var it = picked ? elementItem(picked.key, picked.n) : null;
    if (!code || !it || !mapFresh(picked.key) || code.value !== elementSource(picked.key)) return;
    var before = code.value.slice(0, it[2]), line = before.split('\n').length - 1, col = before.length - before.lastIndexOf('\n') - 1;
    var cs = window.getComputedStyle(code), lh = parseFloat(cs.lineHeight) || 16, fs = parseFloat(cs.fontSize) || 12;
    code.focus();
    try { code.setSelectionRange(it[2], it[3]); } catch (e) { /* campo sem selecao: so o foco */ }
    code.scrollTop = Math.max(0, line * lh - code.clientHeight / 3);
    code.scrollLeft = Math.max(0, col * fs * 0.6 - code.clientWidth / 3);
    if (typeof code.scrollIntoView === 'function') code.scrollIntoView({ block: 'nearest' });
  }
  /** Bloco "Elemento" do painel do trecho. */
  function paintElement(key, host) {
    var it = elementItem(picked.key, picked.n);
    if (!it) return;
    var field = sourceField(key), own = !!(field && field.value !== ''), fresh = mapFresh(key);
    host.appendChild(make('h5', 'kaz-canvas__sidesub', label('el_title', '')));

    var crumbs = make('nav', 'kaz-canvas__crumbs');
    crumbs.setAttribute('aria-label', label('el_path', ''));
    var chain = elementChain(picked), from = Math.max(0, chain.length - 5);
    if (from > 0) crumbs.appendChild(make('span', 'kaz-canvas__crumbsep', '…'));
    for (var i = from; i < chain.length; i++) {
      var ci = elementItem(key, chain[i]);
      if (!ci) continue;
      if (i > from) crumbs.appendChild(make('span', 'kaz-canvas__crumbsep', '›'));
      var b = make('button', 'kaz-canvas__crumb', ci[0]);
      b.type = 'button';
      b.setAttribute('data-kaz-canvas-el', String(chain[i]));
      if (chain[i] === picked.n) b.setAttribute('aria-current', 'true');
      crumbs.appendChild(b);
    }
    host.appendChild(crumbs);

    var f = leadFrame(), count = f ? elementNodes(frameDoc(f), key, picked.n).length : 0;
    if (count > 1) host.appendChild(make('p', 'kaz-canvas__sidehelp', label('el_repeats', '').replace('%s', String(count))));

    var kind = it[1];
    if ((kind === 'fixed' || kind === 'empty') && it[4] >= 0) {
      var id = 'kaz-canvas-el-text';
      var lab = make('label', 'kaz-canvas__fieldname', label('el_text', ''));
      lab.setAttribute('for', id);
      host.appendChild(lab);
      var box = make('textarea', 'kaz-canvas__text');
      box.id = id;
      box.rows = 3;
      box.maxLength = TEXT_MAX;
      box.setAttribute('data-kaz-canvas-text', key + ':' + picked.n);
      box.setAttribute('placeholder', label('el_empty', ''));
      box.value = fresh ? decodeText(elementSource(key).slice(it[4], it[5])) : '';
      box.readOnly = !own || !fresh;
      host.appendChild(box);
      var warn = make('p', 'kaz-canvas__sidehelp kaz-canvas__sidehelp--warn', '');
      warn.setAttribute('data-kaz-canvas-el-warn', '');
      warn.setAttribute('role', 'status');
      warn.hidden = fresh;
      if (!fresh) warn.textContent = label('el_stale', '');
      host.appendChild(warn);
      host.appendChild(make('p', 'kaz-canvas__sidehelp', label(own ? 'el_text_help' : 'el_need_customize', '')));
    } else if (kind === 'variable') {
      var p = make('p', 'kaz-canvas__sidehelp', label('el_variable', '') + ' ');
      p.appendChild(make('code', 'kaz-canvas__expr', '{{ ' + String(it[6] || '') + ' }}'));
      host.appendChild(p);
      host.appendChild(make('p', 'kaz-canvas__sidehelp', label('el_variable_help', '')));
    } else {
      host.appendChild(make('p', 'kaz-canvas__sidehelp', label(kind === 'mixed' ? 'el_mixed' : (kind === 'void' ? 'el_void' : 'el_group'), '')));
    }

    var hasFields = paintAttrs(key, host, it, own, fresh);
    /* KZ-05: o clique costuma cair no texto ou no icone DENTRO do link; o link fica a um passo. */
    if (ATTRS && it[0] !== 'a') {
      for (var up = chain.length - 2; up >= 0; up--) {
        var upItem = elementItem(key, chain[up]);
        if (!upItem || upItem[0] !== 'a') continue;
        var inLink = make('p', 'kaz-canvas__sidehelp', label('el_in_link', '') + ' ');
        var go = make('button', 'kaz-canvas__crumb', label('el_go_link', ''));
        go.type = 'button';
        go.setAttribute('data-kaz-canvas-el', String(chain[up]));
        go.setAttribute('data-kaz-canvas-focus', 'href');
        inLink.appendChild(go);
        host.appendChild(inLink);
        break;
      }
    }

    /* KZ-06: cor e tamanho ficam depois dos atributos. */
    if (paintStyle(key, host, it, own, fresh)) hasFields = true;
    if (hasFields && !own) attrHelp(host, 'el_attr_need_customize', '');

    var bar = make('div', 'kaz-canvas__actions');
    var show = make('button', 'kaz-btn kaz-btn--ghost', label('el_show_code', ''));
    show.type = 'button';
    show.setAttribute('data-kaz-canvas-el-act', 'code');
    show.disabled = !fresh || !field;
    bar.appendChild(show);
    var whole = make('button', 'kaz-btn kaz-btn--ghost', label('el_whole_piece', ''));
    whole.type = 'button';
    whole.setAttribute('data-kaz-canvas-el-act', 'clear');
    bar.appendChild(whole);
    host.appendChild(bar);
  }
  /** Lista "Textos deste trecho": caminho por teclado ate cada texto fixo. */
  function paintTextList(key, host) {
    var map = elements[key];
    if (!map || !mapFresh(key)) return;
    var src = elementSource(key), rows = [], total = 0;
    map.items.forEach(function (it, n) {
      if (it[1] !== 'fixed' || it[4] < 0) return;
      total++;
      if (rows.length < TEXT_LIST_MAX) rows.push([n, it[0], decodeText(src.slice(it[4], it[5]))]);
    });
    if (!rows.length) return;
    host.appendChild(make('h5', 'kaz-canvas__sidesub', label('el_texts', '')));
    host.appendChild(make('p', 'kaz-canvas__sidehelp', label('el_texts_help', '')));
    var ul = make('ul', 'kaz-canvas__textlist');
    rows.forEach(function (r) {
      var li = make('li', ''), b = make('button', 'kaz-canvas__textrow', '');
      b.type = 'button';
      b.setAttribute('data-kaz-canvas-el', String(r[0]));
      b.appendChild(make('code', 'kaz-canvas__texttag', r[1]));
      b.appendChild(make('span', 'kaz-canvas__textsnip', r[2].length > 90 ? r[2].slice(0, 90) + '…' : r[2]));
      li.appendChild(b);
      ul.appendChild(li);
    });
    host.appendChild(ul);
    if (total > rows.length) host.appendChild(make('p', 'kaz-canvas__sidehelp', label('el_more', '').replace('%s', String(total - rows.length))));
  }

  /** Bloco do elemento (ou a lista de textos), em um espaco proprio do painel. */
  function paintElementBlock() {
    var host = inspectorEl ? inspectorEl.querySelector('[data-kaz-canvas-elbox]') : null, key = keyOf(selected);
    if (!host) return;
    clear(host);
    /* O valor diz de que elemento e o bloco (vazio = lista do trecho). */
    host.setAttribute('data-kaz-canvas-elbox', picked && key && picked.key === key ? picked.key + ':' + picked.n : '');
    if (!key) return;
    if (picked && picked.key === key) paintElement(key, host);
    else { paintTextList(key, host); paintLinkList(key, host); }
  }
  /** Roda fn sem deixar o controle em uso (anchor) sair do lugar na rolagem do painel. */
  function steady(anchor, fn) {
    var before = anchor ? anchor.getBoundingClientRect().top : 0;
    fn();
    var box = anchor && inspectorEl && typeof inspectorEl.closest === 'function' ? inspectorEl.closest('.kaz-canvas__side') : null;
    if (box) box.scrollTop += anchor.getBoundingClientRect().top - before;
  }
  /** O campo "Texto" esta em uso e o painel nao e reconstruido: so o aviso acompanha o mapa. */
  function syncElement() {
    if (!inspectorEl || !picked) return;
    var fresh = mapFresh(picked.key);
    var warn = inspectorEl.querySelector('[data-kaz-canvas-el-warn]');
    if (warn) {
      warn.hidden = fresh;
      if (!fresh) warn.textContent = label('el_stale', '');
    }
    /* KZ-05: com o mapa defasado, cada campo de atributo avisa; em dia, o aviso de destino recusado fica. */
    if (!fresh) WARN_NAMES.forEach(function (name) { attrWarn(name, 'el_stale'); });
    /* KZ-06: o quadro foi redesenhado; o aviso de valor forcado pelo tema acompanha. */
    syncForced();
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
      steady(keep, paintElementBlock);
      return;
    }
    /* Digitando no texto do mesmo elemento: idem. */
    var typing = inspectorEl.querySelector('[data-kaz-canvas-text]');
    if (typing && document.activeElement === typing && picked && typing.getAttribute('data-kaz-canvas-text') === picked.key + ':' + picked.n) {
      paintSizes();
      syncElement();
      return;
    }
    /* KZ-05: num campo de atributo ou na caixa "nova aba" do mesmo elemento: idem. */
    var using = document.activeElement, usingBox = using && using.closest ? using.closest('[data-kaz-canvas-elbox]') : null;
    if (usingBox && inspectorEl.contains(usingBox) && picked && usingBox.getAttribute('data-kaz-canvas-elbox') === picked.key + ':' + picked.n &&
        (using.getAttribute('data-kaz-canvas-attr') !== null || using.getAttribute('data-kaz-canvas-newtab') !== null ||
         using.getAttribute('data-kaz-canvas-style') !== null)) {
      paintSizes();
      syncElement();
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

    var elbox = make('div', 'kaz-canvas__elbox');
    elbox.setAttribute('data-kaz-canvas-elbox', '');
    inspectorEl.appendChild(elbox);
    paintElementBlock();

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
      if (rewritten === null || overLimit(rewritten)) {
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
      /* KZ-04: mapa de elementos. Se o usuario digitou depois do pedido, o mapa
         local (ja ajustado) continua valendo ate o servidor alcancar. */
      var maps = {};
      if (json.elements && typeof json.elements === 'object') Object.keys(json.elements).forEach(function (key) {
        var m = json.elements[key];
        if (!MARK.test('kz:b:' + key) || !m || typeof m.sig !== 'string' || !Array.isArray(m.items)) return;
        var items = m.items.filter(function (it) {
          return Array.isArray(it) && it.length >= 7 && typeof it[0] === 'string' && typeof it[1] === 'string' &&
            [2, 3, 4, 5].every(function (k) { return typeof it[k] === 'number' && isFinite(it[k]); });
        });
        if (items.length !== m.items.length) return;
        /* KZ-05: atributos conferidos; fora do formato, a tag fica sem campos de atributo. */
        items.forEach(function (it) { it[7] = ATTRS ? ATTRS.clean(it[7]) : null; });
        var now = sigOf(elementSource(key)), old = elements[key];
        maps[key] = (old && old.sig === now && m.sig !== now) ? old : { sig: m.sig, items: items };
      });
      elements = maps;
      if (liveText) {
        var served = json.elements && json.elements[liveText.key], local = elements[liveText.key];
        if (!local || (served && served.sig === local.sig)) liveText = null;
      }
      if (picked) {
        /* A fonte mudou por outro caminho (codigo, Templates, voltar ao padrao):
           os numeros dos elementos podem ter mudado, entao a escolha e solta. */
        var still = elementItem(picked.key, picked.n), pm = elements[picked.key];
        if (!still || still[0] !== picked.tag || !pm || pm.sig !== picked.sig) picked = null;
      }
      hoverEl = null;
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
      '[data-kaz-canvas-tab],[data-kaz-canvas-scheme],[data-kaz-canvas-el],[data-kaz-canvas-el-act],' +
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
    var elAct = t.getAttribute('data-kaz-canvas-el-act');
    if (elAct === 'code') { showInCode(); return; }
    if (elAct === 'clear') { picked = null; select(selected, false); return; }
    var elN = t.getAttribute('data-kaz-canvas-el');
    if (elN !== null && selected) {
      /* Caminho ou lista de textos: escolhe outro elemento da mesma fonte. */
      var elKey = keyOf(selected), want = Number(elN), inst = 0, lead = leadFrame();
      var cur = picked && lead ? elementNode(lead, picked) : null;
      while (cur) {
        var up = elementRef(cur);
        if (!up) break;
        if (up.key === elKey && up.n === want) { inst = instanceOf(frameDoc(lead), up); break; }
        cur = up.node.parentElement;
      }
      pick({ key: elKey, n: want, inst: inst }, selected, true);
      /* O foco segue: o campo pedido por quem chamou (lista de links), senao o primeiro campo
         editavel, senao o item atual do caminho. */
      var wantField = t.getAttribute('data-kaz-canvas-focus');
      var asked = inspectorEl && ATTR_FIELDS.indexOf(wantField) !== -1
        ? inspectorEl.querySelector('[data-kaz-canvas-attr="' + wantField + '"]:not([readonly])') : null;
      var again = asked ? asked : inspectorEl ? (inspectorEl.querySelector('[data-kaz-canvas-text]:not([readonly]),[data-kaz-canvas-attr]:not([readonly])') ||
        inspectorEl.querySelector('[data-kaz-canvas-el][aria-current="true"]')) : null;
      if (again) again.focus();
      return;
    }
    var uid = t.getAttribute('data-kaz-canvas-uid');
    if (uid) {
      /* Escolher na Estrutura e pedir para ver aquele trecho (inteiro). */
      if (tab !== 'piece') { tab = 'piece'; paintPanels(); savePrefs(); }
      picked = null;
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
    if (t.getAttribute('data-kaz-canvas-text') !== null) { if (!t.readOnly) onTextInput(t); return; }
    if (t.getAttribute('data-kaz-canvas-attr') !== null) { if (!t.readOnly) onAttrInput(t); return; }
    if (t.getAttribute('data-kaz-canvas-newtab') !== null) { if (!t.disabled) onNewTab(t); return; }
    if (t.getAttribute('data-kaz-canvas-style') !== null) { if (!t.disabled && !t.readOnly) onStyleInput(t); return; }
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
  if (routeSel) routeSel.addEventListener('change', function () { savePrefs(); picked = null; liveText = null; select('', false); refresh(); });
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
      if (ev.pointerType !== 'touch') { var w = worldPoint(ev); setHover(hitTest(w.x, w.y)); setHoverElement(elementAt(w.x, w.y)); }
      return;
    }
    if (press && press.id === ev.pointerId && Math.hypot(ev.clientX - press.x, ev.clientY - press.y) > CLICK_SLOP) {
      press = null;
      setHover('');
      setHoverElement(null);
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
    /* Soltou sem arrastar: e um clique. Seleciona o elemento apontado e o
       trecho que o contem (ou limpa, no vazio). */
    if (press && press.id === ev.pointerId && ev.type === 'pointerup') {
      var w = worldPoint(ev), hitEl = elementAt(w.x, w.y), uid = hitTest(w.x, w.y);
      if (hitEl && keyOf(uid) !== hitEl.key) uid = regionFor(hitEl.key, w.x, w.y) || uid;
      pick(hitEl, uid, false);
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
  viewport.addEventListener('pointerleave', function () { if (!pointerCount()) { setHover(''); setHoverElement(null); } });
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
    else if (ev.key === 'Escape' && picked) { picked = null; select(selected, false); }
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
