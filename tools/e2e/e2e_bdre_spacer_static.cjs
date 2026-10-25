'use strict';
const fs=require('fs'),path=require('path'),root=path.resolve(__dirname,'../..');let checks=0;function ok(v,l){checks++;if(!v)throw new Error('FAIL: '+l);console.log('PASS: '+l);}
const js=fs.readFileSync(path.join(root,'dev/admin/view/js/blog-block-editor.js'),'utf8'),php=fs.readFileSync(path.join(root,'dev/system/library/blog/block_document.php'),'utf8'),renderer=fs.readFileSync(path.join(root,'dev/system/library/blog/block_renderer.php'),'utf8'),ctl=fs.readFileSync(path.join(root,'dev/admin/controller/blog/blog.php'),'utf8'),css=fs.readFileSync(path.join(root,'dev/catalog/view/css/blog-blocks.css'),'utf8'),adminCss=fs.readFileSync(path.join(root,'dev/admin/view/css/blog-block-editor.css'),'utf8');
const fixture=JSON.parse(fs.readFileSync(path.join(root,'tests/fixtures/core_spacer.json'),'utf8')).document;
ok(fixture.format_version===2&&fixture.blocks.length===5,'v2 fixture covers fixed spacer presets');
ok(/\['core\/spacer', 'Espaçador'\]/.test(js)&&/core\/spacer':'layout'/.test(js),'spacer is registered in layout inserter category');
ok(/'core\/spacer':\['height'\]/.test(js)&&/spacer_invalid/.test(js),'client validation allowlists height and closes enum');
ok(/\[16,32,48,64,96\]\.includes\(a\.height\)/.test(js),'client allows only five fixed numeric heights');
ok(/spacer_requires_v2/.test(js),'client rejects spacer in legacy v1 documents');
ok(/if \(type === 'core\/spacer'\) return this\.node\(type, \{ height:32 \}/.test(js),'spacer default is in the fixed scale');
ok(/this\.strings\.spacer\.heightLabel/.test(js)&&/Object\.entries\(this\.strings\.spacer\.heights\)/.test(js),'inspector exposes localized fixed-height choices');
ok(/card\.dataset\.spacerHeight/.test(js)&&/rb-spacer-preview/.test(js),'admin preview reflects selected preset');
ok(/'core\/spacer'/.test(php)&&/\['height'\]/.test(php)&&/spacer_height_invalid/.test(php),'server validation checks block type and allowed height');
ok(/spacer_requires_v2/.test(php),'server rejects spacer in document v1');
ok(/case 'core\/spacer':/.test(renderer)&&/aria-hidden="true"/.test(renderer)&&/rms-block-spacer--/.test(renderer),'public renderer outputs decorative semantic spacer with fixed class');
ok(/\[16,32,48,64,96\]/.test(php),'server accepts only fixed integer height presets');
ok(/rms-block-spacer--16/.test(css)&&/rms-block-spacer--96/.test(css),'public CSS maps preset classes to fixed heights');
ok(/data-spacer-height="64"/.test(adminCss),'admin CSS maps preview presets without inline styles');
ok(/'core\/spacer' => \$data\['text_block_type_spacer'\]/.test(ctl)&&/'spacer' => \['heightLabel' =>/.test(ctl),'controller wires localized labels');
for(const locale of ['pt-br','en-us','es-eu']){const text=fs.readFileSync(path.join(root,'dev/admin/language',locale,'blog/blog.php'),'utf8');for(const k of ['text_block_type_spacer','text_block_spacer_height','text_block_spacer_16','text_block_spacer_32','text_block_spacer_48','text_block_spacer_64','text_block_spacer_96'])ok(text.includes("$_['"+k+"']"),locale+' includes '+k);}
console.log(`STATIC_ONLY: ${checks} PASS; no PHP runtime, browser, database, staging, deploy or migrations.`);
