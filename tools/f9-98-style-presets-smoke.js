'use strict';
const fs=require('fs'),vm=require('vm'),path=require('path');
const rootPath=path.join(__dirname,'..'),source=fs.readFileSync(path.join(rootPath,'dev/admin/view/js/blog-block-editor.js'),'utf8');
const page=fs.readFileSync(path.join(rootPath,'dev/admin/controller/cms/page.php'),'utf8'),blog=fs.readFileSync(path.join(rootPath,'dev/admin/controller/blog/blog.php'),'utf8');
const root={document:{documentElement:{lang:'pt-br'}},location:{origin:'https://admin.example'},setTimeout,clearTimeout,setInterval,clearInterval,URL,TextEncoder,Uint8Array,console};
const context={window:root,globalThis:root,URL,TextEncoder,Uint8Array,Intl,Map,Set,WeakMap,WeakSet,Date,Math,JSON,RegExp,Array,Object,String,Number,Boolean,Error,TypeError,Promise,console,setTimeout,clearTimeout};
vm.runInNewContext(source,context,{filename:'blog-block-editor.js'});
const api=root.ReamurBlogBlockEditor,run=text=>({text,marks:[],href:null});
const sourceNode={id:'src',type:'core/paragraph',attributes:{align:'center',appearance:{background_color:'#f1f2f3',padding:12}},children:[],content:[run('Source')]};
const preset={name:'Editorial highlight',style:api.captureBlockStyle(sourceNode)};
if(!preset.style||JSON.stringify(preset).length>10000)throw new Error('Preset descriptor should persist only a small visual-style payload');
const stored=JSON.parse(JSON.stringify([preset]));if(stored[0].style.sourceType!=='core/paragraph'||stored[0].style.native.align!=='center')throw new Error('Preset JSON round-trip lost visual fields');
const doc={format_version:2,blocks:[sourceNode,{id:'target',type:'core/paragraph',attributes:{align:'left'},children:[],content:[run('Keep this text')]}]};
const result=api.applyBlockStyle(doc,['target'],stored[0].style,false);
if(!result.valid||result.document.blocks[1].content[0].text!=='Keep this text'||result.document.blocks[1].id!=='target')throw new Error('A saved preset must reapply to a compatible target without replacing content/ID');
for(const [label,text] of [['localStorage read','global.localStorage.getItem'],['localStorage write','global.localStorage.setItem'],['bounded preset count','slice(0,50)'],['bounded storage size','524288'],['unique names','item.name.toLowerCase()===name.toLowerCase()'],['keyboard-trapped library','stylePresetLibraryOpen||this.stylePresetSaveOpen'],['searchable library','data-style-preset-query'],['multi-select application','open-style-presets'],['preview before apply','openBlockStylePreview(null,false,targets)'],['explicit save dialog','renderStylePresetSaveDialog()'],['user/context key in Pages','stylePresetKey'],['user/context key in Blog','stylePresetKey']]){
 const hay=label.includes('Pages')?page:label.includes('Blog')?blog:source;if(!hay.includes(text))throw new Error('Preset library check missing '+label+' ('+text+')');
}
if(!page.includes("':pages'")||!blog.includes("':blog'"))throw new Error('Pages and Blog presets must have separate localStorage namespaces');
console.log('PASS: named style presets persist a bounded, JSON-safe style-only descriptor in per-user Pages/Blog local storage; library supports search, selection and preview-confirm application while preserving content/IDs.');
