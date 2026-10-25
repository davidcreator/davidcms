'use strict';
const fs=require('fs');const path=require('path');global.window={};global.Element=class Element{};
require('../dev/admin/view/js/blog-block-editor.js');const api=window.ReamurBlogBlockEditor;
const fixture=JSON.parse(fs.readFileSync(path.join(__dirname,'fixtures/core_details.json'),'utf8')).document;
let checks=0;function check(v,label){checks++;if(!v)throw new Error('FAIL: '+label);}
function clone(v){return JSON.parse(JSON.stringify(v));}
function doc(node,version=2){return {format_version:version,blocks:[node]};}
check(api.validateDocument(fixture).valid,'details fixture is valid v2');
check(fixture.blocks.every(n=>n.type==='core/details'&&n.children.length===0),'details is a rich-text leaf block');
check(fixture.blocks.every(n=>typeof n.attributes.summary==='string'&&n.attributes.summary.trim()),'fixture has accessible summary labels');
let node=clone(fixture.blocks[0]);node.attributes.summary='';check(!api.validateDocument(doc(node)).valid,'empty summary rejected');
node=clone(fixture.blocks[0]);delete node.attributes.summary;check(!api.validateDocument(doc(node)).valid,'missing summary rejected');
node=clone(fixture.blocks[0]);node.attributes.summary='x'.repeat(513);check(!api.validateDocument(doc(node)).valid,'summary above byte limit rejected');
node=clone(fixture.blocks[0]);node.attributes.open=true;check(!api.validateDocument(doc(node)).valid,'unallowlisted attributes rejected');
node=clone(fixture.blocks[0]);node.children=[{id:'nested',type:'core/paragraph',attributes:{},children:[],content:[{text:'Nested',marks:[],href:null}]}];check(!api.validateDocument(doc(node)).valid,'child blocks rejected');
node=clone(fixture.blocks[0]);node.content='Legacy body';check(!api.validateDocument(doc(node,1)).valid,'details cannot be stored in document v1');
node=clone(fixture.blocks[0]);node.attributes.summary='<img src=x onerror=alert(1)>';check(api.validateDocument(doc(node)).valid,'markup-looking summary is treated as plain text');
node=clone(fixture.blocks[0]);node.content=[{text:'<script>alert(1)</script>',marks:[],href:null}];check(api.validateDocument(doc(node)).valid,'markup-looking body remains plain rich text');
check(new Set(fixture.blocks.map(n=>n.id)).size===fixture.blocks.length,'fixture IDs are unique');
console.log(`Details checker: ${checks} PASS`);
