/* Explicit reload action for optimistic edit conflicts; only clears local recovery drafts after a deliberate click. */
(function (global, document) {
  'use strict';
  document.addEventListener('click', function (event) {
    var button = event.target.closest && event.target.closest('[data-bdre-concurrency-reload]');
    if (!button) return;
    event.preventDefault();
    var form = document.querySelector('[data-cms-page-form], [data-blog-post-form]');
    if (!form) return;
    Array.prototype.forEach.call(form.querySelectorAll('[data-blog-block-editor]'), function (root) {
      try {
        var config = JSON.parse(root.dataset.patternConfig || '{}');
        if (config.draftKey) global.sessionStorage.removeItem(config.draftKey);
      } catch (_) {}
    });
    if (form.__blogBlockUnsavedState) form.__blogBlockUnsavedState.bypass = true;
    global.location.assign(form.action);
  });
})(window, document);
