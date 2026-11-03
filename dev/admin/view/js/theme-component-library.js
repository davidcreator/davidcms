/* F13-G — biblioteca de componentes: inserir snippet Twig e atualizar miniatura.
   Vanilla, sem fetch/eval/innerHTML/document.write. Os snippets sao JSON gerado
   no servidor (constantes allowlistadas); o unico escrito e textarea.value. */
(function (document) {
  'use strict';
  var root = document.getElementById('theme-component-library');
  if (!root) return;
  var snippets = {};
  try { snippets = JSON.parse(root.getAttribute('data-snippets') || '{}'); } catch (e) { return; }
  var target = root.querySelector('[data-component-target]');
  var status = root.querySelector('[data-component-status]');
  var base = root.getAttribute('data-thumb-base') || '';

  function say(text) { if (status) status.textContent = String(text); }
  function textarea() {
    if (!target) return null;
    var key = String(target.value || '');
    return root.ownerDocument.querySelector('textarea[name="templates[' + key + ']"]');
  }
  function thumbFor(row, img) {
    if (!img || !base) return;
    var id = String(row.getAttribute('data-component') || '');
    var variant = row.querySelector('[data-component-variant]');
    var tone = row.querySelector('[data-component-tone]');
    var sep = base.indexOf('?') >= 0 ? '&' : '?';
    img.setAttribute('src', base + sep + 'id=' + encodeURIComponent(id)
      + '&variant=' + encodeURIComponent(variant ? variant.value : '')
      + '&tone=' + encodeURIComponent(tone ? tone.value : ''));
  }
  function rows() {
    return Array.prototype.slice.call(root.querySelectorAll('[data-component]'));
  }
  rows().forEach(function (row) {
    var img = row.querySelector('[data-component-thumb]');
    var variant = row.querySelector('[data-component-variant]');
    var tone = row.querySelector('[data-component-tone]');
    if (variant) variant.addEventListener('change', function () { thumbFor(row, img); });
    if (tone) tone.addEventListener('change', function () { thumbFor(row, img); });
  });
  root.addEventListener('click', function (event) {
    var node = event.target;
    var button = null;
    while (node && node !== root) {
      if (node.matches && node.matches('[data-component-insert]')) { button = node; break; }
      node = node.parentNode;
    }
    if (!button) return;
    var id = String(button.getAttribute('data-component-insert') || '');
    var snippet = snippets[id];
    var area = textarea();
    if (typeof snippet !== 'string' || !area) { say('!'); return; }
    area.value = area.value + (area.value && area.value.slice(-1) !== '\n' ? '\n' : '') + snippet + '\n';
    if (area.dispatchEvent) area.dispatchEvent(new Event('input', { bubbles: true }));
    say(id);
  });
  say('');
})(document);
