#!/usr/bin/env node
'use strict';
// JS real; DOM/Bootstrap simulados. Não testa navegador nem validação física.
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const file = process.argv[2] || path.resolve(__dirname, '../../dev/admin/view/js/cms-page.js');
const source = fs.readFileSync(file, 'utf8');
let pass = 0; let fail = 0;
function test(label, fn) { try { fn(); pass++; console.log('PASS ' + label); } catch (e) { fail++; console.error('FAIL ' + label + ': ' + e.message); } }
function fixture(options = {}) {
  const listeners = [];
  let firstTarget = null;
  const ready = [];
  const pane = { id: 'pane-page-lang-1', active: Boolean(options.active), classList: { contains: () => pane.active } };
  const button = { clicks: 0, click() { this.clicks++; pane.active = true; } };
  const form = {
    dataset: {},
    querySelector(selector) { if (selector === 'input:invalid, select:invalid, textarea:invalid') return firstTarget; assert.equal(selector, '[data-bs-target="#pane-page-lang-1"]'); return button; },
    addEventListener(type, fn, capture) { listeners.push({ type, fn, capture }); }
  };
  const document = {
    readyState: options.state || 'interactive',
    querySelector(selector) { assert.equal(selector, '[data-cms-page-form]'); return options.noForm ? null : form; },
    addEventListener(type, fn, flags) { ready.push({ type, fn, flags }); }
  };
  const context = vm.createContext({ document, window: { bootstrap: options.noBootstrap ? undefined : { Tab: function () {} } } });
  return {
    document, form, pane, button, listeners, ready,
    run() { vm.runInContext(source, context, { timeout: 1000 }); },
    domReady() { document.readyState = 'interactive'; for (const entry of ready.splice(0)) entry.fn(); },
    invalid(outside = false, later = false) { const target = { closest: () => outside ? null : later ? { id: 'pane-page-lang-2', classList: { contains: () => false } } : pane }; if (!later) firstTarget = target; for (const entry of listeners) entry.fn({ target, preventDefault() { throw new Error('Não impedir validação nativa'); } }); }
  };
}
test('01 · loading aguarda DOMContentLoaded once', () => { const x = fixture({ state: 'loading' }); x.run(); assert.equal(x.listeners.length, 0); assert.equal(x.ready.length, 1); assert.equal(x.ready[0].flags.once, true); x.domReady(); assert.equal(x.listeners.length, 1); });
test('02 · interactive/complete: invalid usa captura', () => { for (const state of ['interactive', 'complete']) { const x = fixture({ state }); x.run(); assert.equal(x.listeners[0].type, 'invalid'); assert.equal(x.listeners[0].capture, true); } });
test('03 · campo inválido oculto revela a aba, sem preventDefault', () => { const x = fixture(); x.run(); x.invalid(); x.invalid(false, true); assert.equal(x.button.clicks, 1); assert.equal(x.pane.active, true); });
test('04 · aba ativa/campo fora de abas não provoca troca', () => { const x = fixture({ active: true }); x.run(); x.invalid(); x.invalid(true); assert.equal(x.button.clicks, 0); });
test('05 · sem formulário/Bootstrap: não quebra o fluxo', () => { const x = fixture({ noForm: true }); x.run(); assert.equal(x.listeners.length, 0); const y = fixture({ noBootstrap: true }); y.run(); y.invalid(); assert.equal(y.button.clicks, 0); });
test('06 · segunda execução não duplica invalid', () => { const x = fixture(); x.run(); x.run(); assert.equal(x.listeners.length, 1); x.invalid(); assert.equal(x.button.clicks, 1); });
console.log(`F8-06 DOM simulado: ${pass} PASS / ${fail} FAIL — 6 casos; não é navegador real.`);
process.exitCode = fail ? 1 : 0;
