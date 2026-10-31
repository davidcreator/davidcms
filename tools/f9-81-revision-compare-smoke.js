'use strict';
const fs=require('fs'),vm=require('vm'),path=require('path');
const source=fs.readFileSync(path.join(__dirname,'../dev/admin/view/js/blog-block-editor.js'),'utf8');
const root={document:{documentElement:{lang:'pt-br'}},setTimeout,clearTimeout,setInterval,clearInterval,URL,TextEncoder,Uint8Array,console};
const context={window:root,globalThis:root,URL,TextEncoder,Uint8Array,Intl,Map,Set,WeakMap,WeakSet,Date,Math,JSON,RegExp,Array,Object,String,Number,Boolean,Error,TypeError,Promise,console,setTimeout,clearTimeout};
vm.runInNewContext(source,context,{filename:'blog-block-editor.js'});
const api=root.ReamurBlogBlockEditor;
if(!api||typeof api.compareBDREDocuments!=='function')throw new Error('Read-only revision comparison unavailable');
const node=(id,type,attributes={},content=[],children=[])=>({id,type,attributes,content,children});
const run=text=>[{text,marks:[],href:null}];
const oldDoc={format_version:2,blocks:[
 node('a','core/paragraph',{},run('Old wording')),
 node('b','core/paragraph',{},run('Unchanged block')),
 node('c','core/quote',{},run('Removed quotation'))
]};
const currentDoc={format_version:2,blocks:[
 node('b','core/paragraph',{},run('Unchanged block')),
 node('a','core/paragraph',{},run('Revised wording')),
 node('d','core/heading',{level:2},run('New heading'))
]};
function deepFreeze(value){if(value&&typeof value==='object'){Object.freeze(value);Object.values(value).forEach(deepFreeze);}}
const oldSnapshot=JSON.stringify(oldDoc),currentSnapshot=JSON.stringify(currentDoc);deepFreeze(oldDoc);deepFreeze(currentDoc);
const diff=api.compareBDREDocuments(currentDoc,oldDoc,false);
if(!diff.valid||diff.added!==1||diff.removed!==1||diff.changed!==2)throw new Error(`Unexpected diff totals ${diff.added}/${diff.removed}/${diff.changed}`);
const changed=Object.fromEntries(diff.items.filter(x=>x.kind==='changed').map(x=>[x.nodeId,x]));
if(!changed.a||!changed.a.fields.includes('content'))throw new Error('Edited content was not identified');
if(!changed.b||!changed.b.fields.includes('position'))throw new Error('Reordered sibling was not identified');
if(diff.items.find(x=>x.kind==='added').nodeId!=='d'||diff.items.find(x=>x.kind==='removed').nodeId!=='c')throw new Error('Added/removed block identities are incorrect');
if(JSON.stringify(oldDoc)!==oldSnapshot||JSON.stringify(currentDoc)!==currentSnapshot)throw new Error('Comparison changed one of its inputs');
const parentBefore={format_version:2,blocks:[node('group','core/group',{},'',[
 node('moving','core/paragraph',{},run('Move me')),
 node('stay','core/paragraph',{},run('Stay'))
])]};
const parentAfter={format_version:2,blocks:[
 node('group','core/group',{},'', [node('stay','core/paragraph',{},run('Stay'))]),
 node('moving','core/paragraph',{},run('Move me'))
]};
const parentDiff=api.compareBDREDocuments(parentAfter,parentBefore,false);
const moved=parentDiff.items.find(x=>x.nodeId==='moving');
if(!moved||moved.kind!=='changed'||!moved.fields.includes('container'))throw new Error('Changed parent container was not identified');
const legacy={format_version:1,blocks:[{id:'same',type:'core/paragraph',attributes:{},content:'Legacy wording',children:[]}]};
const modern={format_version:2,blocks:[node('same','core/paragraph',{},run('Legacy wording'))]};
const upgraded=api.compareBDREDocuments(modern,legacy,false);
if(!upgraded.valid||upgraded.added||upgraded.removed||upgraded.changed)throw new Error('Equivalent BDRE v1 and v2 documents should compare without false changes');
const same=api.compareBDREDocuments(modern,modern,false);
if(!same.valid||same.items.length)throw new Error('Identical documents should have no differences');
const invalid=api.compareBDREDocuments(modern,{format_version:2,blocks:[{id:'bad',type:'core/unknown',attributes:{},content:'',children:[]}]},false);
if(invalid.valid||invalid.items.length)throw new Error('Invalid revision should not be compared');
const unreadable=api.compareBDREDocuments(null,modern,false);
if(unreadable.valid||unreadable.items.length)throw new Error('Missing current document should fail safely');
console.log('PASS: revision diff detects additions, removals, content edits, sibling reorders and container moves; upgrades v1 safely, preserves inputs and handles identical/invalid revisions.');
