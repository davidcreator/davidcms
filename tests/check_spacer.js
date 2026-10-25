'use strict';
const fs=require('fs'),path=require('path');global.window={};global.Element=class Element{};require('../dev/admin/view/js/blog-block-editor.js');const api=window.ReamurBlogBlockEditor;
const fixture=JSON.parse(fs.readFileSync(path.join(__dirname,'fixtures/core_spacer.json'),'utf8')).document;let checks=0;function check(v,l){checks++;if(!v)throw new Error('FAIL: '+l);}function clone(v){return JSON.parse(JSON.stringify(v));}function doc(n,version=2){return {format_version:version,blocks:[n]};}
check(api.validateDocument(fixture).valid,'fixture contains valid fixed-height spacer presets');
check(fixture.blocks.map(n=>n.attributes.height).join(',')==='16,32,48,64,96','fixture covers the closed height scale');
for(const h of [16,32,48,64,96]){const n=clone(fixture.blocks[0]);n.attributes.height=h;check(api.validateDocument(doc(n)).valid,'accepts fixed height '+h);}
let n=clone(fixture.blocks[0]);n.attributes.height=17;check(!api.validateDocument(doc(n)).valid,'rejects arbitrary height');
n=clone(fixture.blocks[0]);n.attributes.height='32';check(!api.validateDocument(doc(n)).valid,'requires numeric integer height');
n=clone(fixture.blocks[0]);delete n.attributes.height;check(!api.validateDocument(doc(n)).valid,'requires height');
n=clone(fixture.blocks[0]);n.attributes.style='height:999px';check(!api.validateDocument(doc(n)).valid,'rejects unallowlisted inline styling');
n=clone(fixture.blocks[0]);n.children=[{id:'child',type:'core/paragraph',attributes:{},children:[],content:[{text:'x',marks:[],href:null}]}];check(!api.validateDocument(doc(n)).valid,'rejects child blocks');
n=clone(fixture.blocks[0]);n.content='not empty';check(!api.validateDocument(doc(n)).valid,'requires empty content');
n=clone(fixture.blocks[0]);check(!api.validateDocument(doc(n,1)).valid,'spacer is v2-only');
check(new Set(fixture.blocks.map(n=>n.id)).size===fixture.blocks.length,'fixture IDs are unique');
console.log(`Spacer checker: ${checks} PASS`);
