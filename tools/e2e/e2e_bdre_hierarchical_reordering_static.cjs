'use strict';
const fs=require('fs'); const path=require('path'); const root=path.resolve(__dirname,'../..');
let checks=0; function ok(condition,label){checks++; if(!condition) throw new Error('FAIL: '+label); console.log('PASS: '+label);}
const js=fs.readFileSync(path.join(root,'dev/admin/view/js/blog-block-editor.js'),'utf8');
const controller=fs.readFileSync(path.join(root,'dev/admin/controller/blog/blog.php'),'utf8');
const fixture=JSON.parse(fs.readFileSync(path.join(root,'tests/fixtures/hierarchy_move.json'),'utf8')).document;
ok(fixture.format_version===2,'hierarchy fixture uses document v2');
ok(/function moveBlockAcrossHierarchy\(document, nodeId, direction\)/.test(js),'pure hierarchy move helper is present');
ok(/!validateDocument\(document\)\.valid/.test(js),'original document is validated before move');
ok(/!validateDocument\(next\)\.valid/.test(js),'full destination document is validated before commit');
ok(/core\/table-row','core\/table-cell/.test(js),'table structure block types are excluded');
ok(/this\.commit\(result\.document, result\.focusId\)/.test(js),'successful move commits as a single history transaction');
ok(/this\.focusNode\(result\.focusId\)/.test(js),'focus is restored by preserved block ID');
ok(/this\.button\(this\.strings\.indentBlock, 'indent'/.test(js)&&/this\.button\(this\.strings\.outdentBlock, 'outdent'/.test(js),'inspector controls expose explicit indent and outdent actions');
ok(/indent\.disabled = !this\.canIndent/.test(js)&&/outdent\.disabled = !this\.canOutdent/.test(js),'impossible moves are disabled in the interface');
ok(/requiredParents/.test(js)&&/found\.nodes\.length <= 1/.test(js),'required non-empty containers are protected');
ok(/hierarchyMoveUnavailable/.test(js),'failed movement announces localized accessible status');
ok(/'indentBlock' => \$data\['text_block_indent'\]/.test(controller),'controller passes hierarchy action strings');
for(const locale of ['pt-br','en-us','es-eu']){const f=path.join(root,'dev/admin/language',locale,'blog/blog.php');const src=fs.readFileSync(f,'utf8');for(const k of ['text_block_indent','text_block_outdent','text_block_status_indented','text_block_status_outdented','text_block_hierarchy_move_unavailable'])ok(src.includes("$_['"+k+"']"),locale+' contains '+k);}
console.log(`STATIC_ONLY: ${checks} PASS; no browser, PHP runtime, database, staging, deploy or migrations.`);
