#!/usr/bin/env node
'use strict';
const fs=require('fs');const path=require('path');
const root=path.resolve(process.argv[2]||path.join(__dirname,'../..'));
const read=(p)=>fs.readFileSync(path.join(root,p),'utf8');
const errors=[];const need=(ok,msg)=>{if(!ok)errors.push(msg)};
const editor=read('dev/admin/view/js/blog-block-editor.js');
const init=read('dev/admin/view/js/blog-block-editor-init.js');
const css=read('dev/admin/view/css/blog-block-editor.css');
const php=read('dev/system/library/blog/block_document.php');
const controller=read('dev/admin/controller/blog/blog.php');
for(const marker of ['this.updateTextNode(id, field)','recordTextEditSnapshot()','pushHistory(stack, side, snapshot)','8388608','utf8Length(json) > 4194304']) need(editor.includes(marker),`editor missing ${marker}`);
need(!/setAttribute\('role',\s*'list(item)?'\)/.test(editor),'native inserter buttons must not have overridden list roles');
for(const marker of ["setAttribute('aria-controls','rb-inserter-results-'", "setAttribute('aria-expanded', String(this.inserterOpen))", "setAttribute('role','status')",'first.dataset.pattern','event.key === \'Escape\'','event.altKey']) need(editor.includes(marker),`keyboard/a11y behavior missing ${marker}`);
for(const marker of ['beforeunload','instance.serialize()','aria-invalid','documentTooLarge']) need(init.includes(marker),`form/accessibility contract missing ${marker}`);
for(const marker of ['outline:3px solid #174ea6','color:#5e6b7f','@media(max-width:900px)','@media(max-width:560px)','prefers-reduced-motion:reduce']) need(css.includes(marker),`CSS polish missing ${marker}`);
need(!css.includes('#7b8798'),'low-contrast placeholder color remains');
function luminance(hex){const c=hex.match(/[a-f0-9]{2}/gi).map(x=>parseInt(x,16)/255).map(x=>x<=0.04045?x/12.92:Math.pow((x+0.055)/1.055,2.4));return 0.2126*c[0]+0.7152*c[1]+0.0722*c[2];}
function contrast(a,b){const x=luminance(a),y=luminance(b);return (Math.max(x,y)+0.05)/(Math.min(x,y)+0.05);}
need(contrast('5e6b7f','ffffff')>=4.5,'muted/placeholder text contrast token below 4.5:1 on white');
need(contrast('174ea6','ffffff')>=3,'focus outline contrast token below 3:1 on white');
for(const [marker,label] of [['MAX_INLINE_CONTENT_BYTES = 262144','inline content limit'],['MAX_BLOCKS = 1000','root block limit'],['MAX_TOTAL_NODES = 2000','total node limit'],['MAX_DEPTH = 16','depth limit'],['MAX_MEDIA_TEXT_BYTES = 4096','media text limit']]) need(php.includes(marker),`server ${label} changed or missing`);
need(controller.includes("'text_block_initial_invalid'")&&controller.includes("'text_block_document_too_large'"),'controller misses localized validation messages');
const localeFiles=['pt-br','en-us','es-eu'].map(locale=>[locale,read(`dev/admin/language/${locale}/blog/blog.php`)]);
const localeKeys=localeFiles.map(([locale,text])=>[locale,[...text.matchAll(/\$_\[['"]([^'"]+)['"]\]/g)].map(m=>m[1]).sort()]);
const keySignature=JSON.stringify(localeKeys[0][1]);for(const [locale,keys] of localeKeys.slice(1))need(JSON.stringify(keys)===keySignature,`${locale} language-key set differs`);
for(const [locale,text] of localeFiles)for(const key of ['text_block_initial_invalid','text_block_document_too_large','text_block_outline_label'])need(text.includes(`$_['${key}']`),`${locale} missing ${key}`);
let limits={};try{limits=JSON.parse(read('tests/fixtures/editor_limits_f926.json'));}catch(e){errors.push(`limit fixture parse failed: ${e.message}`)}
const expected={inline_utf8_bytes:[262144,262145],root_blocks:[1000,1001],total_nodes:[2000,2001],depth_zero_indexed:[16,17],document_json_utf8_bytes:[4194304,4194305],media_text_utf8_bytes:[4096,4097],outline_label_utf8_bytes:[160,161]};
for(const item of limits.limits||[])need(JSON.stringify([item.accepted,item.rejected])===JSON.stringify(expected[item.name]),`fixture boundary mismatch ${item.name}`);
need((limits.limits||[]).length===Object.keys(expected).length,'limit fixture coverage incomplete');
need(Buffer.byteLength('Aé😀','utf8')===7,'unicode byte-length reference case changed');
let table={};try{table=JSON.parse(read('docs/F9-26-Contrato-A11y-Performance.json'));}catch(e){errors.push(`contract parse failed: ${e.message}`)}
need(table.task==='F9-26'&&table.verification&&table.verification.php_sql_browser_app_e2e_runtime===false,'contract status is invalid');
if(errors.length){console.error('F9-26 STATIC QUALITY CHECK: FAIL\n- '+errors.join('\n- '));process.exit(1)}
console.log(`F9-26 STATIC QUALITY CHECK: PASS — keyboard/a11y/performance markers, ${localeKeys[0][1].length} keys aligned × 3 locales, ${Object.keys(expected).length} synthetic boundary descriptors. No PHP/SQL/browser/app executed.`);
