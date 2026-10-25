'use strict';
const fs=require('fs'),path=require('path'),root=path.resolve(__dirname,'../..');let checks=0;function ok(v,l){checks++;if(!v)throw new Error('FAIL: '+l);console.log('PASS: '+l);}
const js=fs.readFileSync(path.join(root,'dev/admin/view/js/blog-block-editor.js'),'utf8'),php=fs.readFileSync(path.join(root,'dev/system/library/blog/block_document.php'),'utf8'),renderer=fs.readFileSync(path.join(root,'dev/system/library/blog/block_renderer.php'),'utf8'),ctl=fs.readFileSync(path.join(root,'dev/admin/controller/blog/blog.php'),'utf8'),css=fs.readFileSync(path.join(root,'dev/catalog/view/css/blog-blocks.css'),'utf8'),adminCss=fs.readFileSync(path.join(root,'dev/admin/view/css/blog-block-editor.css'),'utf8');
const fixture=JSON.parse(fs.readFileSync(path.join(root,'tests/fixtures/core_code.json'),'utf8')).document;
ok(fixture.format_version===2&&fixture.blocks.every(n=>typeof n.content==='string'),'fixture uses v2 with plain strings');
ok(/\['core\/code', 'Código pré-formatado'\]/.test(js)&&/core\/code':'content'/.test(js),'code block is in inserter content category');
ok(/'core\/code':\[\]/.test(js)&&/code_invalid/.test(js),'client allowlists no attributes and validates code shape');
ok(/node\.type !== 'core\/code' && node\.content !== ''/.test(js),'client accepts non-empty plain-string code in v2');
ok(/utf8Length\(node\.content\) > 262144/.test(js),'client enforces 256 KiB UTF-8 bound');
ok(/if \(version === 1 && node\.type === 'core\/code'\)/.test(js),'client blocks legacy v1 code nodes');
ok(/data-edit-code/.test(js)&&/updateCodeNode\(id, editor\)/.test(js)&&/this\.recordTextEditSnapshot\(\)/.test(js),'textarea editing integrates with bounded undo snapshots');
ok(/code\.value = node\.content/.test(js)&&/code\.maxLength = 131072/.test(js),'admin editor preserves raw text with UTF-16 bound');
ok(/preformatted:\{label:/.test(js)&&/this\.strings\.preformatted\.label/.test(js),'code UI strings do not collide with inline-code mark labels');
ok(/\$types = \[[^;]*'core\/code'/.test(php)&&/\$type === 'core\/code'/.test(php),'server validator recognizes core/code');
ok(/'core\/code' => \[\]/.test(php)&&/code_requires_v2/.test(php),'server enforces no attrs and v2-only format');
ok(/strlen\(\$node\['content'\]\) > self::MAX_INLINE_CONTENT_BYTES/.test(php),'server enforces 256 KiB content limit');
ok(/case 'core\/code':/.test(renderer)&&/\$this->escape\(\(string\)\$node\['content'\]\)/.test(renderer),'public code renderer HTML-escapes plain source');
ok(/<pre class=/.test(renderer)&&/<code>/.test(renderer),'public code output uses pre/code semantics');
ok(/\.rms-block-code/.test(css)&&/white-space:pre/.test(css),'public code CSS preserves preformatted whitespace');
ok(/\.rb-code-editor/.test(adminCss)&&/tab-size:2/.test(adminCss),'admin textarea preserves tabs and whitespace');
ok(/'core\/code' => \$data\['text_block_type_code'\]/.test(ctl)&&/'preformatted' => \['label' =>/.test(ctl),'controller wires localized code strings');
for(const locale of ['pt-br','en-us','es-eu']){const text=fs.readFileSync(path.join(root,'dev/admin/language',locale,'blog/blog.php'),'utf8');for(const k of ['text_block_type_code','text_block_code_label','text_block_code_default','text_block_code_too_large'])ok(text.includes("$_['"+k+"']"),locale+' includes '+k);}
console.log(`STATIC_ONLY: ${checks} PASS; no PHP runtime, browser, database, staging, deploy or migrations.`);
