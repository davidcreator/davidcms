#!/usr/bin/env node
'use strict';
const fs=require('fs'); const path=require('path');
const root=path.resolve(process.argv[2]||path.join(__dirname,'../..'));
const read=(p)=>fs.readFileSync(path.join(root,p),'utf8');
const errors=[]; const need=(ok,msg)=>{if(!ok)errors.push(msg)};
const php=read('dev/system/library/blog/block_document.php');
const js=read('dev/admin/view/js/blog-block-editor.js');
const init=read('dev/admin/view/js/blog-block-editor-init.js');
const controller=read('dev/admin/controller/blog/blog.php');
for(const marker of ["['outline_label']",'outline_label_invalid','group_attributes_invalid']) need(php.includes(marker),`PHP contract missing ${marker}`);
for(const marker of ["['outline_label']",'outline_label_invalid','outlineLabel','data-action']) need(js.includes(marker),`editor contract missing ${marker}`);
for(const marker of ['beforeunload','allEditorsValid','changeState.bypass','changeState.dirty']) need(init.includes(marker),`unsaved-change guard missing ${marker}`);
need(controller.includes("'text_block_outline_label'"),'controller misses outline label language key');
need(controller.includes("'outlineLabel' => $data['text_block_outline_label']"),'controller does not expose outlineLabel editor string');
for(const forbidden of ['localStorage','sessionStorage','fetch(','XMLHttpRequest']) need(!init.includes(forbidden)&&!js.includes(forbidden),`unexpected persistence/network marker ${forbidden}`);
for(const locale of ['pt-br','en-us','es-eu']) need(read(`dev/admin/language/${locale}/blog/blog.php`).includes("$_['text_block_outline_label']"),`${locale} missing outline label`);
let fixtures=[];try{fixtures=JSON.parse(read('tests/fixtures/block_patterns_f925.json'));}catch(e){errors.push(`fixture JSON parse failed: ${e.message}`)}
need(Array.isArray(fixtures)&&fixtures.length===4,'expected four synthetic fixtures');
if(Array.isArray(fixtures)){
 const ids=new Set(); for(const f of fixtures){need(typeof f.id==='string'&&!ids.has(f.id),`fixture ID missing/duplicate ${f.id}`);ids.add(f.id);}
 const tooLong=fixtures.find(f=>f.id==='outline-label-too-long-rejected');
 need(!!tooLong&&tooLong.document.blocks[0].attributes.outline_label.length>160,'oversize label fixture does not exceed byte/code-unit limit');
}
let contract={};try{contract=JSON.parse(read('docs/F9-25-Contrato-Editor-Editorial.json'));}catch(e){errors.push(`contract JSON parse failed: ${e.message}`)}
need(contract.patterns_persistence&&contract.patterns_persistence.status==='PROPOSAL_PENDING','persistent patterns must remain explicitly pending');
need(contract.catalog_context_preview&&contract.catalog_context_preview.status==='DESIGN_GATE_PENDING','catalog preview must remain explicitly gated');
if(errors.length){console.error('F9-25 STATIC CONTRACT CHECK: FAIL\n- '+errors.join('\n- '));process.exit(1)}
console.log(`F9-25 STATIC CONTRACT CHECK: PASS — outline/dirty markers, 1 key × 3 locales, ${fixtures.length} synthetic fixtures. No PHP/SQL/browser/app executed.`);
