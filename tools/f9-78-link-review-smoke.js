'use strict';
const fs=require('fs'),vm=require('vm'),path=require('path');
const source=fs.readFileSync(path.join(__dirname,'../dev/admin/view/js/blog-block-editor.js'),'utf8');
const root={document:{documentElement:{lang:'pt-br'}},location:{origin:'https://cms.example'},setTimeout,clearTimeout,setInterval,clearInterval,URL,TextEncoder,Uint8Array,console};
const context={window:root,globalThis:root,URL,TextEncoder,Uint8Array,Intl,Map,Set,WeakMap,WeakSet,Date,Math,JSON,RegExp,Array,Object,String,Number,Boolean,Error,TypeError,Promise,console,setTimeout,clearTimeout};
vm.runInNewContext(source,context,{filename:'blog-block-editor.js'});
const api=root.ReamurBlogBlockEditor;
if(!api||typeof api.analyzeDocumentLinks!=='function')throw new Error('Read-only link analyzer unavailable');
const node=(id,type,attributes={},content=[],children=[])=>({id,type,attributes,content,children});
const run=(text,href=null)=>({text,marks:[],href});
const issueDoc={format_version:2,blocks:[
 node('http_raw','core/button',{url:'http://outside.example/info'},[run('http://outside.example/info')]),
 node('repeat_one','core/button',{url:'https://repeat.example/docs'},[run('Documentation')]),
 node('repeat_two','core/paragraph',{},[run('Read '),run('the docs','https://repeat.example/docs')]),
 node('styled_anchor','core/paragraph',{},[run('Visit ','https://split.example/guide'),run('the guide','https://split.example/guide')])
]};
function deepFreeze(value){if(value&&typeof value==='object'){Object.freeze(value);Object.values(value).forEach(deepFreeze);}}
const serialized=JSON.stringify(issueDoc);deepFreeze(issueDoc);
const result=api.analyzeDocumentLinks(issueDoc,false);
if(!result.valid)throw new Error(`Expected valid BDRE document, got ${result.errors.join(',')}`);
const count=code=>result.issues.filter(x=>x.code===code).length;
if(count('http_external')!==1)throw new Error(`Expected one external HTTP warning, got ${count('http_external')}`);
if(count('raw_url_label')!==1)throw new Error(`Expected one raw URL label, got ${count('raw_url_label')}`);
if(count('repeated_destination')!==2)throw new Error(`Expected repeated destination findings on both blocks, got ${count('repeated_destination')}`);
if(result.issues.length!==4)throw new Error(`Expected 4 findings, got ${result.issues.length}`);
if(JSON.stringify(issueDoc)!==serialized)throw new Error('Link analyzer changed the document');
if(result.issues.some(x=>!x.nodeId||!x.title||!x.suggestion||!['warning','info'].includes(x.severity)))throw new Error('Finding is missing a block target or readable detail');
const clean={format_version:2,blocks:[
 node('internal','core/paragraph',{},[run('About the project','https://cms.example/about')]),
 node('secure_external','core/button',{url:'https://outside.example/guide'},[run('Read the guide')])
]};
const cleanResult=api.analyzeDocumentLinks(clean,false);
if(!cleanResult.valid||cleanResult.issues.length)throw new Error('Descriptive secure links should have no findings');
const invalid=api.analyzeDocumentLinks({format_version:2,blocks:[{id:'bad',type:'core/unknown',attributes:{},content:'',children:[]}]},false);
if(invalid.valid||invalid.issues.length)throw new Error('Invalid BDRE document should not be audited');
for(const locale of ['pt-br','es-eu','en-us']){
 root.document.documentElement.lang=locale;
 const localized=api.analyzeDocumentLinks(issueDoc,false);
 if(!localized.issues.length||!localized.issues.every(x=>x.title&&x.suggestion))throw new Error(`Missing localized labels for ${locale}`);
}
console.log('PASS: link audit flags external HTTP, raw URL labels and both repeated-destination blocks; keeps input unchanged, localizes PT/ES/EN, and ignores clean/invalid documents appropriately.');
