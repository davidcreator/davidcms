/* Frente AM — Ajuste direto na tela (companion ADITIVO, depois de AJ + AL).
   Causa medida: o fitZoom() da AC so e chamado no boot em max-width:980px.
   Em desktop o editor abria em 100%, o board 960x540 ficava cortado e as
   camadas caiam FORA da area clicavel - "nao consigo mexer para ajustar".
   Aqui o ajuste e universal, a screenbar volta a uma linha, as colunas
   ganham divisorias arrastaveis e X/Y/W/H acompanham o arraste ao vivo.
   DOM puro, sem armazenamento, rede, execucao dinamica ou gravacao de tema. */
(function () {
  'use strict';
  var root = null, vp = null, ws = null, canvas = null;
  var grips = {}, resizeTimer = null, rafPending = false, observer = null;
  var MIN = 160, MAX = 420;

  function click(sel) {
    var e = root && root.querySelector(sel);
    if (e && typeof e.click === 'function') { e.click(); return true; }
    return false;
  }
  function over() {
    if (!vp) return 0;
    return Math.max(0, vp.scrollHeight - vp.clientHeight,
                       vp.scrollWidth - vp.clientWidth);
  }
  function positionGrips() {
    if (!ws || !grips.left || !grips.right) return;
    var l = ws.querySelector('.kaz-sidebar--left'), r = ws.querySelector('.kaz-sidebar--right');
    if (l) grips.left.style.left = Math.max(0, l.getBoundingClientRect().width - 5) + 'px';
    if (r) grips.right.style.right = Math.max(0, r.getBoundingClientRect().width - 5) + 'px';
  }
  /* fitZoom() da AC nao contabiliza margin-top do frame nem o padding do
     viewport. Reduzimos um passo ate o board caber de verdade (max 3). */
  function fitAll() {
    if (!root || !vp) return;
    click('[data-kaz-action="screen-fit"]');
    for (var i = 0; i < 3 && over() > 4; i++) click('[data-kaz-zoom="out"]');
    root.setAttribute('data-kaz-fit', '1');
    root.setAttribute('data-kaz-fit-overflow', String(Math.round(over())));
    positionGrips();
  }
  function scheduleFit() {
    if (rafPending || typeof window.requestAnimationFrame !== 'function') return;
    rafPending = true;
    window.requestAnimationFrame(function () { rafPending = false; fitAll(); });
  }
  function buildGrips() {
    if (!ws) return;
    ['left', 'right'].forEach(function (side) {
      var g = document.createElement('div');
      g.setAttribute('class', 'kaz-grip');
      g.setAttribute('data-kaz-grip', side);
      g.setAttribute('role', 'separator');
      g.setAttribute('tabindex', '0');
      g.setAttribute('aria-orientation', 'vertical');
      g.setAttribute('aria-label', 'Redimensionar painel ' +
        (side === 'left' ? 'esquerdo' : 'direito'));
      g.title = 'Arraste para redimensionar · duplo clique restaura';
      bindGrip(g, side);
      ws.appendChild(g);
      grips[side] = g;
    });
  }
  function bindGrip(g, side) {
    g.addEventListener('pointerdown', function (ev) {
      if (ev.button !== undefined && ev.button !== 0) return;
      var col = ws.querySelector('.kaz-sidebar--' + side);
      if (!col) return;
      var startW = col.getBoundingClientRect().width, sx = ev.clientX, current = startW;
      g.setAttribute('data-kaz-dragging', '1');
      if (g.setPointerCapture && ev.pointerId !== undefined) {
        try { g.setPointerCapture(ev.pointerId); } catch (e) { /* sem capture */ }
      }
      ev.preventDefault();
      function move(e2) {
        var d = e2.clientX - sx;
        current = Math.max(MIN, Math.min(MAX, startW + (side === 'left' ? d : -d)));
        var w = Math.round(current);
        root.style.setProperty('--kaz-col-' + side, w + 'px');
        root.setAttribute('data-kaz-cols', '1');
        root.setAttribute('data-kaz-col-' + side, String(w));
        g.setAttribute('aria-valuenow', String(w));
        positionGrips();
      }
      function up() {
        g.removeAttribute('data-kaz-dragging');
        g.removeEventListener('pointermove', move);
        g.removeEventListener('pointerup', up);
        g.removeEventListener('pointercancel', up);
        scheduleFit();
      }
      g.addEventListener('pointermove', move);
      g.addEventListener('pointerup', up);
      g.addEventListener('pointercancel', up);
    });
    g.addEventListener('dblclick', function () {
      root.removeAttribute('data-kaz-cols');
      root.removeAttribute('data-kaz-col-left');
      root.removeAttribute('data-kaz-col-right');
      root.style.removeProperty('--kaz-col-left');
      root.style.removeProperty('--kaz-col-right');
      positionGrips(); scheduleFit();
    });
    g.addEventListener('keydown', function (ev) {
      var step = ev.shiftKey ? 32 : 8;
      if (ev.key !== 'ArrowLeft' && ev.key !== 'ArrowRight') return;
      var col = ws.querySelector('.kaz-sidebar--' + side);
      var w0 = col ? col.getBoundingClientRect().width : 236;
      var d = (ev.key === 'ArrowRight' ? 1 : -1) * (side === 'left' ? step : -step);
      var w = Math.max(MIN, Math.min(MAX, Math.round(w0 + d)));
      root.style.setProperty('--kaz-col-' + side, w + 'px');
      root.setAttribute('data-kaz-cols', '1');
      root.setAttribute('data-kaz-col-' + side, String(w));
      g.setAttribute('aria-valuenow', String(w));
      positionGrips(); scheduleFit(); ev.preventDefault();
    });
  }
  /* O mousemove original so redesenha o SVG; os campos do inspector so eram
     atualizados no mouseup. Acompanhamos o retangulo selecionado ao vivo,
     escrevendo .value (sem disparar eventos, sem loop). */
  function syncInspector() {
    if (!root) return;
    var g = root.querySelector('.kaz-g--selected');
    var rc = g ? g.querySelector('rect') : null;
    if (!rc) return;
    ['x', 'y', 'w', 'h'].forEach(function (k) {
      var f = root.querySelector('[data-kaz-field="' + k + '"]');
      if (!f) return;
      var attr = (k === 'w' ? 'width' : k === 'h' ? 'height' : k);
      var v = rc.getAttribute(attr);
      if (v !== null && f.value !== String(v)) f.value = String(v);
    });
  }
  function watchCanvas() {
    if (!canvas || typeof window.MutationObserver !== 'function') return;
    observer = new window.MutationObserver(function () {
      if (rafPending) return;
      rafPending = true;
      window.requestAnimationFrame(function () { rafPending = false; syncInspector(); });
    });
    observer.observe(canvas, { childList: true, subtree: true, attributes: true,
      attributeFilter: ['x', 'y', 'width', 'height', 'class'] });
  }
  function boot() {
    root = document.getElementById('kaz-theme-editor');
    if (!root || root.getAttribute('data-kaz-ajuste-ready') === '1') return;
    vp = root.querySelector('.kaz-viewport');
    ws = root.querySelector('.kaz-workspace');
    canvas = root.querySelector('[data-kaz-canvas]');
    buildGrips();
    watchCanvas();
    root.setAttribute('data-kaz-ajuste-ready', '1');
    /* Duas passagens: a primeira apos o layout, a segunda apos fontes/CSS. */
    scheduleFit();
    if (typeof window.setTimeout === 'function') {
      window.setTimeout(fitAll, 120);
    }
    window.addEventListener('resize', function () {
      if (resizeTimer) window.clearTimeout(resizeTimer);
      resizeTimer = window.setTimeout(fitAll, 140);
    });
    if (typeof window.ResizeObserver === 'function' && ws) {
      new window.ResizeObserver(function () { positionGrips(); }).observe(ws);
    }
  }
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot, { once: true });
  } else boot();

  window.__kazAjuste = function () {
    var z = root ? root.querySelector('[data-kaz-zoom-value]') : null;
    return {
      ready: !!(root && root.getAttribute('data-kaz-ajuste-ready') === '1'),
      zoom: z ? z.textContent : '',
      overflow: Math.round(over()),
      cols: root ? root.getAttribute('data-kaz-cols') : null,
      left: root ? root.getAttribute('data-kaz-col-left') : null,
      right: root ? root.getAttribute('data-kaz-col-right') : null,
      grips: Object.keys(grips).length
    };
  };
}());
