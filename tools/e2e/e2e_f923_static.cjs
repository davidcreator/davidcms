#!/usr/bin/env node
'use strict';
const fs=require('fs'); const path=require('path');
const root=path.resolve(process.argv[2]||path.join(__dirname,'../..'));
const src=fs.readFileSync(path.join(root,'dev/admin/view/js/blog-block-editor.js'),'utf8');
const css=fs.readFileSync(path.join(root,'dev/admin/view/css/blog-block-editor.css'),'utf8');
const controller=fs.readFileSync(path.join(root,'dev/admin/controller/blog/blog.php'),'utf8');
const locales=['pt-br','en-us','es-eu']; const errors=[];
const keys=['text_block_add','text_block_search','text_block_no_results','text_block_category_content','text_block_category_media','text_block_category_layout','text_block_outline','text_block_outline_empty','text_block_move_up','text_block_move_down','text_block_duplicate','text_block_transform','text_block_remove','text_block_add_after','text_block_drag','text_block_type_paragraph','text_block_type_heading','text_block_type_list','text_block_type_image','text_block_type_quote','text_block_type_video','text_block_type_button','text_block_type_separator','text_block_type_group','text_block_type_columns','text_block_type_list_item','text_block_type_column','text_block_transform_hint','text_block_insert_empty','text_block_status_inserted','text_block_status_moved','text_block_status_duplicated','text_block_status_transformed','text_block_status_removed','text_block_status_undo','text_block_status_redo','text_block_undo','text_block_redo','text_block_preview_desktop','text_block_preview_tablet','text_block_preview_mobile','text_block_editor_tools','text_block_canvas','text_block_canvas_empty','text_block_inspector','text_block_inspector_empty','text_block_column_count'];
function need(ok,msg){if(!ok)errors.push(msg)}
for(const marker of ['insertTypes(afterId)','renderInserter()','filterInserter()','slashTrigger','duplicateNode(id)','canTransformNode(node, type)','transformNode(id, type)','reorderSibling(fromId, toId)','renderOutline()','aria-live','this.undoStack.length > 50','focusNode(id)']) need(src.includes(marker),`missing source marker: ${marker}`);
for(const marker of ['.rb-inserter-menu','.rb-block-toolbar','.rb-outline','.rb-drag-handle']) need(css.includes(marker),`missing CSS marker: ${marker}`);
for(const key of keys){need(controller.includes(`'${key}'`),`controller missing ${key}`);}
for(const locale of locales){
 const f=fs.readFileSync(path.join(root,`dev/admin/language/${locale}/blog/blog.php`),'utf8');
 for(const key of keys) need(f.includes(`$_['${key}']`),`${locale} missing ${key}`);
}
let fixtures=[];
try{fixtures=JSON.parse(fs.readFileSync(path.join(root,'tests/fixtures/block_ux_f923.json'),'utf8'));}catch(e){errors.push(`fixture JSON parse failed: ${e.message}`)}
need(Array.isArray(fixtures)&&fixtures.length>=9,'fixture matrix incomplete');
if(Array.isArray(fixtures)){const ids=new Set();for(const f of fixtures){need(typeof f.id==='string'&&!ids.has(f.id),`fixture ID missing/duplicate: ${f.id}`);ids.add(f.id);}}
if(errors.length){console.error('F9-23 STATIC CONTRACT CHECK: FAIL\n- '+errors.join('\n- '));process.exit(1)}
console.log(`F9-23 STATIC CONTRACT CHECK: PASS — block UX markers, ${keys.length} localized keys × ${locales.length} locales, ${fixtures.length} synthetic cases. No PHP/SQL/browser/app was executed.`);
