'use strict';
const fs=require('fs'),vm=require('vm'),path=require('path');
const source=fs.readFileSync(path.join(__dirname,'../dev/admin/view/js/blog-block-editor.js'),'utf8');
const root={document:{documentElement:{lang:'pt-br'}},location:{origin:'https://admin.example'},setTimeout,clearTimeout,setInterval,clearInterval,URL,TextEncoder,Uint8Array,console};
const context={window:root,globalThis:root,URL,TextEncoder,Uint8Array,Intl,Map,Set,WeakMap,WeakSet,Date,Math,JSON,RegExp,Array,Object,String,Number,Boolean,Error,TypeError,Promise,console,setTimeout,clearTimeout};
vm.runInNewContext(source,context,{filename:'blog-block-editor.js'});
const api=root.ReamurBlogBlockEditor;
if(!api||typeof api.duplicateSiblingBlocksAsGroup!=='function')throw new Error('Duplicate-as-Group helper unavailable');
const js=source;
if(!js.includes("groupSelectionMode==='duplicate'")||!js.includes("copy.duplicatePreviewHint")||!js.includes("this.openGroupSelectionPreview('duplicate')"))throw new Error('Duplicate selection mode or preview UI is not wired');
const run=text=>({text,marks:[],href:null});
const node=(id,type,attributes={},content=[],children=[])=>({id,type,attributes,content,children});
const doc={format_version:2,blocks:[
  node('a','core/heading',{level:2},[run('Section')]),
  node('b','core/paragraph',{},[run('First paragraph')]),
  node('c','core/quote',{},[run('A quote')]),
  node('d','core/paragraph',{},[run('Keep separate')])
]};
const original=JSON.stringify(doc);let generated=0;
const duplicated=api.duplicateSiblingBlocksAsGroup(doc,['c','b'],'copy-group','Repeated section',()=>`copy-${++generated}`,false);
if(!duplicated.valid)throw new Error('Adjacent sibling duplicates should validate: '+JSON.stringify(duplicated.errors));
if(JSON.stringify(doc)!==original)throw new Error('Duplicate-as-Group mutated the source document');
if(!api.validateDocument(duplicated.document,false).valid)throw new Error('Duplicated document should pass BDRE validation');
const copiedGroup=duplicated.document.blocks[3];
if(copiedGroup.type!=='core/group'||copiedGroup.attributes.outline_label!=='Repeated section'||copiedGroup.children.map(x=>x.type).join(',')!=='core/paragraph,core/quote')throw new Error('Duplicate should be inserted after the selected range in a Group, preserving order and types');
const originalIds=new Set(['a','b','c','d']);const clonedIds=[];const collect=node=>{clonedIds.push(node.id);(node.children||[]).forEach(collect);};copiedGroup.children.forEach(collect);
if(originalIds.has(copiedGroup.id)||clonedIds.some(id=>originalIds.has(id))||new Set([copiedGroup.id,...clonedIds]).size!==clonedIds.length+1)throw new Error('Duplicated Group and descendants must receive unique fresh IDs');
if(copiedGroup.children[0].content[0].text!=='First paragraph'||copiedGroup.children[1].content[0].text!=='A quote'||duplicated.document.blocks[1].id!=='b'||duplicated.document.blocks[2].id!=='c')throw new Error('Duplicate must preserve original content and keep originals intact');
const nestedDoc={format_version:2,blocks:[node('container','core/group',{outline_label:'Original'},'', [node('inner','core/paragraph',{},[run('Nested content')])]),node('after','core/quote',{},[run('Following')])]};
let nestedGenerated=0;const nestedCopy=api.duplicateSiblingBlocksAsGroup(nestedDoc,['container','after'],'nested-copy','',()=>`nested-${++nestedGenerated}`,false);
if(!nestedCopy.valid)throw new Error('Nested subtree should duplicate: '+JSON.stringify(nestedCopy.errors));
const nestedCopiedGroup=nestedCopy.document.blocks[2],nestedChild=nestedCopiedGroup.children[0].children[0];
if(nestedCopiedGroup.children[0].id==='container'||nestedChild.id==='inner'||nestedChild.content[0].text!=='Nested content')throw new Error('Every nested descendant must get a fresh ID while retaining content');
const collision=api.duplicateSiblingBlocksAsGroup(doc,['b','c'],'copy-group','',()=> 'b',false);
if(collision.valid)throw new Error('Duplicate ID collisions must be rejected');
const separated=api.duplicateSiblingBlocksAsGroup(doc,['b','d'],'copy-group','',()=>`new-${++generated}`,false);
if(separated.valid)throw new Error('Noncontiguous source blocks must not be duplicated');
console.log('PASS: explicit duplicate-as-Group preview inserts a validated copy after adjacent sources; source blocks stay unchanged, clone IDs are fresh, and collision/noncontiguous selections are rejected.');
