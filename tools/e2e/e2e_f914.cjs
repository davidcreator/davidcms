#!/usr/bin/env node
'use strict';
// Static-only UI contract check. Does not execute browser/app code.
const fs = require('node:fs');
const path = require('node:path');
const root = path.resolve(__dirname, '../..');
const errors = [];
function read(rel) { const p = path.join(root, rel); if (!fs.existsSync(p)) { errors.push(`missing: ${rel}`); return ''; } return fs.readFileSync(p, 'utf8'); }
function need(ok, msg) { if (!ok) errors.push(msg); }
let contract;
try { contract = JSON.parse(read('docs/F9-14-Contrato-Editor-MVP.json')); } catch (e) { errors.push(`contract JSON invalid: ${e.message}`); }
const js = read('dev/admin/view/js/blog-block-editor.js');
const css = read('dev/admin/view/css/blog-block-editor.css');
const preview = read('tools/preview/f914-editor-mvp.html');
const docs = read('docs/F9-14-Editor-de-Blocos-MVP.md');
for (const marker of ['rb-inserter','rb-inspector','undo','redo','dragstart','data-preview','core/columns','core/video_embed']) need(js.includes(marker), `editor capability missing: ${marker}`);
need(js.includes('createElement') && js.includes('textContent'), 'DOM must be constructed without injecting editor values as HTML');
need(!/\.innerHTML\s*=|\beval\s*\(|\bfetch\s*\(|XMLHttpRequest|\$this->db|DB_PREFIX/i.test(js), 'no HTML injection, network, or DB code allowed');
need(/unknown|invalid initial document/i.test(js) || /initialError/.test(js), 'invalid initial data must have a guarded state');
need(js.includes('document_requires_review') && js.includes('this.locked'), 'invalid initial document must block serialization/editing');
need(/@media\(max-width:900px\)/.test(css) && /rb-columns-3/.test(css), 'responsive layout/column styles missing');
need(contract?.task === 'F9-14', 'contract must identify F9-14');
need(contract?.component?.persistence === false && contract?.component?.form_submission_binding === false, 'component must remain non-persistent and unbound');
need(contract?.validation_policy?.browser_or_app_tests_executed === false, 'contract must disclose no browser/app tests');
need(!/<script[^>]+src=|<link[^>]+href=/i.test(preview), 'preview must be self-contained (no external scripts/styles)');
need(preview.includes('nada será enviado ou salvo') && preview.includes('ReamurBlogBlockEditor.mount'), 'preview must show in-memory disclaimer and mount component');
need(/not loaded|não carregado|não está ligado/i.test(docs), 'docs must state production form is not wired');
if (errors.length) { console.error('F9-14 STATIC FAIL'); for (const e of errors) console.error(` - ${e}`); process.exit(1); }
console.log('F9-14 STATIC PASS — contract/assets/preview checked; no browser, app, PHP, SQL, or DB execution.');
