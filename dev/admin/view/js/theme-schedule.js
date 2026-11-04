/* F13-O — escopo por site no status; apenas DOM.
   F13-H — agendamento/rollout: apenas DOM. Sem fetch/eval/innerHTML/document.write.
   O servidor valida tudo; o JS so habilita campos e mostra o percentual. */
(function (document) {
  'use strict';
  var root = document.getElementById('theme-schedule');
  if (!root) return;
  var toggle = root.querySelector('[data-schedule-enabled]');
  var fields = root.querySelectorAll('[data-schedule-input]');
  var percent = root.querySelector('[data-schedule-percent]');
  var sites = root.querySelector('[data-schedule-sites]');
  var status = root.querySelector('[data-schedule-status]');
  function say(text) { if (status) status.textContent = String(text); }
  function sync() {
    var on = !!(toggle && toggle.checked);
    Array.prototype.forEach.call(fields, function (field) { field.disabled = !on; });
    var count = sites ? Array.prototype.filter.call(sites.options, function (o) { return o.selected; }).length : 0;
    say(on && percent ? percent.value + ' %' + (count ? ' · ' + count + ' sites' : '') : '');
  }
  if (toggle) toggle.addEventListener('change', sync);
  if (sites) sites.addEventListener('change', sync);
  if (percent) percent.addEventListener('input', function () {
    if (toggle && toggle.checked) say(percent.value + ' %');
  });
  sync();
})(document);
