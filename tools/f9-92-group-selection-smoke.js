'use strict';
const fs=require('fs'),vm=require('vm'),path=require('path');
const source=fs.readFileSync(path.join(__dirname,'../dev/admin/view/js/blog-block-editor.js'),'utf8');
const root={document:{documentElement:{lang:'pt-br'}},location:{origin:'https://admin.example'},setTimeout,clearTimeout,setInterval,clearInterval,URL,TextEncoder,Uint8Array,console};
const context={window:root,globalThis:root,URL,TextEncoder,Uint8Array,Intl,Map,Set,WeakMap,WeakSet,Date,Math,JSON,RegExp,Array,Object,String,Number,Boolean,Error,TypeError,Promise,console,setTimeout,clearTimeout};
vm.runInNewContext(source,context,{filename:'blog-block-editor.js'});
const api=root.ReamurBlogBlockEditor;
if(!api||typeof api.groupSiblingBlocks!=='function')throw new Error('Group-selection helper unavailable');
const run=text=>({text,marks:[],href:null});
const node=(id,type,attributes={},content=[],children=[])=>({id,type,attributes,content,children});
const doc={format_version:2,blocks:[
  node('a','core/heading',{level:2},[run('Section')]),
  node('b','core/paragraph',{},[run('First paragraph')]),
  node('c','core/quote',{},[run('A quote')]),
  node('d','core/paragraph',{},[run('Keep separate')])
]};
const original=JSON.stringify(doc);
const grouped=api.groupSiblingBlocks(doc,['c','b'],'new-group','Editorial section',false);
if(!grouped.valid)throw new Error('Adjacent siblings should group: '+JSON.stringify(grouped.errors));
if(JSON.stringify(doc)!==original)throw new Error('Grouping mutated source document');
if(!api.validateDocument(grouped.document,false).valid)throw new Error('Grouped document should pass BDRE validation');
const group=grouped.document.blocks[1];
if(group.type!=='core/group'||group.attributes.outline_label!=='Editorial section'||group.children.map(x=>x.id).join(',')!=='b,c')throw new Error('Group should preserve canonical block order, IDs and optional outline label');
if(grouped.childIds.join(',')!=='b,c'||grouped.document.blocks[2].id!=='d')throw new Error('Grouping should preserve nested IDs and unrelated siblings');
const separated=api.groupSiblingBlocks(doc,['b','d'],'bad-group','',false);
if(separated.valid)throw new Error('Nonadjacent siblings must not be grouped');
const nested={format_version:2,blocks:[node('container','core/group',{},'', [node('inner','core/paragraph',{},[run('Nested')])]),node('outer','core/paragraph',{},[run('Outer')])]};
const differentParents=api.groupSiblingBlocks(nested,['inner','outer'],'bad-parent','',false);
if(differentParents.valid)throw new Error('Blocks from different parents must not be grouped');
const list={format_version:2,blocks:[node('list','core/list',{ordered:false},'', [node('li1','core/list-item',{},[run('One')]),node('li2','core/list-item',{},[run('Two')])])]};
const invalidParent=api.groupSiblingBlocks(list,['li1','li2'],'bad-list-group','',false);
if(invalidParent.valid)throw new Error('Grouping must be rejected where BDRE child schema disallows a Group');
const longLabel=api.groupSiblingBlocks(doc,['b','c'],'long-group','Á'.repeat(81),false);
if(longLabel.valid)throw new Error('An overlong encoded outline label must not silently mutate content');
console.log('PASS: adjacent sibling blocks become one validated native Group in a single pure transformation; original IDs/content/order are preserved; nonadjacent, cross-parent, schema-invalid and overlong-label groupings are rejected.');
