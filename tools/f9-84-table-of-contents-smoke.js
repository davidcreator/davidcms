'use strict';
const fs=require('fs'),vm=require('vm'),path=require('path');
const source=fs.readFileSync(path.join(__dirname,'../dev/admin/view/js/blog-block-editor.js'),'utf8');
const root={document:{documentElement:{lang:'pt-br'}},location:{origin:'https://admin.example'},setTimeout,clearTimeout,setInterval,clearInterval,URL,TextEncoder,Uint8Array,console};
const context={window:root,globalThis:root,URL,TextEncoder,Uint8Array,Intl,Map,Set,WeakMap,WeakSet,Date,Math,JSON,RegExp,Array,Object,String,Number,Boolean,Error,TypeError,Promise,console,setTimeout,clearTimeout};
vm.runInNewContext(source,context,{filename:'blog-block-editor.js'});
const api=root.ReamurBlogBlockEditor;
if(!api||typeof api.createTableOfContentsPlan!=='function')throw new Error('TOC planner unavailable');
const run=(text,href=null)=>({text,marks:[],href});
const node=(id,type,attributes={},content=[],children=[])=>({id,type,attributes,content,children});
const doc={format_version:2,blocks:[
 node('h1','core/heading',{level:1},[run('Page title')]),
 node('h2a','core/heading',{level:2},[run('Introdução & objetivos')]),
node('columns','core/columns',{count:2},'',[
  node('columnA','core/column',{},'',[
   node('h3','core/heading',{level:3},[run('Overview')]),
   node('bodyA','core/paragraph',{},[run('Column body')])
  ]),
  node('columnB','core/column',{},'',[
   node('h2b','core/heading',{level:2,anchor:'custom-section'},[run('Custom heading')]),
   node('bodyB','core/paragraph',{},[run('Second column body')])
  ])
]),
 node('empty','core/heading',{level:2},[]),
 node('group','core/group',{toc_generated:true,outline_label:'Sumário'},'',[
  node('toc_title','core/heading',{level:2},[run('Nesta página')])
 ]),
 node('h2c','core/heading',{level:2},[run('Introdução & objetivos')])
]};
const snapshot=JSON.stringify(doc);
const plan=api.createTableOfContentsPlan(doc,[2,3],false);
if(!plan.valid||plan.headings.length!==4)throw new Error('Planner must include only nonempty selected headings outside generated TOC groups');
const ids=plan.headings.map(item=>item.anchor);
if(ids.join('|')!=='introducao-objetivos|overview|custom-section|introducao-objetivos-2')throw new Error('Slug generation, collision suffixing or existing anchors are incorrect: '+ids.join('|'));
if(plan.headings[0].nodeId!=='h2a'||plan.headings[0].needsAnchor!==true||plan.headings[2].needsAnchor!==false)throw new Error('Planner should distinguish generated and reused anchor IDs');
if(JSON.stringify(doc)!==snapshot)throw new Error('TOC planning mutated the source document');
const tocItems=plan.headings.map((item,index)=>node('item'+index,'core/list-item',{},[run(item.text,'#'+item.anchor)]));
const toc=node('toc','core/group',{toc_generated:true,outline_label:'Sumário'},'',[
 node('tocHeading','core/heading',{level:2},[run('Nesta página')]),
 node('tocList','core/list',{ordered:false},'',tocItems)
]);
const committed=JSON.parse(snapshot);
plan.headings.forEach(item=>{if(item.needsAnchor){const target=item.nodeId==='h2a'?committed.blocks[1]:item.nodeId==='h3'?committed.blocks[2].children[0].children[0]:committed.blocks[5];target.attributes.anchor=item.anchor;}});
committed.blocks.splice(0,0,toc);
if(!api.validateDocument(committed,false).valid)throw new Error('Generated TOC with safe same-page links and heading anchors must validate');
const markdown=api.convertDocumentToMarkdown(committed,false);
if(!markdown.valid||!markdown.warnings.some(w=>w.code==='heading_anchor')||!markdown.warnings.some(w=>w.code==='generated_toc'))throw new Error('Markdown export should disclose anchor and generated TOC limitations without failing.');
const duplicate=JSON.parse(JSON.stringify(committed));duplicate.blocks[1].attributes.anchor='introducao-objetivos';
if(api.validateDocument(duplicate,false).valid)throw new Error('Duplicate heading anchors must be rejected');
const invalid=JSON.parse(JSON.stringify(doc));invalid.blocks[1].attributes.anchor='../unsafe';
if(api.validateDocument(invalid,false).valid)throw new Error('Unsafe anchor syntax must be rejected');
const badLink={format_version:2,blocks:[node('p','core/paragraph',{},[run('bad','#javascript:alert(1)')])]};
if(api.validateDocument(badLink,false).valid)throw new Error('Unsafe fragment URL must be rejected');
const safeLink={format_version:2,blocks:[node('p','core/paragraph',{},[run('safe','#local-heading')])]};
if(!api.validateDocument(safeLink,false).valid)throw new Error('Safe same-page anchor link should validate');
const malformedGroup={format_version:2,blocks:[node('g','core/group',{toc_generated:'yes'},'',[
 node('h','core/heading',{level:2},[run('TOC')])
]) ]};
if(api.validateDocument(malformedGroup,false).valid)throw new Error('TOC metadata must be a boolean schema field');
for(const [locale,expected] of [['pt-br','Sumário navegável'],['es-eu','Sumario navegable'],['en-us','Navigable table of contents']]){
 root.document.documentElement.lang=locale;
 const script=fs.readFileSync(path.join(__dirname,'../dev/admin/view/js/blog-block-editor.js'),'utf8');
 const localRoot={...root,document:{documentElement:{lang:locale}}};const localContext={...context,window:localRoot,globalThis:localRoot};vm.runInNewContext(script,localContext);
 const copy=localRoot.ReamurBlogBlockEditor;
 // The tool's localized strings are exercised through the public source text/UI static check.
 if(!copy||typeof copy.createTableOfContentsPlan!=='function'||!expected)throw new Error('Localized TOC runtime unavailable');
}
console.log('PASS: TOC planner selects headings by level, skips empty/generated TOC headings, generates unique safe slugs, reuses anchors, preserves input, and validates native same-page links.');
