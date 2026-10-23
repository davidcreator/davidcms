#!/usr/bin/env node
'use strict';
// JS real com DOM/Bootstrap/fetch simulados; não valida foco/preview em navegador.
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const source = fs.readFileSync(process.argv[2] || path.resolve(__dirname, '../../dev/admin/view/js/catalog-category.js'), 'utf8');
let pass = 0; let fail = 0;
async function test(label, fn) { try { await fn(); pass++; console.log('PASS ' + label); } catch (e) { fail++; console.error('FAIL ' + label + ': ' + e.message); } }
function fixture(options = {}) {
  const ready = []; const listeners = []; const clicks = []; const requests = [];
  let firstTarget = null;
  const pane = { id: 'pane-category-lang-1', active: false, classList: { contains: () => pane.active } };
  const tab = { count: 0, click() { this.count++; pane.active = true; } };
  const input = { files: options.noFile ? [] : [{ name: 'fixture.png', size: 77 }], disabled: Boolean(options.readonly) };
  const button = { disabled: Boolean(options.readonly), addEventListener(type, fn) { clicks.push({ type, fn }); } };
  const csrf = { value: options.noCsrf ? '' : 'c'.repeat(64) };
  const hidden = { value: 'view/image/category/old.png', events: [], dispatchEvent(event) { this.events.push(event.type); } };
  const classes = new Set(['d-none']);
  const errorBox = { textContent: '', classList: { add(c) { classes.add(c); }, remove(c) { classes.delete(c); }, contains(c) { return classes.has(c); } } };
  const preview = { children: [], appendChild(x) { this.children.push(x); } };
  Object.defineProperty(preview, 'textContent', { set() { this.children = []; } });
  Object.defineProperty(preview, 'innerHTML', { set() { throw new Error('Não usar innerHTML para dados do upload'); } });
  const config = { upload: options.foreignUpload ? 'https://example.com/upload' : 'http://127.0.0.1:8090/dev/admin/index.php?route=catalog/category.upload', catalog: 'http://127.0.0.1:8090/dev/catalog/', error: 'Falha local de upload', wait: 'Aguarde o envio' };
  const form = {
    dataset: { categoryConfig: JSON.stringify(config) },
    querySelector(selector) {
      if (selector === 'input:invalid, select:invalid, textarea:invalid') return firstTarget;
      if (selector === '[data-bs-target="#pane-category-lang-1"]') return tab;
      const objects = { '#btn-upload-cover': button, '#input-cover-file': input, '#input-image': hidden, '#cover-preview': preview, '#category-upload-error': errorBox, '[name="csrf_token"]': csrf };
      assert.ok(Object.hasOwn(objects, selector), 'Selector inesperado: ' + selector); return objects[selector];
    },
    addEventListener(type, fn, capture) { listeners.push({ type, fn, capture }); }
  };
  const document = { readyState: options.state || 'interactive', querySelector(selector) { assert.equal(selector, '[data-category-form]'); return options.noForm ? null : form; }, addEventListener(type, fn, flags) { ready.push({ type, fn, flags }); }, createElement(tag) { assert.equal(tag, 'img'); return {}; } };
  class FormDataFake { constructor() { this.values = new Map(); } append(key, value) { this.values.set(key, value); } }
  class EventFake { constructor(type, flags) { this.type = type; this.bubbles = flags.bubbles; } }
  const fetchFn = async (url, settings) => { requests.push({ url, settings }); if (options.fetchFn) return options.fetchFn(url, settings); return { ok: true, async json() { return options.result || { success: true, path: 'view/image/category/20261023160000-abcdef012345.png', error: '' }; } }; };
  const context = vm.createContext({ document, window: { location: { href: 'http://127.0.0.1:8090/dev/admin/index.php?route=catalog/category.add', origin: 'http://127.0.0.1:8090' }, bootstrap: options.noBootstrap ? undefined : { Tab: function () {} } }, URL, FormData: FormDataFake, Event: EventFake, fetch: fetchFn, AbortSignal });
  return {
    ready, listeners, clicks, requests, form, document, pane, tab, input, button, hidden, errorBox, preview, csrf, config,
    run() { vm.runInContext(source, context, { timeout: 1000 }); },
    domReady() { document.readyState = 'interactive'; for (const entry of ready.splice(0)) entry.fn(); },
    async click() { if (clicks[0]) return clicks[0].fn(); },
    invalid(later = false, outside = false) { const target = { closest: () => outside ? null : later ? { id: 'pane-category-lang-2', classList: { contains: () => false } } : pane }; if (!later) firstTarget = target; for (const entry of listeners.filter(x => x.type === 'invalid')) entry.fn({ target, preventDefault() { throw new Error('Não cancelar validação nativa'); } }); },
    submit() { const result = { prevented: false, preventDefault() { this.prevented = true; } }; for (const entry of listeners.filter(x => x.type === 'submit')) entry.fn(result); return result; }
  };
}
(async () => {
  await test('01 · DOM ready once e reexecução idempotente', () => { const x = fixture({ state: 'loading' }); x.run(); assert.equal(x.listeners.length, 0); assert.equal(x.ready[0].flags.once, true); x.domReady(); x.run(); assert.equal(x.clicks.length, 1); assert.equal(x.listeners.filter(l => l.type === 'invalid').length, 1); });
  await test('02 · primeiro inválido abre a aba, demais não a escondem', () => { const x = fixture(); x.run(); assert.equal(x.listeners.find(l => l.type === 'invalid').capture, true); x.invalid(); x.invalid(true); x.invalid(false, true); assert.equal(x.tab.count, 1); assert.equal(x.pane.active, true); });
  await test('03 · readonly/sem form não associa upload', async () => { for (const option of [{ readonly: true }, { noForm: true }]) { const x = fixture(option); x.run(); await x.click(); assert.equal(x.requests.length, 0); assert.equal(x.clicks.length, 0); } });
  await test('04 · sem arquivo/CSRF não envia request', async () => { const x = fixture({ noFile: true }); x.run(); await x.click(); assert.equal(x.requests.length, 0); const y = fixture({ noCsrf: true }); y.run(); await y.click(); assert.equal(y.requests.length, 0); assert.equal(y.errorBox.textContent, y.config.error); });
  await test('05 · upload inclui token/file, eventos e preview DOM seguro', async () => { const x = fixture(); x.run(); await x.click(); assert.equal(x.requests.length, 1); const settings = x.requests[0].settings; assert.equal(settings.body.values.get('csrf_token'), 'c'.repeat(64)); assert.equal(settings.body.values.get('file'), x.input.files[0]); assert.equal(settings.credentials, 'same-origin'); assert.equal(settings.redirect, 'error'); assert.deepEqual(x.hidden.events, ['input', 'change']); assert.equal(x.preview.children[0].src, 'http://127.0.0.1:8090/dev/catalog/' + x.hidden.value); assert.equal(x.button.disabled, false); assert.equal(x.input.disabled, false); });
  await test('06 · erro JSON vira texto e não troca imagem', async () => { const x = fixture({ result: { success: false, error: '<script>erro</script>', path: '' } }); x.run(); await x.click(); assert.equal(x.errorBox.textContent, '<script>erro</script>'); assert.equal(x.hidden.value, 'view/image/category/old.png'); assert.equal(x.preview.children.length, 0); });
  await test('07 · um upload pendente bloqueia submit/duplo clique, sem retry', async () => { let resolve; const pending = new Promise(r => { resolve = r; }); const x = fixture({ fetchFn: () => pending }); x.run(); const first = x.click(); await x.click(); assert.equal(x.requests.length, 1); assert.equal(x.submit().prevented, true); assert.equal(x.errorBox.textContent, x.config.wait); assert.equal(x.input.disabled, true); resolve({ ok: true, async json() { return { success: true, path: 'view/image/category/20261023160000-abcdef012345.png' }; } }); await first; assert.equal(x.submit().prevented, false); assert.equal(x.errorBox.classList.contains('d-none'), true); });
  await test('08 · path externo/traversal e origem de upload recusados', async () => { for (const value of ['../evil.png', 'https://example.com/evil.png', 'view/image/category/a/evil.png']) { const x = fixture({ result: { success: true, path: value } }); x.run(); await x.click(); assert.equal(x.hidden.value, 'view/image/category/old.png'); assert.equal(x.preview.children.length, 0); } const x = fixture({ foreignUpload: true }); x.run(); await x.click(); assert.equal(x.requests.length, 0); });
  await test('09 · falha/timeout restaura controles sem repetição', async () => { const x = fixture({ fetchFn: async () => { throw new Error('timeout'); } }); x.run(); await x.click(); assert.equal(x.requests.length, 1); assert.equal(x.errorBox.textContent, x.config.error); assert.equal(x.button.disabled, false); assert.equal(x.input.disabled, false); });
  console.log(`F8-07 DOM/upload simulados: ${pass} PASS / ${fail} FAIL — sem navegador, foco, fetch ou upload reais.`);
  process.exitCode = fail ? 1 : 0;
})();
