'use strict';
const fs=require('fs'),vm=require('vm'),path=require('path');
const source=fs.readFileSync(path.join(__dirname,'../dev/admin/view/js/blog-block-editor.js'),'utf8');
const root={document:{documentElement:{lang:'pt-br'}},setTimeout,clearTimeout,setInterval,clearInterval,URL,TextEncoder,Uint8Array,console};
const context={window:root,globalThis:root,URL,TextEncoder,Uint8Array,Intl,Map,Set,WeakMap,WeakSet,Date,Math,JSON,RegExp,Array,Object,String,Number,Boolean,Error,TypeError,Promise,console,setTimeout,clearTimeout};
vm.runInNewContext(source,context,{filename:'blog-block-editor.js'});
const api=root.ReamurBlogBlockEditor;
if(!api||typeof api.analyzeDocumentReadability!=='function')throw new Error('Read-only readability analyzer unavailable');
const node=(id,type,attributes={},content=[],children=[])=>({id,type,attributes,content,children});
const run=text=>[{text,marks:[],href:null}];
const longFirst=Array(130).fill('conteúdo').join(' ')+'.';
const longSection=Array(505).fill('informação').join(' ')+'.';
const issueDoc={format_version:2,blocks:[
 node('heading1','core/heading',{level:1},run('Visão geral')),
 node('paragraph_long','core/paragraph',{},run(longFirst)),
 node('heading2','core/heading',{level:2},run('Detalhes')),
 node('paragraph_section','core/paragraph',{},run(longSection))
]};
function deepFreeze(value){if(value&&typeof value==='object'){Object.freeze(value);Object.values(value).forEach(deepFreeze);}}
const serialized=JSON.stringify(issueDoc);deepFreeze(issueDoc);
const result=api.analyzeDocumentReadability(issueDoc,false);
if(!result.valid)throw new Error(`Expected valid BDRE document, got ${result.errors.join(',')}`);
const count=code=>result.issues.filter(x=>x.code===code).length;
if(count('long_paragraph')!==2)throw new Error(`Expected two long paragraph findings, got ${count('long_paragraph')}`);
if(count('long_sentence')!==2)throw new Error(`Expected two long sentence findings, got ${count('long_sentence')}`);
if(count('long_section')!==1)throw new Error(`Expected one long section finding, got ${count('long_section')}`);
if(result.issues.length!==5)throw new Error(`Expected five findings, got ${result.issues.length}`);
if(result.wordCount!==635||result.readingMinutes!==4)throw new Error(`Unexpected metrics ${result.wordCount} words / ${result.readingMinutes} minutes`);
if(JSON.stringify(issueDoc)!==serialized)throw new Error('Readability analyzer changed the document');
if(result.issues.some(x=>!x.nodeId||!x.title||!x.suggestion||!['warning','info'].includes(x.severity)))throw new Error('Finding is missing a block target or readable detail');
const clean={format_version:2,blocks:[
 node('heading','core/heading',{level:2},run('Overview')),
 node('paragraph','core/paragraph',{},run('A short and clear paragraph.'))
]};
const cleanResult=api.analyzeDocumentReadability(clean,false);
if(!cleanResult.valid||cleanResult.issues.length||cleanResult.wordCount!==5||cleanResult.readingMinutes!==1)throw new Error('Clean document metrics/findings are incorrect');
const empty=api.analyzeDocumentReadability({format_version:2,blocks:[]},false);
if(!empty.valid||empty.wordCount!==0||empty.readingMinutes!==0||empty.issues.length)throw new Error('Empty document metrics are incorrect');
const invalid=api.analyzeDocumentReadability({format_version:2,blocks:[{id:'bad',type:'core/unknown',attributes:{},content:'',children:[]}]},false);
if(invalid.valid||invalid.issues.length)throw new Error('Invalid BDRE document should not be audited');
for(const locale of ['pt-br','es-eu','en-us']){
 root.document.documentElement.lang=locale;
 const localized=api.analyzeDocumentReadability(issueDoc,false);
 if(!localized.issues.length||!localized.issues.every(x=>x.title&&x.suggestion))throw new Error(`Missing localized labels for ${locale}`);
 if(localized.wordsPerMinute!==(locale==='en-us'?225:200))throw new Error(`Incorrect localized estimate rate for ${locale}`);
}
console.log('PASS: readability audit finds long paragraphs, sentences and a section without subheadings; reports word/time estimates, preserves input, localizes PT/ES/EN and handles clean/empty/invalid documents.');
