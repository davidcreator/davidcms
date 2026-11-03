/* F13-D — editor de cena Three.js: presets + miniatura SVG ao vivo.
   Vanilla, sem fetch/eval/innerHTML/document.write. O <select name="scene_preset">
   e a fonte de verdade no POST; sem JS os presets continuam aplicaveis pelo select. */
(function (document, window) {
  'use strict';
  var root = document.getElementById('theme-scene-editor');
  if (!root) return;
  var baseUrl = root.getAttribute('data-url') || '';
  var live = document.getElementById('scene-thumb-live');
  var select = document.getElementById('scene-preset-select');
  var cards = Array.prototype.slice.call(root.querySelectorAll('[data-scene-preset]'));
  var fields = {
    enabled: document.getElementById('three_enabled'),
    scene: document.querySelector('select[name="three_scene"]'),
    slot: document.querySelector('select[name="three_slot"]'),
    count: document.querySelector('input[name="three_count"]'),
    color: document.querySelector('input[name="three_color"]'),
    speed: document.querySelector('input[name="three_speed"]')
  };
  if (!live || !select || !fields.scene || !fields.count || !fields.color) return;
  var names = { enabled: 'three_enabled', scene: 'three_scene', slot: 'three_slot', count: 'three_count', color: 'three_color', speed: 'three_speed' };
  var timer = null, tick = 0, applying = false;

  function values() {
    return {
      enabled: fields.enabled && fields.enabled.checked ? '1' : '0',
      scene: fields.scene ? String(fields.scene.value || '') : '',
      slot: fields.slot ? String(fields.slot.value || '') : 'hero',
      count: fields.count ? String(fields.count.value || '') : '400',
      color: fields.color ? String(fields.color.value || '') : '#8a4fff',
      speed: fields.speed ? String(fields.speed.value || '') : '0.05'
    };
  }
  function query(v) {
    var out = [];
    Object.keys(names).forEach(function (k) { out.push(k + '=' + encodeURIComponent(v[k])); });
    return out.join('&');
  }
  function same(a, b) {
    return Object.keys(names).every(function (k) { return String(a[k]) === String(b[k]); });
  }
  function markActive(v) {
    var active = '';
    cards.forEach(function (card) {
      var candidate = {
        enabled: card.getAttribute('data-three-enabled') || '0',
        scene: card.getAttribute('data-three-scene') || '',
        slot: card.getAttribute('data-three-slot') || 'hero',
        count: card.getAttribute('data-three-count') || '400',
        color: card.getAttribute('data-three-color') || '#8a4fff',
        speed: card.getAttribute('data-three-speed') || '0.05'
      };
      var hit = same(v, candidate);
      if (hit) active = card.getAttribute('data-scene-preset') || '';
      card.classList.toggle('is-active', hit);
      card.setAttribute('aria-pressed', hit ? 'true' : 'false');
      var badge = card.querySelector('[data-preset-badge]');
      if (badge) badge.hidden = !hit;
    });
    if (!applying) select.value = active;
  }
  function update() {
    if (baseUrl) {
      tick += 1;
      live.setAttribute('src', baseUrl + (baseUrl.indexOf('?') >= 0 ? '&' : '?') + query(values()) + '&v=' + tick);
    }
    var v = values();
    markActive(v);
    root.setAttribute('data-active-preset', select.value || '');
  }
  function schedule() {
    if (timer) window.clearTimeout(timer);
    timer = window.setTimeout(update, 180);
  }
  function applyPreset(card) {
    applying = true;
    var map = {
      enabled: card.getAttribute('data-three-enabled') || '0',
      scene: card.getAttribute('data-three-scene') || '',
      slot: card.getAttribute('data-three-slot') || 'hero',
      count: card.getAttribute('data-three-count') || '400',
      color: card.getAttribute('data-three-color') || '#8a4fff',
      speed: card.getAttribute('data-three-speed') || '0.05'
    };
    if (fields.enabled) fields.enabled.checked = map.enabled === '1';
    Object.keys(map).forEach(function (k) {
      if (k === 'enabled' || !fields[k]) return;
      fields[k].value = map[k];
    });
    select.value = card.getAttribute('data-scene-preset') || '';
    applying = false;
    update();
  }

  Object.keys(fields).forEach(function (k) {
    if (!fields[k]) return;
    fields[k].addEventListener('change', function () { if (!applying) { select.value = ''; schedule(); } });
    fields[k].addEventListener('input', function () { if (!applying) { select.value = ''; schedule(); } });
  });
  select.addEventListener('change', function () {
    var id = select.value, card = null;
    cards.forEach(function (c) { if (c.getAttribute('data-scene-preset') === id) card = c; });
    if (card) applyPreset(card); else update();
  });
  cards.forEach(function (card) {
    card.addEventListener('click', function (e) { e.preventDefault(); applyPreset(card); });
  });

  update();
  // Exposicao minima para testes automatizados (sem alterar comportamento).
  root.__sceneEditor = { values: values, update: update, apply: applyPreset, cards: cards, select: select, live: live };
})(document, window);
