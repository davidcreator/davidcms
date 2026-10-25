#!/usr/bin/env node
'use strict';
// Static source/fixture contract only. Never loads PHP, SQL, Twig, browser, or the CMS.
const fs = require('node:fs');
const path = require('node:path');
const root = process.argv[2] ? path.resolve(process.argv[2]) : path.resolve(__dirname, '../..');
const errors = [];
function read(rel) { const file = path.join(root, rel); if (!fs.existsSync(file)) { errors.push(`missing ${rel}`); return ''; } return fs.readFileSync(file, 'utf8'); }
function need(ok, msg) { if (!ok) errors.push(msg); }
const doc = read('dev/system/library/blog/block_document.php');
const renderer = read('dev/system/library/blog/block_renderer.php');
const editor = read('dev/admin/view/js/blog-block-editor.js');
const init = read('dev/admin/view/js/blog-block-editor-init.js');
const controller = read('dev/admin/controller/blog/blog.php');
const twig = read('dev/admin/view/template/blog/blog_form.twig');
need(doc.includes('RICH_TEXT_FORMAT_VERSION = 2') && doc.includes('FORMAT_VERSION = 1'), 'PHP document validator must preserve v1 and support v2');
need(doc.includes('validateRuns') && doc.includes('canonicalRuns') && doc.includes('inline_mark_not_allowlisted'), 'server inline-run validation/canonicalization markers missing');
need(doc.includes('isSafeActionUrl($run[\'href\'])') && doc.includes('core/button'), 'inline links need server allowlist and button nesting guard');
need(renderer.includes('renderInlineRuns') && renderer.includes('htmlspecialchars') && renderer.includes('ENT_QUOTES'), 'renderer must render inline AST with escaping');
for (const tag of ['strong','em','u','s','code']) need(renderer.includes(`'${tag}'`), `renderer fixed inline tag missing ${tag}`);
need(renderer.includes("'<a href=\"") && renderer.includes('rel=\"nofollow noopener noreferrer\"'), 'renderer fixed safe anchor missing');
need(editor.includes('upgradeDocument') && editor.includes('format_version = 2'), 'editor must upgrade v1 to v2 in memory');
need(editor.includes('contentEditable = \'true\'') && editor.includes('normalizeDomInline') && editor.includes('buildInlineDom'), 'inline editor/DOM reconstruction missing');
need(!editor.includes('execCommand') && !/\.innerHTML\s*=/.test(editor), 'editor must not use execCommand or persist/render data via innerHTML');
for (const mark of ['bold','italic','underline','strike','code']) need(editor.includes(`'${mark}'`), `editor mark missing ${mark}`);
need(init.includes('editorStrings') && init.includes('editor.serialize()'), 'localized initialization/serialization binding missing');
need(twig.includes('data-editor-strings') && twig.includes('data-document'), 'Twig must pass escaped initial document and strings');
need(controller.includes('text_inline_bold') && controller.includes('text_inline_invalid_link') && controller.includes('block_editor_strings'), 'controller translations/config missing');
const locales=['pt-br','en-us','es-eu'];
for (const locale of locales) {
 const text=read(`dev/admin/language/${locale}/blog/blog.php`);
 for (const key of ['text_inline_bold','text_inline_italic','text_inline_link','text_inline_invalid_link','text_inline_editor_label']) need(text.includes(`$_['${key}']`), `${locale}: missing ${key}`);
}
let fixtures;
try { fixtures=JSON.parse(fs.readFileSync(path.join(root,'tests/fixtures/rich_text_v2.json'),'utf8')); }
catch(e){ errors.push(`fixture JSON: ${e.message}`); }
need(Array.isArray(fixtures) && fixtures.length >= 10, 'rich-text fixture matrix needs positive and negative cases');
if (Array.isArray(fixtures)) {
 const ids=new Set();
 for(const f of fixtures){ need(typeof f.id==='string'&&!ids.has(f.id),`fixture ID missing/duplicate ${f.id}`);ids.add(f.id);need(f.document&&typeof f.document.format_version==='number',`fixture document malformed ${f.id}`); }
 need(fixtures.some(f=>f.id==='v2-unsafe-javascript-link'),'missing unsafe link fixture');
 need(fixtures.some(f=>f.id==='v2-text-html-is-escaped'),'missing escaped-text fixture');
}
if(errors.length){console.error('F9-22 STATIC CONTRACT CHECK: FAIL');for(const e of errors)console.error(` - ${e}`);process.exit(1);}
console.log(`F9-22 STATIC CONTRACT CHECK: PASS — v1/v2 source markers, ${locales.length} locales and ${fixtures.length} synthetic fixtures inspected. No PHP/SQL/browser/app was executed.`);
