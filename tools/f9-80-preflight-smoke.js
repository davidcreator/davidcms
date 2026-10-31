'use strict';
const fs=require('fs'),vm=require('vm'),path=require('path');
const source=fs.readFileSync(path.join(__dirname,'../dev/admin/view/js/blog-block-editor.js'),'utf8');
const root={document:{documentElement:{lang:'pt-br'}},location:{origin:'https://cms.example'},setTimeout,clearTimeout,setInterval,clearInterval,URL,TextEncoder,Uint8Array,console};
const context={window:root,globalThis:root,URL,TextEncoder,Uint8Array,Intl,Map,Set,WeakMap,WeakSet,Date,Math,JSON,RegExp,Array,Object,String,Number,Boolean,Error,TypeError,Promise,console,setTimeout,clearTimeout};
vm.runInNewContext(source,context,{filename:'blog-block-editor.js'});
const api=root.ReamurBlogBlockEditor;
if(!api||typeof api.analyzeDocumentPreflight!=='function')throw new Error('Pre-publication checklist analyzer unavailable');
const node=(id,type,attributes={},content=[],children=[])=>({id,type,attributes,content,children});
const run=(text,href=null)=>({text,marks:[],href});
const longText=Array(505).fill('palavra').join(' ')+'.';
const issueDoc={format_version:2,blocks:[
 node('h2_empty','core/heading',{level:2}),
 node('h4','core/heading',{level:4},[run('A later heading')]),
 node('empty_p','core/paragraph'),
 node('empty_alt','core/image',{src:'image/test.png',alt:''},''),
 node('http_button','core/button',{url:'http://outside.example/info'},[run('Click here')]),
 node('raw_button','core/button',{url:'https://raw.example/info'},[run('https://raw.example/info')]),
 node('repeat_a','core/button',{url:'https://repeat.example/docs'},[run('View documentation')]),
 node('repeat_b','core/button',{url:'https://repeat.example/docs'},[run('Read documentation')]),
 node('long_heading','core/heading',{level:2},[run('Long section')]),
 node('long_paragraph','core/paragraph',{},[run(longText)]),
 node('table','core/table',{header:false},'',[
  node('row','core/table-row',{},'',[
   node('cell','core/table-cell',{},[run('Value')])
  ])
 ])
]};
function deepFreeze(value){if(value&&typeof value==='object'){Object.freeze(value);Object.values(value).forEach(deepFreeze);}}
const serialized=JSON.stringify(issueDoc);deepFreeze(issueDoc);
const result=api.analyzeDocumentPreflight(issueDoc,false);
if(!result.valid)throw new Error(`Expected valid BDRE document, got ${result.errors.join(',')}`);
const categories=Object.fromEntries(result.categories.map(c=>[c.id,c]));
if(result.categories.length!==3)throw new Error('Expected three consolidated review areas');
if(categories.accessibility.issues.length!==6)throw new Error(`Expected six accessibility findings, got ${categories.accessibility.issues.length}`);
if(categories.links.issues.length!==4)throw new Error(`Expected four link findings, got ${categories.links.issues.length}`);
if(categories.readability.issues.length!==3)throw new Error(`Expected three readability findings, got ${categories.readability.issues.length}`);
if(result.total!==13||result.warnings!==5||result.suggestions!==8)throw new Error(`Incorrect totals: ${result.total}/${result.warnings}/${result.suggestions}`);
if(result.wordCount!==506||result.readingMinutes!==3)throw new Error(`Unexpected document metrics: ${result.wordCount} words / ${result.readingMinutes} minutes`);
if(JSON.stringify(issueDoc)!==serialized)throw new Error('Preflight analyzer changed the document');
for(const category of result.categories)for(const item of category.issues)if(!item.category||!item.nodeId||!item.title||!item.suggestion)throw new Error('A consolidated finding lacks its category, block target or guidance');
const clean={format_version:2,blocks:[
 node('heading','core/heading',{level:1},[run('Overview')]),
 node('paragraph','core/paragraph',{},[run('Short content')]),
 node('button','core/button',{url:'https://example.org/guide'},[run('Read the guide')])
]};
const cleanResult=api.analyzeDocumentPreflight(clean,false);
if(!cleanResult.valid||cleanResult.total||cleanResult.categories.some(c=>c.issues.length))throw new Error('Clean document should have zero findings');
const invalid=api.analyzeDocumentPreflight({format_version:2,blocks:[{id:'bad',type:'core/unknown',attributes:{},content:'',children:[]}]},false);
if(invalid.valid||invalid.categories.length||invalid.total)throw new Error('Invalid BDRE document should not be aggregated');
for(const locale of ['pt-br','es-eu','en-us']){
 root.document.documentElement.lang=locale;
 const localized=api.analyzeDocumentPreflight(clean,false);
 if(localized.categories.some(c=>!c.title)||localized.categories.map(c=>c.title).join('|')===['Acessibilidade','Links e destinos','Legibilidade'].join('|')&&locale!=='pt-br')throw new Error(`Missing localized categories for ${locale}`);
}
console.log('PASS: preflight aggregates all three existing audits, counts warning/suggestion totals and reading metrics, preserves input, localizes categories, and handles clean/invalid BDRE documents.');
