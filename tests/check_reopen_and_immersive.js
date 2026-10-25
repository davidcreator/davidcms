'use strict';
const fs = require('fs');
const path = require('path');
const vm = require('vm');
const root = path.resolve(__dirname, '..');
const js = fs.readFileSync(path.join(root, 'dev/admin/view/js/blog-block-editor.js'), 'utf8');
const php = fs.readFileSync(path.join(root, 'dev/system/library/blog/block_document.php'), 'utf8');
const page = fs.readFileSync(path.join(root, 'dev/admin/controller/cms/page.php'), 'utf8');
const css = fs.readFileSync(path.join(root, 'dev/admin/view/css/blog-block-editor.css'), 'utf8');
const context = { window:{location:{origin:'http://localhost'},document:{documentElement:{lang:'pt-BR'}}}, URL, console };
context.window.window = context.window; vm.createContext(context); vm.runInContext(js, context);
const run = text => [{text,marks:[],href:null}];
const phpSerializedShape = JSON.parse('{"format_version":2,"blocks":[{"id":"paragraph-1","type":"core/paragraph","attributes":{},"children":[],"content":[{"text":"Teste salvo e reaberto","marks":[],"href":null}]}]}');
const formerBrokenShape = JSON.parse('{"format_version":2,"blocks":[{"id":"paragraph-1","type":"core/paragraph","attributes":[],"children":[],"content":[{"text":"Teste salvo e reaberto","marks":[],"href":null}]}]}');
const good = context.window.ReamurBlogBlockEditor.validateDocument(phpSerializedShape);
const bad = context.window.ReamurBlogBlockEditor.validateDocument(formerBrokenShape);
const checks = [
 ['PHP serializer preserves attributes as JSON object', /'attributes'\s*=>\s*\(object\)\$attributes/.test(php)],
 ['Canonical v2 node with empty attributes reopens as valid', good.valid],
 ['Existing stored documents are canonicalized in memory before editor mount', /blockJson = \(new \\Reamur\\System\\Library\\Blog\\BlockDocument\(\)\)->serializeDocument\(\$decoded\['document'\]\)/.test(page)],
 ['Regression fixture reproduces the former node_fields_invalid failure', !bad.valid && bad.errors.includes('node_fields_invalid')],
 ['Fullscreen mode has an explicit accessible dialog label', /aria-modal','true'/.test(js) && /fullscreenDialog/.test(js)],
 ['Escape closes fullscreen and restores focus', /event\.key === 'Escape'.*setFullscreen\(false\)/s.test(js) && /returnFocus\.focus|target\.focus/.test(js)],
 ['Keyboard focus stays trapped within the active editor', /event\.key !== 'Tab'/.test(js) && /focusable\[0\]/.test(js) && /focusable\[focusable\.length - 1\]/.test(js)],
 ['Fullscreen editing locks the background page scroll', /body\.classList\.add\('rb-bdre-fullscreen-open'\)/.test(js) && /body\.rb-bdre-fullscreen-open/.test(css)],
 ['Fullscreen editor occupies the viewport with a separate canvas layout', /\.rb-editor\.is-fullscreen\{position:fixed;inset:0/.test(css) && /\.rb-editor\.is-fullscreen \.rb-canvas/.test(css)],
 ['Fullscreen control and return-to-form action are present', /fullscreenEnter/.test(js) && /fullscreenExit/.test(js) && /rb-fullscreen-done/.test(js)],
 ['Fullscreen labels support Portuguese, English, and Spanish', /startsWith\('pt'\)/.test(js) && /startsWith\('es'\)/.test(js) && /Open fullscreen editor/.test(js)],
];
let failed=0; for (const [name,ok] of checks) { console.log(`${ok?'PASS':'FAIL'} ${name}`); if (!ok) failed++; }
process.exitCode = failed ? 1 : 0;
