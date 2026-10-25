'use strict';
const fs=require('fs'),path=require('path'),root=path.resolve(__dirname,'../..');let checks=0;function ok(v,l){checks++;if(!v)throw new Error('FAIL: '+l);console.log('PASS: '+l);}
const js=fs.readFileSync(path.join(root,'dev/admin/view/js/blog-block-editor.js'),'utf8'),php=fs.readFileSync(path.join(root,'dev/system/library/blog/block_document.php'),'utf8'),renderer=fs.readFileSync(path.join(root,'dev/system/library/blog/block_renderer.php'),'utf8'),ctl=fs.readFileSync(path.join(root,'dev/admin/controller/blog/blog.php'),'utf8'),css=fs.readFileSync(path.join(root,'dev/catalog/view/css/blog-blocks.css'),'utf8'),adminCss=fs.readFileSync(path.join(root,'dev/admin/view/css/blog-block-editor.css'),'utf8');
const fixture=JSON.parse(fs.readFileSync(path.join(root,'tests/fixtures/core_details.json'),'utf8')).document;
ok(fixture.format_version===2&&fixture.blocks.length===2,'v2 fixture contains details blocks');
ok(/\['core\/details', 'Detalhes'\]/.test(js)&&/core\/details':'content'/.test(js),'details block is registered in editor content inserter');
ok(/'core\/details':\['summary'\]/.test(js)&&/details_invalid/.test(js),'client allowlists summary and rejects invalid leaf nodes');
ok(/details_requires_v2/.test(js)&&/version === 1 && node\.type === 'core\/details'/.test(js),'client rejects details in legacy v1 documents');
ok(/if \(type === 'core\/details'\) return this\.node/.test(js)&&/this\.strings\.details\.defaultText/.test(js),'new details uses localized safe default content');
ok(/this\.strings\.details\.summaryLabel/.test(js)&&/key === 'summary'\) input\.maxLength = 512/.test(js),'inspector edits localized summary with bounded length');
ok(/this\.el\('details','rb-details-admin-preview'\)/.test(js)&&/details\.open = true/.test(js),'admin preview uses native disclosure and stays open for editing');
ok(/'core\/details'/.test(php)&&/\['summary'\]/.test(php)&&/details_summary_invalid/.test(php),'server validator enforces summary and attributes');
ok(/details_requires_v2/.test(php)&&/core\/details/.test(php),'server validator rejects legacy v1 details blocks');
ok(/case 'core\/details':/.test(renderer)&&/<details class=/.test(renderer)&&/<summary>/.test(renderer),'public renderer uses native details and summary elements');
ok(/\$this->escape\(\(string\)\$attributes\['summary'\]\)/.test(renderer),'summary label is HTML-escaped');
ok(/renderInlineRuns\(\$node\['content'\]\)/.test(renderer),'body uses safe rich-text renderer');
ok(/rms-block-details>summary:focus-visible/.test(css)&&/cursor:pointer/.test(css),'public summary is keyboard operable with visible focus');
ok(/rb-details-admin-preview\[open\]/.test(adminCss),'admin expanded preview styles editor body');
ok(/'core\/details' => \$data\['text_block_type_details'\]/.test(ctl)&&/'details' => \['summaryLabel' =>/.test(ctl),'controller wires localized labels and editor defaults');
for(const locale of ['pt-br','en-us','es-eu']){const text=fs.readFileSync(path.join(root,'dev/admin/language',locale,'blog/blog.php'),'utf8');for(const k of ['text_block_type_details','text_block_details_summary_label','text_block_details_summary_default','text_block_details_default'])ok(text.includes("$_['"+k+"']"),locale+' includes '+k);}
console.log(`STATIC_ONLY: ${checks} PASS; no PHP runtime, browser, database, staging, deploy or migrations.`);
