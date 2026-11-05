/* Frente AN — companion ADITIVO de identidade visual do BDREditor.
   Nao monta, nao edita e nao serializa o documento de blocos. Apenas cria
   controles de escala/dobras no host e aplica atributos de apresentacao.
   DOM puro: sem escrita dinamica de marcacao, execucao dinamica, rede,
   armazenamento, formulario, CSRF, renderer, catalogo ou banco. */
(function () {
  'use strict';
  var SCALES = [['compact', 'Compacto'], ['normal', 'Normal'],
                ['comfortable', 'Confortável'], ['large', 'Amplo']];
  var FOLDS = ['toolbar', 'tabs', 'status'];
  var FOLD_LABELS = { toolbar: 'Ícones', tabs: 'Abas', status: 'Status' };
  var hosts = [];

  function make(tag, cls, text) {
    var e = document.createElement(tag);
    if (cls) e.setAttribute('class', cls);
    if (text !== undefined) e.textContent = text;
    return e;
  }
  function foldState(host) {
    return (host.getAttribute('data-kaz-fold') || '').split(/\s+/).filter(Boolean);
  }
  function hasFold(host, k) { return foldState(host).indexOf(k) !== -1; }
  function setFold(host, k, on) {
    var a = foldState(host).filter(function (x) { return x !== k; });
    if (on) a.push(k);
    if (a.length) host.setAttribute('data-kaz-fold', a.join(' '));
    else host.removeAttribute('data-kaz-fold');
  }
  function status(host, msg) {
    var s = host.querySelector(':scope > .kaz-bdre-bar .kaz-bdre-status');
    if (s) s.textContent = msg;
  }
  function build(host) {
    if (host.querySelector(':scope > .kaz-bdre-bar')) return;
    var bar = make('div', 'kaz-bdre-bar');
    bar.setAttribute('role', 'group');
    bar.setAttribute('aria-label', 'Aparência e ergonomia do editor');

    var label = make('label', null, 'ELEMENTOS');
    var select = make('select');
    select.setAttribute('data-kaz-bdre-scale', '');
    select.setAttribute('aria-label', 'Tamanho dos elementos');
    SCALES.forEach(function (p) {
      var o = make('option', null, p[1]);
      o.value = p[0];
      if (p[0] === 'normal') o.setAttribute('selected', 'selected');
      select.appendChild(o);
    });
    select.addEventListener('change', function () {
      if (select.value === 'normal') host.removeAttribute('data-kaz-ui-scale');
      else host.setAttribute('data-kaz-ui-scale', select.value);
      status(host, 'Escala: ' + select.options[select.selectedIndex].text);
    });
    bar.appendChild(label); bar.appendChild(select);

    var buttons = [];
    FOLDS.forEach(function (k) {
      var b = make('button', null, FOLD_LABELS[k]);
      b.type = 'button';
      b.setAttribute('data-kaz-bdre-fold', k);
      b.setAttribute('aria-pressed', 'false');
      b.addEventListener('click', function () {
        var on = !hasFold(host, k);
        setFold(host, k, on);
        b.setAttribute('aria-pressed', on ? 'true' : 'false');
        status(host, (on ? 'Recolhido: ' : 'Expandido: ') + FOLD_LABELS[k]);
      });
      buttons.push(b); bar.appendChild(b);
    });

    var all = make('button', null, 'Recolher tudo');
    all.type = 'button';
    all.setAttribute('data-kaz-bdre-all', '');
    all.setAttribute('aria-pressed', 'false');
    all.addEventListener('click', function () {
      var on = foldState(host).length !== FOLDS.length;
      FOLDS.forEach(function (k) { setFold(host, k, on); });
      buttons.forEach(function (b) { b.setAttribute('aria-pressed', on ? 'true' : 'false'); });
      all.setAttribute('aria-pressed', on ? 'true' : 'false');
      all.textContent = on ? 'Expandir tudo' : 'Recolher tudo';
      status(host, on ? 'Ferramentas recolhidas' : 'Ferramentas expandidas');
    });
    bar.appendChild(all);

    var st = make('span', 'kaz-bdre-status', 'Padrão KAZTheme-Editor ativo');
    st.setAttribute('role', 'status');
    st.setAttribute('aria-live', 'polite');
    bar.appendChild(st);

    host.insertBefore(bar, host.firstChild);
    status(host, 'Padrão KAZTheme-Editor ativo');
  }
  function boot() {
    hosts = Array.prototype.slice.call(document.querySelectorAll('[data-blog-block-editor]'));
    hosts.forEach(function (h) {
      /* O init do BDREditor REPLICA/SUBSTITUI os filhos do host ao montar.
         build() e idempotente pela barra, entao chamamos sempre: se o init
         removeu a barra, o MutationObserver a reconstrói. */
      build(h);
      h.setAttribute('data-kaz-bdre-ready', '1');
    });
  }
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot, { once: true });
  } else boot();

  /* O init do BDREditor pode montar novos hosts (idiomas, fullscreen).
     So criamos a barra; nunca mexemos no conteudo montado. */
  if (typeof window.MutationObserver === 'function') {
    var pending = false;
    new window.MutationObserver(function () {
      if (pending) return;
      pending = true;
      window.requestAnimationFrame(function () { pending = false; boot(); });
    }).observe(document.documentElement, { childList: true, subtree: true });
  }

  window.__kazBdre = function () {
    return hosts.map(function (h) {
      var s = h.querySelector(':scope > .kaz-bdre-bar select[data-kaz-bdre-scale]');
      return {
        ready: h.getAttribute('data-kaz-bdre-ready') === '1',
        scale: h.getAttribute('data-kaz-ui-scale') || 'normal',
        fold: h.getAttribute('data-kaz-fold') || '',
        bar: !!h.querySelector(':scope > .kaz-bdre-bar'),
        editorMounted: !!h.querySelector('.rb-editor'),
        selectValue: s ? s.value : null,
        primary: getComputedStyle(h).getPropertyValue('--kaz-bdre-primary').trim(),
        canvasBg: (function () {
          var c = h.querySelector('.rb-canvas');
          return c ? getComputedStyle(c).backgroundColor : '';
        })()
      };
    });
  };
}());
