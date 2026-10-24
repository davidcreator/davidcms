#!/usr/bin/env node
'use strict';
// Source-contract audit only. This tool never loads PHP, SQL, Twig, app, or browser code.
const fs = require('node:fs');
const path = require('node:path');
const pkg = path.resolve(__dirname, '../..');
const errors = [];
function need(value, message) { if (!value) errors.push(message); }
function read(root, rel) {
  const file = path.join(root, rel);
  if (!fs.existsSync(file)) { errors.push(`missing source: ${rel}`); return ''; }
  try { return fs.readFileSync(file, 'utf8'); }
  catch (e) { errors.push(`cannot read ${rel}: ${e.message}`); return ''; }
}
const root = process.argv[2] && path.resolve(process.argv[2]);
if (!root || !fs.existsSync(root)) {
  console.error('Usage: node e2e_f917.cjs <path-to-DavidCMS-checkout>');
  process.exit(2);
}
const files = {
  adminController: 'dev/admin/controller/blog/blog.php',
  adminModel: 'dev/admin/model/blog/blog.php',
  form: 'dev/admin/view/template/blog/blog_form.twig',
  editor: 'dev/admin/view/js/blog-block-editor.js',
  editorInit: 'dev/admin/view/js/blog-block-editor-init.js',
  media: 'dev/admin/view/js/blog-media.js',
  document: 'dev/system/library/blog/block_document.php',
  renderer: 'dev/system/library/blog/block_renderer.php',
  catalogModel: 'dev/catalog/model/blog/blog.php',
  catalogController: 'dev/catalog/controller/blog/blog.php',
  catalogTemplate: 'dev/catalog/view/template/blog/blog_info.twig'
};
const source = Object.fromEntries(Object.entries(files).map(([key, rel]) => [key, read(root, rel)]));
const requireMarkers = (value, markers, label) => markers.forEach((marker) => need(value.includes(marker), `${label}: missing marker ${marker}`));
requireMarkers(source.adminController, ['prepareBlockSubmission', 'blog_editor_ready', 'error_block_editor_unavailable', 'savePostWithBlocks', 'error_block_schema_unavailable'], 'admin controller flow');
requireMarkers(source.adminModel, ['information_schema', 'blog_post_content', 'START TRANSACTION', 'COMMIT', 'ROLLBACK', 'legacy_html_backup', 'source_hash'], 'sidecar transaction');
requireMarkers(source.form, ['data-blog-block-editor', 'blog_block_mode', 'blog_blocks', 'blog_editor_ready', "block_mode == 'blocked'", 'value="legacy"'], 'admin form');
requireMarkers(source.editorInit, ['blog-block-media-open', 'blog-block-media-selected', 'hidden.value', 'form.addEventListener'], 'editor/form binding');
requireMarkers(source.editor, ['setSelectedAttribute', 'view\\/image\\/blog', 'blog-block-media-open'], 'editor/image validation');
requireMarkers(source.media, ['blog-block-media-open', 'blog-block-media-selected', 'blockEditorMediaTarget'], 'media picker handoff');
requireMarkers(source.document, ['view/image/blog/', 'isSafeImagePath', 'deserializeDocument'], 'document validator');
requireMarkers(source.renderer, ['renderJson', 'htmlspecialchars', 'ENT_QUOTES', 'ENT_SUBSTITUTE'], 'allowlist renderer');
requireMarkers(source.catalogModel, ['blog_post_content', 'information_schema'], 'catalog sidecar read');
requireMarkers(source.catalogController, ['BlockRenderer', 'renderJson'], 'public rendering');
requireMarkers(source.catalogTemplate, ["content_mode == 'blocks'", 'block_html|raw', 'description|raw'], 'catalog fallback branches');
const localeFiles = ['dev/admin/language/pt-br/blog/blog.php', 'dev/admin/language/en-us/blog/blog.php', 'dev/admin/language/es-eu/blog/blog.php'];
for (const rel of localeFiles) {
  const text = read(root, rel);
  for (const key of ['text_block_editor_intro','text_block_conversion_note','text_block_review_required','text_block_invalid','error_block_schema_unavailable','error_block_document_invalid','error_block_editor_unavailable']) {
    need(text.includes(`$_['${key}']`), `${rel}: missing translation ${key}`);
  }
}
let contract, fixtures;
try { contract = JSON.parse(fs.readFileSync(path.join(pkg, 'docs/F9-17-Contrato-E2E-Blog-Blocos.json'), 'utf8')); }
catch (e) { errors.push(`contract JSON: ${e.message}`); }
try { fixtures = JSON.parse(fs.readFileSync(path.join(pkg, 'tests/fixtures/blog_block_workflows.json'), 'utf8')); }
catch (e) { errors.push(`workflow fixtures JSON: ${e.message}`); }
need(contract?.task === 'F9-17', 'contract task must be F9-17');
need(contract?.base_head === '1e3f15873436accbe21003c41720054eaeb0b09a', 'contract HEAD must match verified F9-16 base');
need(contract?.safety?.migration_f9_12_assumed_applied === false, 'contract must not presume F9-12 migration');
need(contract?.safety?.database_accessed === false && contract?.safety?.php_sql_browser_app_tests_executed === false, 'contract must disclose no runtime/database tests');
need(Array.isArray(contract?.workflows) && contract.workflows.length >= 10, 'workflow coverage must include at least ten cases');
if (Array.isArray(fixtures)) {
  const ids = new Set();
  for (const f of fixtures) {
    need(typeof f.id === 'string' && !ids.has(f.id), `fixture ID missing or duplicated: ${f.id}`); ids.add(f.id);
    need(typeof f.workflow === 'string' && f.workflow.startsWith('F917-'), `fixture workflow missing: ${f.id}`);
    need(typeof f.expected_server_result === 'string', `fixture result missing: ${f.id}`);
  }
  need(fixtures.some((f) => f.id === 'invalid-uninitialized-editor'), 'missing editor-init rejection fixture');
  need(fixtures.some((f) => f.id === 'local-image-from-media-library'), 'missing local media fixture');
  need(fixtures.every((f) => !JSON.stringify(f).includes('bd-davidalmeida.sql')), 'fixtures must not reference private dump');
}
if (errors.length) {
  console.error('F9-17 STATIC CONTRACT CHECK: FAIL');
  for (const error of errors) console.error(` - ${error}`);
  process.exit(1);
}
console.log(`F9-17 STATIC CONTRACT CHECK: PASS — ${Object.keys(files).length} source files, ${localeFiles.length} locales and ${fixtures.length} synthetic workflow fixtures inspected. No PHP/SQL/browser/app was executed.`);
