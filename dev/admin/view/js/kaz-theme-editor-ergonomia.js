/* Frente AL — Ergonomia visual do KAZTheme-Editor (companion ADITIVO).
   Carregado DEPOIS de kaz-theme-editor.js. Nao altera boot, cena, tema,
   banco, rota, CSRF, CSP ou formulario. DOM puro: createElement + textContent
   + setAttribute. Sem escrita dinamica de marcacao, execucao dinamica,
   requisicao de rede ou armazenamento.
   Preferencias sao estado LOCAL descartavel, como densidade/fullscreen da AA. */
(function () {
  'use strict';
  var SCALES = { compact: 0.86, normal: 1, comfortable: 1.14, large: 1.30 };
  var LABELS = { compact: 'compacto', normal: 'normal', comfortable: 'confortável',
                 large: 'amplo' };
  var FOLDS = ['toolbar', 'tabs', 'status'];
  var FOLD_LABELS = { toolbar: 'Ícones', tabs: 'Abas', status: 'Status' };
  var root = null, scale = 'normal', folded = {}, buttons = {}, statusEl = null;
  var allBtn = null, select = null;

  function hex(v) {
    return /^#([0-9a-f]{3}|[0-9a-f]{6}|[0-9a-f]{8})$/i.test(String(v || ''))
      ? String(v) : '';
  }
  function el(tag, cls, text) {
    var n = document.createElement(tag);
    if (cls) n.setAttribute('class', cls);
    if (text != null) n.textContent = text;
    return n;
  }
  function paintColors() {
    if (!root) return;
    var p = hex(root.getAttribute('data-kaz-primary'));
    var s = hex(root.getAttribute('data-kaz-surface'));
    if (p) root.style.setProperty('--kaz-al-primary', p);
    if (s) root.style.setProperty('--kaz-al-surface', s);
  }
  /* A escala muda a altura do chrome e, portanto, a area util do stage.
     Reaproveitamos o botao publico "Ajustar à tela" da AC para recalcular o
     zoom da cena - sem duplicar fitZoom() e sem tocar no JS da AJ. */
  function refit() {
    if (!root || typeof window.requestAnimationFrame !== 'function') return;
    window.requestAnimationFrame(function () {
      var fit = root.querySelector('[data-kaz-action="screen-fit"]');
      if (fit && typeof fit.click === 'function') fit.click();
    });
  }
  function setScale(v) {
    if (!root || !Object.prototype.hasOwnProperty.call(SCALES, v)) return false;
    scale = v;
    root.setAttribute('data-kaz-ui-scale', v);
    if (select) select.value = v;
    say(); refit();
    return true;
  }
  function activeFolds() {
    return FOLDS.filter(function (f) { return !!folded[f]; });
  }
  function applyFolds() {
    if (!root) return;
    var v = activeFolds();
    if (v.length) root.setAttribute('data-kaz-fold', v.join(' '));
    else root.removeAttribute('data-kaz-fold');
    FOLDS.forEach(function (f) {
      if (buttons[f]) buttons[f].setAttribute('aria-pressed', folded[f] ? 'true' : 'false');
    });
    if (allBtn) {
      allBtn.setAttribute('aria-pressed', v.length === FOLDS.length ? 'true' : 'false');
      allBtn.textContent = v.length ? 'Expandir tudo' : 'Recolher tudo';
    }
    say();
  }
  function toggleFold(name) {
    if (name === 'all') {
      var any = activeFolds().length > 0;
      FOLDS.forEach(function (f) { folded[f] = !any; });
    } else if (FOLDS.indexOf(name) !== -1) {
      folded[name] = !folded[name];
    } else return false;
    applyFolds(); refit();
    return true;
  }
  function say() {
    if (!statusEl) return;
    var n = activeFolds().length;
    statusEl.textContent = 'Elementos: ' + (LABELS[scale] || scale) +
      ' · ' + (n ? n + ' grupo(s) recolhido(s)' : 'ferramentas visíveis');
  }
  function buildControls(bar) {
    if (!bar) return;
    var wrap = el('span', 'kaz-ergo');
    wrap.setAttribute('data-kaz-ergo', '1');
    wrap.appendChild(el('span', 'kaz-screenbar__sep'));
    wrap.firstChild.setAttribute('aria-hidden', 'true');

    var label = el('label', 'kaz-ergo__label', 'Elementos');
    label.setAttribute('for', 'kaz-ergo-scale');
    wrap.appendChild(label);

    select = el('select', 'kaz-screenbar__select');
    select.setAttribute('id', 'kaz-ergo-scale');
    select.setAttribute('data-kaz-ui-scale', '');
    select.setAttribute('aria-label', 'Tamanho de todos os elementos');
    ['compact', 'normal', 'comfortable', 'large'].forEach(function (k) {
      var o = el('option', null, LABELS[k]);
      o.value = k;
      select.appendChild(o);
    });
    select.value = scale;
    select.addEventListener('change', function () { setScale(select.value); });
    wrap.appendChild(select);

    wrap.appendChild(el('span', 'kaz-screenbar__sep'));

    FOLDS.forEach(function (f) {
      var b = el('button', 'kaz-btn kaz-btn--ghost', FOLD_LABELS[f]);
      b.setAttribute('type', 'button');
      b.setAttribute('data-kaz-ergo-fold', f);
      b.setAttribute('aria-pressed', 'false');
      b.setAttribute('aria-label', 'Recolher ou expandir ' + FOLD_LABELS[f]);
      b.title = 'Recolher / expandir ' + FOLD_LABELS[f];
      b.addEventListener('click', function () { toggleFold(f); });
      buttons[f] = b;
      wrap.appendChild(b);
    });

    allBtn = el('button', 'kaz-btn kaz-btn--ghost', 'Recolher tudo');
    allBtn.setAttribute('type', 'button');
    allBtn.setAttribute('data-kaz-ergo-fold', 'all');
    allBtn.setAttribute('aria-pressed', 'false');
    allBtn.setAttribute('aria-label', 'Recolher ou expandir todas as ferramentas');
    allBtn.addEventListener('click', function () { toggleFold('all'); });
    wrap.appendChild(allBtn);

    statusEl = el('span', 'kaz-ergo__status', '');
    statusEl.setAttribute('data-kaz-ergo-status', '');
    statusEl.setAttribute('role', 'status');
    statusEl.setAttribute('aria-live', 'polite');
    wrap.appendChild(statusEl);

    /* Primeiro filho da screenbar: em ate 1280px a barra rola horizontal-
       mente e os controles no fim ficariam fora da area visivel. */
    bar.insertBefore(wrap, bar.firstChild);
  }
  function bindColorInputs() {
    if (!root) return;
    ['primary', 'surface'].forEach(function (k) {
      var inp = root.querySelector('input[type="color"][name="' + k + '"]');
      if (!inp) return;
      inp.addEventListener('input', function () {
        var v = hex(inp.value);
        if (v) root.style.setProperty('--kaz-al-' + k, v);
      });
    });
  }
  function boot() {
    root = document.getElementById('kaz-theme-editor');
    if (!root || root.getAttribute('data-kaz-ergo-ready') === '1') return;
    var bar = root.querySelector('[data-kaz-screenbar]');
    paintColors();
    buildControls(bar);
    bindColorInputs();
    root.setAttribute('data-kaz-ui-scale', scale);
    applyFolds();
    root.setAttribute('data-kaz-ergo-ready', '1');
  }
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot, { once: true });
  } else boot();

  /* Diagnostico somente-leitura. NAO e exigido para usar a ferramenta. */
  window.__kazErgo = function () {
    return {
      ready: !!(root && root.getAttribute('data-kaz-ergo-ready') === '1'),
      scale: scale, factor: SCALES[scale],
      folded: activeFolds(),
      primary: root ? root.style.getPropertyValue('--kaz-al-primary') : '',
      surface: root ? root.style.getPropertyValue('--kaz-al-surface') : ''
    };
  };
}());
