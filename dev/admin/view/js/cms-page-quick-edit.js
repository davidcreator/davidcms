/* F9-88: accessible inline Quick Edit rows for the CMS Pages list. */
(function (document) {
  'use strict';
  var activeButton = null;

  function close(row, returnFocus) {
    if (!row) return;
    row.hidden = true;
    var id = row.id;
    document.querySelectorAll('[data-page-quick-edit-toggle]').forEach(function (button) {
      if (button.dataset.target === id) button.setAttribute('aria-expanded', 'false');
    });
    if (returnFocus && activeButton && activeButton.isConnected) activeButton.focus();
    activeButton = null;
  }

  document.addEventListener('click', function (event) {
    var toggle = event.target.closest('[data-page-quick-edit-toggle]');
    if (toggle) {
      event.preventDefault();
      var row = document.getElementById(toggle.dataset.target || '');
      if (!row) return;
      if (!row.hidden) { close(row, false); toggle.focus(); return; }
      document.querySelectorAll('.page-quick-edit-row:not([hidden])').forEach(function (openRow) { close(openRow, false); });
      activeButton = toggle;
      row.hidden = false;
      toggle.setAttribute('aria-expanded', 'true');
      var title = row.querySelector('[name="quick_title"]');
      if (title) title.focus();
      return;
    }
    var cancel = event.target.closest('[data-page-quick-edit-cancel]');
    if (cancel) {
      event.preventDefault();
      close(document.getElementById(cancel.dataset.target || ''), true);
    }
  });

  document.addEventListener('keydown', function (event) {
    if (event.key !== 'Escape') return;
    var row = event.target.closest && event.target.closest('.page-quick-edit-row:not([hidden])');
    if (row) { event.preventDefault(); close(row, true); }
  });
})(document);
