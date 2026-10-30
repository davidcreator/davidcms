#!/usr/bin/env node
// Exercise the actual URL validation/helper function bodies from the editor source.
const fs = require('node:fs');
const vm = require('node:vm');
const source = fs.readFileSync('dev/admin/view/js/blog-block-editor.js', 'utf8');
const start = source.indexOf('  function validActionUrl(value) {');
const end = source.indexOf('\n  const INLINE_MARKS', start);
if (start < 0 || end < 0) throw new Error('URL helper definitions not found');
const helpers = source.slice(start, end);
const context = vm.createContext({ URL });
vm.runInContext(`${helpers}\nglobalThis.urlHelpers={validActionUrl,safeActionUrl};`, context);
const { validActionUrl, safeActionUrl } = context.urlHelpers;
const cases = [
  ['https://example.com/path', true, 'https://example.com/path'],
  ['http://example.com', true, 'http://example.com'],
  ['/pages/contact', true, '/pages/contact'],
  ['javascript:alert(1)', false, null],
  ['//attacker.example/path', false, null],
  ['ftp://example.com/file', false, null],
  ['https://user:pass@example.com/', false, null],
  ['', false, null],
  [' /pages/contact', false, null],
];
for (const [input, expectedValidity, expectedSafe] of cases) {
  const actualValidity = validActionUrl(input);
  const actualSafe = safeActionUrl(input);
  if (actualValidity !== expectedValidity || actualSafe !== expectedSafe) {
    throw new Error(`URL case failed for ${JSON.stringify(input)}: valid=${actualValidity}, safe=${JSON.stringify(actualSafe)}`);
  }
  console.log(`PASS URL helper | ${JSON.stringify(input)}`);
}
console.log(`All ${cases.length} URL helper smoke tests passed.`);
