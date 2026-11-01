'use strict';
const fs=require('fs'),vm=require('vm'),path=require('path');
const source=fs.readFileSync(path.join(__dirname,'../dev/admin/view/js/blog-block-editor.js'),'utf8');
const root={document:{documentElement:{lang:'pt-br'}},location:{origin:'https://admin.example'},setTimeout,clearTimeout,setInterval,clearInterval,URL,TextEncoder,Uint8Array,console};
const context={window:root,globalThis:root,URL,TextEncoder,Uint8Array,Intl,Map,Set,WeakMap,WeakSet,Date,Math,JSON,RegExp,Array,Object,String,Number,Boolean,Error,TypeError,Promise,console,setTimeout,clearTimeout};
vm.runInNewContext(source,context,{filename:'blog-block-editor.js'});
const api=root.ReamurBlogBlockEditor;
if(!api||typeof api.ungroupBlock!=='function')throw new Error('Ungroup helper unavailable');
const run=text=>({text,marks:[],href:null});
const node=(id,type,attributes={},content=[],children=[])=>({id,type,attributes,content,children});
const doc={format_version:2,blocks:[
  node('before','core/heading',{level:2},[run('Before')]),
  node('group','core/group',{outline_label:'Section label'},'',[
    node('child-a','core/paragraph',{},[run('First child')]),
    node('child-b','core/quote',{},[run('Second child')])
  ]),
  node('after','core/paragraph',{},[run('After')])
]};
const original=JSON.stringify(doc),result=api.ungroupBlock(doc,'group',false);
if(!result.valid)throw new Error('Valid Group should ungroup: '+JSON.stringify(result.errors));
if(JSON.stringify(doc)!==original)throw new Error('Ungroup helper mutated source document');
if(!api.validateDocument(result.document,false).valid)throw new Error('Ungrouped document should remain valid');
if(result.document.blocks.map(x=>x.id).join(',')!=='before,child-a,child-b,after'||result.unwrappedIds.join(',')!=='child-a,child-b')throw new Error('Ungroup should splice children at the wrapper position in original order');
if(result.labelRemoved!==true||result.document.blocks.some(x=>x.id==='group')||result.document.blocks[1].content[0].text!=='First child'||result.document.blocks[2].content[0].text!=='Second child')throw new Error('Only the wrapper/outline label should be removed; children and IDs remain');
const nested={format_version:2,blocks:[node('outer','core/group',{},'', [node('inner-group','core/group',{},'', [node('nested-child','core/paragraph',{},[run('Nested')])]),node('sibling','core/quote',{},[run('Sibling')])])]};
const nestedResult=api.ungroupBlock(nested,'inner-group',false);
if(!nestedResult.valid||nestedResult.document.blocks[0].children.map(x=>x.id).join(',')!=='nested-child,sibling'||nestedResult.unwrappedIds[0]!=='nested-child')throw new Error('Nested Group should unwrap in place and preserve sibling order');
for(const badId of ['missing','child-a'])if(api.ungroupBlock(doc,badId,false).valid)throw new Error('Missing/non-Group targets must be rejected');
const invalid={format_version:2,blocks:[node('bad','core/group',{outline_label:42},'', [node('child','core/paragraph',{},[run('Child')])])]};
if(api.ungroupBlock(invalid,'bad',false).valid)throw new Error('Invalid source documents must not be ungrouped');
const block=source.slice(source.indexOf('renderBlockToolbar(node) {'),source.indexOf('renderThreeJsInlineEditor(node) {'));
if(!block.includes("this.button(this.ungroupCopy.open,'ungroup-block-preview'")||!source.includes("this.commit(result.document,focusId)"))throw new Error('Ungroup action is not reachable or transactionally committed');
console.log('PASS: ungroup preview helper removes only the Group wrapper, preserves child order/content/IDs, supports nested Groups, validates output, leaves source untouched and rejects invalid targets/documents.');
