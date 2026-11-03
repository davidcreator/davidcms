/* F13-F — section builder: habilitar e reordenar seções da home.
   Vanilla, sem fetch/eval/innerHTML/document.write. A fonte de verdade no POST
   continua sendo `sections[]` (checkboxes) + `sections_order` (CSV); sem JS o
   formulário já envia a ordem renderizada pelo servidor. */
(function (document) {
  'use strict';
  var root = document.getElementById('theme-section-builder');
  if (!root) return;
  var list = document.getElementById('theme-section-list');
  var hidden = document.getElementById('sections-order');
  var counter = root.querySelector('[data-section-count]');
  if (!list || !hidden) return;

  function rows() {
    return Array.prototype.slice.call(list.querySelectorAll('li[data-section]'));
  }
  function enabled(row) {
    var box = row.querySelector('input[type="checkbox"]');
    return !!(box && box.checked);
  }
  function sync() {
    var all = rows(), ids = [], active = 0, i;
    for (i = 0; i < all.length; i++) {
      ids.push(String(all[i].getAttribute('data-section') || ''));
      if (enabled(all[i])) active++;
      if (all[i].classList) all[i].classList.toggle('tsb-row--off', !enabled(all[i]));
    }
    hidden.value = ids.join(',');
    if (counter) counter.textContent = String(active);
    for (i = 0; i < all.length; i++) {
      var up = all[i].querySelector('[data-section-move="up"]');
      var down = all[i].querySelector('[data-section-move="down"]');
      if (up) up.disabled = (i === 0);
      if (down) down.disabled = (i === all.length - 1);
    }
  }
  function closest(node, selector) {
    while (node && node !== list) {
      if (node.matches && node.matches(selector)) return node;
      node = node.parentNode;
    }
    return null;
  }
  list.addEventListener('click', function (event) {
    var target = event.target;
    var button = closest(target, '[data-section-move]');
    if (!button) return;
    var row = closest(button, 'li[data-section]');
    if (!row) return;
    var direction = button.getAttribute('data-section-move');
    if (direction === 'up' && row.previousElementSibling) {
      list.insertBefore(row, row.previousElementSibling);
    } else if (direction === 'down' && row.nextElementSibling) {
      list.insertBefore(row.nextElementSibling, row);
    }
    sync();
  });
  list.addEventListener('change', sync);
  var form = root.closest ? root.closest('form') : null;
  if (form) form.addEventListener('submit', sync);
  sync();
})(document);
