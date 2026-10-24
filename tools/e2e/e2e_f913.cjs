#!/usr/bin/env node
'use strict';
// Static contract/fixture gate only. This does not execute or parse the PHP implementation.
const fs = require('node:fs');
const path = require('node:path');
const root = path.resolve(__dirname, '../..');
const errors = [];
function read(rel) {
  const p = path.join(root, rel);
  if (!fs.existsSync(p)) { errors.push(`missing: ${rel}`); return ''; }
  return fs.readFileSync(p, 'utf8');
}
function need(ok, message) { if (!ok) errors.push(message); }
const php = read('dev/system/library/blog/block_document.php');
const contractText = read('docs/F9-13-Contrato-Parser-y-Conversion-Blog.json');
const fixturesText = read('tests/fixtures/legacy-conversion.json');
const docs = read('docs/F9-13-Parser-Serializer-Conversor-Legado.md');
let contract, fixtures;
try { contract = JSON.parse(contractText); } catch (e) { errors.push(`invalid task contract JSON: ${e.message}`); }
try { fixtures = JSON.parse(fixturesText); } catch (e) { errors.push(`invalid fixtures JSON: ${e.message}`); }
need(/class BlockDocument/.test(php), 'isolated BlockDocument library missing');
for (const method of ['convertLegacy','validateDocument','serializeDocument','deserializeDocument']) need(php.includes(`function ${method}(`), `required API missing: ${method}`);
for (const marker of ['LIBXML_NONET','review_required','source_html','format_version','unknown_block_type','isSafeImagePath']) need(php.includes(marker), `safety/contract marker missing: ${marker}`);
need(!/\$this->db|DB_PREFIX|->query\s*\(|\b(?:INSERT|UPDATE|DELETE|DROP TABLE)\b/i.test(php), 'conversion library must not access persistence/database');
need(contract?.task === 'F9-13', 'contract task identifier must be F9-13');
need(contract?.execution_policy?.php_tests_executed === false, 'contract must disclose PHP tests were not executed');
need(contract?.legacy_conversion?.unsupported?.includes('exact source_html retained'), 'unsupported content must fail closed and retain source');
need(contract?.document?.format_version === 1, 'format version must match F9-11');
need(Array.isArray(fixtures) && fixtures.length >= 8, 'expected at least eight fixtures');
if (Array.isArray(fixtures)) {
  const ids = new Set();
  let positive = 0, review = 0;
  for (const f of fixtures) {
    need(typeof f.id === 'string' && !ids.has(f.id), `fixture id missing/duplicate: ${f.id}`); ids.add(f.id);
    need(typeof f.html === 'string', `fixture HTML missing: ${f.id}`);
    need(['converted','review_required'].includes(f.expected_status), `fixture status invalid: ${f.id}`);
    need(Array.isArray(f.expected_types), `expected_types missing: ${f.id}`);
    if (f.expected_status === 'converted') positive++;
    else { review++; need(f.expected_types.length === 0, `review fixture must not expect partial blocks: ${f.id}`); }
  }
  need(positive >= 4 && review >= 4, 'fixtures must cover positive and fail-closed cases');
}
need(/não (?:foi|foram) executad[oa]s?/i.test(docs), 'docs must disclose runtime tests were not executed');
if (errors.length) { console.error('F9-13 STATIC FAIL'); for (const e of errors) console.error(` - ${e}`); process.exit(1); }
console.log(`F9-13 STATIC PASS — ${fixtures.length} fixture records inspected; PHP/app/browser/database code not executed.`);
