#!/usr/bin/env node
'use strict';
// Static source/fixture inspection only; no product runtime is loaded or invoked.
const fs = require('node:fs');
const path = require('node:path');
const root = process.argv[2] && path.resolve(process.argv[2]);
if (!root || !fs.existsSync(root)) {
  console.error('Usage: node e2e_f919_static.cjs <checkout-or-F9-19-package-root>');
  process.exit(2);
}
const errors = [];
function read(rel) {
  const file = path.join(root, rel);
  if (!fs.existsSync(file)) { errors.push(`missing ${rel}`); return ''; }
  return fs.readFileSync(file, 'utf8');
}
function need(condition, message) { if (!condition) errors.push(message); }
const model = read('dev/catalog/model/blog/blog.php');
const controller = read('dev/catalog/controller/blog/blog.php');
const renderer = read('dev/system/library/blog/block_renderer.php');
const adminModel = read('dev/admin/model/blog/blog.php');
const detectorAt = model.indexOf('private function blockContentTableExists');
const tablesAt = model.indexOf('private function tablesExist', detectorAt);
const detector = detectorAt >= 0 && tablesAt > detectorAt ? model.slice(detectorAt, tablesAt) : '';
const getBlockAt = model.indexOf('public function getPostBlockContent');
const getBlockEnd = model.indexOf('private function blockContentTableExists', getBlockAt);
const getBlock = getBlockAt >= 0 && getBlockEnd > getBlockAt ? model.slice(getBlockAt, getBlockEnd) : '';
need(detector.includes('blog_block_schema_detection_failed'), 'metadata query must throw a distinguishable schema detection error');
need(!/catch\s*\(\\Throwable\s+\$e\)\s*\{[^}]*\$cache\s*=\s*false/s.test(detector), 'metadata failure must not be cached as absent schema');
need(getBlock.includes('query_error') && /catch\s*\(\\Throwable/.test(getBlock), 'metadata/content read failures must produce explicit query_error');
need(/'" \. \$lang \. "' AS `active_language_id`/.test(model), 'catalog post query must expose active language id');
need(controller.includes('activeLanguageId') && controller.includes('fallbackLanguageId') && controller.includes("['query_error']"), 'controller must prefer active sidecar and gate fallback on confirmed absence');
need(controller.includes('$base') && /renderJson\([\s\S]*?\$base/s.test(controller), 'public rendering must pass trusted catalog base');
need(renderer.includes('resolveLocalImageUrl') && renderer.includes('assetBase'), 'renderer must resolve local paths against trusted base');
const deleteAt = adminModel.indexOf('public function deletePost');
const internalAt = adminModel.indexOf('// ─── internos', deleteAt);
const deleteMethod = deleteAt >= 0 && internalAt > deleteAt ? adminModel.slice(deleteAt, internalAt) : '';
need(deleteMethod.includes('START TRANSACTION') && deleteMethod.includes('COMMIT') && deleteMethod.includes('ROLLBACK'), 'post/sidecar delete must be transactional');
let fixtures;
try { fixtures = JSON.parse(read('tests/fixtures/f918_regressions.json')); }
catch (e) { errors.push(`fixture JSON invalid: ${e.message}`); }
need(Array.isArray(fixtures) && fixtures.length >= 8, 'regression fixture matrix must include at least eight cases');
if (Array.isArray(fixtures)) {
  const ids = new Set();
  for (const f of fixtures) { need(typeof f.id === 'string' && !ids.has(f.id), `fixture id missing/duplicate: ${f.id}`); ids.add(f.id); }
  for (const id of ['schema-metadata-error','active-language-empty-sidecar','image-subdirectory-install','delete-rollback']) need(ids.has(id), `missing regression fixture ${id}`);
}
if (errors.length) {
  console.error('F9-19 STATIC SOURCE CHECK: FAIL');
  for (const e of errors) console.error(` - ${e}`);
  process.exit(1);
}
console.log(`F9-19 STATIC SOURCE CHECK: PASS — ${fixtures.length} synthetic regression scenarios inspected. No PHP/SQL/browser/app was executed.`);
