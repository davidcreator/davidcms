/* Fase 8 / Tarefa 06 — revela a aba de um campo inválido sem impedir a validação nativa. */
(function initCmsPageForm() {
  'use strict';
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initCmsPageForm, { once: true });
    return;
  }
  var form = document.querySelector('[data-cms-page-form]');
  if (!form || form.dataset.cmsValidationReady === '1') { return; }
  form.dataset.cmsValidationReady = '1';
  form.addEventListener('invalid', function (event) {
    // Só a primeira restrição inválida determina o foco; não alternar para
    // outras abas quando vários campos falham na mesma tentativa de submit.
    var firstInvalid = form.querySelector('input:invalid, select:invalid, textarea:invalid');
    if (firstInvalid && firstInvalid !== event.target) { return; }
    var pane = event.target.closest('.tab-pane');
    if (!pane || pane.classList.contains('active')) { return; }
    var button = form.querySelector('[data-bs-target="#' + pane.id + '"]');
    if (button && window.bootstrap && window.bootstrap.Tab) {
      button.click();
    }
  }, true);
})();
