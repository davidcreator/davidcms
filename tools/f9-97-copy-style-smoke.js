'use strict';
const fs=require('fs'),vm=require('vm'),path=require('path');
const source=fs.readFileSync(path.join(__dirname,'../dev/admin/view/js/blog-block-editor.js'),'utf8');
const root={document:{documentElement:{lang:'pt-br'}},location:{origin:'https://admin.example'},setTimeout,clearTimeout,setInterval,clearInterval,URL,TextEncoder,Uint8Array,console};
const context={window:root,globalThis:root,URL,TextEncoder,Uint8Array,Intl,Map,Set,WeakMap,WeakSet,Date,Math,JSON,RegExp,Array,Object,String,Number,Boolean,Error,TypeError,Promise,console,setTimeout,clearTimeout};
vm.runInNewContext(source,context,{filename:'blog-block-editor.js'});
const api=root.ReamurBlogBlockEditor;
const run=text=>({text,marks:[],href:null});
const node=(id,type,attributes={},content=[],children=[])=>({id,type,attributes,content,children});
const doc={format_version:2,blocks:[
 node('source','core/paragraph',{align:'center',appearance:{text_color:'#123456',padding:16}},[run('Source text')]),
 node('target-a','core/paragraph',{align:'right',appearance:{text_color:'#abcdef',margin:8}},[run('Target A')]),
 node('target-b','core/paragraph',{},[run('Target B')]),
 node('image','core/image',{src:'image/pic.png',alt:'Keep this image',align:'left',size:'large'},'')
]};
const original=JSON.stringify(doc),style=api.captureBlockStyle(doc.blocks[0]);
if(!style||style.sourceType!=='core/paragraph'||style.native.align!=='center'||style.appearance.padding!==16)throw new Error('Style capture should include only whitelisted visual properties');
const result=api.applyBlockStyle(doc,['target-a','target-b'],style,false);
if(!result.valid||result.appliedIds.join(',')!=='target-a,target-b')throw new Error('Compatible batch style application should validate: '+JSON.stringify(result.errors));
if(JSON.stringify(doc)!==original)throw new Error('Style helper mutated original document');
if(result.document.blocks[1].content[0].text!=='Target A'||result.document.blocks[1].id!=='target-a'||result.document.blocks[1].attributes.align!=='center'||result.document.blocks[1].attributes.appearance.text_color!=='#123456')throw new Error('Style transfer must preserve target content and IDs while applying whitelisted visual data');
if(result.document.blocks[2].content[0].text!=='Target B'||result.document.blocks[2].attributes.align!=='center')throw new Error('Batch style application failed on a second target');
const cross=api.applyBlockStyle(result.document,['image'],style,false);
if(!cross.valid||cross.document.blocks[3].attributes.appearance.text_color!=='#123456'||cross.document.blocks[3].attributes.src!=='image/pic.png'||cross.document.blocks[3].attributes.alt!=='Keep this image'||cross.document.blocks[3].attributes.align!=='left')throw new Error('Cross-type compatible appearance transfer must leave media and type-specific properties untouched');
const noCross=api.captureBlockStyle(doc.blocks[0]);noCross.appearance=null;
if(api.applyBlockStyle(doc,['image'],noCross,false).valid)throw new Error('Type-specific presentation fields must not transfer across incompatible block types');
const locked=JSON.parse(JSON.stringify(doc));locked.blocks[1].attributes.locked=true;
if(api.applyBlockStyle(locked,['target-a'],style,false).valid)throw new Error('Locked target must reject style application');
const mixed=api.applyBlockStyle(doc,['target-a','missing'],style,false);
if(mixed.valid||JSON.stringify(doc)!==original)throw new Error('Mixed or missing targets must reject the entire operation without mutation');
const noOp=api.applyBlockStyle(result.document,['target-a'],style,false);
if(noOp.valid)throw new Error('Applying identical styles must not create a redundant transaction');
for(const fragment of ['copy-block-style','apply-group-style-preview','renderBlockStyleConfirm()','block-style-confirm','block-style-cancel','this.commit(result.document,focusId)'])if(!source.includes(fragment))throw new Error('Style preview/confirmation integration missing: '+fragment);
console.log('PASS: style copy captures only visual allowlisted fields; compatible multi-target and cross-type appearance changes preserve content, media, IDs and locks; invalid/locked/no-op targets are rejected atomically; preview requires explicit one-step confirmation.');
