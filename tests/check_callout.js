'use strict';
const fs=require('fs'); const path=require('path');
global.window={}; global.Element=class Element{};
require('../dev/admin/view/js/blog-block-editor.js');
const api=window.ReamurBlogBlockEditor;
const base=JSON.parse(fs.readFileSync(path.join(__dirname,'fixtures/core_callout.json'),'utf8')).document;
let checks=0; function check(value,label){checks++;if(!value)throw new Error('FAIL: '+label);}
function clone(value){return JSON.parse(JSON.stringify(value));}
function docWith(block){return {format_version:2,blocks:[block]};}
check(api.validateDocument(base).valid,'fixture with all supported tones is valid');
check(base.blocks.map(n=>n.attributes.tone).join(',')==='info,success,warning,danger','fixture covers the closed tone enum');
check(base.blocks.every(n=>n.children.length===0&&Array.isArray(n.content)),'callout is a rich-text leaf block');
for(const tone of ['info','success','warning','danger']){
 const block=clone(base.blocks[0]); block.attributes.tone=tone;
 check(api.validateDocument(docWith(block)).valid,'accepts tone '+tone);
}
let bad=clone(base.blocks[0]); bad.attributes.tone='javascript:alert(1)'; check(!api.validateDocument(docWith(bad)).valid,'rejects arbitrary tone/class injection');
bad=clone(base.blocks[0]); delete bad.attributes.tone; check(!api.validateDocument(docWith(bad)).valid,'requires tone');
bad=clone(base.blocks[0]); bad.attributes.style='color:red'; check(!api.validateDocument(docWith(bad)).valid,'rejects unallowlisted attributes');
bad=clone(base.blocks[0]); bad.children=[{id:'child',type:'core/paragraph',attributes:{},children:[],content:[{text:'x',marks:[],href:null}]}]; check(!api.validateDocument(docWith(bad)).valid,'rejects nested child blocks');
bad=clone(base.blocks[0]); check(!api.validateDocument({format_version:1,blocks:[{...bad,content:'legacy'}]}).valid,'rejects callout in legacy document version');
bad=clone(base.blocks[0]); bad.content=[{text:'<script>alert(1)</script>',marks:[],href:null}]; check(api.validateDocument(docWith(bad)).valid,'treats markup-looking content as ordinary rich text');
const ids=base.blocks.map(n=>n.id); check(new Set(ids).size===ids.length,'fixture has unique stable IDs');
console.log(`Callout checker: ${checks} PASS`);
