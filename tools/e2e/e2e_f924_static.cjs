#!/usr/bin/env node
'use strict';
const fs=require('fs'); const path=require('path');
const root=path.resolve(process.argv[2]||path.join(__dirname,'../..'));
const doc=fs.readFileSync(path.join(root,'dev/system/library/blog/block_document.php'),'utf8');
const renderer=fs.readFileSync(path.join(root,'dev/system/library/blog/block_renderer.php'),'utf8');
const editor=fs.readFileSync(path.join(root,'dev/admin/view/js/blog-block-editor.js'),'utf8');
const css=fs.readFileSync(path.join(root,'dev/catalog/view/css/blog-blocks.css'),'utf8');
const locales=['pt-br','en-us','es-eu']; const errors=[];
const keys=['text_block_type_gallery','text_block_type_cover','text_block_caption','text_block_alignment','text_block_image_size','text_block_mobile_size','text_block_gallery_columns','text_block_pattern_category','text_block_pattern_two_columns','text_block_pattern_cover_hero','text_block_video_preview'];
function need(ok,msg){if(!ok)errors.push(msg)}
for(const marker of ['core/gallery','core/cover','MAX_GALLERY_IMAGES','media_block_requires_v2','gallery_child_invalid','cover_child_invalid','mobile_size']) need(doc.includes(marker),`validator missing ${marker}`);
for(const marker of ['renderImageFigure','rms-block-gallery','rms-block-cover','rms-block-image__caption']) need(renderer.includes(marker),`renderer missing ${marker}`);
for(const marker of ['insertPattern(pattern)','createPattern(pattern)','canInsertPatterns(afterId)','renderAdminVideoPreview(url)','rb-content-button-preview','mobile_size','core/gallery','core/cover']) need(editor.includes(marker),`editor missing ${marker}`);
for(const marker of ['.rms-block-gallery--columns-4','.rms-block-cover__content','.rms-block-image__caption','.rms-block-image--mobile-full']) need(css.includes(marker),`catalog CSS missing ${marker}`);
const controller=fs.readFileSync(path.join(root,'dev/admin/controller/blog/blog.php'),'utf8');
for(const key of keys) need(controller.includes(`'${key}'`),`controller missing ${key}`);
for(const locale of locales){const lang=fs.readFileSync(path.join(root,`dev/admin/language/${locale}/blog/blog.php`),'utf8');for(const key of keys)need(lang.includes(`$_['${key}']`),`${locale} missing ${key}`);}
let fixtures=[];try{fixtures=JSON.parse(fs.readFileSync(path.join(root,'tests/fixtures/media_blocks_f924.json'),'utf8'));}catch(e){errors.push(`fixture JSON parse failed: ${e.message}`)}
need(Array.isArray(fixtures)&&fixtures.length>=11,'media fixture matrix incomplete');
if(Array.isArray(fixtures)){const ids=new Set();for(const f of fixtures){need(typeof f.id==='string'&&!ids.has(f.id),`fixture ID missing/duplicate: ${f.id}`);ids.add(f.id);}}
if(errors.length){console.error('F9-24 STATIC CONTRACT CHECK: FAIL\n- '+errors.join('\n- '));process.exit(1)}
console.log(`F9-24 STATIC CONTRACT CHECK: PASS — validator/renderer/editor markers, ${keys.length} keys × ${locales.length} locales, ${fixtures.length} synthetic cases. No PHP/SQL/browser/app was executed.`);
