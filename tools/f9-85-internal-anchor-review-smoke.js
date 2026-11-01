'use strict';
const fs=require('fs'),vm=require('vm'),path=require('path');
const source=fs.readFileSync(path.join(__dirname,'../dev/admin/view/js/blog-block-editor.js'),'utf8');
const root={document:{documentElement:{lang:'pt-br'}},location:{origin:'https://admin.example'},setTimeout,clearTimeout,setInterval,clearInterval,URL,TextEncoder,Uint8Array,console};
const context={window:root,globalThis:root,URL,TextEncoder,Uint8Array,Intl,Map,Set,WeakMap,WeakSet,Date,Math,JSON,RegExp,Array,Object,String,Number,Boolean,Error,TypeError,Promise,console,setTimeout,clearTimeout};
vm.runInNewContext(source,context,{filename:'blog-block-editor.js'});
const api=root.ReamurBlogBlockEditor;
if(!api||typeof api.analyzeInternalAnchors!=='function')throw new Error('Internal anchor analyzer unavailable');
const run=(text,href=null)=>({text,marks:[],href});
const node=(id,type,attributes={},content=[],children=[])=>({id,type,attributes,content,children});
const document={format_version:2,blocks:[
 node('h1','core/heading',{level:1,anchor:'known'},[run('Known section')]),
 node('paragraph','core/paragraph',{},[
  run('known','#known'),run(' · broken one','#missing-id'),run(' · broken two','#missing-id'),run(' · external','https://example.org/page#not-local')
 ]),
 node('button','core/button',{url:'#missing-id'},[run('Jump')]),
 node('toc','core/group',{outline_label:'Sumário',toc_generated:true},'',[
  node('toclabel','core/paragraph',{},[run('Nesta página')]),
  node('toclist','core/list',{ordered:false},'',[
   node('tocitem','core/list-item',{},[run('Missing target','#also-missing')])
  ])
 ])
]};
const snapshot=JSON.stringify(document);
const result=api.analyzeInternalAnchors(document,false);
if(!result.valid||result.checked!==5||result.issues.length!==3)throw new Error('Expected five local fragment links and one finding per affected block/destination; got '+JSON.stringify({checked:result.checked,issues:result.issues.length}));
if(!result.issues.some(x=>x.nodeId==='paragraph'&&x.destination==='missing-id')||!result.issues.some(x=>x.nodeId==='button'&&x.destination==='missing-id')||!result.issues.some(x=>x.nodeId==='tocitem'&&x.destination==='also-missing'))throw new Error('Missing link source block locator or destination');
if(result.issues.some(x=>x.destination==='not-local'))throw new Error('External URL fragment must not be treated as a page-local anchor');
if(JSON.stringify(document)!==snapshot)throw new Error('Internal anchor review mutated the BDRE source');
const clean={format_version:2,blocks:[
 node('heading','core/heading',{level:2,anchor:'section-1'},[run('Section')]),
 node('link','core/paragraph',{},[run('Jump','#section-1')])
]};
const cleanResult=api.analyzeInternalAnchors(clean,false);
if(!cleanResult.valid||cleanResult.checked!==1||cleanResult.issues.length)throw new Error('Valid same-page target should have no findings');
const empty=api.analyzeInternalAnchors({format_version:2,blocks:[]},false);
if(!empty.valid||empty.checked||empty.issues.length)throw new Error('Empty document should return a clean result');
const invalid=api.analyzeInternalAnchors({format_version:2,blocks:[node('bad','core/heading',{level:2,anchor:'bad/id'},[run('Bad')])]},false);
if(invalid.valid||invalid.issues.length)throw new Error('Invalid document should be refused without findings');
console.log('PASS: internal anchor review checks local fragment links against heading IDs, finds missing targets, ignores external URL fragments, reports results and preserves the document.');
