#!/usr/bin/env node
'use strict';
// Static contract/source/fixture checks only. Does not execute PHP, SQL, Twig, or browser code.
const fs = require('node:fs');
const path = require('node:path');
const root = path.resolve(__dirname, '../..');
const errors = [];
function read(rel) { const p = path.join(root, rel); if (!fs.existsSync(p)) { errors.push(`missing: ${rel}`); return ''; } return fs.readFileSync(p, 'utf8'); }
function need(ok, msg) { if (!ok) errors.push(msg); }
const renderer = read('dev/system/library/blog/block_renderer.php');
const validator = read('dev/system/library/blog/block_document.php');
const model = read('dev/catalog/model/blog/blog.php');
const controller = read('dev/catalog/controller/blog/blog.php');
const twig = read('dev/catalog/view/template/blog/blog_info.twig');
const css = read('dev/catalog/view/css/blog-blocks.css');
const preview = read('tools/preview/f915-renderer-preview.html');
const docs = read('docs/F9-15-Renderer-Publico-Blog-Blocos.md');
let contract, fixtures;
try { contract = JSON.parse(read('docs/F9-15-Contrato-Renderer-Publico.json')); } catch (e) { errors.push(`contract JSON: ${e.message}`); }
try { fixtures = JSON.parse(read('tests/fixtures/renderer.json')); } catch (e) { errors.push(`fixture JSON: ${e.message}`); }
for (const marker of ['renderJson','validateDocument','htmlspecialchars','ENT_QUOTES','ENT_SUBSTITUTE','ENT_HTML5','youtube-nocookie.com','player.vimeo.com','sandbox=']) need(renderer.includes(marker), `renderer safety marker missing: ${marker}`);
for (const type of ['core/paragraph','core/heading','core/list','core/image','core/quote','core/video_embed','core/button','core/separator','core/group','core/columns','core/column']) need(validator.includes(type), `validator block type missing: ${type}`);
need(model.includes('information_schema') && model.includes('blog_post_content') && model.includes('query_error'), 'sidecar detection/graceful query handling missing');
need(!/^\s*(INSERT|UPDATE|DELETE|DROP|ALTER|TRUNCATE)\b/im.test(model), 'model must contain no schema/data mutations');
need(controller.includes('BlockRenderer') && controller.includes('renderJson') && controller.includes('content_valid'), 'catalog controller must gate renderer output');
need(twig.includes("post.content_mode == 'blocks'") && twig.includes('post.block_html|raw') && twig.includes('post.description|raw'), 'template needs explicit trusted-render and legacy fallback branches');
need(/rms-block-columns--2/.test(css) && /rms-block-columns--3/.test(css) && /max-width:\s*767px/.test(css), '2/3 column responsive CSS missing');
need(!/<script[^>]+src=|<link[^>]+href=|<iframe/i.test(preview), 'visual preview must not load external assets or embeds');
need(preview.includes('rms-block-columns--2') && preview.includes('rms-block-columns--3'), 'responsive visual preview must demonstrate both column presets');
need(contract?.task === 'F9-15', 'contract task must be F9-15');
need(contract?.catalog_compatibility?.table_absent_or_no_row?.includes('legacy HTML'), 'contract must retain legacy fallback when table/row absent');
need(contract?.catalog_compatibility?.row_invalid_or_query_error?.includes('fail closed'), 'invalid sidecar must fail closed');
need(contract?.tests?.database_accessed === false && contract?.tests?.php_browser_app_sql_tests_executed === false, 'contract must disclose no database/runtime tests');
need(Array.isArray(fixtures) && fixtures.length >= 10, 'renderer fixture set too small');
if (Array.isArray(fixtures)) {
  const ids = new Set(); let accepted = 0, rejected = 0;
  for (const f of fixtures) {
    need(typeof f.id === 'string' && !ids.has(f.id), `fixture id missing/duplicate: ${f.id}`); ids.add(f.id);
    need(f.document && f.document.format_version === 1 && Array.isArray(f.document.blocks), `document fixture malformed: ${f.id}`);
    need(typeof f.valid === 'boolean', `expected validity missing: ${f.id}`);
    if (f.valid) accepted++; else rejected++;
  }
  need(accepted >= 5 && rejected >= 5, 'fixtures must cover allowlisted output and fail-closed rejects');
}
need(/Não houve execução|Nenhuma execução|não foram executados/i.test(docs), 'docs must disclose PHP/browser/SQL execution was not done');
if (errors.length) { console.error('F9-15 STATIC FAIL'); for (const e of errors) console.error(` - ${e}`); process.exit(1); }
console.log(`F9-15 STATIC PASS — ${fixtures.length} fixtures/contracts/source guards checked; PHP/SQL/Twig/browser not executed.`);
