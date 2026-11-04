/* F13-W — preserva sites desabilitados no submit; F13-T — sonda local usa a politica PUBLICADA (ID estavel da R).
   F13-J — multisite declarativo: sonda local de host, apenas DOM.
   Sem fetch/eval/innerHTML/document.write. O mapa de sites vem do servidor
   em data-sites (constantes allowlistadas); o host digitado so e COMPARADO,
   nunca enviado, refletido em HTML nem publicado em global. */
(function (document, window) {
  'use strict';
  var root = document.getElementById('theme-sites');
  if (!root) return;
  var sites = {}, ids = ['primary','shop','blog','docs','landing','partner','legacy','staging'];
  // F13-T: data-sites-published e a politica PUBLICADA (mesma fonte do ID
  // estavel usado por decide()/byId() no Catalog). Sem o atributo (pacote
  // parcial/HEAD A-S), cai no mapa atual: comportamento identico ao legado.
  var mapJson = root.getAttribute('data-sites-published');
  if (mapJson === null || mapJson === '') mapJson = root.getAttribute('data-sites');
  try { sites = JSON.parse(mapJson || '{}'); } catch (e) { sites = {}; }
  var status = root.querySelector('[data-site-status]');
  var unknown = root.getAttribute('data-site-unknown') || '';

  function say(text) { if (status) status.textContent = String(text); }
  function hostKey(raw) {
    var h = String(raw || '').toLowerCase().replace(/^\s+|\s+$/g, '');
    if (!h || /[\/\\@]/.test(h)) return '';
    var m = /^([a-z0-9.-]+):\d{1,5}$/.exec(h);
    if (m) h = m[1];
    if (h.length > 253 || !/^[a-z0-9](?:[a-z0-9.-]{0,251}[a-z0-9])?$/.test(h)) return '';
    if (h.indexOf('..') >= 0 || h.indexOf('.-') >= 0 || h.indexOf('-.') >= 0) return '';
    return h;
  }
  function probe(raw) {
    var key = hostKey(raw);
    if (key !== '') {
      for (var i = 0; i < ids.length; i++) {
        var cfg = sites[ids[i]];
        if (cfg && cfg.enabled && cfg.host === key) {
          return { id: ids[i], slug: cfg.slug || '', language: cfg.language || '', reason: 'exact' };
        }
      }
    }
    for (var j = 0; j < ids.length; j++) {
      var d = sites[ids[j]];
      if (d && d.enabled) {
        return { id: ids[j], slug: d.slug || '', language: d.language || '', reason: 'default' };
      }
    }
    return null;
  }
  function fields(row) {
    return Array.prototype.slice.call(row.querySelectorAll('[data-site-host],[data-site-slug],[data-site-language]'));
  }
  function sync(row) {
    var check = row.querySelector('[data-site-enabled]');
    if (!check) return;
    fields(row).forEach(function (f) { f.disabled = !check.checked; });
  }
  Array.prototype.slice.call(root.querySelectorAll('[data-site]')).forEach(function (row) {
    sync(row);
    var check = row.querySelector('[data-site-enabled]');
    if (check) check.addEventListener('change', function () { sync(row); });
  });
  var button = root.querySelector('[data-site-probe-button]');
  var input = root.querySelector('[data-site-probe]');
  function run() {
    if (!input) return;
    var hit = probe(input.value);
    if (!hit) { say(unknown); return; }
    say(hit.id + ' · ' + hit.slug + ' · ' + hit.language + ' · ' + hit.reason);
  }
  if (button) button.addEventListener('click', run);
  if (input) input.addEventListener('keydown', function (ev) {
    if (ev.key === 'Enter') { ev.preventDefault(); run(); }
  });
  // F13-W: linhas totalmente vazias CONTINUAM disabled (nao entram no POST).
  // Linhas com host preenchido sao liberadas no submit para PRESERVAR a
  // politica de um site desabilitado (host/slug/idioma nao se perdem ao
  // salvar). O servidor descarta linhas vazias e continua soberano: nenhum
  // dado novo e aceito sem ThemeSites::normalize(). Sem rede e sem markup.
  var form = root.closest('form');
  if (form) form.addEventListener('submit', function () {
    Array.prototype.slice.call(root.querySelectorAll('[data-site]')).forEach(function (row) {
      var input = row.querySelector('[data-site-host]');
      if (!input || String(input.value).replace(/^\s+|\s+$/g, '') === '') return;
      fields(row).forEach(function (f) { f.disabled = false; });
    });
  });
})(document, window);
