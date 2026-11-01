'use strict';
const fs=require('fs'),vm=require('vm'),path=require('path');
const source=fs.readFileSync(path.join(__dirname,'../dev/admin/view/js/blog-block-editor.js'),'utf8');
const root={document:{documentElement:{lang:'pt-br'}},location:{origin:'https://admin.example'},setTimeout,clearTimeout,setInterval,clearInterval,URL,TextEncoder,Uint8Array,console};
const context={window:root,globalThis:root,URL,TextEncoder,Uint8Array,Intl,Map,Set,WeakMap,WeakSet,Date,Math,JSON,RegExp,Array,Object,String,Number,Boolean,Error,TypeError,Promise,console,setTimeout,clearTimeout};
vm.runInNewContext(source,context,{filename:'blog-block-editor.js'});
const api=root.ReamurBlogBlockEditor;
if(!api||typeof api.repairInternalAnchorLinks!=='function')throw new Error('Internal anchor repair helper unavailable');
const run=(text,href=null)=>({text,marks:[],href});
const node=(id,type,attributes={},content=[],children=[])=>({id,type,attributes,content,children});
const doc={format_version:2,blocks:[
 node('heading','core/heading',{level:2},[run('Ação e serviços')]),
 node('body','core/paragraph',{},[
  run('Jump one','#missing'),run(' and again','#missing'),run(' unrelated','#other')
 ]),
 node('button','core/button',{url:'#button-missing'},[run('Jump button')]),
 node('existing','core/heading',{level:3,anchor:'custom-destination'},[run('Existing target')])
]};
const snapshot=JSON.stringify(doc);
const fixed=api.repairInternalAnchorLinks(doc,'body','missing','heading',false);
if(!fixed.valid||fixed.updatedLinks!==2||fixed.anchor!=='acao-e-servicos'||!fixed.anchorAdded)throw new Error('Repair should update matching runs and add a unique target anchor: '+JSON.stringify(fixed));
const repairedRuns=fixed.document.blocks[1].content;if(fixed.document.blocks[0].attributes.anchor!=='acao-e-servicos'||repairedRuns.filter(run=>run.href==='#acao-e-servicos').length!==1||!repairedRuns.some(run=>run.href==='#other'))throw new Error('Repair changed the wrong content or failed to create the matching target');
if(JSON.stringify(doc)!==snapshot)throw new Error('Repair mutated the original source document');
if(!api.validateDocument(fixed.document,false).valid)throw new Error('Repaired document should pass BDRE validation');
const buttonFix=api.repairInternalAnchorLinks(fixed.document,'button','button-missing','existing',false);
if(!buttonFix.valid||buttonFix.updatedLinks!==1||buttonFix.document.blocks[2].attributes.url!=='#custom-destination'||buttonFix.document.blocks[3].attributes.anchor!=='custom-destination')throw new Error('Button destination repair should reuse an existing anchor');
const missing=api.repairInternalAnchorLinks(doc,'body','not-a-broken-link','heading',false);
if(missing.valid||missing.updatedLinks)throw new Error('Stale findings must not modify the document');
const invalid=api.repairInternalAnchorLinks({format_version:2,blocks:[node('bad','core/heading',{level:2,anchor:'bad/id'},[run('Bad')])]},'body','missing','heading',false);
if(invalid.valid||invalid.updatedLinks)throw new Error('Invalid BDRE source must not be repaired');
console.log('PASS: assisted repair updates only matching local links in one source block, assigns or reuses a safe heading anchor, validates the result and preserves the original document.');
