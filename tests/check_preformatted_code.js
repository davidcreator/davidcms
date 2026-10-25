'use strict';
const fs=require('fs'),path=require('path');global.window={};global.Element=class Element{};require('../dev/admin/view/js/blog-block-editor.js');const api=window.ReamurBlogBlockEditor;
const fixture=JSON.parse(fs.readFileSync(path.join(__dirname,'fixtures/core_code.json'),'utf8')).document;let checks=0;function check(v,l){checks++;if(!v)throw new Error('FAIL: '+l);}function clone(v){return JSON.parse(JSON.stringify(v));}function doc(n,version=2){return {format_version:version,blocks:[n]};}
check(api.validateDocument(fixture).valid,'plain-text code fixture is valid v2');
check(fixture.blocks.every(n=>typeof n.content==='string'&&n.children.length===0),'code content is plain string and leaf-only');
check(fixture.blocks[0].content.includes('\n\t'),'fixture preserves newlines and tabs');
let n=clone(fixture.blocks[0]);n.content='';check(api.validateDocument(doc(n)).valid,'empty code block is permitted while editing');
n=clone(fixture.blocks[0]);n.content='x'.repeat(262145);check(!api.validateDocument(doc(n)).valid,'rejects code above 256 KiB');
n=clone(fixture.blocks[0]);n.content=[{text:'rich run',marks:[],href:null}];check(!api.validateDocument(doc(n)).valid,'rejects rich-run content representation');
n=clone(fixture.blocks[0]);n.attributes.language='php';check(!api.validateDocument(doc(n)).valid,'rejects unallowlisted attributes');
n=clone(fixture.blocks[0]);n.children=[{id:'child',type:'core/paragraph',attributes:{},children:[],content:[{text:'x',marks:[],href:null}]}];check(!api.validateDocument(doc(n)).valid,'rejects nested blocks');
n=clone(fixture.blocks[0]);check(!api.validateDocument(doc(n,1)).valid,'preformatted code is v2-only');
n=clone(fixture.blocks[0]);n.content='<script>alert(1)</script>';check(api.validateDocument(doc(n)).valid,'script-looking source remains plain text');
check(new Set(fixture.blocks.map(n=>n.id)).size===fixture.blocks.length,'fixture IDs are unique');
console.log(`Preformatted code checker: ${checks} PASS`);
