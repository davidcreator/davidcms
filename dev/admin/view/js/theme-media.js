/* F13-I — midia vetorial + modelos de pagina: apenas DOM.
   Sem fetch/eval/innerHTML/document.write. Snippets e modelos sao JSON gerado
   no servidor (constantes allowlistadas); a unica escrita e textarea.value. */
(function (document, window) {
  'use strict';
  var root = document.getElementById('theme-media');
  var modelsRoot = document.getElementById('theme-page-models');
  var snippets = {}, models = {};
  if (root) {
    try { snippets = JSON.parse(root.getAttribute('data-snippets') || '{}'); } catch (e) { snippets = {}; }
  }
  if (modelsRoot) {
    try { models = JSON.parse(modelsRoot.getAttribute('data-models') || '{}'); } catch (e) { models = {}; }
  }
  var status = root ? root.querySelector('[data-media-status]') : null;
  var modelStatus = modelsRoot ? modelsRoot.querySelector('[data-model-status]') : null;
  var target = root ? root.querySelector('[data-media-target]') : null;
  var base = root ? (root.getAttribute('data-thumb-base') || '') : '';

  function say(node, text) { if (node) node.textContent = String(text); }
  function area(key) {
    if (!target) return null;
    return root.ownerDocument.querySelector('textarea[name="templates[' + String(key || '') + ']"]');
  }
  function rows() { return root ? Array.prototype.slice.call(root.querySelectorAll('[data-media]')) : []; }
  function thumbFor(row, img) {
    if (!img || !base) return;
    var id = String(row.getAttribute('data-media') || '');
    var variant = row.querySelector('[data-media-variant]');
    var tone = row.querySelector('[data-media-tone]');
    var sep = base.indexOf('?') >= 0 ? '&' : '?';
    img.setAttribute('src', base + sep + 'id=' + encodeURIComponent(id)
      + '&variant=' + encodeURIComponent(variant ? variant.value : '')
      + '&tone=' + encodeURIComponent(tone ? tone.value : ''));
  }
  function setSelect(row, attr, value) {
    var select = row.querySelector('[' + attr + ']');
    if (!select) return;
    for (var i = 0; i < select.options.length; i++) {
      if (select.options[i].value === String(value)) { select.selectedIndex = i; return; }
    }
  }
  function applyRow(row, attrs, cfg) {
    var check = row.querySelector('[data-media-enabled]');
    if (check) check.checked = !!cfg.enabled;
    setSelect(row, attrs.variant, cfg.variant || '');
    setSelect(row, attrs.tone, cfg.tone || '');
  }
  function applyModel(id) {
    var payload = models[id];
    if (!payload) { say(modelStatus, '!'); return; }
    var confirm = modelsRoot.querySelector('[data-model-confirm]');
    if (!confirm || !confirm.checked) { say(modelStatus, '?'); return; }
    var wanted = payload.sections || [];
    var list = document.getElementById('theme-section-list');
    var byId = {};
    if (list) {
      Array.prototype.forEach.call(list.querySelectorAll('[data-section]'), function (row) {
        var sid = String(row.getAttribute('data-section') || '');
        byId[sid] = row;
        var check = row.querySelector('input[type="checkbox"]');
        if (check) check.checked = wanted.indexOf(sid) >= 0;
      });
      var order = wanted.slice();
      Object.keys(byId).forEach(function (sid) { if (order.indexOf(sid) < 0) order.push(sid); });
      order.forEach(function (sid) { if (byId[sid]) list.appendChild(byId[sid]); });
      var hidden = document.getElementById('sections-order');
      if (hidden) hidden.value = order.join(',');
    }
    var components = payload.components || {}, media = payload.media || {};
    Array.prototype.forEach.call(document.querySelectorAll('[data-component]'), function (row) {
      var cid = String(row.getAttribute('data-component') || '');
      var check = row.querySelector('input[type="checkbox"]');
      if (check) check.checked = !!components[cid];
      if (components[cid]) {
        setSelect(row, 'data-component-variant', components[cid].variant || '');
        setSelect(row, 'data-component-tone', components[cid].tone || '');
      }
    });
    rows().forEach(function (row) {
      var mid = String(row.getAttribute('data-media') || '');
      applyRow(row, { variant: 'data-media-variant', tone: 'data-media-tone' }, media[mid] || { enabled: false });
      var img = row.querySelector('[data-media-thumb]'); thumbFor(row, img);
    });
    var ta = document.querySelector('textarea[name="templates[common/home]"]');
    if (ta) ta.value = String(payload.template || '');
    say(modelStatus, id);
    say(status, '');
  }
  rows().forEach(function (row) {
    var img = row.querySelector('[data-media-thumb]');
    ['[data-media-variant]', '[data-media-tone]'].forEach(function (sel) {
      var el = row.querySelector(sel);
      if (el) el.addEventListener('change', function () { thumbFor(row, img); });
    });
  });
  if (root) root.addEventListener('click', function (event) {
    var node = event.target, button = null;
    while (node && node !== root) {
      if (node.matches && node.matches('[data-media-insert]')) { button = node; break; }
      node = node.parentNode;
    }
    if (!button) return;
    var id = String(button.getAttribute('data-media-insert') || '');
    var snippet = snippets[id], ta = area(target ? target.value : '');
    if (typeof snippet !== 'string' || !ta) { say(status, '!'); return; }
    ta.value = ta.value + (ta.value && ta.value.slice(-1) !== '\n' ? '\n' : '') + snippet + '\n';
    if (ta.dispatchEvent) ta.dispatchEvent(new window.Event('input', { bubbles: true }));
    say(status, id);
  });
  if (modelsRoot) modelsRoot.addEventListener('click', function (event) {
    var node = event.target, button = null;
    while (node && node !== modelsRoot) {
      if (node.matches && node.matches('[data-model-apply]')) { button = node; break; }
      node = node.parentNode;
    }
    if (button) applyModel(String(button.getAttribute('data-model-apply') || ''));
  });
})(document, window);
