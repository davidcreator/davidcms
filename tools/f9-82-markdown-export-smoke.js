'use strict';
const fs=require('fs'),vm=require('vm'),path=require('path');
const source=fs.readFileSync(path.join(__dirname,'../dev/admin/view/js/blog-block-editor.js'),'utf8');
const root={document:{documentElement:{lang:'pt-br'}},location:{origin:'https://admin.example'},setTimeout,clearTimeout,setInterval,clearInterval,URL,TextEncoder,Uint8Array,console};
const context={window:root,globalThis:root,URL,TextEncoder,Uint8Array,Intl,Map,Set,WeakMap,WeakSet,Date,Math,JSON,RegExp,Array,Object,String,Number,Boolean,Error,TypeError,Promise,console,setTimeout,clearTimeout};
vm.runInNewContext(source,context,{filename:'blog-block-editor.js'});
const api=root.ReamurBlogBlockEditor;
if(!api||typeof api.convertDocumentToMarkdown!=='function')throw new Error('Markdown converter unavailable');
const node=(id,type,attributes={},content=[],children=[])=>({id,type,attributes,content,children});
const run=(text,marks=[],href=null)=>({text,marks,href});
const scene=JSON.stringify({version:1,background:'#ffffff',camera:{fov:45,position:[0,0,6]},objects:[{type:'box',color:'#ff0000',position:[0,0,0],rotation:[0,0,0],scale:1,animation:'none'}],lights:[{type:'ambient',color:'#ffffff',intensity:1}],auto_rotate:false});
const doc={format_version:2,blocks:[
 node('heading','core/heading',{level:1},[run('Export title')]),
 node('paragraph','core/paragraph',{},[run('**literal** & <script>text</script> '),run('bold', ['bold']),run(' and '),run('link',[], 'https://example.org/docs')]),
 node('underlined','core/paragraph',{},[run('underline',['underline'])]),
 node('ordered','core/list',{ordered:true},'',[
  node('list_item_1','core/list-item',{},[run('First item')]),
  node('list_item_2','core/list-item',{},[run('Second item')])
 ]),
 node('quote','core/quote',{},[run('A quoted idea')]),
 node('callout','core/callout',{tone:'info'},[run('Important note')]),
 node('details','core/details',{summary:'More details'},[run('Expanded body')]),
 node('code','core/code',{},'const value = 1;'),
 node('separator','core/separator',{},''),
 node('spacer','core/spacer',{height:16},''),
 node('image','core/image',{src:'image/photo.png',alt:'A photo',title:'Photo title',caption:'Caption'} ,''),
 node('cover','core/cover',{src:'image/cover.png',alt:'Cover art',align:'wide'},'',[
  node('cover_heading','core/heading',{level:2},[run('Cover heading')])
 ]),
 node('gallery','core/gallery',{columns:2,align:'content'},'',[
  node('gallery_image','core/image',{src:'image/gallery.png',alt:'Gallery picture'},'')
 ]),
 node('group','core/group',{outline_label:'A group'},'',[
  node('group_text','core/paragraph',{},[run('Grouped paragraph')])
 ]),
 node('columns','core/columns',{count:2},'',[
  node('column_a','core/column',{},'',[
   node('column_text_a','core/paragraph',{},[run('Column A')])
  ]),
  node('column_b','core/column',{},'',[
   node('column_text_b','core/paragraph',{},[run('Column B')])
  ])
 ]),
 node('button','core/button',{url:'/contact'},[run('Contact us')]),
 node('file','core/file',{src:'view/download/blog/guide.pdf'},[run('Download guide')]),
 node('audio','core/audio',{src:'view/audio/blog/sample.mp3'},[run('Listen to audio')]),
 node('video_file','core/video_file',{src:'view/video/blog/clip.mp4',title:'Short clip',caption:'Video caption'},''),
 node('video_embed','core/video_embed',{url:'https://www.youtube.com/watch?v=abcdefghijk'},''),
 node('table','core/table',{header:true},'',[
  node('row1','core/table-row',{},'',[
   node('head_cell_a','core/table-cell',{},[run('Name')]),
   node('head_cell_b','core/table-cell',{},[run('Value')])
  ]),
  node('row2','core/table-row',{},'',[
   node('body_cell_a','core/table-cell',{},[run('Item')]),
   node('body_cell_b','core/table-cell',{},[run('42')])
  ])
 ]),
 node('three_d','core/threejs_thumb',{scene_json:scene,label:'3D product',custom_js:'alert(1)'},'')
]};
function deepFreeze(value){if(value&&typeof value==='object'){Object.freeze(value);Object.values(value).forEach(deepFreeze);}}
const snapshot=JSON.stringify(doc);deepFreeze(doc);
const result=api.convertDocumentToMarkdown(doc,false);
if(!result.valid)throw new Error(`Expected valid document, got ${result.errors.join(',')}`);
for(const token of ['# Export title','**bold**','[link](<https://example.org/docs>)','1. First item','> A quoted idea','```\\nconst value = 1;\\n```','![A photo](<image/photo.png> "Photo title")','| Name | Value |','[Download guide](<view/download/blog/guide.pdf>)','[Listen to audio](<view/audio/blog/sample.mp3>)','[Vídeo](<https://www.youtube.com/watch?v=abcdefghijk>)']){
 if(!result.markdown.includes(token.replaceAll('\\n','\n')))throw new Error(`Markdown output missing expected syntax: ${token}`);
}
if(!result.markdown.includes('&lt;script&gt;text&lt;/script&gt;'))throw new Error('HTML-like text was not escaped');
if(result.markdown.includes('<script>')||result.markdown.includes('custom_js')||result.markdown.includes('alert(1)'))throw new Error('Markdown export leaked executable markup or custom JS');
const warningCodes=new Set(result.warnings.map(x=>x.code));
for(const code of ['underline','callout_style','details_behavior','spacer','cover_layout','gallery_layout','layout','media_link','video_embed','interactive_3d'])if(!warningCodes.has(code))throw new Error(`Missing expected non-representation note: ${code}`);
if(!result.warnings.every(x=>x.nodeId&&x.type))throw new Error('Conversion note missing block reference');
if(JSON.stringify(doc)!==snapshot)throw new Error('Markdown conversion changed the source document');
const clean={format_version:2,blocks:[
 node('clean_heading','core/heading',{level:2},[run('Clean')]),
 node('clean_p','core/paragraph',{},[run('Simple text.')]),
 node('clean_image','core/image',{src:'image/clean.png',alt:'A clean image'},'')
]};
const cleanResult=api.convertDocumentToMarkdown(clean,false);
if(!cleanResult.valid||cleanResult.warnings.length||!cleanResult.markdown.includes('## Clean'))throw new Error('Common native blocks should export without unsupported-feature notes');
const invalid=api.convertDocumentToMarkdown({format_version:2,blocks:[{id:'bad',type:'core/unknown',attributes:{},content:'',children:[]}]},false);
if(invalid.valid||invalid.markdown||invalid.warnings.length)throw new Error('Invalid BDRE document should not be exported');
for(const [locale,expected] of [['pt-br','**Nota:**'],['es-eu','**Nota:**'],['en-us','**Note:**']]){
 root.document.documentElement.lang=locale;
 const localized=api.convertDocumentToMarkdown({format_version:2,blocks:[node('localized_callout','core/callout',{tone:'info'},[run('Check')])]},false);
 if(!localized.markdown.includes(expected))throw new Error(`Missing localized Markdown callout text for ${locale}`);
}
console.log('PASS: safe Markdown conversion covers core text/media/layout, escapes markup, reports non-representable behavior, preserves the BDRE source, localizes callouts and rejects invalid documents.');
