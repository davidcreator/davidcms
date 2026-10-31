#!/usr/bin/env node
'use strict';
const fs=require('fs'),vm=require('vm'),path=require('path');
const source=fs.readFileSync(path.join(__dirname,'../dev/admin/view/js/blog-block-editor.js'),'utf8');
const root={document:{documentElement:{lang:'pt-br'}},setTimeout,clearTimeout,setInterval,clearInterval,URL,TextEncoder,Uint8Array,console};
const context={window:root,globalThis:root,URL,TextEncoder,Uint8Array,Intl,Map,Set,WeakMap,WeakSet,Date,Math,JSON,RegExp,Array,Object,String,Number,Boolean,Error,TypeError,Promise,console,setTimeout,clearTimeout};
vm.runInNewContext(source,context,{filename:'blog-block-editor.js'});
const api=root.ReamurBlogBlockEditor;
if(!api||typeof api.analyzeDocumentAccessibility!=='function')throw new Error('Read-only accessibility analyzer unavailable');
const node=(id,type,attributes={},content=[],children=[])=>({id,type,attributes,content,children});
const run=text=>[{text,marks:[],href:null}];
const issueDoc={format_version:2,blocks:[
 node('h2','core/heading',{level:2}),
 node('h4','core/heading',{level:4},run('Next section')),
 node('empty_p','core/paragraph'),
 node('image_empty_alt','core/image',{src:'image/a.png',alt:''},''),
 node('button_empty','core/button',{url:'/action'}),
 node('button_generic','core/button',{url:'/action'},run('Click here')),
 node('table_no_headers','core/table',{header:false},'',[
  node('row','core/table-row',{},'',[
   node('cell','core/table-cell',{},run('Value'))
  ])
 ])
]};
function deepFreeze(value){if(value&&typeof value==='object'){Object.freeze(value);Object.values(value).forEach(deepFreeze);}return value;}
const serialized=JSON.stringify(issueDoc);deepFreeze(issueDoc);
const result=api.analyzeDocumentAccessibility(issueDoc,false);
if(!result.valid)throw new Error(`Expected valid test document, got ${result.errors.join(',')}`);
const found=new Set(result.issues.map(x=>x.code));
const expected=['empty_heading','heading_order','empty_paragraph','image_alt','button_empty','button_generic','table_headers'];
for(const code of expected)if(!found.has(code))throw new Error(`Missing expected advisory finding: ${code}`);
if(result.issues.length!==expected.length)throw new Error(`Expected ${expected.length} findings, got ${result.issues.length}`);
if(JSON.stringify(issueDoc)!==serialized)throw new Error('Analyzer changed the document');
if(result.issues.some(x=>!x.nodeId||!x.title||!x.suggestion||!['warning','info'].includes(x.severity)))throw new Error('Finding is missing accessible detail or target id');
const clean={format_version:2,blocks:[
 node('heading','core/heading',{level:1},run('Overview')),
 node('paragraph','core/paragraph',{},run('Useful content')),
 node('image','core/image',{src:'image/meaningful.png',alt:'A meaningful image'},''),
 node('button','core/button',{url:'/details'},run('View project details')),
 node('table','core/table',{header:true},'',[
  node('row2','core/table-row',{},'',[
   node('header_cell','core/table-cell',{header:true},run('Name'))
  ])
 ])
]};
const cleanResult=api.analyzeDocumentAccessibility(clean,false);
if(!cleanResult.valid||cleanResult.issues.length)throw new Error('Clean document should have no findings');
const invalid=api.analyzeDocumentAccessibility({format_version:2,blocks:[{id:'bad',type:'core/unknown',attributes:{},content:'',children:[]}]},false);
if(invalid.valid||invalid.issues.length)throw new Error('Invalid BDRE document should not be audited');
for(const locale of ['pt-br','es-eu','en-us']){
 root.document.documentElement.lang=locale;
 const localized=api.analyzeDocumentAccessibility(issueDoc,false);
 if(!localized.issues.length||!localized.issues.every(x=>x.title&&x.suggestion))throw new Error(`Missing localized labels for ${locale}`);
}
console.log('PASS: accessibility analyzer finds 7 advisory cases, returns block IDs, preserves the input, localizes PT/ES/EN, and reports no alerts on a clean document.');
