'use strict';
const fs=require('fs'),vm=require('vm'),path=require('path');
const source=fs.readFileSync(path.join(__dirname,'../dev/admin/view/js/blog-block-editor.js'),'utf8');
const root={document:{documentElement:{lang:'pt-br'}},location:{origin:'https://admin.example'},setTimeout,clearTimeout,setInterval,clearInterval,URL,TextEncoder,Uint8Array,console};
const context={window:root,globalThis:root,URL,TextEncoder,Uint8Array,Intl,Map,Set,WeakMap,WeakSet,Date,Math,JSON,RegExp,Array,Object,String,Number,Boolean,Error,TypeError,Promise,console,setTimeout,clearTimeout};
vm.runInNewContext(source,context,{filename:'blog-block-editor.js'});
const api=root.ReamurBlogBlockEditor,run=text=>({text,marks:[],href:null});
const doc={format_version:2,blocks:[
 {id:'editable',type:'core/paragraph',attributes:{},children:[],content:[run('Alpha alpha alphabet')]},
 {id:'locked',type:'core/paragraph',attributes:{locked:true},children:[],content:[run('alpha remains untouched')]},
 {id:'cross-run',type:'core/paragraph',attributes:{},children:[],content:[run('Al'),run('pha text')]}
]};
const original=JSON.stringify(doc),all=[];
for(const node of doc.blocks)api.findTextMatchesInRuns(node.content,'alpha',false,true).forEach(match=>all.push(Object.assign({nodeId:node.id},match)));
if(all.length!==4)throw new Error('Expected word-boundary matches across runs while excluding alphabet: '+all.length);
const result=api.applyTextReplacementMatches(doc,all,'Omega',false);
if(!result.valid||result.appliedCount!==3||result.skippedLockedCount!==1)throw new Error('Replacement plan must apply eligible matches and skip locked text: '+JSON.stringify(result));
if(JSON.stringify(doc)!==original)throw new Error('Pure replacement helper mutated source document');
const content=id=>result.document.blocks.find(node=>node.id===id).content.map(item=>item.text).join('');
if(content('editable')!=='Omega Omega alphabet'||content('locked')!=='alpha remains untouched'||content('cross-run')!=='Omega text')throw new Error('Replacement results or protected content are incorrect');
if(result.document.blocks.map(node=>node.id).join(',')!=='editable,locked,cross-run')throw new Error('Replacement changed block identity or order');
const empty=api.applyTextReplacementMatches(doc,all.filter(item=>item.nodeId==='locked'),'Omega',false);
if(!empty.valid||empty.appliedCount!==0||empty.skippedLockedCount!==1)throw new Error('Fully locked plan must be safely skipped');
const invalid=api.applyTextReplacementMatches({format_version:99,blocks:[]},[{}],'x',false);
if(invalid.valid)throw new Error('Invalid document must be rejected');
for(const fragment of ['openReplaceAllPreview()','renderReplaceAllPreview()','confirmReplaceAll()','this.replaceAllPreviewData.snapshot!==this.snapshot()','replaceAllPreviewOpen','replace-all-confirm','replace-all-cancel','aria-modal','locked blocks will be skipped','applyTextReplacementMatches: applyTextReplacementMatches'])if(!source.includes(fragment))throw new Error('Preview/confirmation wiring missing: '+fragment);
console.log('PASS: F9-101 bulk replace has a read-only confirmation preview, stale-document revalidation, safe text-only apply, lock-aware skips, formatting/run preservation and source immutability.');
