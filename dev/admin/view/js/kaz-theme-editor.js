/* KAZTheme-Editor — frentes U/V/W/X + Y + Z + AA + AB + AC + AD (boot apos DOM).
   Editor de temas estruturado: canvas SVG + árvore de camadas + inspector.
   Apenas DOM puro: sem rede, codigo executavel do usuario, WebGL, Three.js,
   upload ou escrita remota. A cena e PERSISTENTE (frente V): volta ao abrir
   o tema, viaja no pacote e e validada no servidor (kaztheme-scene/v1).
   A frente X adiciona 6 blocos allowlistados e o estado "nao salvo".
   A frente Y reformula APENAS a interface: navbar/ferramentas, 3 colunas,
   abas, viewport infinito com zoom/pan, réguas, paleta, tokens, histórico,
   inspector com abas e barra de status. Nenhum contrato de dados mudou.
   A conexão com o tema usa SOMENTE campos allowlistados do formulário.
   A frente AA torna TODOS os controles do workspace operacionais e adiciona
   tamanhos de tela, densidade de colunas e modo tela cheia (nesta aba e em
   outra pagina). Screen/densidade/fullscreen sao estado LOCAL descartavel:
   nao entram em kaz_scene_json, no baseline ou em armazenamento. */
(function (document, window) {
  'use strict';
  /* Frente AD — BOOT RESILIENTE.
     header.twig emite a tag script no <head> sem defer. A IIFE rodava antes
     do <body>, getElementById devolvia null e o 'if (!root) return;' saia
     SILENCIOSAMENTE: nenhum handler, nenhum erro no console, nenhuma
     ferramenta respondia. Todo o corpo agora vive em boot(), disparado
     quando o editor ja existe; se ainda nao existe, espera o DOM. */
  var bootDone = false;
  function boot() {
    if (bootDone) return;
    bootDone = true;
  var NS = 'http://www.w3.org/2000/svg';
  var VERSION = 1;
  var KINDS = ['rect', 'text', 'button', 'card', 'section'];
  var ROLES = ['none', 'primary', 'surface', 'section', 'component'];
  var GRID = 8;
  var W = 960, H = 540;
  var MAX_LAYERS = 48;

  /* Frente Y — estado LOCAL do workspace. Nada disso entra na cena JSON:
     zoom/pan, ferramenta ativa e abas sao apenas preferencias de interface. */
  var ZOOM_MIN = 0.25, ZOOM_MAX = 2, ZOOM_STEP = 0.25;
  var zoom = 1, panX = 0, panY = 0, tool = 'move';
  var tabLeft = 'layers', tabRight = 'design';
  /* ---- Frente AA: tamanhos de tela, densidade, colapso e fullscreen ----- */
  var SCREENS = { tema: null, desktop: [1440, 900], laptop: [1280, 800],
                  tablet: [768, 1024], mobile: [390, 844], wide: [1920, 1080] };
  var DENSITY = { compact: [200, 220], normal: [236, 264], wide: [300, 340] };
  var screenId = 'tema', screenSize = { w: W, h: H }, density = 'normal';
  var collapsed = { left: false, right: false }, fullscreenLocal = false;
  var LEFT_TABS = ['pages', 'layers', 'assets', 'tokens', 'history'];
  var RIGHT_TABS = ['design', 'prototype', 'inspect'];

  /* Frente X — biblioteca de blocos. Presets DECLARATIVOS: nenhum texto do
     usuario entra aqui. Cada template gera uma camada valida do contrato
     kaztheme-scene/v1; dx/dy sao relativos a uma origem calculada no canvas. */
  var BLOCKS = {
    hero: { label: 'Hero', layers: [
      { kind: 'rect', w: 560, h: 200, fill: '#e2e8f0', radius: 12, text: '', fontSize: 16, dx: 0, dy: 0 },
      { kind: 'text', w: 480, h: 80, fill: '#0f172a', radius: 0, text: 'Titulo do hero', fontSize: 32, dx: 24, dy: 32 },
      { kind: 'button', w: 160, h: 56, fill: '#2563eb', radius: 12, text: 'Saiba mais', fontSize: 16, dx: 24, dy: 120 }
    ] },
    card: { label: 'Cartao', layers: [
      { kind: 'card', w: 280, h: 176, fill: '#ffffff', radius: 12, text: '', fontSize: 16, dx: 0, dy: 0 },
      { kind: 'text', w: 240, h: 64, fill: '#0f172a', radius: 0, text: 'Cartao de conteudo', fontSize: 20, dx: 16, dy: 24 }
    ] },
    banner: { label: 'Faixa', layers: [
      { kind: 'rect', w: 720, h: 96, fill: '#1e3a8a', radius: 8, text: '', fontSize: 16, dx: 0, dy: 0 },
      { kind: 'text', w: 640, h: 48, fill: '#ffffff', radius: 0, text: 'Aviso ou chamada', fontSize: 24, dx: 24, dy: 24 }
    ] },
    titulo: { label: 'Titulo', layers: [
      { kind: 'text', w: 480, h: 64, fill: '#0f172a', radius: 0, text: 'Novo titulo', fontSize: 40, dx: 0, dy: 0 },
      { kind: 'rect', w: 480, h: 8, fill: '#38bdf8', radius: 0, text: '', fontSize: 16, dx: 0, dy: 72 }
    ] },
    galeria: { label: 'Galeria', layers: [
      { kind: 'rect', w: 160, h: 120, fill: '#cbd5e1', radius: 8, text: '', fontSize: 16, dx: 0, dy: 0 },
      { kind: 'rect', w: 160, h: 120, fill: '#cbd5e1', radius: 8, text: '', fontSize: 16, dx: 176, dy: 0 },
      { kind: 'rect', w: 160, h: 120, fill: '#cbd5e1', radius: 8, text: '', fontSize: 16, dx: 352, dy: 0 }
    ] },
    cta: { label: 'CTA', layers: [
      { kind: 'section', w: 480, h: 120, fill: '#f1f5f9', radius: 8, text: '', fontSize: 16, dx: 0, dy: 0 },
      { kind: 'button', w: 160, h: 56, fill: '#2563eb', radius: 12, text: 'Comecar', fontSize: 16, dx: 24, dy: 32 }
    ] }
  };

  var root = document.getElementById('kaz-theme-editor');
  if (!root) return;
  var canvas = root.querySelector('[data-kaz-canvas]');
  var layerList = root.querySelector('[data-kaz-layer-list]');
  var jsonBox = root.querySelector('[data-kaz-json]');
  var status = root.querySelector('[data-kaz-status]');
  var viewport = root.querySelector('[data-kaz-viewport]');
  var board = root.querySelector('[data-kaz-board]');
  var zoomValue = root.querySelector('[data-kaz-zoom-value]');
  var coords = root.querySelector('[data-kaz-coords]');
  var count = root.querySelector('[data-kaz-count]');
  var historyList = root.querySelector('[data-kaz-history-list]');
  var inspectBox = root.querySelector('[data-kaz-inspect]');
  var rulerH = root.querySelector('[data-kaz-ruler-h]');
  var rulerV = root.querySelector('[data-kaz-ruler-v]');
  var swatches = root.querySelector('[data-kaz-swatches]');
  /* Frente Z: o form principal agora e FILHO do workspace, nao ancestral. */
  var form = root.querySelector('#kaz-theme-form') || root.closest('form');
  var owner = root.getAttribute('data-kaz-owner') === '1';
  var modulesMap = {
    design: 'Design', geral: 'Geral', heranca: 'Heranca', tokens: 'Tokens',
    three: 'Three.js', cena3d: 'Cena 3D', secoes: 'Secoes', agenda: 'Agendamento',
    midia: 'Midia', modelos: 'Modelos', sites: 'Sites', componentes: 'Componentes',
    condicoes: 'Condicoes', comparar: 'Previa', templates: 'Templates'
  };
  var stateModule = 'design';
  var formTouched = false, formBaseline = '';
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
  /* Frente X — "alteracoes nao salvas". O baseline e a cena carregada do
     servidor; abrir a tela NAO suja o estado. Desfazer ate o baseline limpa. */
  var baseline = copy(scene);
  function dirty() { return JSON.stringify(scene) !== JSON.stringify(baseline); }
  /* Frente Z — selo de alteracoes do FORMULARIO. Nao substitui dirty(): o
     data-kaz-dirty e o file status continuam exclusivos de kaz_scene_json. */
  function formSnapshot() {
    if (!form) return '';
    var out = [], nodes = form.querySelectorAll('input,select,textarea'), i, n, v, k, o;
    for (i = 0; i < nodes.length; i++) {
      n = nodes[i];
      if (!n.name || n.name === 'kaz_scene_json' || n.type === 'file') continue;
      if (n.type === 'checkbox' || n.type === 'radio') v = n.checked ? '1' : '0';
      else if (n.multiple) { o = []; for (k = 0; k < n.options.length; k++) if (n.options[k].selected) o.push(n.options[k].value); v = o.join(','); }
      else v = n.value;
      out.push(n.name + '=' + v);
    }
    return out.join('\n');
  }
  function formDirty() { return owner && formTouched && formSnapshot() !== formBaseline; }
  function setModule(n) {
    if (!Object.prototype.hasOwnProperty.call(modulesMap, n)) return false;
    stateModule = n; paintModules(); return true;
  }
  function paintModules() {
    var panels = root.querySelectorAll('[data-kaz-module-panel]'), i, n;
    for (i = 0; i < panels.length; i++)
      panels[i].hidden = panels[i].getAttribute('data-kaz-module-panel') !== stateModule;
    var box = root.querySelector('[data-kaz-modules]');
    if (box) box.hidden = stateModule === 'design';
    if (viewport) viewport.hidden = stateModule !== 'design';
    var sb = root.querySelector('[data-kaz-screenbar]');
    if (sb) sb.hidden = stateModule !== 'design';
    var stg = root.querySelector('[data-kaz-stage]');
    if (stg) stg.setAttribute('data-kaz-active-module', stateModule);
    var links = root.querySelectorAll('button[data-kaz-module]');
    for (i = 0; i < links.length; i++) {
      n = links[i];
      if (!n.classList.contains('kaz-module-link')) continue;
      var on = n.getAttribute('data-kaz-module') === stateModule;
      n.classList.toggle('kaz-module-link--on', on);
      n.setAttribute('aria-pressed', on ? 'true' : 'false');
    }
    var lab = root.querySelector('.kaz-statusbar__module');
    if (lab) lab.textContent = modulesMap[stateModule] || 'Design';
  }
  function paintDirty() {
    var on = dirty();
    root.setAttribute('data-kaz-dirty', on ? '1' : '0');
    var badge = root.querySelector('[data-kaz-dirty-badge]');
    if (badge) badge.hidden = !on;
    var fbadge = root.querySelector('[data-kaz-form-dirty]');
    if (fbadge) fbadge.hidden = !formDirty();
    /* Penpot #20: file status acompanha o estado de gravacao. */
    var ok = root.querySelector('[data-kaz-file-status]');
    if (ok) { ok.hidden = on; ok.textContent = on ? 'Não salvo' : 'Salvo'; }
  }

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

  /* Frente V: o textarea e um campo REAL do formulario. Mantem o JSON da
     cena sempre sincronizado; o listener de submit garante o ultimo estado. */
  function syncField() { jsonBox.value = JSON.stringify(scene, null, 2); }
  /* ---- Frente Y: chrome do workspace ---------------------------------- */
  /* Tudo com createElement/setAttribute/textContent/style; nenhum markup do
     usuario e injetado, nenhum codigo dinamico, nenhuma rede. */
  /* ---- Frente AA: tela, densidade, fullscreen e acoes de conveniencia ---- */
  function screenBox() { return root.querySelector('[data-kaz-screen-frame]'); }
  function setScreen(id) {
    if (id !== 'custom' && !Object.prototype.hasOwnProperty.call(SCREENS, id)) return false;
    screenId = id;
    if (id === 'tema') screenSize = { w: scene.canvas.w, h: scene.canvas.h };
    else if (id === 'custom') {
      var wi = root.querySelector('[data-kaz-screen-w]'), hi = root.querySelector('[data-kaz-screen-h]');
      screenSize = { w: clamp(Number(wi && wi.value) || 960, 320, 3840),
                     h: clamp(Number(hi && hi.value) || 540, 240, 2160) };
    } else screenSize = { w: SCREENS[id][0], h: SCREENS[id][1] };
    paintScreen(); return true;
  }
  function setScreenCustom(w, h) {
    var wi = root.querySelector('[data-kaz-screen-w]'), hi = root.querySelector('[data-kaz-screen-h]');
    if (wi) wi.value = String(clamp(Number(w) || 960, 320, 3840));
    if (hi) hi.value = String(clamp(Number(h) || 540, 240, 2160));
    return setScreen('custom');
  }
  function paintScreen() {
    var box = screenBox();
    paintFrame();
    var sel = root.querySelector('[data-kaz-screen]');
    if (sel) sel.value = screenId;
    var wi = root.querySelector('[data-kaz-screen-w]'), hi = root.querySelector('[data-kaz-screen-h]');
    if (wi && screenId !== 'custom') wi.value = String(screenSize.w);
    if (hi && screenId !== 'custom') hi.value = String(screenSize.h);
    var label = screenId === 'tema' ? 'Tema' : (screenId === 'custom' ? 'Personalizado' : screenId);
    var txt = label + ' · ' + screenSize.w + ' × ' + screenSize.h;
    var size = root.querySelector('[data-kaz-screen-size]');
    if (size) size.textContent = screenSize.w + ' × ' + screenSize.h;
    var fl = root.querySelector('[data-kaz-screen-frame-label]');
    if (fl) fl.textContent = txt;
    var st = root.querySelector('[data-kaz-status-screen]');
    if (st) st.textContent = txt;
    root.setAttribute('data-kaz-screen-id', screenId);
    root.setAttribute('data-kaz-screen-w', String(screenSize.w));
    root.setAttribute('data-kaz-screen-h', String(screenSize.h));
  }
  function setDensity(d) {
    if (!Object.prototype.hasOwnProperty.call(DENSITY, d)) return false;
    density = d; root.setAttribute('data-kaz-density', d);
    say('layout ' + d + ' · ' + DENSITY[d][0] + 'px / ' + DENSITY[d][1] + 'px');
    return true;
  }
  function togglePanel(side) {
    if (side !== 'left' && side !== 'right') return false;
    collapsed[side] = !collapsed[side];
    var v = [];
    if (collapsed.left) v.push('left');
    if (collapsed.right) v.push('right');
    if (v.length) root.setAttribute('data-kaz-collapsed', v.join(' '));
    else root.removeAttribute('data-kaz-collapsed');
    /* AC: em telas pequenas as sidebars sao overlay controladas por este
       mesmo atributo; sem isto o toggle nao tinha efeito visual no mobile. */
    root.setAttribute('data-kaz-mobile-open', collapsed[side] ? '' : side);
    say('painel ' + side + (collapsed[side] ? ' oculto' : ' visível'));
    return true;
  }
  function setFullscreenLocal(on) {
    fullscreenLocal = !!on;
    root.setAttribute('data-kaz-fullscreen', fullscreenLocal ? '1' : '0');
    var d = document.documentElement || document.body;
    try {
      if (fullscreenLocal && d && d.requestFullscreen) d.requestFullscreen();
      else if (!fullscreenLocal && document.exitFullscreen && document.fullscreenElement) document.exitFullscreen();
    } catch (e) { /* sem Fullscreen API: permanece apenas o modo CSS */ }
    say(fullscreenLocal ? 'tela cheia nesta aba' : 'tela cheia encerrada');
    return fullscreenLocal;
  }
  function revertScene() {
    scene = copy(baseline);
    selected = scene.layers.length ? scene.layers[0].id : '';
    history = [copy(scene)]; cursor = 0;
    render(); say('alteracoes da cena descartadas'); return true;
  }
  function applyColor(c) {
    if (!hex(c) || !selected) return false;
    return set(selected, 'fill', c);
  }
  /* AC: o CONTÊINER da tela acompanha o zoom. Antes so o board era
     escalado e o .kaz-screen continuava com 960px, gerando scroll horizontal
     e canvas cortado mesmo com o zoom em 25%. */
  function paintFrame() {
    var box = screenBox();
    if (box) {
      box.style.width = Math.max(1, Math.round(screenSize.w * zoom)) + 'px';
      box.style.height = Math.max(1, Math.round(screenSize.h * zoom)) + 'px';
    }
  }
  function paintZoom() {
    /* AC: sem scale() no board. paintFrame() ja dimensiona o .kaz-screen
       (w*zoom, h*zoom) e o SVG com viewBox escala a cena. Manter scale aqui
       escalava DUAS vezes: em 390px o frame de 135px virava 33px de SVG. */
    if (board) board.setAttribute('style',
      'transform: translate(' + panX + 'px,' + panY + 'px)');
    if (zoomValue) zoomValue.textContent = Math.round(zoom * 100) + '%';
    if (viewport) viewport.setAttribute('data-kaz-tool-state', tool);
    canvas.setAttribute('data-kaz-zoom', String(zoom));
    paintFrame();
  }
  function setZoom(z) {
    zoom = clamp(Math.round(z / ZOOM_STEP) * ZOOM_STEP, ZOOM_MIN, ZOOM_MAX);
    paintZoom(); say('zoom ' + Math.round(zoom * 100) + '%'); return zoom;
  }
  function fitZoom() {
    var vw = viewport && viewport.clientWidth ? viewport.clientWidth - 72 : 720;
    var vh = viewport && viewport.clientHeight ? viewport.clientHeight - 72 : 420;
    var z = Math.min(vw / screenSize.w, vh / screenSize.h, 1);
    /* AC: passo de 0.05 e arredondamento para BAIXO garante que o frame
       escalado caiba no viewport (0.25 deixava 720px em 710px). */
    zoom = clamp(Math.floor(z * 20) / 20, ZOOM_MIN, ZOOM_MAX);
    panX = 0; panY = 0; paintZoom();
    say('zoom ajustado ' + Math.round(zoom * 100) + '%'); return zoom;
  }
  function setPan(x, y) {
    panX = clamp(Number(x) || 0, -4000, 4000);
    panY = clamp(Number(y) || 0, -4000, 4000);
    paintZoom(); return { x: panX, y: panY };
  }
  function setTool(t) {
    if (t !== 'move' && t !== 'hand') return false;
    tool = t; paintZoom();
    root.querySelectorAll('[data-kaz-tool]').forEach(function (b) {
      b.setAttribute('class', 'kaz-tool' + (b.getAttribute('data-kaz-tool') === t ? ' kaz-tool--on' : ''));
    });
    say(t === 'hand' ? 'ferramenta mão: arraste o canvas' : 'ferramenta mover');
    return true;
  }
  function setTab(name) {
    if (LEFT_TABS.indexOf(name) >= 0) tabLeft = name;
    else if (RIGHT_TABS.indexOf(name) >= 0) tabRight = name;
    else return false;
    paintTabs(); return true;
  }
  function paintTabs() {
    root.querySelectorAll('[data-kaz-tab]').forEach(function (t) {
      var name = t.getAttribute('data-kaz-tab');
      var on = (name === tabLeft || name === tabRight);
      t.setAttribute('class', 'kaz-tab' + (on ? ' kaz-tab--on' : ''));
      t.setAttribute('aria-selected', on ? 'true' : 'false');
    });
    root.querySelectorAll('[data-kaz-panel]').forEach(function (p) {
      var name = p.getAttribute('data-kaz-panel');
      p.hidden = !(name === tabLeft || name === tabRight);
    });
  }
  function paintRulers() {
    if (!rulerH || !rulerV) return;
    if (!rulerH.childNodes.length) {
      for (var x = 0; x <= scene.canvas.w; x += 80) rulerH.appendChild(el('span', 'kaz-ruler__tick', String(x)));
    }
    if (!rulerV.childNodes.length) {
      for (var y = 0; y <= scene.canvas.h; y += 80) rulerV.appendChild(el('span', 'kaz-ruler__tick', String(y)));
    }
  }
  function paintHistory() {
    if (!historyList) return;
    clear(historyList);
    for (var i = 0; i < history.length; i++) {
      var li = el('li', '', (i + 1) + '. ' + history[i].layers.length + ' camadas' + (i === cursor ? ' · atual' : ''));
      if (i === cursor) li.setAttribute('data-kaz-current', '1');
      historyList.appendChild(li);
    }
  }
  function paintInspect() {
    if (!inspectBox) return;
    var l = layer(selected);
    if (!l) { inspectBox.textContent = '// nenhuma camada selecionada'; return; }
    var css = '.' + l.id + ' {\n'
      + '  position: absolute;\n  left: ' + l.x + 'px;\n  top: ' + l.y + 'px;\n'
      + '  width: ' + l.w + 'px;\n  height: ' + l.h + 'px;\n'
      + '  background: ' + l.fill + ';\n  border-radius: ' + l.radius + 'px;\n}';
    inspectBox.textContent = css + '\n\n' + JSON.stringify(l, null, 2);
  }
  function paintSwatches() {
    if (!swatches || swatches.childNodes.length) return;
    ['#31efb8', '#7b61ff', '#0f172a', '#e2e8f0', '#ffffff', '#2563eb'].forEach(function (c) {
      var s = el('button', 'kaz-swatch');
      s.setAttribute('type', 'button');
      s.setAttribute('data-kaz-swatch', c);
      s.setAttribute('title', 'Aplicar ' + c + ' na camada selecionada');
      s.style.background = c; swatches.appendChild(s);
    });
  }
  function paintTokens() {
    ['primary', 'surface'].forEach(function (k) {
      var v = root.getAttribute('data-kaz-' + k) || '#ffffff';
      var dot = root.querySelector('[data-kaz-token="' + k + '"]');
      var val = root.querySelector('[data-kaz-token-value="' + k + '"]');
      if (dot) dot.style.background = v;
      if (val) val.textContent = v;
    });
  }
  function paintChrome() {
    paintZoom(); paintTabs(); paintRulers(); paintHistory(); paintInspect();
    paintSwatches(); paintTokens(); paintModules();
    if (count) count.textContent = scene.layers.length + ' camadas';
    var l = layer(selected);
    if (coords) coords.textContent = l ? 'X ' + l.x + ' · Y ' + l.y : 'X 0 · Y 0';
    var bs = root.querySelector('[data-kaz-board-size]');
    if (bs) bs.textContent = scene.canvas.w + ' × ' + scene.canvas.h;
  }

  /* Frente AB: defesa em profundidade. paintChrome() ja chamava
     paintModules(); a mudanca real e que o HTML agora vem com `hidden`
     no SERVIDOR, entao uma falha no JS nao empilha os 14 formularios. */
  function render() { drawCanvas(); drawLayers(); drawInspector(); syncField();
                      paintDirty(); paintChrome(); paintModules(); }

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
  /* Frente X — insere um bloco allowlistado como camadas REAIS da cena.
     Respeita MAX_LAYERS, gera IDs unicos e nao aplica bindings no boot. */
  function nextOrigin() {
    var maxY = 0;
    scene.layers.forEach(function (l) { maxY = Math.max(maxY, l.y + l.h); });
    var span = Math.max(GRID * 2, scene.canvas.h - 160);
    return { x: 40, y: snap((maxY + GRID * 2) % span) };
  }
  function insertBlock(blockId) {
    var b = Object.prototype.hasOwnProperty.call(BLOCKS, blockId) ? BLOCKS[blockId] : null;
    if (!b) { say('bloco desconhecido'); return false; }
    if (scene.layers.length + b.layers.length > MAX_LAYERS) {
      say('limite de ' + MAX_LAYERS + ' camadas atingido'); return false;
    }
    var o = nextOrigin(), added = [];
    b.layers.forEach(function (tpl) {
      var l = {
        id: newId(), kind: tpl.kind, role: 'none',
        x: clamp(snap(o.x + tpl.dx), -2000, 2000),
        y: clamp(snap(o.y + tpl.dy), -2000, 2000),
        w: tpl.w, h: tpl.h, fill: tpl.fill, radius: tpl.radius,
        text: tpl.text, fontSize: tpl.fontSize,
        visible: true, locked: false, bindId: ''
      };
      scene.layers.push(l); added.push(l.id);
    });
    selected = added[0]; commit(); render();
    say('bloco ' + blockId + ' inserido · ' + added.length + ' camadas');
    return true;
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
    syncField();
    try {
      if (window.Blob && window.URL && window.URL.createObjectURL && document.body) {
        var url = window.URL.createObjectURL(new window.Blob([s], { type: 'application/json' }));
        var a = document.createElement('a');
        a.setAttribute('href', url); a.setAttribute('download', 'kaztheme-scene.json');
        a.setAttribute('data-kaz-export-link', '1');
        document.body.appendChild(a); a.click(); document.body.removeChild(a);
        if (window.URL.revokeObjectURL) window.URL.revokeObjectURL(url);
      }
    } catch (e) { /* navegador sem download: o textarea continua sendo a fonte */ }
    say('cena exportada (' + scene.layers.length + ' camadas)');
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
    if (tool === 'hand') return;
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
  var panDrag = null;
  if (viewport) viewport.addEventListener('mousedown', function (ev) {
    if (tool !== 'hand') return;
    if (ev.target && ev.target.closest && ev.target.closest('[data-kaz-layer]')) return;
    panDrag = { x: ev.clientX, y: ev.clientY, px: panX, py: panY };
    ev.preventDefault();
  });
  if (viewport) viewport.addEventListener('wheel', function (ev) {
    if (!ev.ctrlKey && !ev.metaKey) return;
    ev.preventDefault();
    setZoom(zoom + (ev.deltaY < 0 ? ZOOM_STEP : -ZOOM_STEP));
  }, { passive: false });
  document.addEventListener('mousemove', function (ev) {
    if (panDrag) {
      panX = panDrag.px + (ev.clientX - panDrag.x);
      panY = panDrag.py + (ev.clientY - panDrag.y);
      paintZoom(); return;
    }
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
    if (panDrag) { panDrag = null; return; }
    if (!drag) return;
    drag = null; commit(); render();
  });

  /* ---- Toolbar / inspector / IO ----------------------------------------- */
  root.addEventListener('click', function (ev) {
    var b = ev.target;
    while (b && b !== root && !b.getAttribute('data-kaz-action') && !b.getAttribute('data-kaz-select')
           && !b.getAttribute('data-kaz-kind') && !b.getAttribute('data-kaz-block')
           && !b.getAttribute('data-kaz-tab') && !b.getAttribute('data-kaz-zoom')
           && !b.getAttribute('data-kaz-tool') && !b.getAttribute('data-kaz-module')
           && !b.getAttribute('data-kaz-swatch') && !b.getAttribute('data-kaz-token-apply')) b = b.parentNode;
    if (!b || b === root) return;
    var sw = b.getAttribute('data-kaz-swatch');
    if (sw) { applyColor(sw); return; }
    var tk = b.getAttribute('data-kaz-token-apply');
    if (tk) { applyColor(root.getAttribute('data-kaz-' + tk) || '#ffffff'); return; }
    var mod = b.getAttribute('data-kaz-module');
    if (mod) { setModule(mod); return; }
    var tab = b.getAttribute('data-kaz-tab');
    if (tab) { setTab(tab); return; }
    var zm = b.getAttribute('data-kaz-zoom');
    if (zm) {
      if (zm === 'in') setZoom(zoom + ZOOM_STEP);
      else if (zm === 'out') setZoom(zoom - ZOOM_STEP);
      else fitZoom();
      return;
    }
    var tl = b.getAttribute('data-kaz-tool');
    if (tl) { setTool(tl); return; }
    var sel = b.getAttribute('data-kaz-select');
    if (sel) { selected = sel; render(); return; }
    var kind = b.getAttribute('data-kaz-kind');
    if (kind) { add(kind); return; }
    var block = b.getAttribute('data-kaz-block');
    if (block) { insertBlock(block); return; }
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
    else if (act === 'pages') { setTab('pages'); setModule('design'); say('painel de paginas aberto'); }
    else if (act === 'users') say('1 editor local · sessão Admin · sem colaboração em tempo real');
    else if (act === 'revert') revertScene();
    else if (act === 'goto-board') { setModule('design'); setTab('layers'); fitZoom(); say('board em foco'); }
    else if (act === 'add-text') add('text');
    else if (act === 'screen-apply') {
      var sel = root.querySelector('[data-kaz-screen]');
      setScreen(sel ? sel.value : 'tema'); fitZoom();
    }
    else if (act === 'screen-fit') fitZoom();
    else if (act === 'toggle-left') togglePanel('left');
    else if (act === 'toggle-right') togglePanel('right');
    else if (act === 'fullscreen-local') setFullscreenLocal(!fullscreenLocal);
  });
  root.addEventListener('change', function (ev) {
    var f = ev.target;
    var sc = f && f.hasAttribute && f.hasAttribute('data-kaz-screen') ? f.value : null;
    if (sc) { setScreen(sc); fitZoom(); return; }
    var dn = f && f.hasAttribute && f.hasAttribute('data-kaz-density') ? f.value : null;
    if (dn) { setDensity(dn); return; }
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
    if (ev.shiftKey && /^[1-6]$/.test(ev.key)) {
      var ids = ['desktop', 'laptop', 'tablet', 'mobile', 'wide', 'tema'];
      setScreen(ids[Number(ev.key) - 1]); fitZoom(); return;
    }
    if (!ctrl) {
      var k = ev.key.toLowerCase();
      if (k === 'f') { setFullscreenLocal(!fullscreenLocal); return; }
      if (k === '[') { setDensity('compact'); return; }
      if (k === ']') { setDensity('wide'); return; }
      if (k === 'v' || k === 'h') { setTool(k === 'v' ? 'move' : 'hand'); return; }
      var kinds = { r: 'rect', t: 'text', b: 'button', c: 'card', s: 'section' };
      if (kinds[k]) { add(kinds[k]); return; }
      if (k === '+' || k === '=') { setZoom(zoom + ZOOM_STEP); return; }
      if (k === '-') { setZoom(zoom - ZOOM_STEP); return; }
      if (k === '0') { fitZoom(); return; }
    }
    var step = ev.shiftKey ? GRID * 4 : GRID;
    if (ev.key === 'ArrowLeft') { ev.preventDefault(); move(selected, -step, 0); }
    else if (ev.key === 'ArrowRight') { ev.preventDefault(); move(selected, step, 0); }
    else if (ev.key === 'ArrowUp') { ev.preventDefault(); move(selected, 0, -step); }
    else if (ev.key === 'ArrowDown') { ev.preventDefault(); move(selected, 0, step); }
  });

  if (form) form.addEventListener('input', function (ev) {
    if (ev.target && ev.target.name && ev.target.name !== 'kaz_scene_json') formTouched = true;
    paintDirty();
  });
  if (form) form.addEventListener('change', function (ev) {
    if (ev.target && ev.target.name && ev.target.name !== 'kaz_scene_json') formTouched = true;
    paintDirty();
  });
  if (form) form.addEventListener('submit', function () {
    syncField(); baseline = copy(scene);
    formBaseline = formSnapshot(); formTouched = false;
    paintDirty();
  });
  root.setAttribute('data-kaz-ready','1');
  /* AC: no mobile as duas sidebars comecam recolhidas (overlay), senao o
     grid de 3 colunas espremia o centro a 0px. matchMedia e opcional para
     nao quebrar ambientes sem MediaQueryList (jsdom/PHP WASM). */
  if (typeof window.matchMedia === 'function'
      && window.matchMedia('(max-width: 980px)').matches) {
    collapsed.left = true; collapsed.right = true;
    root.setAttribute('data-kaz-collapsed', 'left right');
    root.setAttribute('data-kaz-mobile-open', '');
  }
  setScreen('tema');
  /* AC: auto-ajuste REAL. Se o canvas nao cabe no viewport, entra em 'Ajustar'
     no boot. Em jsdom/clientWidth=0 nao dispara (zoom permanece 1). */
  if (typeof window.matchMedia === 'function'
      && window.matchMedia('(max-width: 980px)').matches
      && viewport && viewport.clientWidth > 0) fitZoom();
  render();
  formBaseline = formSnapshot();
  paintDirty();
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
    blocks: function () { return Object.keys(BLOCKS); },
    insertBlock: insertBlock, dirty: dirty,
    owner: function () { return owner; },
    modules: function () { return Object.keys(modulesMap); },
    module: function () { return stateModule; }, setModule: setModule,
    formDirty: formDirty, formId: function () { return form ? form.id : ''; },
    screen: function () { return { id: screenId, w: screenSize.w, h: screenSize.h }; },
    setScreen: setScreen, setScreenCustom: setScreenCustom,
    screens: function () { return Object.keys(SCREENS).concat(['custom']); },
    density: function () { return density; }, setDensity: setDensity,
    collapsed: function () { return { left: collapsed.left, right: collapsed.right }; },
    togglePanel: togglePanel,
    fullscreen: function () { return fullscreenLocal; }, setFullscreen: setFullscreenLocal,
    pageMode: function () { return root.getAttribute('data-kaz-page-mode'); },
    revert: revertScene, applyColor: applyColor,
    panelVisible: function (n) {
      var e = root.querySelector('[data-kaz-module-panel="' + n + '"]');
      return !!e && !e.hidden;
    },
    baseline: function () { return copy(baseline); },
    render: render, validScene: validScene, fallback: fallback,
    zoom: function () { return zoom; }, setZoom: setZoom, fitZoom: fitZoom,
    pan: function () { return { x: panX, y: panY }; }, setPan: setPan,
    tool: function () { return tool; }, setTool: setTool,
    tabs: function () { return { left: tabLeft, right: tabRight }; }, setTab: setTab,
    ui: function () { return { zoom: zoom, panX: panX, panY: panY, tool: tool,
                              tabLeft: tabLeft, tabRight: tabRight,
                              screenId: screenId, screenW: screenSize.w, screenH: screenSize.h,
                              density: density, collapsedLeft: collapsed.left,
                              collapsedRight: collapsed.right, fullscreen: fullscreenLocal }; }
  };
  say('KAZTheme-Editor pronto · ' + scene.layers.length + ' camadas · '
      + (root.getAttribute('data-kaz-saved') === '1' ? 'cena salva no tema' : 'cena derivada, salve para persistir'));
  }
  /* Decide pela PRESENCA DO ELEMENTO, nao por readyState: jsdom (eval
     sincrono) reporta 'loading' mesmo com o DOM ja montado. */
  if (document.getElementById('kaz-theme-editor')) {
    boot();
  } else {
    document.addEventListener('DOMContentLoaded', boot, { once: true });
    window.addEventListener('load', boot, { once: true });
  }
})(document, window);
