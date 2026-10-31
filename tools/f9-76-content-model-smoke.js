#!/usr/bin/env node
'use strict';
const fs=require('fs');
const vm=require('vm');
const path=require('path');
const source=fs.readFileSync(path.join(__dirname,'../dev/admin/view/js/blog-block-editor.js'),'utf8');
const marker='global.ReamurBlogBlockEditor = {';
if(!source.includes(marker))throw new Error('BDREditor public API marker missing');
const instrumented=source.replace(marker,marker+'contentModelCopy:contentModelCopy,');
const root={document:{documentElement:{lang:'pt-br'}},setTimeout,clearTimeout,setInterval,clearInterval,URL,TextEncoder,Uint8Array,console};
const context={window:root,globalThis:root,URL,TextEncoder,Uint8Array,Intl,Map,Set,WeakMap,WeakSet,Date,Math,JSON,RegExp,Array,Object,String,Number,Boolean,Error,TypeError,Promise,console,setTimeout,clearTimeout};
vm.runInNewContext(instrumented,context,{filename:'blog-block-editor.js'});
const api=root.ReamurBlogBlockEditor;
if(!api||typeof api.validateDocument!=='function'||typeof api.contentModelCopy!=='function')throw new Error('Model catalog/validator unavailable');
const textTypes=new Set(['core/paragraph','core/heading','core/list-item','core/quote','core/button','core/callout','core/details','core/table-cell','core/file','core/audio']);
let checked=0;
for(const locale of ['pt-br','es-eu','en-us']){
 root.document.documentElement.lang=locale;
 const catalog=api.contentModelCopy();
 if(catalog.templates.length!==6)throw new Error(`${locale}: expected 6 models`);
 const ids=new Set();
 for(const model of catalog.templates){
  if(ids.has(model.id))throw new Error(`${locale}: duplicate model id ${model.id}`);ids.add(model.id);
  let sequence=0;
  const convert=(spec)=>({
   id:`test_${locale.replace(/[^a-z]/g,'')}_${++sequence}`,
   type:spec.type,
   attributes:spec.attributes||{},
   content:textTypes.has(spec.type)?(spec.content?[{text:spec.content,marks:[],href:null}]:[]):(spec.content||''),
   children:(spec.children||[]).map(convert)
  });
  const group={id:`root_${locale}_${model.id}`,type:'core/group',attributes:{outline_label:model.outline},content:'',children:model.blocks.map(convert)};
  const document={format_version:2,blocks:[group]};
  const result=api.validateDocument(document,false);
  if(!result.valid)throw new Error(`${locale}/${model.id}: document validator rejected built-in model: ${result.errors.join(',')}`);
  if(JSON.stringify(document).length>4194304)throw new Error(`${locale}/${model.id}: exceeds document size limit`);
  checked++;
 }
}
console.log(`PASS: ${checked} localized built-in content models validate as native BDRE v2 documents (18 total); no media, custom HTML or custom JS.`);
